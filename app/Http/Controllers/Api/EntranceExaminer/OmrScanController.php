<?php

namespace App\Http\Controllers\Api\EntranceExaminer;

use App\Http\Controllers\Controller;
use App\Jobs\SendEmailRequest;
use App\Services\PythonOmrApiClient;
use App\Models\AnswerKey;
use App\Models\AnswerSheet;
use App\Models\EmailRequest;
use App\Models\ExamSubject;
use App\Models\ProgramRequirement;
use App\Models\Recommendation;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class OmrScanController extends Controller
{
    private const ENTRANCE_TYPE_ALIASES = ['entrance', 'entrance exam'];
    private const SCREENING_TYPE_ALIASES = ['screening', 'screening exam'];
    private const PASSING_SCORE = 75;
    private const TYPE_STUDENT_CHOICE = 'student_choice';
    private const TYPE_SYSTEM = 'system';

    public function __construct(private readonly PythonOmrApiClient $omrApiClient)
    {
    }

    public function check(Request $request)
    {
        @set_time_limit(300);

        $user = Auth::user();
        if (!$user || !$this->hasAnyRole($user->role, ['entrance_examiner', 'college_dean', 'instructor'])) {
            return response()->json([
                'message' => 'Only entrance examiners, college deans, and instructors can check answer sheets.',
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

    public function destroyScannedEntry(Request $request, int $answerSheetId)
    {
        $user = Auth::user();
        if (!$user || !$this->hasAnyRole($user->role, ['entrance_examiner', 'college_dean', 'instructor'])) {
            return response()->json([
                'message' => 'Only entrance examiners, college deans, and instructors can delete scanned entries.',
            ], 403);
        }

        $sheet = AnswerSheet::with('exam')->find($answerSheetId);
        if (!$sheet) {
            return response()->json([
                'message' => 'Answer sheet not found.',
            ], 404);
        }

        if (!$this->canManageExam($user, (int) $sheet->exam_id)) {
            return response()->json([
                'message' => 'You can only delete scanned entries for exams you created.',
            ], 403);
        }

        if (
            $this->hasScannedByColumn()
            && $this->hasAnyRole($user->role, ['instructor'])
            && !$this->hasAnyRole($user->role, ['college_dean', 'entrance_examiner'])
            && (int) ($sheet->scanned_by ?? 0) !== (int) $user->id
        ) {
            return response()->json([
                'message' => 'You can only delete scanned entries that you checked.',
            ], 403);
        }

        $oldImagePath = (string) ($sheet->image_path ?? '');

        DB::transaction(function () use ($sheet) {
            DB::table('exam_results')->where('answer_sheet_id', $sheet->id)->delete();

            $updates = [
                'image_path' => null,
                'scanned_data' => null,
                'total_score' => null,
                'status' => 'generated',
                'scanned_at' => null,
            ];

            if ($this->hasScannedByColumn()) {
                $updates['scanned_by'] = null;
            }

            $sheet->update($updates);
        });

        $this->deleteStoredImage($oldImagePath);

        return response()->json([
            'success' => true,
            'message' => 'Scanned entry deleted. You can scan the answer sheet again.',
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
        if ($payload === '' || strtoupper($payload) === 'UNKNOWN') {
            return [
                'success' => false,
                'file' => $file->getClientOriginalName(),
                'message' => 'QR code was not detected from the image.',
            ];
        }

        $sheet = AnswerSheet::with('exam')->where('qr_payload', $payload)->first();
        if (!$sheet) {
            return [
                'success' => false,
                'file' => $file->getClientOriginalName(),
                'message' => "No answer sheet matched QR payload {$payload}.",
            ];
        }

        $user = Auth::user();
        if (!$user || !$this->canManageExam($user, (int) $sheet->exam_id)) {
            return [
                'success' => false,
                'file' => $file->getClientOriginalName(),
                'sheet_code' => $payload,
                'message' => "You can only check answer sheets for exams you created.",
            ];
        }

        $answerKey = AnswerKey::where('exam_id', $sheet->exam_id)->latest('id')->first();
        if (!$answerKey) {
            return [
                'success' => false,
                'file' => $file->getClientOriginalName(),
                'sheet_code' => $payload,
                'message' => "No answer key found for exam {$sheet->exam?->Exam_Title}.",
            ];
        }

        $studentAnswers = $this->normalizeAnswers((array) ($omr['data']['answers'] ?? []));
        $correctAnswers = $this->normalizeAnswers((array) ($answerKey->answers ?? []));
        $wasChecked = (string) ($sheet->status ?? '') === 'checked';

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

        if (
            !$wasChecked
            && $this->isScreeningExamType((string) ($sheet->exam?->Exam_Type ?? ''))
            && $totalScore < self::PASSING_SCORE
            && (int) ($sheet->user_id ?? 0) > 0
        ) {
            $this->handleFailedScreeningExam($sheet->fresh(['exam']));
        }

        if (
            !$wasChecked
            && $this->isScreeningExamType((string) ($sheet->exam?->Exam_Type ?? ''))
            && $totalScore >= self::PASSING_SCORE
            && (int) ($sheet->exam?->program_id ?? 0) > 0
            && (int) ($sheet->user_id ?? 0) > 0
        ) {
            $this->handlePassedScreeningExam($sheet->fresh(['exam']));
        }

        if (
            $this->isScreeningExamType((string) ($sheet->exam?->Exam_Type ?? ''))
            && $totalScore >= self::PASSING_SCORE
            && (int) ($sheet->exam?->program_id ?? 0) > 0
            && (int) ($sheet->user_id ?? 0) > 0
        ) {
            $this->assignStudentProgram((int) $sheet->user_id, (int) $sheet->exam->program_id);
        }

        if (
            !$wasChecked
            && $this->isEntranceExamType((string) ($sheet->exam?->Exam_Type ?? ''))
            && $totalScore >= self::PASSING_SCORE
            && (int) ($sheet->user_id ?? 0) > 0
        ) {
            $this->handlePassedEntranceExam($sheet->fresh(['exam']));
        }

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
        return $this->omrApiClient->scanEntrance($imagePath);
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

    private function hasAnyRole(?string $roles, array $allowedRoles): bool
    {
        if (!$roles) {
            return false;
        }

        $roleList = array_map('trim', explode(',', $roles));
        foreach ($allowedRoles as $role) {
            if (in_array($role, $roleList, true)) {
                return true;
            }
        }

        return false;
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

    private function isScreeningExamType(string $examType): bool
    {
        $value = strtolower(trim($examType));
        return in_array($value, self::SCREENING_TYPE_ALIASES, true);
    }

    private function isEntranceExamType(string $examType): bool
    {
        $value = strtolower(trim($examType));
        return in_array($value, self::ENTRANCE_TYPE_ALIASES, true);
    }

    private function assignStudentProgram(int $userId, int $programId): void
    {
        if ($userId <= 0 || $programId <= 0) {
            return;
        }

        $student = Student::query()->where('user_id', $userId)->first();
        if ($student) {
            $student->update(['program_id' => $programId]);
            return;
        }

        Student::query()->create([
            'user_id' => $userId,
            'Student_Number' => Student::generateStudentNumber(),
            'program_id' => $programId,
        ]);
    }

    private function handlePassedEntranceExam(AnswerSheet $sheet): void
    {
        $userId = (int) ($sheet->user_id ?? 0);
        if ($userId <= 0) {
            return;
        }
        $this->assignStudentNumberForPassedEntrance($userId);

        $user = User::query()->find($userId);
        if (!$user || trim((string) $user->email) === '') {
            return;
        }

        $programChoices = $this->programChoicesForUser($userId);
        $recommendedPrograms = $this->recommendedProgramsForAnswerSheet($sheet);

        if (empty($recommendedPrograms)) {
            return;
        }

        $this->storeSystemRecommendations($userId, $recommendedPrograms);
        $this->setActiveScreeningRank($userId, 1);
        $scheduledScreening = $this->autoScheduleRecommendedFirstChoice($userId, $sheet, $programChoices, $recommendedPrograms);
        $this->sendEntranceResultEmail($user, $sheet, $programChoices, $recommendedPrograms, $scheduledScreening);
    }

    private function assignStudentNumberForPassedEntrance(int $userId): void
    {
        if ($userId <= 0) return;
        $student = Student::query()->where('user_id', $userId)->first();
        if (!$student) return;
        if (!empty($student->Student_Number)) return;
        $student->update(['Student_Number' => Student::generateStudentNumber()]);
    }

    private function handlePassedScreeningExam(AnswerSheet $sheet): void
    {
        $userId = (int) ($sheet->user_id ?? 0);
        if ($userId <= 0) {
            return;
        }

        $user = User::query()->find($userId);
        if (!$user || trim((string) $user->email) === '') {
            return;
        }

        $programName = trim((string) DB::table('programs')
            ->where('id', (int) ($sheet->exam?->program_id ?? 0))
            ->value('Program_Name'));

        $this->sendScreeningPassEmail($user, $sheet, $programName);
    }

    private function handleFailedScreeningExam(AnswerSheet $sheet): void
    {
        $userId = (int) ($sheet->user_id ?? 0);
        if ($userId <= 0) {
            return;
        }

        $user = User::query()->find($userId);
        if (!$user || trim((string) $user->email) === '') {
            return;
        }

        $failedProgramId = (int) ($sheet->exam?->program_id ?? 0);
        $failedRank = $this->studentChoiceRankForProgram($userId, $failedProgramId);
        $nextProgram = $this->advanceToNextScreeningRank($userId, $failedRank);
        $recommendedPrograms = $this->storedSystemRecommendationsForUser($userId);
        $this->sendScreeningFailEmail($user, $sheet, $recommendedPrograms, $failedRank, $nextProgram);
    }

    private function programChoicesForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $choices = Recommendation::query()
            ->join('programs', 'programs.id', '=', 'recommendations.program_id')
            ->where('recommendations.user_id', $userId)
            ->where('recommendations.type', self::TYPE_STUDENT_CHOICE)
            ->orderBy('recommendations.rank')
            ->get([
                'recommendations.program_id',
                'recommendations.rank',
                'programs.Program_Name as program_name',
            ]);

        $mapped = [1 => null, 2 => null, 3 => null];
        foreach ($choices as $choice) {
            $rank = (int) ($choice->rank ?? 0);
            if (!isset($mapped[$rank])) {
                continue;
            }

            $mapped[$rank] = [
                'program_id' => (int) ($choice->program_id ?? 0),
                'program_name' => trim((string) ($choice->program_name ?? '')),
                'rank' => $rank,
            ];
        }

        return $mapped;
    }

    private function recommendedProgramsForAnswerSheet(AnswerSheet $sheet): array
    {
        $scores = $this->subjectScoresForAnswerSheet((int) $sheet->id);
        $totalScore = (int) ($sheet->total_score ?? 0);

        return ProgramRequirement::query()
            ->with('program')
            ->get()
            ->map(function (ProgramRequirement $requirement) use ($scores, $totalScore) {
                $importanceTotal =
                    (float) $requirement->math_scale +
                    (float) $requirement->english_scale +
                    (float) $requirement->science_scale +
                    (float) $requirement->social_science_scale;

                $weightedSum =
                    ($scores['math'] * (float) $requirement->math_scale) +
                    ($scores['english'] * (float) $requirement->english_scale) +
                    ($scores['science'] * (float) $requirement->science_scale) +
                    ($scores['social_science'] * (float) $requirement->social_science_scale);

                $weightedScore = $importanceTotal > 0 ? round($weightedSum / $importanceTotal, 2) : 0.0;
                $minimumScore = (int) $requirement->total_score;
                $isQualified = $totalScore >= $minimumScore;

                return [
                    'program_id' => (int) ($requirement->program_id ?? 0),
                    'program_name' => trim((string) ($requirement->program?->Program_Name ?? '')),
                    'is_qualified' => $isQualified,
                    'weighted_score' => $weightedScore,
                ];
            })
            ->filter(fn (array $row) => $row['program_id'] > 0 && $row['program_name'] !== '' && $row['is_qualified'])
            ->sortByDesc(fn (array $row) => $row['weighted_score'])
            ->take(3)
            ->values()
            ->all();
    }

    private function storeSystemRecommendations(int $userId, array $recommendedPrograms): void
    {
        if ($userId <= 0) {
            return;
        }

        DB::transaction(function () use ($userId, $recommendedPrograms) {
            Recommendation::query()
                ->where('user_id', $userId)
                ->where('type', self::TYPE_SYSTEM)
                ->delete();

            foreach (array_values($recommendedPrograms) as $index => $program) {
                $programId = (int) ($program['program_id'] ?? 0);
                if ($programId <= 0) {
                    continue;
                }

                Recommendation::query()->create([
                    'user_id' => $userId,
                    'program_id' => $programId,
                    'rank' => $index + 1,
                    'type' => self::TYPE_SYSTEM,
                ]);
            }
        });
    }

    private function storedSystemRecommendationsForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        return Recommendation::query()
            ->join('programs', 'programs.id', '=', 'recommendations.program_id')
            ->where('recommendations.user_id', $userId)
            ->where('recommendations.type', self::TYPE_SYSTEM)
            ->orderBy('recommendations.rank')
            ->get([
                'recommendations.program_id',
                'recommendations.rank',
                'programs.Program_Name as program_name',
            ])
            ->map(fn ($row) => [
                'program_id' => (int) ($row->program_id ?? 0),
                'program_name' => trim((string) ($row->program_name ?? '')),
                'rank' => (int) ($row->rank ?? 0),
            ])
            ->values()
            ->all();
    }

    private function sendEntranceResultEmail(User $user, AnswerSheet $sheet, array $programChoices, array $recommendedPrograms, ?array $scheduledScreening = null): void
    {
        $fullName = trim(implode(' ', array_filter([
            trim((string) ($user->first_name ?? '')),
            trim((string) ($user->middle_name ?? '')),
            trim((string) ($user->last_name ?? '')),
            trim((string) ($user->extension_name ?? '')),
        ])));
        $choice1 = $programChoices[1]['program_name'] ?? '';
        $choice2 = $programChoices[2]['program_name'] ?? '';
        $choice3 = $programChoices[3]['program_name'] ?? '';
        $entranceScore = (int) ($sheet->total_score ?? 0);

        $recommended = array_values(array_pad($recommendedPrograms, 3, []));

        $body = implode("\n", [
            'Congratulations' . ($fullName !== '' ? ', ' . $fullName : '') . '!',
            '',
            'We are pleased to inform you that you passed the entrance examination.',
            'Your entrance exam score is: ' . $entranceScore,
            '',
            'Program Choices:',
            '1st Choice: ' . ($choice1 !== '' ? $choice1 : '-'),
            '2nd Choice: ' . ($choice2 !== '' ? $choice2 : '-'),
            '3rd Choice: ' . ($choice3 !== '' ? $choice3 : '-'),
            '',
            'Recommended Programs:',
            '1st: ' . trim((string) ($recommended[0]['program_name'] ?? '-')),
            '2nd: ' . trim((string) ($recommended[1]['program_name'] ?? '-')),
            '3rd: ' . trim((string) ($recommended[2]['program_name'] ?? '-')),
            '',
            'Screening Exam Schedule:',
            'Exam: ' . trim((string) ($scheduledScreening['exam_title'] ?? 'Not yet scheduled')),
            'Date: ' . trim((string) ($scheduledScreening['date'] ?? 'Not yet scheduled')),
            'Time: ' . trim((string) ($scheduledScreening['time'] ?? 'Not yet scheduled')),
            'Location: ' . trim((string) ($scheduledScreening['location'] ?? 'Not yet scheduled')),
            '',
            'Congratulations once again, and we wish you success in the next step of the admission process.',
        ]);

        $this->sendTrackedEmail($user, 'Entrance Exam Result', $body);
    }

    private function sendScreeningPassEmail(User $user, AnswerSheet $sheet, string $programName): void
    {
        $fullName = trim(implode(' ', array_filter([
            trim((string) ($user->first_name ?? '')),
            trim((string) ($user->middle_name ?? '')),
            trim((string) ($user->last_name ?? '')),
            trim((string) ($user->extension_name ?? '')),
        ])));

        $body = implode("\n", [
            'Congratulations' . ($fullName !== '' ? ', ' . $fullName : '') . '!',
            '',
            'We are pleased to inform you that you passed the screening examination.',
            'Screening Exam: ' . trim((string) ($sheet->exam?->Exam_Title ?? '-')),
            'Program: ' . ($programName !== '' ? $programName : '-'),
            'Screening Exam Score: ' . (int) ($sheet->total_score ?? 0),
            '',
            'You may now proceed with the next step of the admission process for your qualified program.',
        ]);

        $this->sendTrackedEmail($user, 'Screening Exam Result', $body);
    }

    private function sendScreeningFailEmail(
        User $user,
        AnswerSheet $sheet,
        array $recommendedPrograms,
        ?int $failedRank = null,
        ?array $nextProgram = null
    ): void
    {
        $fullName = trim(implode(' ', array_filter([
            trim((string) ($user->first_name ?? '')),
            trim((string) ($user->middle_name ?? '')),
            trim((string) ($user->last_name ?? '')),
            trim((string) ($user->extension_name ?? '')),
        ])));
        $recommended = array_values(array_pad($recommendedPrograms, 3, []));

        $bodyLines = [
            'Dear ' . ($fullName !== '' ? $fullName : 'Applicant') . ',',
            '',
            'Thank you for taking the screening examination.',
            'We regret to inform you that you did not meet the passing score for this screening exam.',
            'Screening Exam: ' . trim((string) ($sheet->exam?->Exam_Title ?? '-')),
            'Screening Exam Score: ' . (int) ($sheet->total_score ?? 0),
        ];

        if ($failedRank) {
            $bodyLines[] = 'Screening Choice Rank: ' . $failedRank;
        }

        if ($nextProgram) {
            $bodyLines[] = '';
            $bodyLines[] = 'Next Eligible Screening Option:';
            $bodyLines[] = 'Rank: ' . (int) ($nextProgram['rank'] ?? 0);
            $bodyLines[] = 'Program: ' . trim((string) ($nextProgram['program_name'] ?? '-'));
            $bodyLines[] = 'You are now eligible to be scheduled for this next option.';
        } else {
            $bodyLines[] = '';
            $bodyLines[] = 'There are no further ranked screening options available at this time.';
        }

        $body = implode("\n", array_merge($bodyLines, [
            '',
            'Recommended Programs:',
            '1st: ' . trim((string) ($recommended[0]['program_name'] ?? '-')),
            '2nd: ' . trim((string) ($recommended[1]['program_name'] ?? '-')),
            '3rd: ' . trim((string) ($recommended[2]['program_name'] ?? '-')),
            '',
            'Please go to the college office based on your recommended programs for your scheduling, or take the exam there if instructed by the college.',
        ]));

        $this->sendTrackedEmail($user, 'Screening Exam Result', $body);
    }

    private function sendTrackedEmail(User $user, string $subject, string $body): void
    {
        $fullName = trim(implode(' ', array_filter([
            trim((string) ($user->first_name ?? '')),
            trim((string) ($user->middle_name ?? '')),
            trim((string) ($user->last_name ?? '')),
            trim((string) ($user->extension_name ?? '')),
        ])));

        $emailRequest = EmailRequest::query()->create([
            'user_id' => (int) $user->id,
            'full_name' => $fullName,
            'email' => (string) $user->email,
            'subject' => $subject,
            'message' => $body,
            'status' => 'pending',
        ]);

        try {
            SendEmailRequest::dispatch((int) $emailRequest->id);
        } catch (\Throwable $exception) {
            $emailRequest->markFailed($exception->getMessage());
            Log::warning('Email request dispatch failed after exam result scan.', [
                'email_request_id' => (int) $emailRequest->id,
                'user_id' => (int) $user->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function autoScheduleRecommendedFirstChoice(int $userId, AnswerSheet $sheet, array $programChoices, array $recommendedPrograms): ?array
    {
        $firstChoiceProgramId = (int) ($programChoices[1]['program_id'] ?? 0);
        if ($firstChoiceProgramId <= 0) {
            return null;
        }

        $recommendedProgramIds = collect($recommendedPrograms)
            ->pluck('program_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (!in_array($firstChoiceProgramId, $recommendedProgramIds, true)) {
            return null;
        }

        $screeningExam = DB::table('exams')
            ->where('program_id', $firstChoiceProgramId)
            ->whereRaw("LOWER(TRIM(Exam_Type)) IN ('screening','screening exam')")
            ->orderByDesc('id')
            ->first();

        if (!$screeningExam) {
            return null;
        }

        $existingAssignment = DB::table('student_exam_schedules')
            ->where('user_id', $userId)
            ->where('exam_id', (int) $screeningExam->id)
            ->exists();

        if ($existingAssignment) {
            $existingSchedule = DB::table('student_exam_schedules as ses')
                ->join('exam_schedules as sch', 'sch.id', '=', 'ses.exam_schedule_id')
                ->where('ses.user_id', $userId)
                ->where('ses.exam_id', (int) $screeningExam->id)
                ->select([
                    'sch.date',
                    'sch.time',
                    'sch.location',
                ])
                ->first();

            return [
                'exam_title' => (string) ($screeningExam->Exam_Title ?? ''),
                'date' => (string) ($existingSchedule->date ?? ''),
                'time' => (string) ($existingSchedule->time ?? ''),
                'location' => (string) ($existingSchedule->location ?? ''),
            ];
        }

        $resultAt = $sheet->updated_at ?? now();

        $schedule = DB::table('exam_schedules as sch')
            ->where('sch.schedule_type', 'screening')
            ->where(function ($query) use ($resultAt) {
                $query->where('sch.date', '>', $resultAt->toDateString())
                    ->orWhere(function ($inner) use ($resultAt) {
                        $inner->where('sch.date', '=', $resultAt->toDateString())
                            ->where('sch.time', '>', $resultAt->format('H:i:s'));
                    });
            })
            ->whereRaw('(
                SELECT COUNT(DISTINCT ses.user_id)
                FROM student_exam_schedules ses
                WHERE ses.exam_schedule_id = sch.id
            ) < sch.capacity')
            ->orderBy('sch.date', 'desc')
            ->orderBy('sch.time', 'desc')
            ->first();

        if (!$schedule) {
            return null;
        }

        DB::table('student_exam_schedules')->insert([
            'user_id' => $userId,
            'exam_id' => (int) $screeningExam->id,
            'exam_schedule_id' => (int) $schedule->id,
            'status' => 'scheduled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'exam_title' => (string) ($screeningExam->Exam_Title ?? ''),
            'date' => (string) ($schedule->date ?? ''),
            'time' => (string) ($schedule->time ?? ''),
            'location' => (string) ($schedule->location ?? ''),
        ];
    }

    private function studentChoiceRankForProgram(int $userId, int $programId): ?int
    {
        if ($userId <= 0 || $programId <= 0) {
            return null;
        }

        $rank = Recommendation::query()
            ->where('user_id', $userId)
            ->where('program_id', $programId)
            ->where('type', self::TYPE_STUDENT_CHOICE)
            ->value('rank');

        $rank = (int) ($rank ?? 0);
        return $rank > 0 ? $rank : null;
    }

    private function advanceToNextScreeningRank(int $userId, ?int $currentRank): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $baseRank = $currentRank && $currentRank > 0 ? $currentRank : 1;
        $nextChoice = Recommendation::query()
            ->join('programs', 'programs.id', '=', 'recommendations.program_id')
            ->where('recommendations.user_id', $userId)
            ->where('recommendations.type', self::TYPE_STUDENT_CHOICE)
            ->where('recommendations.rank', '>', $baseRank)
            ->orderBy('recommendations.rank')
            ->select([
                'recommendations.program_id',
                'recommendations.rank',
                'programs.Program_Name as program_name',
            ])
            ->first();

        if (!$nextChoice) {
            $this->setActiveScreeningRank($userId, $baseRank + 1);
            return null;
        }

        $nextRank = (int) ($nextChoice->rank ?? ($baseRank + 1));
        $this->setActiveScreeningRank($userId, $nextRank);

        return [
            'program_id' => (int) ($nextChoice->program_id ?? 0),
            'program_name' => trim((string) ($nextChoice->program_name ?? '')),
            'rank' => $nextRank,
        ];
    }

    private function setActiveScreeningRank(int $userId, int $rank): void
    {
        if ($userId <= 0 || $rank <= 0) {
            return;
        }

        DB::table('student_screening_progress')->updateOrInsert(
            ['user_id' => $userId],
            [
                'current_rank' => $rank,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function subjectScoresForAnswerSheet(int $answerSheetId): array
    {
        $row = DB::table('exam_results as er')
            ->join('subjects as s', 's.id', '=', 'er.subject_id')
            ->where('er.answer_sheet_id', $answerSheetId)
            ->selectRaw("
                COALESCE(MAX(CASE WHEN LOWER(s.Subject_Name) LIKE '%math%' THEN er.raw_score END), 0) as math,
                COALESCE(MAX(CASE WHEN LOWER(s.Subject_Name) LIKE '%english%' THEN er.raw_score END), 0) as english,
                COALESCE(MAX(CASE
                    WHEN LOWER(s.Subject_Name) LIKE '%science%'
                     AND LOWER(s.Subject_Name) NOT LIKE '%social science%'
                     AND LOWER(s.Subject_Name) NOT LIKE '%social%'
                    THEN er.raw_score
                END), 0) as science,
                COALESCE(MAX(CASE WHEN LOWER(s.Subject_Name) LIKE '%social science%' OR LOWER(s.Subject_Name) LIKE '%social%' THEN er.raw_score END), 0) as social_science
            ")
            ->first();

        return [
            'math' => (int) ($row->math ?? 0),
            'english' => (int) ($row->english ?? 0),
            'science' => (int) ($row->science ?? 0),
            'social_science' => (int) ($row->social_science ?? 0),
        ];
    }
}
