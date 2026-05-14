<?php

namespace App\Http\Controllers\Api\Instructor;

use App\Http\Controllers\Controller;
use App\Services\PythonOmrApiClient;
use App\Models\AnswerKey;
use App\Models\AnswerSheet;
use App\Models\ExamSubject;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TermOmrScanController extends Controller
{
    public function __construct(private readonly PythonOmrApiClient $omrApiClient)
    {
    }

    public function check(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$this->hasRole($user->role, 'instructor')) {
            return response()->json([
                'message' => 'Only instructors can check term exam answer sheets.',
            ], 403);
        }

        $validated = $request->validate([
            'image' => 'nullable|file|image|mimes:jpeg,jpg,png,bmp,webp|max:10240',
            'images' => 'nullable|array|min:1',
            'images.*' => 'file|image|mimes:jpeg,jpg,png,bmp,webp|max:10240',
        ]);

        $files = [];
        if (!empty($validated['image'])) {
            $files[] = $validated['image'];
        }

        if (!empty($validated['images']) && is_array($validated['images'])) {
            $files = array_merge($files, $validated['images']);
        }

        if (empty($files)) {
            return response()->json([
                'message' => 'Please upload at least one image or a folder of images.',
            ], 422);
        }

        $results = [];
        foreach ($files as $file) {
            $results[] = $this->processImage($file);
        }

        $successCount = collect($results)->where('success', true)->count();

        return response()->json([
            'message' => "Processed {$successCount} out of " . count($results) . " image(s).",
            'processed' => $results,
        ]);
    }

    private function processImage($file): array
    {
        $storedPath = $file->store('omr_uploads', 'public');
        $absolutePath = storage_path('app/public/' . $storedPath);

        $omr = $this->runOmrScript($absolutePath);
        if (!$omr['success']) {
            return [
                'success' => false,
                'file' => $file->getClientOriginalName(),
                'message' => $omr['message'],
            ];
        }

        $payload = trim((string) ($omr['data']['sheet_id'] ?? ''));
        if ($payload === '') {
            return [
                'success' => false,
                'file' => $file->getClientOriginalName(),
                'message' => 'QR code was not detected from the image.',
            ];
        }

        $parsed = $this->parseTermPayload($payload);
        if (!$parsed) {
            return [
                'success' => false,
                'file' => $file->getClientOriginalName(),
                'message' => 'Invalid term exam QR payload.',
            ];
        }

        $student = Student::query()->where('Student_Number', $parsed['student_number'])->first();
        if (!$student) {
            return [
                'success' => false,
                'file' => $file->getClientOriginalName(),
                'message' => 'Student number not found.',
            ];
        }

        $examId = (int) $parsed['exam_id'];
        $sheet = AnswerSheet::with('exam')
            ->where('exam_id', $examId)
            ->where('user_id', (int) $student->user_id)
            ->first();
        if (!$sheet) {
            return [
                'success' => false,
                'file' => $file->getClientOriginalName(),
                'message' => 'Answer sheet not found for this student/exam.',
            ];
        }

        $user = Auth::user();
        if (!$user || !$this->canManageExam($user, (int) $sheet->exam_id)) {
            return [
                'success' => false,
                'file' => $file->getClientOriginalName(),
                'sheet_code' => $payload,
                'message' => 'You can only check answer sheets for exams you created.',
            ];
        }

        $subjectId = (int) $parsed['subject_id'];
        $examSubjectId = ExamSubject::query()
            ->where('exam_id', $examId)
            ->where('subject_id', $subjectId)
            ->value('id');

        $answerKey = AnswerKey::query()
            ->where('exam_id', $examId)
            ->when($examSubjectId, fn ($q) => $q->where('exam_subject_id', $examSubjectId))
            ->latest('id')
            ->first();

        if (!$answerKey && $examSubjectId) {
            $answerKey = AnswerKey::query()
                ->where('exam_id', $examId)
                ->latest('id')
                ->first();
        }

        if (!$answerKey) {
            return [
                'success' => false,
                'file' => $file->getClientOriginalName(),
                'sheet_code' => $payload,
                'message' => 'No answer key found for this exam.',
            ];
        }

        $studentAnswers = $this->normalizeAnswers((array) ($omr['data']['answers'] ?? []));
        $correctAnswers = $this->normalizeAnswers((array) ($answerKey->answers ?? []));

        $subjectScores = $this->scoreBySubject((int) $sheet->exam_id, $studentAnswers, $correctAnswers);
        $totalScore = !empty($subjectScores)
            ? array_sum(array_column($subjectScores, 'raw_score'))
            : $this->scoreAllQuestions($studentAnswers, $correctAnswers);

        $debugRelativePath = $this->prepareDebugImageRelativePath((string) ($omr['data']['debug'] ?? ''));

        DB::transaction(function () use ($sheet, $debugRelativePath, $studentAnswers, $subjectScores, $totalScore, $user) {
            $updates = [
                'image_path' => $debugRelativePath,
                'scanned_data' => $studentAnswers,
                'total_score' => $totalScore,
                'status' => 'checked',
            ];
            if ($this->hasScannedByColumn()) {
                $updates['scanned_by'] = $user?->id;
            }

            $sheet->update($updates);

            DB::table('exam_results')->where('answer_sheet_id', $sheet->id)->delete();

            if (!empty($subjectScores)) {
                DB::table('exam_results')->insert(array_map(function ($row) use ($sheet) {
                    return [
                        'answer_sheet_id' => $sheet->id,
                        'subject_id' => $row['subject_id'],
                        'raw_score' => $row['raw_score'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }, $subjectScores));
            }
        });

        $this->cleanupProcessedImages($storedPath, (string) ($omr['data']['debug'] ?? ''), $debugRelativePath);
        $debugImageUrl = $this->relativePathToStorageUrl($debugRelativePath);

        return [
            'success' => true,
            'file' => $file->getClientOriginalName(),
            'sheet_code' => $payload,
            'exam_title' => $sheet->exam?->Exam_Title,
            'student_id' => $sheet->user_id,
            'score' => $totalScore,
            'debug_image' => $debugImageUrl,
        ];
    }

    private function runOmrScript(string $imagePath): array
    {
        return $this->omrApiClient->scanTerm($imagePath);
    }
    private function parseTermPayload(string $payload): ?array
    {
        $parts = array_map('trim', explode('|', $payload));
        if (count($parts) < 7) {
            return null;
        }

        return [
            'student_number' => $parts[0],
            'exam_id' => (int) $parts[1],
            'subject_id' => (int) $parts[2],
            'last_name' => $parts[3],
            'first_name' => $parts[4],
            'middle_name' => $parts[5],
            'extension' => $parts[6],
        ];
    }

    private function normalizeAnswers(array $answers): array
    {
        $normalized = [];
        foreach ($answers as $question => $answer) {
            $key = (string) $question;
            $normalized[$key] = strtoupper(trim((string) $answer));
        }
        return $normalized;
    }

    private function scoreBySubject(int $examId, array $studentAnswers, array $correctAnswers): array
    {
        $rows = ExamSubject::where('exam_id', $examId)->get();
        $scored = [];

        foreach ($rows as $row) {
            $start = (int) $row->Starting_Number;
            $end = (int) $row->Ending_Number;
            $raw = 0;

            for ($q = $start; $q <= $end; $q++) {
                $key = (string) $q;
                if (!isset($correctAnswers[$key])) {
                    continue;
                }

                if (($studentAnswers[$key] ?? null) === $correctAnswers[$key]) {
                    $raw++;
                }
            }

            $scored[] = [
                'subject_id' => (int) $row->subject_id,
                'raw_score' => $raw,
            ];
        }

        return $scored;
    }

    private function scoreAllQuestions(array $studentAnswers, array $correctAnswers): int
    {
        $score = 0;
        foreach ($correctAnswers as $question => $answer) {
            if (($studentAnswers[$question] ?? null) === $answer) {
                $score++;
            }
        }
        return $score;
    }

    private function canManageExam(User $user, int $examId): bool
    {
        if ($examId <= 0) {
            return false;
        }

        $employeeId = DB::table('employees')
            ->where('user_id', $user->id)
            ->value('id');

        return DB::table('exams')
            ->where('id', $examId)
            ->where(function ($query) use ($employeeId, $user) {
                if ($employeeId) {
                    $query->where('created_by', $employeeId)
                        ->orWhere('created_by', $user->id);
                    return;
                }

                $query->where('created_by', $user->id);
            })
            ->exists();
    }

    private function hasScannedByColumn(): bool
    {
        try {
            return Schema::hasColumn('answer_sheets', 'scanned_by');
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function hasRole(?string $roles, string $role): bool
    {
        if (!$roles) {
            return false;
        }

        $roleList = array_map('trim', explode(',', $roles));
        return in_array($role, $roleList, true);
    }

    private function debugImageUrl(string $debugRelative): ?string
    {
        return $this->relativePathToStorageUrl($this->prepareDebugImageRelativePath($debugRelative));
    }

    private function prepareDebugImageRelativePath(string $debugRelative): ?string
    {
        $debugRelative = trim($debugRelative);
        if ($debugRelative === '') {
            return null;
        }

        $normalized = ltrim(str_replace('\\', '/', $debugRelative), '/');

        // Python may return an absolute path under ".../public/storage/...".
        if (preg_match('#/public/storage/(.+)$#', $normalized, $matches)) {
            $normalized = ltrim((string) $matches[1], '/');
        }

        // Keep omr_processed files under their original folder.
        if (str_starts_with($normalized, 'debug/')) {
            $normalized = 'omr_processed/' . ltrim(substr($normalized, strlen('debug/')), '/');
        } elseif (!str_starts_with($normalized, 'omr_processed/')) {
            $normalized = 'omr_processed/' . ltrim($normalized, '/');
        }

        $normalized = $this->ensureDebugImageInAppStorage($normalized);
        $this->compressStoredImage($normalized);
        return $normalized;
    }

    private function ensureDebugImageInAppStorage(string $debugRelative): string
    {
        $normalized = ltrim(str_replace('\\', '/', $debugRelative), '/');
        if (str_starts_with($normalized, 'debug/')) {
            $normalized = 'omr_processed/' . ltrim(substr($normalized, strlen('debug/')), '/');
        } elseif (!str_starts_with($normalized, 'omr_processed/')) {
            $normalized = 'omr_processed/' . ltrim($normalized, '/');
        }

        $fileName = basename($normalized);
        if ($fileName === '' || $fileName === '.' || $fileName === '..') {
            return $normalized;
        }

        $targetDir = public_path('storage/omr_processed');
        $targetFile = $targetDir . DIRECTORY_SEPARATOR . $fileName;
        if (is_file($targetFile)) {
            return 'omr_processed/' . $fileName;
        }

        if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            return $normalized;
        }

        $parentDir = dirname(base_path());
        $sourceCandidates = [
            storage_path('app/public/omr_processed/' . $fileName),
            public_path('storage/omr_processed/' . $fileName),
            storage_path('app/public/debug/' . $fileName),
            public_path('storage/debug/' . $fileName),
            $parentDir . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'omr_processed' . DIRECTORY_SEPARATOR . $fileName,
            $parentDir . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'debug' . DIRECTORY_SEPARATOR . $fileName,
        ];

        foreach ($sourceCandidates as $source) {
            if (!is_file($source)) {
                continue;
            }

            if (@copy($source, $targetFile)) {
                return 'omr_processed/' . $fileName;
            }
        }

        return $normalized;
    }

    private function compressStoredImage(string $relativePath): void
    {
        $absolutePath = storage_path('app/public/' . ltrim($relativePath, '/'));
        if (!is_file($absolutePath) || !function_exists('getimagesize')) {
            return;
        }

        $imageInfo = @getimagesize($absolutePath);
        $mime = strtolower((string) ($imageInfo['mime'] ?? ''));

        if ($mime === 'image/jpeg' && function_exists('imagecreatefromjpeg') && function_exists('imagejpeg')) {
            $image = @imagecreatefromjpeg($absolutePath);
            if ($image !== false) {
                @imagejpeg($image, $absolutePath, 72);
                @imagedestroy($image);
            }
            return;
        }

        if ($mime === 'image/png' && function_exists('imagecreatefrompng') && function_exists('imagepng')) {
            $image = @imagecreatefrompng($absolutePath);
            if ($image !== false) {
                @imagepng($image, $absolutePath, 8);
                @imagedestroy($image);
            }
            return;
        }

        if ($mime === 'image/webp' && function_exists('imagecreatefromwebp') && function_exists('imagewebp')) {
            $image = @imagecreatefromwebp($absolutePath);
            if ($image !== false) {
                @imagewebp($image, $absolutePath, 72);
                @imagedestroy($image);
            }
        }
    }

    private function relativePathToStorageUrl(?string $relativePath): ?string
    {
        $relativePath = ltrim((string) $relativePath, '/');
        if ($relativePath === '') {
            return null;
        }

        return url('storage/' . $relativePath);
    }

    private function cleanupProcessedImages(string $uploadedPath, string $debugRawPath, ?string $debugRelativeToKeep): void
    {
        $debugKeepAbsolute = $debugRelativeToKeep
            ? storage_path('app/public/' . ltrim($debugRelativeToKeep, '/'))
            : null;

        $this->deleteStoredImage($uploadedPath, $debugKeepAbsolute);

        $debugRawPath = trim($debugRawPath);
        if ($debugRawPath === '') {
            return;
        }

        $normalizedRaw = str_replace('\\', DIRECTORY_SEPARATOR, $debugRawPath);
        $rawCandidates = [];
        if (preg_match('#/public/storage/(.+)$#', str_replace('\\', '/', $debugRawPath), $matches)) {
            $rawCandidates[] = storage_path('app/public/' . ltrim((string) $matches[1], '/'));
        }

        $rawCandidates[] = $normalizedRaw;
        foreach ($rawCandidates as $candidate) {
            if ($debugKeepAbsolute && realpath((string) $candidate) === realpath($debugKeepAbsolute)) {
                continue;
            }
            if (is_file($candidate)) {
                @unlink($candidate);
            }
        }
    }

    private function deleteStoredImage(string $relativePath, ?string $excludeAbsolutePath = null): void
    {
        $relativePath = ltrim(trim($relativePath), '/');
        if ($relativePath === '') {
            return;
        }

        $candidates = [
            storage_path('app/public/' . $relativePath),
            public_path('storage/' . $relativePath),
            public_path($relativePath),
        ];

        foreach ($candidates as $file) {
            if ($excludeAbsolutePath && realpath($file) === realpath($excludeAbsolutePath)) {
                continue;
            }
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
}


