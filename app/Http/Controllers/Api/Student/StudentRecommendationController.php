<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\AnswerSheet;
use App\Models\ProgramRequirement;
use App\Models\Recommendation;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StudentRecommendationController extends Controller
{
    private const ENTRANCE_TYPE_ALIASES = ['entrance', 'entrance exam', 'screening', 'screening exam'];
    private const SCREENING_TYPE_ALIASES = ['screening', 'screening exam'];
    private const PASSING_SCORE = 75;
    private const TYPE_STUDENT_CHOICE = 'student_choice';
    private const TYPE_FINAL_PROGRAM = 'final_program';
    private const TYPE_CONTINUE_SCREENING = 'continue_screening';

    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'student') {
            return response()->json([
                'success' => false,
                'message' => 'Only students can access recommendations.',
            ], 403);
        }

        $answerSheetId = $request->integer('answer_sheet_id');

        $sheetQuery = AnswerSheet::query()
            ->with('exam')
            ->where('user_id', $user->id)
            ->where('status', 'checked')
            ->where('total_score', '>=', self::PASSING_SCORE)
            ->orderByDesc('updated_at');

        if ($answerSheetId) {
            $sheetQuery->where('id', $answerSheetId);
        }

        $sheet = $sheetQuery->first();
        if (!$sheet) {
            return response()->json([
                'success' => true,
                'data' => [
                    'eligible' => false,
                    'message' => 'No passed entrance exam result found yet.',
                    'programs' => [],
                    'selected_program_ids' => [],
                ],
            ]);
        }

        if (!$this->isEntranceExamType($sheet->exam?->Exam_Type)) {
            return response()->json([
                'success' => true,
                'data' => [
                    'eligible' => false,
                    'message' => 'Program recommendation is only available for entrance exams.',
                    'programs' => [],
                    'selected_program_ids' => [],
                ],
            ]);
        }

        $scores = $this->subjectScoresForAnswerSheet((int) $sheet->id);
        $totalScore = (int) ($sheet->total_score ?? 0);

        $requirements = ProgramRequirement::query()
            ->with('program.college')
            ->get();

        $programs = $requirements
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
                    'program_requirement_id' => (int) $requirement->id,
                    'program_id' => (int) $requirement->program_id,
                    'program_name' => (string) ($requirement->program?->Program_Name ?? ''),
                    'College_Name' => (string) ($requirement->program?->college?->College_Name ?? ''),
                    'college_id' => (int) (($requirement->program?->college_id ?? null) ?? ($requirement->program?->department_id ?? 0)),
                    'minimum_total_score' => $minimumScore,
                    'student_total_score' => $totalScore,
                    'is_qualified' => $isQualified,
                    'weighted_score' => $weightedScore,
                    'importance' => [
                        'math' => (float) $requirement->math_scale,
                        'english' => (float) $requirement->english_scale,
                        'science' => (float) $requirement->science_scale,
                        'social_science' => (float) $requirement->social_science_scale,
                    ],
                ];
            })
            ->filter(fn (array $row) => $row['program_id'] > 0 && $row['program_name'] !== '')
            ->sortByDesc(function (array $row) {
                return ($row['is_qualified'] ? 100000 : 0) + $row['weighted_score'];
            })
            ->values();

        $screeningAttemptsByProgram = $this->screeningAttemptsByPrograms($user->id, $programs->all());
        $programs = $programs
            ->map(function (array $row) use ($screeningAttemptsByProgram) {
                $attempt = $screeningAttemptsByProgram[(int) $row['program_id']] ?? null;

                return $row + [
                    'screening_attempted' => (bool) ($attempt['attempted'] ?? false),
                    'screening_status' => (string) ($attempt['status'] ?? 'no_attempt'),
                    'screening_exam_title' => $attempt['exam_title'] ?? null,
                    'screening_total_score' => $attempt['total_score'] ?? null,
                ];
            })
            ->values();

        $selectedProgramIds = Recommendation::query()
            ->where('user_id', $user->id)
            ->where('type', self::TYPE_STUDENT_CHOICE)
            ->orderBy('rank')
            ->pluck('program_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $selectionState = $this->selectionLockState($user->id, $selectedProgramIds);
        $workflowState = $this->screeningWorkflowState($user->id, $selectedProgramIds, $selectionState['statuses']);

        return response()->json([
            'success' => true,
            'data' => [
                'eligible' => true,
                'answer_sheet_id' => (int) $sheet->id,
                'exam_name' => (string) ($sheet->exam?->Exam_Title ?? ''),
                'student_scores' => $scores + ['total' => $totalScore],
                'programs' => $programs,
                'selected_program_ids' => $selectedProgramIds,
                'selection_locked' => $selectionState['locked'],
                'can_repick' => $selectionState['can_repick'],
                'lock_reason' => $workflowState['final_program_id']
                    ? 'You already selected your final program. You cannot take screening exams for lower-ranked programs.'
                    : $selectionState['reason'],
                'screening_statuses' => $selectionState['statuses'],
                'screening_attempts' => $programs
                    ->filter(fn (array $row) => !empty($row['screening_attempted']))
                    ->map(fn (array $row) => [
                        'program_id' => (int) $row['program_id'],
                        'program_name' => (string) $row['program_name'],
                        'status' => (string) ($row['screening_status'] ?? 'no_attempt'),
                        'exam_title' => $row['screening_exam_title'] ?? null,
                        'total_score' => $row['screening_total_score'] ?? null,
                    ])
                    ->values(),
                'workflow' => $workflowState,
            ],
        ]);
    }

    public function saveSelection(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'student') {
            return response()->json([
                'success' => false,
                'message' => 'Only students can save recommendations.',
            ], 403);
        }

        $validated = $request->validate([
            'answer_sheet_id' => 'nullable|integer|exists:answer_sheets,id',
            'program_ids' => 'required|array|min:1|max:3',
            'program_ids.*' => 'required|integer|distinct|exists:programs,id',
        ]);

        $existingSelections = Recommendation::query()
            ->where('user_id', $user->id)
            ->where('type', self::TYPE_STUDENT_CHOICE)
            ->orderBy('rank')
            ->pluck('program_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $lockState = $this->selectionLockState($user->id, $existingSelections);
        if (count($existingSelections) === 3 && $lockState['locked']) {
            return response()->json([
                'success' => false,
                'message' => $lockState['reason'],
            ], 422);
        }

        $selected = collect($validated['program_ids'])->map(fn ($id) => (int) $id)->all();
        $isRepickAfterFailAll = count($existingSelections) === 3 && !($lockState['locked'] ?? true);
        if ($isRepickAfterFailAll && count($selected) !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'After failing all 3 selected screening exams, select exactly 1 new program.',
            ], 422);
        }
        if (!$isRepickAfterFailAll && count($selected) !== 3) {
            return response()->json([
                'success' => false,
                'message' => 'Please select exactly 3 qualified programs.',
            ], 422);
        }

        $request->merge(['answer_sheet_id' => $validated['answer_sheet_id'] ?? null]);
        $recommendationData = $this->index($request)->getData(true);

        $payload = $recommendationData['data'] ?? [];
        if (!($payload['eligible'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $payload['message'] ?? 'No eligible entrance exam result found.',
            ], 422);
        }

        $eligibleProgramIds = collect($payload['programs'] ?? [])
            ->filter(fn ($row) => !empty($row['is_qualified']))
            ->pluck('program_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $hasInvalidSelection = collect($selected)->contains(fn ($id) => !in_array($id, $eligibleProgramIds, true));
        if ($hasInvalidSelection) {
            return response()->json([
                'success' => false,
                'message' => 'Selections must come from qualified program recommendations.',
            ], 422);
        }

        // If student is re-picking after failing all 3, do not allow picking previously selected programs again.
        if (count($existingSelections) === 3 && !($lockState['locked'] ?? true)) {
            $hasPreviouslySelected = collect($selected)->contains(
                fn ($id) => in_array((int) $id, $existingSelections, true)
            );

            if ($hasPreviouslySelected) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot re-select programs from your previous screening attempts.',
                ], 422);
            }
        }

        $attemptedProgramIds = collect($payload['programs'] ?? [])
            ->filter(fn ($row) => !empty($row['screening_attempted']))
            ->pluck('program_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $hasTakenSelection = collect($selected)->contains(fn ($id) => in_array($id, $attemptedProgramIds, true));
        if ($hasTakenSelection) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot select programs where you already took a screening exam.',
            ], 422);
        }

        Recommendation::query()
            ->where('user_id', $user->id)
            ->where('type', self::TYPE_STUDENT_CHOICE)
            ->delete();

        Recommendation::query()
            ->where('user_id', $user->id)
            ->whereIn('type', [self::TYPE_FINAL_PROGRAM, self::TYPE_CONTINUE_SCREENING])
            ->delete();

        foreach ($selected as $index => $programId) {
            Recommendation::create([
                'user_id' => $user->id,
                'program_id' => $programId,
                'rank' => $index + 1,
                'type' => self::TYPE_STUDENT_CHOICE,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => count($existingSelections) === 3
                ? 'Top 3 program choices updated after failing all selected screening exams.'
                : 'Top 3 program choices saved.',
        ]);
    }

    public function saveScreeningDecision(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'student') {
            return response()->json([
                'success' => false,
                'message' => 'Only students can save screening decisions.',
            ], 403);
        }

        $validated = $request->validate([
            'program_id' => 'required|integer|exists:programs,id',
            'action' => 'required|string|in:continue,pick',
        ]);

        $programId = (int) $validated['program_id'];
        $action = (string) $validated['action'];

        $selectedProgramIds = Recommendation::query()
            ->where('user_id', $user->id)
            ->where('type', self::TYPE_STUDENT_CHOICE)
            ->orderBy('rank')
            ->pluck('program_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if (!in_array($programId, $selectedProgramIds, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Program is not part of your selected screening programs.',
            ], 422);
        }

        $selectionState = $this->selectionLockState($user->id, $selectedProgramIds);
        $workflowState = $this->screeningWorkflowState($user->id, $selectedProgramIds, $selectionState['statuses']);

        if (!empty($workflowState['final_program_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Final program is already selected.',
            ], 422);
        }

        $passed = collect($workflowState['passed_programs'] ?? [])->firstWhere('program_id', $programId);
        if (!$passed) {
            return response()->json([
                'success' => false,
                'message' => 'This program is not yet passed in screening.',
            ], 422);
        }

        if ($action === 'continue') {
            if (empty($passed['can_continue'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No remaining selected programs are available to continue.',
                ], 422);
            }

            Recommendation::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'program_id' => $programId,
                    'type' => self::TYPE_CONTINUE_SCREENING,
                ],
                ['rank' => (int) ($passed['rank'] ?? 1)]
            );

            return response()->json([
                'success' => true,
                'message' => 'Decision saved. You may proceed to the next selected screening exam.',
            ]);
        }

        Recommendation::query()
            ->where('user_id', $user->id)
            ->where('type', self::TYPE_FINAL_PROGRAM)
            ->delete();

        Recommendation::query()->create([
            'user_id' => $user->id,
            'program_id' => $programId,
            'rank' => 1,
            'type' => self::TYPE_FINAL_PROGRAM,
        ]);

        $this->assignStudentProgram((int) $user->id, $programId);

        return response()->json([
            'success' => true,
            'message' => 'Final program selected. Screening for lower-ranked programs is now locked.',
        ]);
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

    private function isEntranceExamType(?string $examType): bool
    {
        $value = strtolower(trim((string) $examType));
        return in_array($value, self::ENTRANCE_TYPE_ALIASES, true);
    }

    private function selectionLockState(int $userId, array $selectedProgramIds): array
    {
        $selectedProgramIds = collect($selectedProgramIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        if (count($selectedProgramIds) < 3) {
            return [
                'locked' => false,
                'can_repick' => true,
                'reason' => '',
                'statuses' => [],
            ];
        }

        $programs = \App\Models\Program::query()
            ->whereIn('id', $selectedProgramIds)
            ->select(['id', 'Program_Name'])
            ->selectRaw(
                ($this->programOrgUnitColumn() !== null)
                    ? "{$this->programOrgUnitColumn()} as college_id"
                    : "0 as college_id"
            )
            ->get()
            ->keyBy('id');

        $statuses = [];
        $allFailed = true;
        $attemptsByProgram = $this->screeningAttemptsByPrograms(
            $userId,
            collect($selectedProgramIds)
                ->map(fn ($programId) => [
                    'program_id' => (int) $programId,
                    'program_name' => trim((string) ($programs->get($programId)?->Program_Name ?? '')),
                    'college_id' => (int) ($programs->get($programId)?->college_id ?? 0),
                ])
                ->values()
                ->all()
        );

        foreach ($selectedProgramIds as $programId) {
            $program = $programs->get($programId);
            $programName = trim((string) ($program?->Program_Name ?? ''));
            $latest = $attemptsByProgram[(int) $programId] ?? null;

            if (!$latest) {
                $statuses[] = [
                    'program_id' => $programId,
                    'program_name' => $programName,
                    'status' => 'no_attempt',
                    'exam_title' => null,
                    'total_score' => null,
                ];
                $allFailed = false;
                continue;
            }

            $status = (string) ($latest['status'] ?? 'no_attempt');
            $score = isset($latest['total_score']) ? (int) $latest['total_score'] : null;

            $statuses[] = [
                'program_id' => $programId,
                'program_name' => $programName,
                'status' => $status,
                'exam_title' => (string) ($latest['exam_title'] ?? ''),
                'total_score' => $score,
            ];

            if ($status !== 'failed') {
                $allFailed = false;
            }
        }

        if ($allFailed) {
            return [
                'locked' => false,
                'can_repick' => true,
                'reason' => 'You failed all 3 selected screening exams. You may pick a new top 3.',
                'statuses' => $statuses,
            ];
        }

        return [
            'locked' => true,
            'can_repick' => false,
            'reason' => 'You already selected your top 3 programs. Re-picking is allowed only if you fail all 3 selected screening exams.',
            'statuses' => $statuses,
        ];
    }

    private function screeningWorkflowState(int $userId, array $selectedProgramIds, array $statuses): array
    {
        $selectedProgramIds = collect($selectedProgramIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        $programs = \App\Models\Program::query()
            ->whereIn('id', $selectedProgramIds)
            ->get(['id', 'Program_Name'])
            ->keyBy('id');

        $rankMap = [];
        foreach ($selectedProgramIds as $index => $programId) {
            $rankMap[$programId] = $index + 1;
        }

        $finalProgramId = (int) (Recommendation::query()
            ->where('user_id', $userId)
            ->where('type', self::TYPE_FINAL_PROGRAM)
            ->value('program_id') ?? 0);
        if ($finalProgramId <= 0) {
            $finalProgramId = null;
        }

        $continuedProgramIds = Recommendation::query()
            ->where('user_id', $userId)
            ->where('type', self::TYPE_CONTINUE_SCREENING)
            ->pluck('program_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $statusByProgram = collect($statuses)
            ->keyBy(fn ($row) => (int) ($row['program_id'] ?? 0));

        $passedPrograms = [];
        foreach ($selectedProgramIds as $programId) {
            $status = (string) (($statusByProgram[$programId]['status'] ?? 'no_attempt'));
            if ($status !== 'passed') {
                continue;
            }

            $rank = (int) ($rankMap[$programId] ?? 0);
            $hasRemaining = collect($selectedProgramIds)->contains(
                fn ($id) => (int) ($rankMap[$id] ?? 0) > $rank
            );

            $passedPrograms[] = [
                'program_id' => $programId,
                'program_name' => (string) ($programs[$programId]?->Program_Name ?? ''),
                'rank' => $rank,
                'total_score' => $statusByProgram[$programId]['total_score'] ?? null,
                'can_continue' => $hasRemaining,
                'continued' => in_array($programId, $continuedProgramIds, true),
            ];
        }

        $failedCount = collect($statuses)->where('status', 'failed')->count();
        $allow_new_single_program_pick = count($selectedProgramIds) === 3 && $failedCount === 3;

        return [
            'final_program_id' => $finalProgramId,
            'continued_program_ids' => $continuedProgramIds,
            'passed_programs' => array_values($passedPrograms),
            'allow_new_single_program_pick' => $allow_new_single_program_pick,
        ];
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

    private function screeningAttemptsByPrograms(int $userId, array $programs): array
    {
        $deptProgramCounts = collect($programs)
            ->groupBy(fn (array $program) => (int) ($program['college_id'] ?? 0))
            ->map(fn ($rows) => count($rows))
            ->all();

        $employeeOrgUnitColumn = $this->employeeOrgUnitColumn();
        $attemptRows = DB::table('answer_sheets as ans')
            ->join('exams as e', 'e.id', '=', 'ans.exam_id')
            ->leftJoin('employees as emp', 'emp.id', '=', 'e.created_by')
            ->where('ans.user_id', $userId)
            ->whereIn('ans.status', ['scanned', 'checked'])
            ->whereIn(DB::raw('LOWER(e.Exam_Type)'), self::SCREENING_TYPE_ALIASES)
            ->orderByDesc('ans.updated_at')
            ->select('ans.status', 'ans.total_score', 'ans.updated_at', 'e.Exam_Title')
            ->selectRaw(
                $employeeOrgUnitColumn !== null
                    ? "emp.{$employeeOrgUnitColumn} as college_id"
                    : "0 as college_id"
            )
            ->get()
            ->map(fn ($row) => [
                'status' => strtolower(trim((string) ($row->status ?? ''))),
                'total_score' => isset($row->total_score) ? (int) $row->total_score : null,
                'exam_title' => (string) ($row->Exam_Title ?? ''),
                'college_id' => (int) ($row->college_id ?? 0),
            ])
            ->values()
            ->all();

        $results = [];

        foreach ($programs as $program) {
            $programId = (int) ($program['program_id'] ?? 0);
            $programName = trim((string) ($program['program_name'] ?? ''));
            $programDepartmentId = (int) ($program['college_id'] ?? 0);
            $allowDepartmentFallback = $programDepartmentId > 0
                && (($deptProgramCounts[$programDepartmentId] ?? 0) === 1);

            if ($programId <= 0 || $programName === '') {
                continue;
            }

            $latest = collect($attemptRows)->first(function (array $attempt) use ($programName, $programDepartmentId, $allowDepartmentFallback) {
                return $this->examMatchesProgram(
                    (string) ($attempt['exam_title'] ?? ''),
                    $programName,
                    (int) ($attempt['college_id'] ?? 0),
                    $programDepartmentId,
                    $allowDepartmentFallback
                );
            });

            if (!$latest) {
                $results[$programId] = [
                    'attempted' => false,
                    'status' => 'no_attempt',
                    'exam_title' => null,
                    'total_score' => null,
                ];
                continue;
            }

            $sheetStatus = strtolower(trim((string) ($latest['status'] ?? '')));
            if ($sheetStatus !== 'checked') {
                $results[$programId] = [
                    'attempted' => true,
                    'status' => 'in_progress',
                    'exam_title' => (string) ($latest['exam_title'] ?? ''),
                    'total_score' => null,
                ];
                continue;
            }

            $score = (int) ($latest['total_score'] ?? 0);
            $results[$programId] = [
                'attempted' => true,
                'status' => $score >= self::PASSING_SCORE ? 'passed' : 'failed',
                'exam_title' => (string) ($latest['exam_title'] ?? ''),
                'total_score' => $score,
            ];
        }

        return $results;
    }

    private function employeeOrgUnitColumn(): ?string
    {
        if (Schema::hasColumn('employees', 'college_id')) {
            return 'college_id';
        }

        if (Schema::hasColumn('employees', 'department_id')) {
            return 'department_id';
        }

        return null;
    }

    private function programOrgUnitColumn(): ?string
    {
        if (Schema::hasColumn('programs', 'college_id')) {
            return 'college_id';
        }

        if (Schema::hasColumn('programs', 'department_id')) {
            return 'department_id';
        }

        return null;
    }

    private function examMatchesProgram(
        string $examTitle,
        string $programName,
        int $examDepartmentId,
        int $programDepartmentId,
        bool $allowDepartmentFallback
    ): bool {
        if ($this->titleMatchesProgramName($examTitle, $programName)) {
            return true;
        }

        if ($allowDepartmentFallback && $examDepartmentId > 0 && $programDepartmentId > 0) {
            return $examDepartmentId === $programDepartmentId;
        }

        return false;
    }

    private function titleMatchesProgramName(string $examTitle, string $programName): bool
    {
        $normalizedTitle = $this->normalizeText($examTitle);
        $normalizedProgram = $this->normalizeText($programName);

        if ($normalizedTitle === '' || $normalizedProgram === '') {
            return false;
        }

        if (str_contains($normalizedTitle, $normalizedProgram) || str_contains($normalizedProgram, $normalizedTitle)) {
            return true;
        }

        $tokens = $this->significantTokens($normalizedProgram);
        if (empty($tokens)) {
            return false;
        }

        $matched = 0;
        foreach ($tokens as $token) {
            if (str_contains($normalizedTitle, $token)) {
                $matched++;
            }
        }

        $requiredMatches = count($tokens) >= 3 ? 2 : 1;
        return $matched >= $requiredMatches;
    }

    private function normalizeText(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/i', ' ', $value) ?? '';
        $value = preg_replace('/\s+/', ' ', $value) ?? '';
        return trim($value);
    }

    private function significantTokens(string $value): array
    {
        $stopWords = ['bs', 'ba', 'bsa', 'bsc', 'bachelor', 'in', 'of', 'major', 'program'];
        $parts = array_filter(explode(' ', $value), function ($token) use ($stopWords) {
            return strlen($token) >= 3 && !in_array($token, $stopWords, true);
        });

        return array_values(array_unique($parts));
    }
}
