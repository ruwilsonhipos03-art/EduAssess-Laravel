<?php

namespace App\Http\Controllers\Api\CollegeDean;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\SubjectInstructorAssignment;
use App\Models\SubjectStudentAssignment;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class CollegeDeanManagementController extends Controller
{
    private const PASSING_SCORE = 75;
    private const ENTRANCE_TYPE_ALIASES = ['entrance', 'entrance exam'];
    private const SCREENING_TYPE_ALIASES = ['screening', 'screening exam'];
    private const TYPE_STUDENT_CHOICE = 'student_choice';
    private const SCHEDULE_TYPE_SCREENING = 'screening';

    private function employeeOrgUnitColumn(): string
    {
        if (Schema::hasColumn('employees', 'department_id')) {
            return 'department_id';
        }

        return 'college_id';
    }

    private function programOrgUnitColumn(): string
    {
        if (Schema::hasColumn('programs', 'department_id')) {
            return 'department_id';
        }

        return 'college_id';
    }

    private function orgUnitTable(): string
    {
        return $this->programOrgUnitColumn() === 'department_id' ? 'departments' : 'colleges';
    }

    private function orgUnitNameColumn(): string
    {
        $table = $this->orgUnitTable();
        if (Schema::hasColumn($table, 'Department_Name')) {
            return 'Department_Name';
        }

        return 'College_Name';
    }

    public function students(Request $request)
    {
        $context = $this->collegeDeanContext($request);
        if ($context['error']) {
            return $context['error'];
        }

        $departmentId = $context['college_id'];
        $programOrgUnitColumn = $this->programOrgUnitColumn();
        $orgUnitTable = $this->orgUnitTable();
        $orgUnitNameColumn = $this->orgUnitNameColumn();

        $rows = DB::table('users as u')
            ->join('students as s', 's.user_id', '=', 'u.id')
            ->leftJoin('programs as p', 'p.id', '=', 's.program_id')
            ->leftJoin($orgUnitTable . ' as d', 'd.id', '=', 'p.' . $programOrgUnitColumn)
            ->leftJoin('subject_student_assignments as ssa', 'ssa.student_user_id', '=', 'u.id')
            ->leftJoin('subjects as subj', 'subj.id', '=', 'ssa.subject_id')
            ->where('u.role', 'student')
            ->where('p.' . $programOrgUnitColumn, $departmentId)
            ->orderBy('u.last_name')
            ->orderBy('u.first_name')
            ->groupBy(
                'u.id',
                'u.first_name',
                'u.middle_initial',
                'u.last_name',
                'u.extension_name',
                'u.username',
                'u.email',
                's.Student_Number',
                'p.id',
                'p.Program_Name',
                DB::raw('d.' . $orgUnitNameColumn)
            )
            ->selectRaw('
                u.id,
                u.first_name,
                u.middle_initial,
                u.last_name,
                u.extension_name,
                u.username,
                u.email,
                s.Student_Number,
                p.id as program_id,
                p.Program_Name as program_name,
                d.' . $orgUnitNameColumn . ' as College_Name,
                GROUP_CONCAT(DISTINCT subj.Subject_Name SEPARATOR \', \') as subject_names
            ')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rows->map(fn ($row) => [
                'id' => (int) $row->id,
                'student_number' => (string) ($row->Student_Number ?? ''),
                'student_qr_svg' => !empty($row->Student_Number)
                    ? base64_encode(
                        QrCode::format('svg')->size(100)->margin(0)->generate((string) $row->Student_Number)
                    )
                    : null,
                'full_name' => $this->fullName($row),
                'username' => (string) ($row->username ?? ''),
                'email' => (string) ($row->email ?? ''),
                'program_id' => (int) ($row->program_id ?? 0),
                'program_name' => (string) ($row->program_name ?? ''),
                'College_Name' => (string) ($row->College_Name ?? ''),
                'subject_names' => (string) ($row->subject_names ?? ''),
            ])->values(),
        ]);
    }

    public function subjects(Request $request)
    {
        $context = $this->collegeDeanContext($request);
        if ($context['error']) {
            return $context['error'];
        }

        return response()->json([
            'success' => true,
            'data' => Subject::query()->orderBy('Subject_Name')->get(['id', 'Subject_Name']),
        ]);
    }

    public function instructors(Request $request)
    {
        $context = $this->collegeDeanContext($request);
        if ($context['error']) {
            return $context['error'];
        }

        $departmentId = $context['college_id'];
        $employeeOrgUnitColumn = $this->employeeOrgUnitColumn();

        $rows = User::query()
            ->whereHas('employee', function ($query) use ($departmentId, $employeeOrgUnitColumn) {
                $query->where($employeeOrgUnitColumn, $departmentId);
            })
            ->get(['id', 'first_name', 'middle_initial', 'last_name', 'extension_name', 'role'])
            ->filter(fn (User $user) => $this->hasRole($user->role, 'instructor'))
            ->values()
            ->map(fn (User $user) => [
                'id' => (int) $user->id,
                'full_name' => $this->fullName($user),
                'role' => (string) $user->role,
            ]);

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function studentAssignments(Request $request)
    {
        $context = $this->collegeDeanContext($request);
        if ($context['error']) {
            return $context['error'];
        }

        $departmentId = $context['college_id'];
        $programOrgUnitColumn = $this->programOrgUnitColumn();

        $rows = SubjectStudentAssignment::query()
            ->with(['subject:id,Subject_Name', 'student:id,first_name,middle_initial,last_name,extension_name'])
            ->whereHas('student', function ($query) use ($departmentId, $programOrgUnitColumn) {
                $query->whereHas('studentProfile', function ($q) use ($departmentId, $programOrgUnitColumn) {
                    $q->whereHas('program', fn ($programQuery) => $programQuery->where($programOrgUnitColumn, $departmentId));
                });
            })
            ->latest('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rows->map(fn (SubjectStudentAssignment $row) => [
                'id' => (int) $row->id,
                'subject_id' => (int) $row->subject_id,
                'subject_name' => (string) ($row->subject?->Subject_Name ?? ''),
                'student_user_id' => (int) $row->student_user_id,
                'student_name' => $this->fullName($row->student),
            ])->values(),
        ]);
    }

    public function storeStudentAssignment(Request $request)
    {
        $context = $this->collegeDeanContext($request);
        if ($context['error']) {
            return $context['error'];
        }

        $departmentId = $context['college_id'];
        $deanUser = $context['user'];
        $programOrgUnitColumn = $this->programOrgUnitColumn();

        $validated = $request->validate([
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'student_user_ids' => ['nullable', 'array', 'min:1'],
            'student_user_ids.*' => ['integer', 'distinct'],
            'student_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'student')),
            ],
        ]);

        $subjectId = (int) $validated['subject_id'];
        $legacySingleInput = !isset($validated['student_user_ids']) && !empty($validated['student_user_id']);
        $studentUserIds = collect($validated['student_user_ids'] ?? [])
            ->push((int) ($validated['student_user_id'] ?? 0))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($studentUserIds->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Please select at least one student.',
            ], 422);
        }

        $subjectName = (string) (DB::table('subjects')->where('id', $subjectId)->value('Subject_Name') ?? '');
        $latestInstructorUserId = SubjectInstructorAssignment::query()
            ->where('subject_id', $subjectId)
            ->latest('id')
            ->value('instructor_user_id');
        $latestInstructorUserId = $latestInstructorUserId ? (int) $latestInstructorUserId : null;
        $latestInstructorName = '';
        if ($latestInstructorUserId) {
            $latestInstructor = User::query()
                ->where('id', $latestInstructorUserId)
                ->select(['id', 'first_name', 'middle_initial', 'last_name', 'extension_name'])
                ->first();
            $latestInstructorName = $this->fullName($latestInstructor);
        }

        $studentsById = User::query()
            ->whereIn('id', $studentUserIds->all())
            ->get(['id', 'first_name', 'middle_initial', 'last_name', 'extension_name', 'role'])
            ->keyBy('id');

        $validStudentIdsInCollege = DB::table('students as s')
            ->join('programs as p', 'p.id', '=', 's.program_id')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->whereIn('s.user_id', $studentUserIds->all())
            ->where('u.role', 'student')
            ->where('p.' . $programOrgUnitColumn, $departmentId)
            ->pluck('s.user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $validStudentIdLookup = array_fill_keys($validStudentIdsInCollege, true);

        $results = [];
        $createdCount = 0;
        $existingCount = 0;
        $invalidCount = 0;

        foreach ($studentUserIds as $studentUserId) {
            $studentUserId = (int) $studentUserId;
            $student = $studentsById->get($studentUserId);
            $studentName = $this->fullName($student);
            if ($studentName === '') {
                $studentName = 'Student #' . $studentUserId;
            }

            if (!isset($validStudentIdLookup[$studentUserId])) {
                $invalidCount++;
                $results[] = [
                    'student_user_id' => $studentUserId,
                    'student_name' => $studentName,
                    'status' => 'invalid',
                    'message' => 'Student is not under your college programs or is not a student account.',
                ];
                continue;
            }

            $assignment = SubjectStudentAssignment::query()->firstOrCreate([
                'subject_id' => $subjectId,
                'student_user_id' => $studentUserId,
            ], [
                'instructor_user_id' => $latestInstructorUserId ? (int) $latestInstructorUserId : null,
                'assigned_by_user_id' => (int) $deanUser->id,
            ]);

            if (!$assignment->wasRecentlyCreated) {
                if ((int) ($assignment->instructor_user_id ?? 0) !== (int) ($latestInstructorUserId ?? 0)) {
                    $assignment->instructor_user_id = $latestInstructorUserId ? (int) $latestInstructorUserId : null;
                    $assignment->save();
                }

                $existingCount++;
                $results[] = [
                    'student_user_id' => $studentUserId,
                    'student_name' => $studentName,
                    'status' => 'already_assigned',
                    'message' => 'Assignment already exists.',
                    'assignment_id' => (int) $assignment->id,
                ];
                continue;
            }

            $createdCount++;
            $results[] = [
                'student_user_id' => $studentUserId,
                'student_name' => $studentName,
                'status' => 'created',
                'message' => 'Student assigned to subject.',
                'assignment_id' => (int) $assignment->id,
            ];

            ActivityLogger::log(
                (int) $deanUser->id,
                (string) ($deanUser->role ?? ''),
                'student_subject_assigned',
                'subject_student_assignment',
                (int) $assignment->id,
                'Student assigned to subject',
                'Assigned student "' . $studentName . '" to subject "' . $subjectName . '".',
                [
                    'subject_id' => $subjectId,
                    'subject_name' => $subjectName,
                    'student_user_id' => $studentUserId,
                    'student_name' => $studentName,
                    'instructor_user_id' => $latestInstructorUserId,
                    'instructor_name' => $latestInstructorName,
                ]
            );
        }

        if ($legacySingleInput && $studentUserIds->count() === 1) {
            $firstResult = $results[0] ?? null;
            if (!$firstResult || ($firstResult['status'] ?? '') === 'invalid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Student is not under your college programs.',
                ], 422);
            }

            $isCreated = ($firstResult['status'] ?? '') === 'created';
            return response()->json([
                'success' => true,
                'message' => $isCreated ? 'Student assigned to subject.' : 'Assignment already exists.',
            ], $isCreated ? 201 : 200);
        }

        $total = $studentUserIds->count();
        $message = "Processed {$total} student(s): {$createdCount} created, {$existingCount} already assigned, {$invalidCount} invalid.";

        return response()->json([
            'success' => true,
            'message' => $message,
            'summary' => [
                'total' => $total,
                'created' => $createdCount,
                'already_assigned' => $existingCount,
                'invalid' => $invalidCount,
            ],
            'results' => $results,
        ], 200);
    }

    public function destroyStudentAssignment(Request $request, int $id)
    {
        $context = $this->collegeDeanContext($request);
        if ($context['error']) {
            return $context['error'];
        }

        $departmentId = $context['college_id'];
        $programOrgUnitColumn = $this->programOrgUnitColumn();

        $assignment = SubjectStudentAssignment::query()
            ->where('id', $id)
            ->whereHas('student', function ($query) use ($departmentId, $programOrgUnitColumn) {
                $query->whereHas('studentProfile', function ($q) use ($departmentId, $programOrgUnitColumn) {
                    $q->whereHas('program', fn ($programQuery) => $programQuery->where($programOrgUnitColumn, $departmentId));
                });
            })
            ->first();

        if (!$assignment) {
            return response()->json([
                'success' => false,
                'message' => 'Assignment not found in your college.',
            ], 404);
        }

        $assignment->load(['subject:id,Subject_Name', 'student:id,first_name,middle_initial,last_name,extension_name']);
        $subjectName = (string) ($assignment->subject?->Subject_Name ?? '');
        $studentName = $this->fullName($assignment->student);

        $assignment->delete();

        $deanUser = $request->user();
        ActivityLogger::log(
            $deanUser ? (int) $deanUser->id : null,
            (string) ($deanUser?->role ?? ''),
            'student_subject_unassigned',
            'subject_student_assignment',
            (int) $id,
            'Student removed from subject',
            'Removed student "' . $studentName . '" from subject "' . $subjectName . '".',
            [
                'subject_id' => (int) ($assignment->subject_id ?? 0),
                'subject_name' => $subjectName,
                'student_user_id' => (int) ($assignment->student_user_id ?? 0),
                'student_name' => $studentName,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Student-subject assignment removed.',
        ]);
    }

    public function instructorAssignments(Request $request)
    {
        $context = $this->collegeDeanContext($request);
        if ($context['error']) {
            return $context['error'];
        }

        $departmentId = $context['college_id'];
        $employeeOrgUnitColumn = $this->employeeOrgUnitColumn();

        $rows = SubjectInstructorAssignment::query()
            ->with(['subject:id,Subject_Name', 'instructor:id,first_name,middle_initial,last_name,extension_name'])
            ->whereHas('instructor', function ($query) use ($departmentId, $employeeOrgUnitColumn) {
                $query->whereHas('employee', fn ($q) => $q->where($employeeOrgUnitColumn, $departmentId));
            })
            ->latest('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rows->map(fn (SubjectInstructorAssignment $row) => [
                'id' => (int) $row->id,
                'subject_id' => (int) $row->subject_id,
                'subject_name' => (string) ($row->subject?->Subject_Name ?? ''),
                'instructor_user_id' => (int) $row->instructor_user_id,
                'instructor_name' => $this->fullName($row->instructor),
            ])->values(),
        ]);
    }

    public function storeInstructorAssignment(Request $request)
    {
        $context = $this->collegeDeanContext($request);
        if ($context['error']) {
            return $context['error'];
        }

        $departmentId = $context['college_id'];
        $deanUser = $context['user'];
        $employeeOrgUnitColumn = $this->employeeOrgUnitColumn();

        $validated = $request->validate([
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'instructor_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $instructor = User::query()->find((int) $validated['instructor_user_id']);
        if (!$instructor || !$this->hasRole($instructor->role, 'instructor')) {
            return response()->json([
                'success' => false,
                'message' => 'Selected user is not an instructor.',
            ], 422);
        }

        $instructorIsInDepartment = DB::table('employees')
            ->where('user_id', $instructor->id)
            ->where($employeeOrgUnitColumn, $departmentId)
            ->exists();

        if (!$instructorIsInDepartment) {
            return response()->json([
                'success' => false,
                'message' => 'Instructor is not under your college.',
            ], 422);
        }

        $assignment = SubjectInstructorAssignment::query()->firstOrCreate([
            'subject_id' => (int) $validated['subject_id'],
            'instructor_user_id' => (int) $validated['instructor_user_id'],
        ], [
            'assigned_by_user_id' => (int) $deanUser->id,
        ]);

        // Keep student-subject rows in sync with the latest instructor mapped to this subject.
        $latestInstructorUserId = SubjectInstructorAssignment::query()
            ->where('subject_id', (int) $validated['subject_id'])
            ->latest('id')
            ->value('instructor_user_id');

        SubjectStudentAssignment::query()
            ->where('subject_id', (int) $validated['subject_id'])
            ->update([
                'instructor_user_id' => $latestInstructorUserId ? (int) $latestInstructorUserId : null,
                'updated_at' => now(),
            ]);

        if ($assignment->wasRecentlyCreated) {
            $subjectName = (string) (DB::table('subjects')->where('id', (int) $validated['subject_id'])->value('Subject_Name') ?? '');
            $instructorName = $this->fullName($instructor);

            ActivityLogger::log(
                (int) $deanUser->id,
                (string) ($deanUser->role ?? ''),
                'instructor_subject_assigned',
                'subject_instructor_assignment',
                (int) $assignment->id,
                'Instructor assigned to subject',
                'Assigned instructor "' . $instructorName . '" to subject "' . $subjectName . '".',
                [
                    'subject_id' => (int) $validated['subject_id'],
                    'subject_name' => $subjectName,
                    'instructor_user_id' => (int) $validated['instructor_user_id'],
                    'instructor_name' => $instructorName,
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => $assignment->wasRecentlyCreated ? 'Instructor assigned to subject.' : 'Assignment already exists.',
        ], $assignment->wasRecentlyCreated ? 201 : 200);
    }

    public function destroyInstructorAssignment(Request $request, int $id)
    {
        $context = $this->collegeDeanContext($request);
        if ($context['error']) {
            return $context['error'];
        }

        $departmentId = $context['college_id'];
        $employeeOrgUnitColumn = $this->employeeOrgUnitColumn();

        $assignment = SubjectInstructorAssignment::query()
            ->where('id', $id)
            ->whereHas('instructor', function ($query) use ($departmentId, $employeeOrgUnitColumn) {
                $query->whereHas('employee', fn ($q) => $q->where($employeeOrgUnitColumn, $departmentId));
            })
            ->first();

        if (!$assignment) {
            return response()->json([
                'success' => false,
                'message' => 'Assignment not found in your college.',
            ], 404);
        }

        $subjectId = (int) $assignment->subject_id;
        $assignment->delete();

        // Re-resolve current instructor for this subject after deletion.
        $latestInstructorUserId = SubjectInstructorAssignment::query()
            ->where('subject_id', $subjectId)
            ->latest('id')
            ->value('instructor_user_id');

        SubjectStudentAssignment::query()
            ->where('subject_id', $subjectId)
            ->update([
                'instructor_user_id' => $latestInstructorUserId ? (int) $latestInstructorUserId : null,
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Instructor-subject assignment removed.',
        ]);
    }

    public function screeningSchedules(Request $request)
    {
        $context = $this->collegeDeanContext($request);
        if ($context['error']) {
            return $context['error'];
        }

        $validated = $request->validate([
            'exam_id' => ['nullable', 'integer', 'exists:exams,id'],
        ]);

        $examId = (int) ($validated['exam_id'] ?? 0);
        $exam = $examId > 0
            ? $this->screeningExamForDean($examId, (int) $context['college_id'])
            : null;

        if ($examId > 0 && !$exam) {
            return response()->json([
                'success' => false,
                'message' => 'Screening exam not found in your college.',
            ], 404);
        }

        $schedules = DB::table('exam_schedules as sch')
            ->leftJoin('student_exam_schedules as ses', function ($join) use ($examId) {
                $join->on('ses.exam_schedule_id', '=', 'sch.id');
                if ($examId > 0) {
                    $join->where('ses.exam_id', '=', $examId);
                }
            })
            ->where('sch.schedule_type', self::SCHEDULE_TYPE_SCREENING)
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
            ->groupBy('sch.id', 'sch.date', 'sch.time', 'sch.location', 'sch.schedule_name', 'sch.capacity', 'sch.schedule_type')
            ->orderBy('sch.date')
            ->orderBy('sch.time')
            ->get();

        $assignedRows = collect();
        if ($exam) {
            $assignedRows = $this->screeningEligibleStudentsQuery($exam)
                ->whereNotNull('ses.exam_schedule_id')
                ->orderBy('ses.exam_schedule_id')
                ->orderBy('u.last_name')
                ->orderBy('u.first_name')
                ->get();
        }

        $assignedBySchedule = $assignedRows
            ->groupBy(fn ($row) => (int) ($row->exam_schedule_id ?? 0))
            ->map(function ($rows) {
                return $rows->map(fn ($row) => $this->mapScreeningStudentRow($row))->values()->all();
            });

        return response()->json([
            'success' => true,
            'data' => $schedules->map(function ($schedule) use ($assignedBySchedule) {
                $scheduleId = (int) $schedule->id;
                $students = $assignedBySchedule->get($scheduleId, []);

                return [
                    'id' => $scheduleId,
                    'date' => (string) ($schedule->date ?? ''),
                    'formatted_date' => $schedule->date
                        ? \Carbon\Carbon::parse($schedule->date)->format('F j, Y')
                        : '',
                    'time' => (string) ($schedule->time ?? ''),
                    'location' => (string) ($schedule->location ?? ''),
                    'schedule_name' => (string) ($schedule->schedule_name ?? ''),
                    'capacity' => (int) ($schedule->capacity ?? 0),
                    'assigned_students' => (int) ($schedule->assigned_students ?? 0),
                    'students' => $students,
                    'label' => trim(implode(' | ', array_filter([
                        (string) ($schedule->schedule_name ?? ''),
                        $schedule->date ? \Carbon\Carbon::parse($schedule->date)->format('F j, Y') : '',
                        (string) ($schedule->time ?? ''),
                        (string) ($schedule->location ?? ''),
                    ]))),
                ];
            })->values(),
        ]);
    }

    public function screeningEligibleStudents(Request $request)
    {
        $context = $this->collegeDeanContext($request);
        if ($context['error']) {
            return $context['error'];
        }

        $validated = $request->validate([
            'exam_id' => ['required', 'integer', 'exists:exams,id'],
        ]);

        $exam = $this->screeningExamForDean((int) $validated['exam_id'], (int) $context['college_id']);
        if (!$exam) {
            return response()->json([
                'success' => false,
                'message' => 'Screening exam not found in your college.',
            ], 404);
        }

        $rows = $this->screeningEligibleStudentsQuery($exam)
            ->orderByRaw('CASE WHEN ses.exam_schedule_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('u.last_name')
            ->orderBy('u.first_name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rows->map(fn ($row) => $this->mapScreeningStudentRow($row))->values(),
        ]);
    }

    public function assignScreeningStudents(Request $request)
    {
        $context = $this->collegeDeanContext($request);
        if ($context['error']) {
            return $context['error'];
        }

        $validated = $request->validate([
            'exam_id' => ['required', 'integer', 'exists:exams,id'],
            'exam_schedule_id' => ['required', 'integer', 'exists:exam_schedules,id'],
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'distinct'],
        ]);

        $exam = $this->screeningExamForDean((int) $validated['exam_id'], (int) $context['college_id']);
        if (!$exam) {
            return response()->json([
                'success' => false,
                'message' => 'Screening exam not found in your college.',
            ], 404);
        }

        $schedule = DB::table('exam_schedules')
            ->where('id', (int) $validated['exam_schedule_id'])
            ->where('schedule_type', self::SCHEDULE_TYPE_SCREENING)
            ->first();

        if (!$schedule) {
            return response()->json([
                'success' => false,
                'message' => 'Exam schedule not found.',
            ], 404);
        }

        $eligibleRows = $this->screeningEligibleStudentsQuery($exam)
            ->whereIn('u.id', array_map('intval', $validated['user_ids']))
            ->get()
            ->keyBy(fn ($row) => (int) $row->user_id);

        $targetScheduleId = (int) $validated['exam_schedule_id'];
        $capacity = (int) ($schedule->capacity ?? 0);
        $currentCount = (int) DB::table('student_exam_schedules')
            ->where('exam_schedule_id', $targetScheduleId)
            ->distinct('user_id')
            ->count('user_id');

        $results = [];
        $affectedScheduleIds = [$targetScheduleId];

        DB::transaction(function () use (
            $validated,
            $eligibleRows,
            $targetScheduleId,
            $capacity,
            &$currentCount,
            &$results,
            &$affectedScheduleIds
        ) {
            foreach ($validated['user_ids'] as $rawUserId) {
                $userId = (int) $rawUserId;
                $student = $eligibleRows->get($userId);

                if (!$student) {
                    $results[] = [
                        'user_id' => $userId,
                        'status' => 'invalid',
                        'message' => 'Student is not eligible for this screening exam schedule.',
                    ];
                    continue;
                }

                $existing = DB::table('student_exam_schedules')
                    ->where('exam_id', (int) $validated['exam_id'])
                    ->where('user_id', $userId)
                    ->first();

                $existingScheduleId = (int) ($existing->exam_schedule_id ?? 0);
                if ($existingScheduleId === $targetScheduleId) {
                    $results[] = [
                        'user_id' => $userId,
                        'status' => 'unchanged',
                        'message' => 'Student is already assigned to this schedule.',
                    ];
                    continue;
                }

                if ($capacity > 0 && $currentCount >= $capacity) {
                    $results[] = [
                        'user_id' => $userId,
                        'status' => 'full',
                        'message' => 'This exam schedule is already full.',
                    ];
                    continue;
                }

                if ($existing) {
                    DB::table('student_exam_schedules')
                        ->where('id', (int) $existing->id)
                        ->update([
                            'exam_schedule_id' => $targetScheduleId,
                            'status' => 'scheduled',
                            'updated_at' => now(),
                        ]);
                    if ($existingScheduleId > 0) {
                        $affectedScheduleIds[] = $existingScheduleId;
                    }
                } else {
                    DB::table('student_exam_schedules')->insert([
                        'user_id' => $userId,
                        'exam_id' => (int) $validated['exam_id'],
                        'exam_schedule_id' => $targetScheduleId,
                        'status' => 'scheduled',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $currentCount++;
                $results[] = [
                    'user_id' => $userId,
                    'status' => 'assigned',
                    'message' => 'Student scheduled successfully.',
                ];
            }
        });

        app(\App\Services\ExamScheduleCounterService::class)->refreshMany($affectedScheduleIds);

        return response()->json([
            'success' => true,
            'message' => 'Screening schedule assignments updated.',
            'data' => $results,
        ]);
    }

    public function storeScreeningSchedule(Request $request)
    {
        $context = $this->collegeDeanContext($request);
        if ($context['error']) {
            return $context['error'];
        }

        $validated = $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'time' => 'required',
            'location' => 'required|string|max:255',
            'schedule_name' => 'nullable|string|max:255',
            'capacity' => 'required|integer|min:1',
        ]);

        $schedule = DB::table('exam_schedules')->insertGetId([
            'date' => $validated['date'],
            'time' => $validated['time'],
            'location' => $validated['location'],
            'schedule_name' => $validated['schedule_name'] ?? null,
            'capacity' => (int) $validated['capacity'],
            'schedule_type' => self::SCHEDULE_TYPE_SCREENING,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => DB::table('exam_schedules')->where('id', $schedule)->first(),
        ], 201);
    }

    public function updateScreeningSchedule(Request $request, int $scheduleId)
    {
        $context = $this->collegeDeanContext($request);
        if ($context['error']) {
            return $context['error'];
        }

        $validated = $request->validate([
            'date' => 'required|date',
            'time' => 'required',
            'location' => 'required|string|max:255',
            'schedule_name' => 'nullable|string|max:255',
            'capacity' => 'required|integer|min:1',
        ]);

        $updated = DB::table('exam_schedules')
            ->where('id', $scheduleId)
            ->where('schedule_type', self::SCHEDULE_TYPE_SCREENING)
            ->update([
                'date' => $validated['date'],
                'time' => $validated['time'],
                'location' => $validated['location'],
                'schedule_name' => $validated['schedule_name'] ?? null,
                'capacity' => (int) $validated['capacity'],
                'updated_at' => now(),
            ]);

        if ($updated < 1) {
            return response()->json([
                'success' => false,
                'message' => 'Screening schedule not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => DB::table('exam_schedules')->where('id', $scheduleId)->first(),
        ]);
    }

    public function destroyScreeningSchedule(Request $request, int $scheduleId)
    {
        $context = $this->collegeDeanContext($request);
        if ($context['error']) {
            return $context['error'];
        }

        $deleted = DB::table('exam_schedules')
            ->where('id', $scheduleId)
            ->where('schedule_type', self::SCHEDULE_TYPE_SCREENING)
            ->delete();

        if ($deleted < 1) {
            return response()->json([
                'success' => false,
                'message' => 'Screening schedule not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Screening schedule removed.',
        ]);
    }

    public function unassignScreeningStudent(Request $request, int $examId, int $scheduleId, int $userId)
    {
        $context = $this->collegeDeanContext($request);
        if ($context['error']) {
            return $context['error'];
        }

        $exam = $this->screeningExamForDean($examId, (int) $context['college_id']);
        if (!$exam) {
            return response()->json([
                'success' => false,
                'message' => 'Screening exam not found in your college.',
            ], 404);
        }

        $deleted = DB::table('student_exam_schedules')
            ->where('exam_id', $examId)
            ->where('exam_schedule_id', $scheduleId)
            ->where('user_id', $userId)
            ->delete();

        if ($deleted < 1) {
            return response()->json([
                'success' => false,
                'message' => 'Schedule assignment not found.',
            ], 404);
        }

        app(\App\Services\ExamScheduleCounterService::class)->refreshMany([$scheduleId]);

        return response()->json([
            'success' => true,
            'message' => 'Student removed from screening schedule.',
        ]);
    }

    private function collegeDeanContext(Request $request): array
    {
        $user = $request->user();
        if (!$user || !$this->hasRole($user->role, 'college_dean')) {
            return [
                'error' => response()->json([
                    'success' => false,
                    'message' => 'Only college deans can access this resource.',
                ], 403),
            ];
        }

        $departmentId = (int) ($user->employee?->college_id ?? $user->employee?->department_id ?? 0);
        if ($departmentId <= 0) {
            return [
                'error' => response()->json([
                    'success' => false,
                    'message' => 'College dean does not have an assigned college.',
                ], 422),
            ];
        }

        return [
            'error' => null,
            'user' => $user,
            'college_id' => $departmentId,
        ];
    }

    private function hasRole(?string $roles, string $role): bool
    {
        if (!$roles) {
            return false;
        }

        $roleList = array_map('trim', explode(',', $roles));
        return in_array($role, $roleList, true);
    }

    private function fullName($row): string
    {
        if (!$row) {
            return '';
        }

        $first = trim((string) ($row->first_name ?? ''));
        $middle = trim((string) ($row->middle_initial ?? ''));
        $last = trim((string) ($row->last_name ?? ''));
        $ext = trim((string) ($row->extension_name ?? ''));

        $parts = array_values(array_filter([$last, $first]));
        $name = implode(', ', $parts);

        if ($middle !== '') {
            $name .= ($name !== '' ? ' ' : '') . $middle;
        }
        if ($ext !== '') {
            $name .= ($name !== '' ? ' ' : '') . $ext;
        }

        return trim($name);
    }

    private function screeningExamForDean(int $examId, int $collegeId): ?\App\Models\Exam
    {
        if ($examId <= 0 || $collegeId <= 0) {
            return null;
        }

        return \App\Models\Exam::query()
            ->with('program')
            ->where('id', $examId)
            ->whereHas('program', function ($query) use ($collegeId) {
                $query->where($this->programOrgUnitColumn(), $collegeId);
            })
            ->where(function ($query) {
                foreach (self::SCREENING_TYPE_ALIASES as $index => $alias) {
                    if ($index === 0) {
                        $query->whereRaw('LOWER(TRIM(Exam_Type)) = ?', [$alias]);
                        continue;
                    }

                    $query->orWhereRaw('LOWER(TRIM(Exam_Type)) = ?', [$alias]);
                }
            })
            ->first();
    }

    private function passedEntranceAttemptSubquery()
    {
        return DB::table('answer_sheets as ans')
            ->join('exams as e', 'e.id', '=', 'ans.exam_id')
            ->where('ans.status', 'checked')
            ->where('ans.total_score', '>=', self::PASSING_SCORE)
            ->where(function ($query) {
                foreach (self::ENTRANCE_TYPE_ALIASES as $index => $alias) {
                    if ($index === 0) {
                        $query->whereRaw('LOWER(TRIM(e.Exam_Type)) = ?', [$alias]);
                        continue;
                    }

                    $query->orWhereRaw('LOWER(TRIM(e.Exam_Type)) = ?', [$alias]);
                }
            })
            ->select([
                'ans.user_id',
            ])
            ->selectRaw('MAX(ans.total_score) as entrance_score')
            ->groupBy('ans.user_id');
    }

    private function screeningEligibleStudentsQuery(\App\Models\Exam $exam)
    {
        return DB::table('users as u')
            ->joinSub($this->passedEntranceAttemptSubquery(), 'passed_entrance', function ($join) {
                $join->on('passed_entrance.user_id', '=', 'u.id');
            })
            ->join('recommendations as rec', function ($join) use ($exam) {
                $join->on('rec.user_id', '=', 'u.id')
                    ->where('rec.type', '=', self::TYPE_STUDENT_CHOICE)
                    ->where('rec.program_id', '=', (int) ($exam->program_id ?? 0));
            })
            ->leftJoin('students as st', 'st.user_id', '=', 'u.id')
            ->leftJoin('student_exam_schedules as ses', function ($join) use ($exam) {
                $join->on('ses.user_id', '=', 'u.id')
                    ->where('ses.exam_id', '=', (int) $exam->id);
            })
            ->leftJoin('exam_schedules as sch', 'sch.id', '=', 'ses.exam_schedule_id')
            ->where('u.role', 'student')
            ->select([
                'u.id as user_id',
                'u.first_name',
                'u.middle_initial',
                'u.last_name',
                'u.extension_name',
                'st.Student_Number',
                'rec.rank as program_rank',
                'ses.exam_schedule_id',
                'sch.date as scheduled_date',
                'sch.time as scheduled_time',
                'sch.location as scheduled_location',
            ])
            ->selectRaw('passed_entrance.entrance_score as entrance_score');
    }

    private function mapScreeningStudentRow($row): array
    {
        return [
            'user_id' => (int) ($row->user_id ?? 0),
            'student_number' => (string) ($row->Student_Number ?? ''),
            'student_name' => $this->fullName($row),
            'first_name' => (string) ($row->first_name ?? ''),
            'middle_initial' => (string) ($row->middle_initial ?? ''),
            'last_name' => (string) ($row->last_name ?? ''),
            'extension_name' => (string) ($row->extension_name ?? ''),
            'entrance_score' => isset($row->entrance_score) ? (int) $row->entrance_score : null,
            'program_rank' => isset($row->program_rank) ? (int) $row->program_rank : null,
            'exam_schedule_id' => isset($row->exam_schedule_id) ? (int) $row->exam_schedule_id : null,
            'scheduled_date' => (string) ($row->scheduled_date ?? ''),
            'scheduled_time' => (string) ($row->scheduled_time ?? ''),
            'scheduled_location' => (string) ($row->scheduled_location ?? ''),
        ];
    }
}
