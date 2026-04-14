<?php

namespace App\Http\Controllers\Api\EntranceExaminer;

use App\Http\Controllers\Controller;
use App\Services\PythonOmrApiClient;
use App\Models\AnswerKey;
use App\Models\AnswerSheet;
use App\Models\ExamSubject;
use App\Models\ProgramRequirement;
use App\Models\Recommendation;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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
            'debug_image' => null,
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
        $scheduledScreening = $this->autoScheduleRecommendedFirstChoice($userId, $sheet, $programChoices, $recommendedPrograms);
        $this->sendEntranceResultEmail($user, $sheet, $programChoices, $recommendedPrograms, $scheduledScreening);
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

        $recommendedPrograms = $this->storedSystemRecommendationsForUser($userId);
        $this->sendScreeningFailEmail($user, $sheet, $recommendedPrograms);
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
            trim((string) ($user->middle_initial ?? '')),
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

        Mail::raw($body, function ($message) use ($user) {
            $message->to((string) $user->email)
                ->subject('Entrance Exam Result');
        });
    }

    private function sendScreeningPassEmail(User $user, AnswerSheet $sheet, string $programName): void
    {
        $fullName = trim(implode(' ', array_filter([
            trim((string) ($user->first_name ?? '')),
            trim((string) ($user->middle_initial ?? '')),
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

        Mail::raw($body, function ($message) use ($user) {
            $message->to((string) $user->email)
                ->subject('Screening Exam Result');
        });
    }

    private function sendScreeningFailEmail(User $user, AnswerSheet $sheet, array $recommendedPrograms): void
    {
        $fullName = trim(implode(' ', array_filter([
            trim((string) ($user->first_name ?? '')),
            trim((string) ($user->middle_initial ?? '')),
            trim((string) ($user->last_name ?? '')),
            trim((string) ($user->extension_name ?? '')),
        ])));
        $recommended = array_values(array_pad($recommendedPrograms, 3, []));

        $body = implode("\n", [
            'Dear ' . ($fullName !== '' ? $fullName : 'Applicant') . ',',
            '',
            'Thank you for taking the screening examination.',
            'We regret to inform you that you did not meet the passing score for this screening exam.',
            'Screening Exam: ' . trim((string) ($sheet->exam?->Exam_Title ?? '-')),
            'Screening Exam Score: ' . (int) ($sheet->total_score ?? 0),
            '',
            'Recommended Programs:',
            '1st: ' . trim((string) ($recommended[0]['program_name'] ?? '-')),
            '2nd: ' . trim((string) ($recommended[1]['program_name'] ?? '-')),
            '3rd: ' . trim((string) ($recommended[2]['program_name'] ?? '-')),
            '',
            'Please go to the college office based on your recommended programs for your scheduling, or take the exam there if instructed by the college.',
        ]);

        Mail::raw($body, function ($message) use ($user) {
            $message->to((string) $user->email)
                ->subject('Screening Exam Result');
        });
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

