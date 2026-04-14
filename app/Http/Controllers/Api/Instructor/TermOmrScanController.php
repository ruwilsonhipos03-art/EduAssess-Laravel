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

        DB::transaction(function () use ($sheet, $storedPath, $studentAnswers, $subjectScores, $totalScore, $user) {
            $updates = [
                'image_path' => $storedPath,
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

        $debugRelative = (string) ($omr['data']['debug'] ?? '');
        if ($debugRelative !== '') {
            $debugRelative = ltrim($debugRelative, '/');
            $candidates = [
                storage_path('app/public/' . $debugRelative),
                public_path('storage/' . $debugRelative),
            ];
            foreach ($candidates as $debugAbsolute) {
                if (is_file($debugAbsolute)) {
                    @unlink($debugAbsolute);
                }
            }
        }

        return [
            'success' => true,
            'file' => $file->getClientOriginalName(),
            'sheet_code' => $payload,
            'exam_title' => $sheet->exam?->Exam_Title,
            'student_id' => $sheet->user_id,
            'score' => $totalScore,
            'debug_image' => null,
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
            'middle_initial' => $parts[5],
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
}

