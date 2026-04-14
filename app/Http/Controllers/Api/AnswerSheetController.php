<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnswerKey;
use App\Models\AnswerSheet;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\Recommendation;
use App\Models\Student;
use App\Models\User;
use App\Services\ActivityLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class AnswerSheetController extends Controller
{
    private const ENTRANCE_TYPE_ALIASES = ['entrance', 'entrance exam'];
    private const SCREENING_TYPE_ALIASES = ['screening', 'screening exam'];
    private const PASSING_SCORE = 75;
    private const TYPE_STUDENT_CHOICE = 'student_choice';
    private const TYPE_FINAL_PROGRAM = 'final_program';
    private const TYPE_CONTINUE_SCREENING = 'continue_screening';

    public function index()
    {
        $user = Auth::user();
        $userId = Auth::id();

        $query = AnswerSheet::with(['exam.creator.college', 'exam.program', 'user.studentProfile.program']);

        if ($user && $user->role === 'student') {
            return $query
                ->where('user_id', $userId)
                ->latest()
                ->get();
        }

        return $query
            ->where(function ($inner) use ($userId) {
                $inner->where('created_by', $userId)
                    ->orWhere(function ($fallback) use ($userId) {
                        $fallback->whereNull('created_by')
                            ->where('user_id', $userId);
                    });
            })
            ->latest()
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'exam_id' => 'required|exists:exams,id',
        ]);

        $sheet = $this->createSheet((int) $data['exam_id']);

        return response()->json($sheet->load('exam'), 201);
    }

    public function update(Request $request, $id)
    {
        $sheet = AnswerSheet::where(function ($query) {
            $query->where('created_by', Auth::id())
                ->orWhere(function ($fallback) {
                    $fallback->whereNull('created_by')
                        ->where('user_id', Auth::id());
                });
        })->findOrFail($id);

        $data = $request->validate([
            'image_path' => 'nullable|string',
            'scanned_data' => 'nullable|array',
            'total_score' => 'nullable|integer',
            'status' => 'nullable|in:generated,scanned,checked',
        ]);

        $sheet->update($data);

        return response()->json($sheet->load('exam'));
    }

    public function destroy($id)
    {
        $sheet = AnswerSheet::where(function ($query) {
            $query->where('created_by', Auth::id())
                ->orWhere(function ($fallback) {
                    $fallback->whereNull('created_by')
                        ->where('user_id', Auth::id());
                });
        })->findOrFail($id);
        $sheet->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function generatePdf(Request $request)
    {
        @set_time_limit(300);
        @ini_set('memory_limit', '1024M');

        $data = $request->validate([
            'exam_id' => 'required|exists:exams,id',
            'count' => 'nullable|integer|min:1|max:200',
            'exam_schedule_id' => 'nullable|exists:exam_schedules,id',
        ]);

        $examId = (int) $data['exam_id'];

        if (!$this->hasReadyAnswerKeyForUser($examId, Auth::id())) {
            return response()->json([
                'message' => 'Please set a complete answer key for this exam before generating answer sheets.',
            ], 422);
        }

        $exam = Exam::find($examId);
        $scheduleId = isset($data['exam_schedule_id']) ? (int) $data['exam_schedule_id'] : 0;

        if ($scheduleId > 0) {
            $scheduledStudents = $this->scheduledStudentsForScheduleQuery($scheduleId, $examId)
                ->get();

            if ($scheduledStudents->isEmpty()) {
                return response()->json([
                    'message' => 'No students are assigned to the selected exam schedule.',
                ], 422);
            }

            $choiceMap = $this->programChoicesMapForUsers(
                $scheduledStudents->pluck('user_id')->map(fn ($id) => (int) $id)->all()
            );

            $pdfSheets = $scheduledStudents->map(function ($row) use ($examId, $choiceMap) {
                $sheet = AnswerSheet::firstOrCreate(
                    [
                        'exam_id' => $examId,
                        'user_id' => (int) $row->user_id,
                        'created_by' => (int) Auth::id(),
                    ],
                    [
                        'status' => 'generated',
                        'qr_payload' => '',
                    ]
                );

                $sheet->update([
                    'qr_payload' => $this->buildSheetCode(
                        (int) ($row->user_id ?? $sheet->user_id ?? 0),
                        (int) $sheet->id,
                        (int) $sheet->exam_id
                    ),
                ]);

                return $this->formatSheetForPdf($sheet, [
                    'student_name' => $this->formatStudentNameFromRow($row),
                    'last_name' => (string) ($row->last_name ?? ''),
                    'first_name' => (string) ($row->first_name ?? ''),
                    'middle_initial' => (string) ($row->middle_initial ?? ''),
                    'extension_name' => (string) ($row->extension_name ?? ''),
                    'student_number' => (string) ($row->Student_Number ?? ''),
                    'scheduled_date' => $row->scheduled_date
                        ? \Carbon\Carbon::parse($row->scheduled_date)->format('F j, Y')
                        : '',
                    'program_choices' => $choiceMap[(int) $row->user_id] ?? [],
                ]);
            })->all();
        } else {
            $count = (int) ($data['count'] ?? 0);
            if ($count <= 0) {
                return response()->json([
                    'message' => 'Please enter the number of sheets to generate.',
                ], 422);
            }

            $created = collect();
            for ($i = 0; $i < $count; $i++) {
                $sheet = $this->createSheet($examId);
                $created->push($sheet->load('exam'));
            }

            $pdfSheets = $created->map(fn ($sheet) => $this->formatSheetForPdf($sheet))->all();
        }

        $pdf = Pdf::setOption([
            'dpi' => 96,
            'defaultFont' => 'Arial',
            'isRemoteEnabled' => false,
        ])->loadView('pdf.bubble_sheet', [
            'sheets' => $pdfSheets,
            'exam' => $exam,
        ]);

        $fileName = 'answer_sheets_generated_' . now()->format('Ymd_His') . '.pdf';

        return $pdf->download($fileName);
    }

    public function entranceScheduledStudents(Request $request)
    {
        $user = $request->user();
        if (!$user || !str_contains((string) $user->role, 'entrance')) {
            return response()->json([
                'message' => 'Only entrance examiners can access scheduled students.',
            ], 403);
        }

        $rows = $this->scheduledEntranceStudentsQuery()->get();
        $choiceMap = $this->programChoicesMapForUsers(
            $rows->pluck('user_id')->map(fn ($id) => (int) $id)->all()
        );

        $schedules = $rows
            ->groupBy('exam_schedule_id')
            ->map(function ($group) use ($choiceMap) {
                $first = $group->first();

                return [
                    'id' => (int) $first->exam_schedule_id,
                    'date' => (string) ($first->scheduled_date ?? ''),
                    'formatted_date' => $first->scheduled_date
                        ? \Carbon\Carbon::parse($first->scheduled_date)->format('F j, Y')
                        : '',
                    'time' => (string) ($first->scheduled_time ?? ''),
                    'location' => (string) ($first->scheduled_location ?? ''),
                    'label' => trim(implode(' | ', array_filter([
                        $first->scheduled_date ? \Carbon\Carbon::parse($first->scheduled_date)->format('F j, Y') : '',
                        (string) ($first->scheduled_time ?? ''),
                        (string) ($first->scheduled_location ?? ''),
                    ]))),
                    'students' => $group->map(function ($row) use ($choiceMap) {
                        $choices = $choiceMap[(int) $row->user_id] ?? [];

                        return [
                            'user_id' => (int) $row->user_id,
                            'student_number' => (string) ($row->Student_Number ?? ''),
                            'student_name' => $this->formatStudentNameFromRow($row),
                            'first_name' => (string) ($row->first_name ?? ''),
                            'middle_initial' => (string) ($row->middle_initial ?? ''),
                            'last_name' => (string) ($row->last_name ?? ''),
                            'extension_name' => (string) ($row->extension_name ?? ''),
                            'program_choices' => $choices,
                            'program_choice_1' => (string) ($choices[0] ?? ''),
                            'program_choice_2' => (string) ($choices[1] ?? ''),
                            'program_choice_3' => (string) ($choices[2] ?? ''),
                        ];
                    })->values()->all(),
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'data' => $schedules,
        ]);
    }

    public function entranceSchedules(Request $request)
    {
        $user = $request->user();
        if (!$user || !str_contains((string) $user->role, 'entrance')) {
            return response()->json([
                'message' => 'Only entrance examiners can access schedules.',
            ], 403);
        }

        $validated = $request->validate([
            'exam_id' => 'nullable|integer|exists:exams,id',
        ]);

        $examId = (int) ($validated['exam_id'] ?? 0);

        $schedules = DB::table('exam_schedules as sch')
            ->leftJoin('student_exam_schedules as ses', function ($join) use ($examId) {
                $join->on('ses.exam_schedule_id', '=', 'sch.id');
                if ($examId > 0) {
                    $join->where('ses.exam_id', '=', $examId);
                }
            })
            ->where('sch.schedule_type', 'entrance')
            ->select([
                'sch.id',
                'sch.date',
                'sch.time',
                'sch.location',
                'sch.schedule_name',
                'sch.capacity',
                'sch.schedule_type',
            ])
            ->selectRaw('COUNT(DISTINCT ses.user_id) as assigned_students')
            ->orderBy('sch.date', 'asc')
            ->orderBy('sch.time', 'asc')
            ->groupBy('sch.id', 'sch.date', 'sch.time', 'sch.location', 'sch.schedule_name', 'sch.capacity', 'sch.schedule_type')
            ->get()
            ->map(function ($schedule) {
                $assignedCount = (int) ($schedule->assigned_students ?? 0);
                $capacity = (int) ($schedule->capacity ?? 0);

                return [
                    'id' => (int) $schedule->id,
                    'date' => (string) ($schedule->date ?? ''),
                    'formatted_date' => $schedule->date
                        ? \Carbon\Carbon::parse($schedule->date)->format('F j, Y')
                        : '',
                    'time' => (string) ($schedule->time ?? ''),
                    'location' => (string) ($schedule->location ?? ''),
                    'schedule_name' => (string) ($schedule->schedule_name ?? ''),
                    'capacity' => $capacity,
                    'assigned_students' => $assignedCount,
                    'current_examinees' => $assignedCount,
                    'label' => trim(implode(' | ', array_filter([
                        (string) ($schedule->schedule_name ?? ''),
                        $schedule->date ? \Carbon\Carbon::parse($schedule->date)->format('F j, Y') : '',
                        (string) ($schedule->time ?? ''),
                        (string) ($schedule->location ?? ''),
                        'Current: ' . $assignedCount . '/' . $capacity,
                    ]))),
                ];
            })
            ->values();

        return response()->json([
            'data' => $schedules,
        ]);
    }

    public function generateTermPdf(Request $request)
    {
        @set_time_limit(300);
        @ini_set('memory_limit', '1024M');

        $user = Auth::user();
        if (!$user || !str_contains((string) $user->role, 'instructor')) {
            return response()->json([
                'message' => 'Only instructors can generate term exam sheets.',
            ], 403);
        }

        $data = $request->validate([
            'exam_id' => 'required|exists:exams,id',
            'subject_id' => 'required|exists:subjects,id',
        ]);

        $examId = (int) $data['exam_id'];
        $subjectId = (int) $data['subject_id'];

        $employeeId = DB::table('employees')->where('user_id', $user->id)->value('id');
        if (!$employeeId) {
            return response()->json([
                'message' => 'No employee profile is linked to your account.',
            ], 422);
        }

        $exam = Exam::with('examSubjects.subject')->where('id', $examId)->first();
        if (!$exam || (int) $exam->created_by !== (int) $employeeId) {
            return response()->json([
                'message' => 'You can only generate sheets for exams you created.',
            ], 403);
        }

        $examSubject = ExamSubject::where('exam_id', $examId)
            ->where('subject_id', $subjectId)
            ->first();
        if (!$examSubject) {
            return response()->json([
                'message' => 'Selected subject is not attached to this exam.',
            ], 422);
        }

        if (!$this->hasReadyAnswerKeyForUser($examId, $user->id, (int) $examSubject->id)) {
            return response()->json([
                'message' => 'Please set a complete answer key for this exam before generating answer sheets.',
            ], 422);
        }

        $isAssigned = DB::table('subject_instructor_assignments')
            ->where('subject_id', $subjectId)
            ->where('instructor_user_id', $user->id)
            ->exists();
        if (!$isAssigned) {
            return response()->json([
                'message' => 'Subject is not assigned to this instructor.',
            ], 403);
        }

        $students = DB::table('subject_student_assignments as ssa')
            ->join('users as u', 'u.id', '=', 'ssa.student_user_id')
            ->leftJoin('students as st', 'st.user_id', '=', 'u.id')
            ->where('ssa.subject_id', $subjectId)
            ->where('u.role', 'student')
            ->orderBy('u.last_name')
            ->orderBy('u.first_name')
            ->select([
                'u.id as user_id',
                'u.first_name',
                'u.middle_initial',
                'u.last_name',
                'u.extension_name',
                'st.Student_Number',
            ])
            ->get();

        if ($students->isEmpty()) {
            return response()->json([
                'message' => 'No students are assigned to this subject.',
            ], 422);
        }

        $sheets = [];
        foreach ($students as $row) {
            $studentNumber = (string) ($row->Student_Number ?? '');
            if ($studentNumber === '') {
                $student = Student::query()->firstOrCreate(
                    ['user_id' => (int) $row->user_id],
                    ['Student_Number' => Student::generateStudentNumber()]
                );
                if (!$student->Student_Number) {
                    $student->Student_Number = Student::generateStudentNumber();
                    $student->save();
                }
                $studentNumber = (string) $student->Student_Number;
            }

            $sheet = AnswerSheet::firstOrCreate(
                [
                    'exam_id' => $examId,
                    'user_id' => (int) $row->user_id,
                    'created_by' => (int) $user->id,
                ],
                [
                    'status' => 'generated',
                    'qr_payload' => '',
                ]
            );

            $payload = $this->buildTermExamQrPayload(
                $studentNumber,
                $examId,
                $subjectId,
                (string) ($row->last_name ?? ''),
                (string) ($row->first_name ?? ''),
                (string) ($row->middle_initial ?? ''),
                (string) ($row->extension_name ?? '')
            );

            $sheet->update([
                'qr_payload' => $payload,
                'status' => $sheet->status ?: 'generated',
            ]);

            $sheets[] = $this->formatTermSheetForPdf($sheet, $exam, $examSubject, $row, $studentNumber, $payload);
        }

        $pdf = Pdf::setOption([
            'dpi' => 96,
            'defaultFont' => 'Arial',
            'isRemoteEnabled' => false,
        ])->loadView('pdf.term_exam_sheet', [
            'sheets' => $sheets,
            'exam' => $exam,
        ]);

        $fileName = 'term_exam_sheets_' . now()->format('Ymd_His') . '.pdf';

        return $pdf->download($fileName);
    }

    public function printSingle($id)
    {
        @set_time_limit(180);
        @ini_set('memory_limit', '768M');

        $sheet = AnswerSheet::where(function ($query) {
            $query->where('created_by', Auth::id())
                ->orWhere(function ($fallback) {
                    $fallback->whereNull('created_by')
                        ->where('user_id', Auth::id());
                });
        })->findOrFail($id);
        $sheets = [$this->formatSheetForPdf($sheet, $this->sheetPdfContext($sheet))];

        $pdf = Pdf::loadView('pdf.bubble_sheet', [
            'sheets' => $sheets,
            'exam' => $sheet->exam,
        ]);

        $fileName = 'answer_sheet_' . $sheet->id . '.pdf';

        return $pdf->download($fileName);
    }

    public function printSelected(Request $request)
    {
        @set_time_limit(300);
        @ini_set('memory_limit', '1024M');

        $data = $request->validate([
            'sheet_ids' => 'required|array|min:1',
            'sheet_ids.*' => 'integer|distinct',
        ]);

        $ids = collect($data['sheet_ids'])->map(fn ($id) => (int) $id)->values();
        $sheets = AnswerSheet::with('exam')
            ->where(function ($query) {
                $query->where('created_by', Auth::id())
                    ->orWhere(function ($fallback) {
                        $fallback->whereNull('created_by')
                            ->where('user_id', Auth::id());
                    });
            })
            ->whereIn('id', $ids)
            ->get();

        if ($sheets->isEmpty()) {
            return response()->json(['message' => 'No valid sheets selected for printing.'], 422);
        }

        if ($sheets->count() !== $ids->count()) {
            return response()->json(['message' => 'Some selected sheets are invalid or not owned by your account.'], 422);
        }

        $pdfSheets = $sheets->map(fn ($sheet) => $this->formatSheetForPdf($sheet, $this->sheetPdfContext($sheet)))->all();
        $exam = Exam::find($sheets->first()->exam_id);

        $pdf = Pdf::setOption([
            'dpi' => 96,
            'defaultFont' => 'Arial',
            'isRemoteEnabled' => false,
        ])->loadView('pdf.bubble_sheet', [
            'sheets' => $pdfSheets,
            'exam' => $exam,
        ]);

        $fileName = 'answer_sheets_selected_' . now()->format('Ymd_His') . '.pdf';

        return $pdf->download($fileName);
    }

    public function scanAndLink(Request $request)
    {
        $data = $request->validate([
            'qr_payload' => 'required|string',
        ]);

        $sheet = AnswerSheet::where('qr_payload', $data['qr_payload'])->first();

        if (!$sheet) {
            return response()->json([
                'message' => 'Answer sheet not found.',
            ], 404);
        }

        $userId = Auth::id();
        $studentRole = Auth::user()?->role;

        if ($studentRole !== 'student') {
            return response()->json([
                'message' => 'Only students can scan and link answer sheets.',
            ], 403);
        }

        $currentOwner = $sheet->user_id ? User::find($sheet->user_id) : null;
        $currentOwnerRole = $currentOwner?->role;

        if ($sheet->user_id && (int) $sheet->user_id !== (int) $userId && $currentOwnerRole === 'student') {
            return response()->json([
                'message' => 'Answer sheet is already linked to another student.',
            ], 409);
        }

        if ((int) $sheet->user_id === (int) $userId) {
            if (!$sheet->scanned_at) {
                $sheet->update(['scanned_at' => now()]);
            }

            return response()->json([
                'message' => 'Answer sheet is already linked to your account.',
                'sheet' => $sheet->load('exam'),
            ]);
        }

        $sheet->loadMissing('exam.creator', 'exam.program');
        $screeningAuditContext = null;
        if ($this->isScreeningExamType($sheet->exam?->Exam_Type)) {
            $screeningGuard = $this->validateScreeningScanOrder($userId, $sheet->exam);
            if (!($screeningGuard['allowed'] ?? false)) {
                return response()->json([
                    'message' => $screeningGuard['message'] ?? 'You cannot take this screening exam yet.',
                ], 422);
            }

            $screeningAuditContext = [
                'program_id' => isset($screeningGuard['program_id']) ? (int) $screeningGuard['program_id'] : null,
                'program_name' => (string) ($screeningGuard['program_name'] ?? ''),
                'program_rank' => isset($screeningGuard['program_rank']) ? (int) $screeningGuard['program_rank'] : null,
                'program_college_id' => isset($screeningGuard['program_college_id']) ? (int) $screeningGuard['program_college_id'] : null,
            ];
        }

        $updates = [
            'user_id' => $userId,
            'status' => $sheet->status ?: 'generated',
            'scanned_at' => now(),
        ];

        if (empty($sheet->created_by) && $sheet->user_id && $currentOwnerRole !== 'student') {
            $updates['created_by'] = (int) $sheet->user_id;
        }

        if (($sheet->status ?? '') === 'generated') {
            $updates['status'] = 'scanned';
        }

        $sheet->update($updates);

        if ($screeningAuditContext !== null) {
            $actor = Auth::user();
            $studentName = trim(
                (string) ($actor?->first_name ?? '') . ' ' . (string) ($actor?->last_name ?? '')
            );
            if ($studentName === '') {
                $studentName = 'Student #' . (int) $userId;
            }

            $programId = $screeningAuditContext['program_id'] ?? ($sheet->exam?->program_id ? (int) $sheet->exam?->program_id : null);
            $programName = trim((string) ($screeningAuditContext['program_name'] ?? ''));
            if ($programName === '') {
                $programName = (string) ($sheet->exam?->program?->Program_Name ?? '');
            }

            ActivityLogger::log(
                $userId ? (int) $userId : null,
                (string) ($actor?->role ?? ''),
                'screening_exam_taken',
                'program',
                $programId,
                'Student took screening exam',
                $studentName . ' scanned answer sheet for screening exam "' . (string) ($sheet->exam?->Exam_Title ?? '') . '".',
                [
                    'student_user_id' => (int) $userId,
                    'student_name' => $studentName,
                    'exam_id' => (int) ($sheet->exam?->id ?? 0),
                    'exam_title' => (string) ($sheet->exam?->Exam_Title ?? ''),
                    'exam_type' => (string) ($sheet->exam?->Exam_Type ?? ''),
                    'program_id' => $programId,
                    'program_name' => $programName,
                    'program_rank' => $screeningAuditContext['program_rank'] ?? null,
                    'program_college_id' => $screeningAuditContext['program_college_id'] ?? null,
                    'college_id' => $screeningAuditContext['program_college_id'] ?? null,
                ]
            );
        }

        return response()->json([
            'message' => 'Answer sheet linked successfully.',
            'sheet' => $sheet->load('exam'),
        ]);
    }

    private function createSheet(int $examId): AnswerSheet
    {
        $sheet = AnswerSheet::create([
            'qr_payload' => (string) Str::uuid(),
            'exam_id' => $examId,
            'user_id' => Auth::id(),
            'created_by' => Auth::id(),
            'status' => 'generated',
        ]);

        $sheetCode = $this->buildSheetCode((int) ($sheet->user_id ?? 0), $sheet->id, $examId);
        $sheet->update(['qr_payload' => $sheetCode]);

        return $sheet;
    }

    private function hasReadyAnswerKeyForUser(int $examId, ?int $userId, ?int $examSubjectId = null): bool
    {
        if ($examId <= 0 || !$userId) {
            return false;
        }

        $query = AnswerKey::query()
            ->where('exam_id', $examId)
            ->where('user_id', $userId);

        if ($examSubjectId) {
            $query->where(function ($scoped) use ($examSubjectId) {
                $scoped->where('exam_subject_id', $examSubjectId)
                    ->orWhereNull('exam_subject_id');
            });
        }

        $answerKey = $query->latest('id')->first();
        if (!$answerKey) {
            return false;
        }

        $answers = collect((array) ($answerKey->answers ?? []))
            ->filter(fn ($value) => trim((string) $value) !== '');

        return $answers->count() >= 100;
    }

    private function buildSheetCode(int $studentId, int $sheetId, int $examId): string
    {
        return implode('-', [
            max(0, $studentId),
            max(0, $sheetId),
            max(0, $examId),
        ]);
    }

    private function formatSheetForPdf(AnswerSheet $sheet, array $context = []): array
    {
        $sheet->loadMissing('exam.program');

        $examType = (string) ($context['exam_type'] ?? $sheet->exam?->Exam_Type ?? '');
        $isScreening = $this->isScreeningExamType($examType);
        $programName = trim((string) (
            $context['program_name']
            ?? $sheet->exam?->program?->Program_Name
            ?? ''
        ));
        $sheetTitle = $isScreening
            ? ($programName !== ''
                ? (strtoupper($programName) . ' SCREENING EXAM ANSWER SHEET')
                : 'SCREENING EXAM ANSWER SHEET')
            : 'COLLEGE ADMISSION TEST ANSWER SHEET';

        $sheetCode = $sheet->qr_payload ?: $this->buildSheetCode(
            (int) ($sheet->user_id ?? 0),
            (int) $sheet->id,
            (int) $sheet->exam_id
        );

        $qrSvg = QrCode::format('svg')
            ->size(100)
            ->margin(0)
            ->generate($sheetCode);

        return [
            'id' => $sheet->id,
            'sheetCode' => $sheetCode,
            'sheetQrMime' => 'image/svg+xml',
            'sheetQr' => base64_encode($qrSvg),
            'student_name' => (string) ($context['student_name'] ?? ''),
            'last_name' => (string) ($context['last_name'] ?? ''),
            'first_name' => (string) ($context['first_name'] ?? ''),
            'middle_initial' => (string) ($context['middle_initial'] ?? ''),
            'extension_name' => (string) ($context['extension_name'] ?? ''),
            'student_number' => (string) ($context['student_number'] ?? ''),
            'scheduled_date' => (string) ($context['scheduled_date'] ?? ''),
            'program_choices' => array_values(array_pad(array_slice((array) ($context['program_choices'] ?? []), 0, 3), 3, '')),
            'show_program_choices' => !$isScreening,
            'sheet_title' => $sheetTitle,
        ];
    }

    private function sheetPdfContext(AnswerSheet $sheet): array
    {
        if (!(int) $sheet->user_id) {
            return [];
        }

        $student = DB::table('users as u')
            ->leftJoin('students as st', 'st.user_id', '=', 'u.id')
            ->leftJoin('student_exam_schedules as ses', function ($join) use ($sheet) {
                $join->on('ses.user_id', '=', 'u.id')
                    ->where('ses.exam_id', '=', $sheet->exam_id);
            })
            ->leftJoin('exam_schedules as sch', 'sch.id', '=', 'ses.exam_schedule_id')
            ->where('u.id', $sheet->user_id)
            ->select([
                'u.first_name',
                'u.middle_initial',
                'u.last_name',
                'u.extension_name',
                'st.Student_Number',
                'sch.date as scheduled_date',
            ])
            ->orderByDesc('ses.id')
            ->first();

        if (!$student) {
            return [];
        }

        return [
            'student_name' => $this->formatStudentNameFromRow($student),
            'last_name' => (string) ($student->last_name ?? ''),
            'first_name' => (string) ($student->first_name ?? ''),
            'middle_initial' => (string) ($student->middle_initial ?? ''),
            'extension_name' => (string) ($student->extension_name ?? ''),
            'student_number' => (string) ($student->Student_Number ?? ''),
            'scheduled_date' => $student->scheduled_date
                ? \Carbon\Carbon::parse($student->scheduled_date)->format('F j, Y')
                : '',
            'program_choices' => $this->programChoicesMapForUsers([(int) $sheet->user_id])[(int) $sheet->user_id] ?? [],
            'exam_type' => (string) ($sheet->exam?->Exam_Type ?? ''),
            'program_name' => (string) ($sheet->exam?->program?->Program_Name ?? ''),
        ];
    }

    private function scheduledStudentsForScheduleQuery(int $scheduleId, int $examId)
    {
        return DB::table('student_exam_schedules as ses')
            ->join('exam_schedules as sch', 'sch.id', '=', 'ses.exam_schedule_id')
            ->join('users as u', 'u.id', '=', 'ses.user_id')
            ->leftJoin('students as st', 'st.user_id', '=', 'u.id')
            ->where('ses.exam_schedule_id', $scheduleId)
            ->where('ses.exam_id', $examId)
            ->where('u.role', 'student')
            ->orderBy('u.last_name', 'asc')
            ->orderBy('u.first_name', 'asc')
            ->select([
                'ses.user_id',
                'ses.exam_schedule_id',
                'sch.date as scheduled_date',
                'sch.time as scheduled_time',
                'sch.location as scheduled_location',
                'u.first_name',
                'u.middle_initial',
                'u.last_name',
                'u.extension_name',
                'st.Student_Number',
            ]);
    }

    private function programChoicesMapForUsers(array $userIds): array
    {
        $ids = collect($userIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        return Recommendation::query()
            ->join('programs', 'programs.id', '=', 'recommendations.program_id')
            ->whereIn('recommendations.user_id', $ids->all())
            ->where('recommendations.type', self::TYPE_STUDENT_CHOICE)
            ->orderBy('recommendations.user_id')
            ->orderBy('recommendations.rank')
            ->get([
                'recommendations.user_id',
                'recommendations.rank',
                'programs.Program_Name as program_name',
            ])
            ->groupBy('user_id')
            ->map(function ($rows) {
                $choices = [];
                foreach ($rows as $row) {
                    $rankIndex = max(0, ((int) ($row->rank ?? 1)) - 1);
                    $choices[$rankIndex] = trim((string) ($row->program_name ?? ''));
                }

                return array_values(array_pad(array_slice($choices, 0, 3), 3, ''));
            })
            ->all();
    }

    private function formatStudentNameFromRow($row): string
    {
        $name = trim(
            trim((string) ($row->last_name ?? '')) . ', ' .
            trim((string) ($row->first_name ?? ''))
        );

        $middle = trim((string) ($row->middle_initial ?? ''));
        if ($middle !== '') {
            $name .= ' ' . $middle;
        }

        $extension = trim((string) ($row->extension_name ?? ''));
        if ($extension !== '') {
            $name .= ' ' . $extension;
        }

        return trim($name, ', ');
    }

    private function formatTermSheetForPdf(
        AnswerSheet $sheet,
        Exam $exam,
        ExamSubject $examSubject,
        $studentRow,
        string $studentNumber,
        string $payload
    ): array {
        $qrSvg = QrCode::format('svg')
            ->size(100)
            ->margin(0)
            ->generate($payload);

        $fullName = trim(
            trim((string) ($studentRow->last_name ?? '')) . ', ' .
            trim((string) ($studentRow->first_name ?? ''))
        );
        $middle = trim((string) ($studentRow->middle_initial ?? ''));
        if ($middle !== '') {
            $fullName .= ' ' . $middle;
        }
        $ext = trim((string) ($studentRow->extension_name ?? ''));
        if ($ext !== '') {
            $fullName .= ' ' . $ext;
        }

        return [
            'id' => $sheet->id,
            'sheetCode' => $payload,
            'sheetQrMime' => 'image/svg+xml',
            'sheetQr' => base64_encode($qrSvg),
            'student_number' => $studentNumber,
            'student_name' => trim($fullName),
            'exam_title' => (string) ($exam->Exam_Title ?? ''),
            'subject_name' => (string) ($examSubject->subject?->Subject_Name ?? ''),
        ];
    }

    private function buildTermExamQrPayload(
        string $studentNumber,
        int $examId,
        int $subjectId,
        string $lastName,
        string $firstName,
        string $middleInitial,
        string $extension
    ): string {
        $safe = function (string $value): string {
            $clean = str_replace('|', ' ', $value);
            return trim($clean);
        };

        return implode('|', [
            $safe($studentNumber),
            (string) $examId,
            (string) $subjectId,
            $safe($lastName),
            $safe($firstName),
            $safe($middleInitial),
            $safe($extension),
        ]);
    }

    private function isScreeningExamType(?string $examType): bool
    {
        $value = strtolower(trim((string) $examType));
        return in_array($value, self::SCREENING_TYPE_ALIASES, true);
    }

    private function validateScreeningScanOrder(int $userId, ?Exam $exam): array
    {
        $programOrgUnitColumn = $this->programOrgUnitColumn();
        $selectedPrograms = Recommendation::query()
            ->join('programs', 'programs.id', '=', 'recommendations.program_id')
            ->where('recommendations.user_id', $userId)
            ->where('recommendations.type', self::TYPE_STUDENT_CHOICE)
            ->orderBy('recommendations.rank')
            ->select([
                'recommendations.program_id',
                'recommendations.rank',
                'programs.Program_Name as program_name',
            ])
            ->selectRaw(
                $programOrgUnitColumn !== null
                    ? "programs.{$programOrgUnitColumn} as college_id"
                    : "0 as college_id"
            )
            ->get()
            ->map(function ($row) {
                return [
                    'program_id' => (int) $row->program_id,
                    'rank' => (int) $row->rank,
                    'program_name' => trim((string) $row->program_name),
                    'college_id' => (int) ($row->college_id ?? 0),
                ];
            })
            ->values()
            ->all();

        if (count($selectedPrograms) < 1 || count($selectedPrograms) > 3) {
            return [
                'allowed' => false,
                'message' => 'Select your screening program(s) first before taking screening exams.',
            ];
        }

        $statuses = $this->screeningStatusesByProgram($userId, $selectedPrograms);
        $finalProgramId = (int) (Recommendation::query()
            ->where('user_id', $userId)
            ->where('type', self::TYPE_FINAL_PROGRAM)
            ->value('program_id') ?? 0);
        $continuedProgramIds = Recommendation::query()
            ->where('user_id', $userId)
            ->where('type', self::TYPE_CONTINUE_SCREENING)
            ->pluck('program_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $targetIndex = $this->findProgramIndexForExam(
            (string) ($exam?->Exam_Title ?? ''),
            (int) ($exam?->creator?->college_id ?? 0),
            $selectedPrograms,
            $statuses
        );
        if ($targetIndex === null) {
            return [
                'allowed' => false,
                'message' => 'This screening exam does not match any of your selected programs.',
            ];
        }
        $target = $selectedPrograms[$targetIndex];
        $targetStatus = $statuses[$targetIndex]['status'] ?? 'no_attempt';
        $targetProgramId = (int) ($target['program_id'] ?? 0);

        if ($finalProgramId > 0 && $targetProgramId !== $finalProgramId) {
            return [
                'allowed' => false,
                'message' => 'You already selected your final program. You cannot take lower-ranked screening exams.',
            ];
        }

        if ($targetStatus === 'passed') {
            return [
                'allowed' => false,
                'message' => "You already passed {$target['program_name']} screening exam.",
            ];
        }

        if ($targetStatus === 'in_progress') {
            return [
                'allowed' => false,
                'message' => "Your {$target['program_name']} screening exam is still pending checking.",
            ];
        }

        for ($i = 0; $i < $targetIndex; $i++) {
            $priorProgram = $selectedPrograms[$i];
            $priorStatus = $statuses[$i]['status'] ?? 'no_attempt';

            if ($priorStatus === 'no_attempt') {
                return [
                    'allowed' => false,
                    'message' => "Take {$priorProgram['program_name']} screening exam first before {$target['program_name']}.",
                ];
            }

            if ($priorStatus === 'in_progress') {
                return [
                    'allowed' => false,
                    'message' => "Wait for the {$priorProgram['program_name']} screening result before taking {$target['program_name']}.",
                ];
            }

            if ($priorStatus === 'passed') {
                $priorProgramId = (int) ($priorProgram['program_id'] ?? 0);
                if (in_array($priorProgramId, $continuedProgramIds, true)) {
                    continue;
                }

                return [
                    'allowed' => false,
                    'message' => "You passed {$priorProgram['program_name']}. Choose whether to continue to the next screening exam or pick it as your final program in Recommendations.",
                ];
            }
        }

        return [
            'allowed' => true,
            'message' => '',
            'program_id' => (int) ($target['program_id'] ?? 0),
            'program_name' => (string) ($target['program_name'] ?? ''),
            'program_rank' => (int) ($target['rank'] ?? ($targetIndex + 1)),
            'program_college_id' => (int) ($target['college_id'] ?? 0),
        ];
    }

    private function screeningStatusesByProgram(int $userId, array $programs): array
    {
        $employeeOrgUnitColumn = $this->employeeOrgUnitColumn();
        $attempts = DB::table('answer_sheets as ans')
            ->join('exams as e', 'e.id', '=', 'ans.exam_id')
            ->leftJoin('employees as emp', 'emp.id', '=', 'e.created_by')
            ->where('ans.user_id', $userId)
            ->whereIn(DB::raw('LOWER(e.Exam_Type)'), self::SCREENING_TYPE_ALIASES)
            ->orderByDesc('ans.updated_at')
            ->select('ans.status', 'ans.total_score', 'e.Exam_Title')
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

        $deptProgramCounts = collect($programs)
            ->groupBy(fn (array $program) => (int) ($program['college_id'] ?? 0))
            ->map(fn ($rows) => count($rows))
            ->all();

        return collect($programs)->map(function (array $program) use ($attempts, $deptProgramCounts) {
            $programName = trim((string) ($program['program_name'] ?? ''));
            $programDepartmentId = (int) ($program['college_id'] ?? 0);
            $allowDepartmentFallback = $programDepartmentId > 0
                && (($deptProgramCounts[$programDepartmentId] ?? 0) === 1);

            $latest = collect($attempts)->first(function (array $attempt) use ($programName, $programDepartmentId, $allowDepartmentFallback) {
                return $this->examMatchesProgram(
                    (string) ($attempt['exam_title'] ?? ''),
                    $programName,
                    (int) ($attempt['college_id'] ?? 0),
                    $programDepartmentId,
                    $allowDepartmentFallback
                );
            });

            if (!$latest) {
                return ['status' => 'no_attempt'];
            }

            $sheetStatus = strtolower(trim((string) ($latest['status'] ?? '')));
            if ($sheetStatus !== 'checked') {
                return ['status' => 'in_progress'];
            }

            $score = (int) ($latest['total_score'] ?? 0);
            return ['status' => $score >= self::PASSING_SCORE ? 'passed' : 'failed'];
        })->values()->all();
    }

    private function findProgramIndexForExam(string $examTitle, int $examDepartmentId, array $programs, array $statuses): ?int
    {
        $normalizedTitle = strtolower(trim($examTitle));

        $titleMatches = [];
        if ($normalizedTitle !== '') {
            foreach ($programs as $index => $program) {
                $programName = (string) ($program['program_name'] ?? '');
                if ($this->titleMatchesProgramName($normalizedTitle, $programName)) {
                    $titleMatches[] = $index;
                }
            }
        }

        if (!empty($titleMatches)) {
            return $titleMatches[0];
        }

        if ($examDepartmentId > 0) {
            $deptMatches = [];
            foreach ($programs as $index => $program) {
                if ((int) ($program['college_id'] ?? 0) === $examDepartmentId) {
                    $deptMatches[] = $index;
                }
            }

            if (!empty($deptMatches)) {
                if (count($deptMatches) === 1) {
                    return $deptMatches[0];
                }

                // Multiple selected programs in the same department:
                // prefer the first one that has not been attempted yet.
                foreach ($deptMatches as $index) {
                    if (($statuses[$index]['status'] ?? 'no_attempt') === 'no_attempt') {
                        return $index;
                    }
                }

                // Fallback to first failed one, then first in list.
                foreach ($deptMatches as $index) {
                    if (($statuses[$index]['status'] ?? '') === 'failed') {
                        return $index;
                    }
                }

                return $deptMatches[0];
            }
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
}
