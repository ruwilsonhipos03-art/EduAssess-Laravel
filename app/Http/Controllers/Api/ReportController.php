<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AnswerKey;
use App\Models\AnswerSheet;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReportController extends Controller
{
    private const PASSING_SCORE = 75;
    private const ENTRANCE_TYPE_ALIASES = ['entrance', 'entrance exam', 'screening', 'screening exam'];

    private function orgUnitTable(): string
    {
        if ($this->programOrgUnitColumn() === 'department_id' || $this->employeeOrgUnitColumn() === 'department_id') {
            return 'departments';
        }

        return Schema::hasTable('colleges') ? 'colleges' : 'departments';
    }

    private function orgUnitNameColumn(): string
    {
        $table = $this->orgUnitTable();
        if (Schema::hasColumn($table, 'College_Name')) {
            return 'College_Name';
        }

        if (Schema::hasColumn($table, 'Department_Name')) {
            return 'Department_Name';
        }

        return 'College_Name';
    }

    private function employeeOrgUnitColumn(): string
    {
        if (Schema::hasColumn('employees', 'department_id')) {
            return 'department_id';
        }

        if (Schema::hasColumn('employees', 'college_id')) {
            return 'college_id';
        }

        return 'college_id';
    }

    private function programOrgUnitColumn(): string
    {
        if (Schema::hasColumn('programs', 'department_id')) {
            return 'department_id';
        }

        if (Schema::hasColumn('programs', 'college_id')) {
            return 'college_id';
        }

        return 'college_id';
    }

    /**
     * Fetch all users formatted for the Reports Dashboard.
     */
    public function index(Request $request)
    {
        try {
            // Eager load relationships to avoid N+1 performance issues
            $users = User::with(['employee.college', 'employee.office'])
                ->latest()
                ->get();

            // Transform data into a flat structure for the frontend table
            $reportData = $users->map(function ($user) {
                return [
                    'id'              => $user->id,
                    'id_number'       => $user->employee->Employee_Number ?? 'N/A',
                    'full_name'       => $this->formatFullName($user),
                    'email'           => $user->email,
                    'username'        => $user->username,
                    'role'            => $user->role, // instructor, student, college_dean, etc.
                    'college_id'   => $user->employee->college_id ?? null,
                    'College_Name' => $user->employee->college->College_Name ?? 'N/A',
                    'office_name'     => $user->employee->office->Office_Name ?? 'N/A',
                    // Assuming 'status' is active if email is verified, or add your custom logic
                    'status'          => $user->email_verified_at ? 'active' : 'active',
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $reportData
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate report: ' . $e->getMessage()
            ], 500);
        }
    }

    public function adminStudents(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$this->hasAnyRole($user->role, ['admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only administrators can access this report.',
            ], 403);
        }

        $programOrgUnitColumn = $this->programOrgUnitColumn();
        $orgUnitTable = $this->orgUnitTable();
        $orgUnitNameColumn = $this->orgUnitNameColumn();

        $latestSheets = DB::table('answer_sheets as ans')
            ->selectRaw('ans.user_id, MAX(ans.id) as latest_answer_sheet_id')
            ->whereIn('ans.status', ['checked', 'scanned'])
            ->groupBy('ans.user_id');

        $rows = DB::table('users as u')
            ->join('students as st', 'st.user_id', '=', 'u.id')
            ->leftJoin('programs as p', 'p.id', '=', 'st.program_id')
            ->leftJoin($orgUnitTable . ' as d', 'd.id', '=', 'p.' . $programOrgUnitColumn)
            ->leftJoinSub($latestSheets, 'latest_sheet', function ($join) {
                $join->on('latest_sheet.user_id', '=', 'u.id');
            })
            ->leftJoin('answer_sheets as ans', 'ans.id', '=', 'latest_sheet.latest_answer_sheet_id')
            ->leftJoin('exams as e', 'e.id', '=', 'ans.exam_id')
            ->where('u.role', 'student')
            ->orderBy('u.last_name')
            ->orderBy('u.first_name')
            ->selectRaw("
                u.id,
                u.first_name,
                u.middle_initial,
                u.last_name,
                u.extension_name,
                u.username,
                u.email,
                st.Student_Number as student_number,
                p.id as program_id,
                p.Program_Name as program_name,
                COALESCE(d.{$orgUnitNameColumn}, 'N/A') as college_name,
                e.id as exam_id,
                e.Exam_Title as exam_name,
                e.Exam_Type as exam_type,
                ans.status as exam_status,
                ans.total_score as exam_total_score
            ")
            ->get()
            ->map(function ($row) {
                return [
                    'id' => (int) $row->id,
                    'student_number' => (string) ($row->student_number ?? ''),
                    'full_name' => $this->formatNameParts(
                        (string) ($row->last_name ?? ''),
                        (string) ($row->first_name ?? ''),
                        (string) ($row->middle_initial ?? ''),
                        (string) ($row->extension_name ?? '')
                    ),
                    'username' => (string) ($row->username ?? ''),
                    'email' => (string) ($row->email ?? ''),
                    'program_id' => (int) ($row->program_id ?? 0),
                    'program_name' => (string) ($row->program_name ?? 'N/A'),
                    'college_name' => (string) ($row->college_name ?? 'N/A'),
                    'exam_id' => $row->exam_id ? (int) $row->exam_id : null,
                    'exam_name' => (string) ($row->exam_name ?? 'N/A'),
                    'exam_type' => (string) ($row->exam_type ?? ''),
                    'exam_status' => (string) ($row->exam_status ?? 'not_taken'),
                    'exam_total_score' => $row->exam_total_score !== null ? (int) $row->exam_total_score : null,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function adminScheduledStudents(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$this->hasAnyRole($user->role, ['admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only administrators can access this report.',
            ], 403);
        }

        $scheduleType = trim((string) $request->query('schedule_type', 'entrance'));
        $scheduleId = (int) $request->query('exam_schedule_id', 0);
        $search = trim((string) $request->query('search', ''));
        try {
            $month = $this->parseMonthFilter($request->query('month'));
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $scheduleOptions = $this->adminScheduledStudentOptions($scheduleType, $month);
        $rows = $this->adminScheduledStudentRows($scheduleType, $scheduleId, $search, $month);

        return response()->json([
            'success' => true,
            'filters' => [
                'schedule_type' => $scheduleType,
                'exam_schedule_id' => $scheduleId > 0 ? $scheduleId : null,
                'month' => $month,
                'search' => $search,
            ],
            'schedule_options' => $scheduleOptions,
            'data' => $rows,
        ]);
    }

    public function adminScheduledStudentsDownload(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$this->hasAnyRole($user->role, ['admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only administrators can access this report.',
            ], 403);
        }

        $scheduleType = trim((string) $request->query('schedule_type', 'entrance'));
        $scheduleId = (int) $request->query('exam_schedule_id', 0);
        $semesterLabel = trim((string) $request->query('semester_label', ''));
        try {
            $month = $this->parseMonthFilter($request->query('month'));
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        if ($semesterLabel === '') {
            return response()->json([
                'success' => false,
                'message' => 'Please provide the semester / academic year before downloading the list.',
            ], 422);
        }

        $scheduleOptions = $this->adminScheduledStudentOptions($scheduleType, $month);
        $pages = [];
        $fileName = '';

        if ($scheduleId > 0) {
            $schedule = collect($scheduleOptions)->firstWhere('id', $scheduleId);

            if (!$schedule) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected schedule was not found.',
                ], 404);
            }

            $rows = $this->adminScheduledStudentRows($scheduleType, $scheduleId, '', $month);
            $pages = $this->buildScheduledStudentsPdfPages(
                $scheduleType,
                $schedule,
                $rows,
                $semesterLabel
            );

            $fileName = sprintf(
                'scheduled_students_%s_%s.pdf',
                $scheduleType !== '' ? strtolower($scheduleType) : 'list',
                $scheduleId
            );
        } else {
            $printableSchedules = collect($scheduleOptions)
                ->filter(fn (array $schedule) => (int) ($schedule['assigned_students'] ?? 0) > 0)
                ->values();

            if ($printableSchedules->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No schedules with assigned students were found for the selected filters.',
                ], 404);
            }

            foreach ($printableSchedules as $schedule) {
                $rows = $this->adminScheduledStudentRows(
                    $scheduleType,
                    (int) ($schedule['id'] ?? 0),
                    '',
                    $month
                );
                $schedulePages = $this->buildScheduledStudentsPdfPages(
                    $scheduleType,
                    $schedule,
                    $rows,
                    $semesterLabel
                );
                $pages = array_merge($pages, $schedulePages);
            }

            if ($this->isScreeningScheduleType($scheduleType)) {
                $pages = $this->sortScreeningPagesByProgramThenSchedule($pages);
            }

            if ($month !== null) {
                $fileName = sprintf(
                    'scheduled_students_%s_%d_%02d_all.pdf',
                    $scheduleType !== '' ? strtolower($scheduleType) : 'list',
                    now()->year,
                    $month
                );
            } else {
                $fileName = sprintf(
                    'scheduled_students_%s_all.pdf',
                    $scheduleType !== '' ? strtolower($scheduleType) : 'list'
                );
            }
        }

        $pdf = Pdf::setPaper('a4', 'portrait')
            ->loadView('pdf.admin_scheduled_students', [
                'pages' => $pages,
                'generatedAt' => now(),
            ]);

        return $pdf->download($fileName);
    }

    public function adminExamReports(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$this->hasAnyRole($user->role, ['admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only administrators can access this report.',
            ], 403);
        }

        $rows = DB::table('exams as e')
            ->leftJoin('programs as p', 'p.id', '=', 'e.program_id')
            ->leftJoin('employees as emp', 'emp.id', '=', 'e.created_by')
            ->leftJoin('users as creator_user', 'creator_user.id', '=', 'emp.user_id')
            ->leftJoin('users as legacy_user', 'legacy_user.id', '=', 'e.created_by')
            ->selectRaw("
                e.id,
                e.Exam_Title as exam_title,
                e.Exam_Type as exam_type,
                e.program_id,
                p.Program_Name as program_name,
                e.created_by,
                creator_user.first_name as creator_first_name,
                creator_user.last_name as creator_last_name,
                legacy_user.first_name as legacy_first_name,
                legacy_user.last_name as legacy_last_name,
                e.created_at
            ")
            ->orderByDesc('e.created_at')
            ->orderBy('e.Exam_Title')
            ->get()
            ->map(function ($row) {
                $creatorFirst = trim((string) ($row->creator_first_name ?? ''));
                $creatorLast = trim((string) ($row->creator_last_name ?? ''));
                $legacyFirst = trim((string) ($row->legacy_first_name ?? ''));
                $legacyLast = trim((string) ($row->legacy_last_name ?? ''));

                $examinerName = trim($creatorFirst . ' ' . $creatorLast);
                if ($examinerName === '') {
                    $examinerName = trim($legacyFirst . ' ' . $legacyLast);
                }
                if ($examinerName === '') {
                    $examinerName = $row->created_by ? 'User #' . (int) $row->created_by : 'N/A';
                }

                return [
                    'id' => (int) $row->id,
                    'exam_title' => (string) ($row->exam_title ?? ''),
                    'exam_type' => (string) ($row->exam_type ?? ''),
                    'program_id' => $row->program_id ? (int) $row->program_id : null,
                    'program_name' => (string) ($row->program_name ?? 'N/A'),
                    'created_by' => $row->created_by ? (int) $row->created_by : null,
                    'examiner_name' => $examinerName,
                    'created_at' => $row->created_at ? (string) $row->created_at : null,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function adminExamReportDetail(Request $request, int $examId)
    {
        $user = Auth::user();
        if (!$user || !$this->hasAnyRole($user->role, ['admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only administrators can access this report.',
            ], 403);
        }

        $exam = DB::table('exams as e')
            ->leftJoin('programs as p', 'p.id', '=', 'e.program_id')
            ->where('e.id', $examId)
            ->select([
                'e.id',
                'e.Exam_Title as exam_title',
                'e.Exam_Type as exam_type',
                'p.Program_Name as program_name',
            ])
            ->first();

        if (!$exam) {
            return response()->json([
                'success' => false,
                'message' => 'Exam not found.',
            ], 404);
        }

        $rows = DB::table('answer_sheets as ans')
            ->join('users as u', 'u.id', '=', 'ans.user_id')
            ->where('ans.exam_id', $examId)
            ->where('u.role', 'student')
            ->whereIn('ans.status', ['scanned', 'checked'])
            ->orderBy('u.last_name')
            ->orderBy('u.first_name')
            ->selectRaw("
                ans.id as answer_sheet_id,
                ans.status,
                ans.total_score,
                ans.updated_at as checked_at,
                u.last_name,
                u.first_name,
                u.middle_initial,
                u.extension_name
            ")
            ->get()
            ->map(function ($row) {
                return [
                    'answer_sheet_id' => (int) $row->answer_sheet_id,
                    'student_full_name' => $this->formatNameParts(
                        (string) ($row->last_name ?? ''),
                        (string) ($row->first_name ?? ''),
                        (string) ($row->middle_initial ?? ''),
                        (string) ($row->extension_name ?? '')
                    ),
                    'status' => (string) ($row->status ?? ''),
                    'score' => $row->total_score !== null ? (int) $row->total_score : null,
                    'checked_at' => $row->checked_at,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'exam' => [
                    'id' => (int) $exam->id,
                    'exam_title' => (string) ($exam->exam_title ?? ''),
                    'exam_type' => (string) ($exam->exam_type ?? ''),
                    'program_name' => (string) ($exam->program_name ?? 'N/A'),
                ],
                'students' => $rows,
            ],
        ]);
    }

    public function adminActivities(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$this->hasAnyRole($user->role, ['admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only administrators can access this activity feed.',
            ], 403);
        }

        $query = ActivityLog::query()->with('actor:id,first_name,last_name,role');

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%')
                    ->orWhereHas('actor', function ($actorQuery) use ($search) {
                        $actorQuery->where('first_name', 'like', '%' . $search . '%')
                            ->orWhere('last_name', 'like', '%' . $search . '%');
                    });
            });
        }

        $actionType = trim((string) $request->query('action_type', ''));
        if ($actionType !== '') {
            $query->where('action_type', $actionType);
        }

        $actorRole = trim((string) $request->query('actor_role', ''));
        if ($actorRole !== '') {
            $query->where('actor_role', $actorRole);
        }

        $dateFrom = trim((string) $request->query('date_from', ''));
        if ($dateFrom !== '') {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        $dateTo = trim((string) $request->query('date_to', ''));
        if ($dateTo !== '') {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $perPage = (int) $request->query('per_page', 20);
        if ($perPage <= 0) {
            $perPage = 20;
        }

        $paginator = $query->orderByDesc('created_at')->paginate($perPage);
        $items = collect($paginator->items())->map(fn (ActivityLog $log) => $this->formatActivity($log))->values();

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'current_page' => (int) $paginator->currentPage(),
                'last_page' => (int) $paginator->lastPage(),
                'per_page' => (int) $paginator->perPage(),
                'total' => (int) $paginator->total(),
            ],
        ]);
    }

    public function collegeDeanActivities(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$this->hasAnyRole($user->role, ['college_dean'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only college deans can access this activity feed.',
            ], 403);
        }

        $collegeId = $this->actorCollegeId((int) $user->id);
        if ($collegeId <= 0) {
            return response()->json([
                'success' => true,
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => (int) max(1, (int) $request->query('per_page', 20)),
                    'total' => 0,
                ],
            ]);
        }

        $query = $this->collegeDeanActivitiesBaseQuery($collegeId)->with('actor:id,first_name,last_name,role');

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%')
                    ->orWhereHas('actor', function ($actorQuery) use ($search) {
                        $actorQuery->where('first_name', 'like', '%' . $search . '%')
                            ->orWhere('last_name', 'like', '%' . $search . '%');
                    });
            });
        }

        $actionType = trim((string) $request->query('action_type', ''));
        if ($actionType !== '') {
            $query->where('action_type', $actionType);
        }

        $actorRole = trim((string) $request->query('actor_role', ''));
        if ($actorRole !== '') {
            $query->where('actor_role', $actorRole);
        }

        $dateFrom = trim((string) $request->query('date_from', ''));
        if ($dateFrom !== '') {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        $dateTo = trim((string) $request->query('date_to', ''));
        if ($dateTo !== '') {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $perPage = (int) $request->query('per_page', 20);
        if ($perPage <= 0) {
            $perPage = 20;
        }

        $paginator = $query->orderByDesc('created_at')->paginate($perPage);
        $items = collect($paginator->items())->map(fn (ActivityLog $log) => $this->formatActivity($log))->values();

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'current_page' => (int) $paginator->currentPage(),
                'last_page' => (int) $paginator->lastPage(),
                'per_page' => (int) $paginator->perPage(),
                'total' => (int) $paginator->total(),
            ],
        ]);
    }

    /**
     * Helper to format user name.
     */
    private function formatFullName($user)
    {
        $name = "{$user->last_name}, {$user->first_name}";
        if ($user->middle_initial) {
            $name .= " " . $user->middle_initial . ".";
        }
        if ($user->extension_name) {
            $name .= " " . $user->extension_name;
        }
        return $name;
    }

    private function formatNameParts(string $lastName, string $firstName, string $middleInitial = '', string $extension = ''): string
    {
        $name = trim($lastName) . ', ' . trim($firstName);
        $middle = trim($middleInitial);
        $ext = trim($extension);

        if ($middle !== '') {
            $name .= ' ' . $middle;
        }

        if ($ext !== '') {
            $name .= ' ' . $ext;
        }

        return trim($name, ", \t\n\r\0\x0B");
    }

    private function adminScheduledStudentOptions(string $scheduleType = 'entrance', ?int $month = null): array
    {
        return DB::table('exam_schedules as sch')
            ->leftJoin('student_exam_schedules as ses', 'ses.exam_schedule_id', '=', 'sch.id')
            ->leftJoin('exams as e', 'e.id', '=', 'ses.exam_id')
            ->when($scheduleType !== '', function ($query) use ($scheduleType) {
                $query->where('sch.schedule_type', $scheduleType);
            })
            ->when($month !== null, function ($query) use ($month) {
                $query->whereYear('sch.date', now()->year)
                    ->whereMonth('sch.date', $month);
            })
            ->groupBy('sch.id', 'sch.date', 'sch.time', 'sch.location', 'sch.capacity', 'sch.schedule_type')
            ->groupBy('sch.schedule_name')
            ->orderBy('sch.date')
            ->orderBy('sch.time')
            ->selectRaw("
                sch.id,
                sch.date,
                sch.time,
                sch.location,
                sch.schedule_name,
                sch.capacity,
                sch.schedule_type,
                COUNT(DISTINCT ses.user_id) as assigned_students,
                GROUP_CONCAT(DISTINCT e.Exam_Title ORDER BY e.Exam_Title SEPARATOR ', ') as exam_titles
            ")
            ->get()
            ->map(function ($row) {
                $dateText = $row->date ? \Carbon\Carbon::parse($row->date)->format('F j, Y') : '';
                $timeText = $row->time ? \Carbon\Carbon::parse($row->time)->format('g:i A') : '';
                $labelParts = array_filter([
                    $dateText,
                    $timeText,
                    trim((string) ($row->location ?? '')),
                ]);

                return [
                    'id' => (int) $row->id,
                    'date' => (string) ($row->date ?? ''),
                    'time' => (string) ($row->time ?? ''),
                    'location' => (string) ($row->location ?? ''),
                    'schedule_name' => (string) ($row->schedule_name ?? ''),
                    'capacity' => (int) ($row->capacity ?? 0),
                    'schedule_type' => (string) ($row->schedule_type ?? ''),
                    'assigned_students' => (int) ($row->assigned_students ?? 0),
                    'exam_titles' => trim((string) ($row->exam_titles ?? '')),
                    'label' => implode(' | ', $labelParts),
                ];
            })
            ->values()
            ->all();
    }

    private function adminScheduledStudentRows(
        string $scheduleType = 'entrance',
        int $scheduleId = 0,
        string $search = '',
        ?int $month = null
    ): array
    {
        $query = DB::table('student_exam_schedules as ses')
            ->join('users as u', 'u.id', '=', 'ses.user_id')
            ->join('exam_schedules as sch', 'sch.id', '=', 'ses.exam_schedule_id')
            ->join('exams as e', 'e.id', '=', 'ses.exam_id')
            ->leftJoin('students as st', 'st.user_id', '=', 'u.id')
            ->leftJoin('programs as p', 'p.id', '=', 'st.program_id')
            ->where('u.role', 'student')
            ->when($scheduleType !== '', function ($inner) use ($scheduleType) {
                $inner->where('sch.schedule_type', $scheduleType);
            })
            ->when($scheduleId > 0, function ($inner) use ($scheduleId) {
                $inner->where('ses.exam_schedule_id', $scheduleId);
            })
            ->when($month !== null, function ($inner) use ($month) {
                $inner->whereYear('sch.date', now()->year)
                    ->whereMonth('sch.date', $month);
            })
            ->when($search !== '', function ($inner) use ($search) {
                $term = '%' . $search . '%';
                $inner->where(function ($searchQuery) use ($term) {
                    $searchQuery
                        ->where('st.Student_Number', 'like', $term)
                        ->orWhere('u.first_name', 'like', $term)
                        ->orWhere('u.last_name', 'like', $term)
                        ->orWhere('u.middle_initial', 'like', $term)
                        ->orWhere('u.email', 'like', $term)
                        ->orWhere('e.Exam_Title', 'like', $term)
                        ->orWhere('sch.location', 'like', $term)
                        ->orWhere('p.Program_Name', 'like', $term);
                });
            })
            ->orderBy('sch.date')
            ->orderBy('sch.time')
            ->orderBy('u.last_name')
            ->orderBy('u.first_name')
            ->selectRaw("
                ses.id,
                ses.status as schedule_status,
                ses.exam_schedule_id,
                u.id as user_id,
                u.first_name,
                u.middle_initial,
                u.last_name,
                u.extension_name,
                u.email,
                st.Student_Number as student_number,
                p.Program_Name as program_name,
                e.Exam_Title as exam_title,
                e.Exam_Type as exam_type,
                sch.date as scheduled_date,
                sch.time as scheduled_time,
                sch.location as scheduled_location,
                sch.schedule_name as scheduled_name,
                sch.schedule_type
            ");

        return $query->get()
            ->map(function ($row) {
                return [
                    'id' => (int) $row->id,
                    'user_id' => (int) $row->user_id,
                    'student_number' => (string) ($row->student_number ?? ''),
                    'email' => (string) ($row->email ?? ''),
                    'full_name' => $this->formatNameParts(
                        (string) ($row->last_name ?? ''),
                        (string) ($row->first_name ?? ''),
                        (string) ($row->middle_initial ?? ''),
                        (string) ($row->extension_name ?? '')
                    ),
                    'last_name' => trim((string) ($row->last_name ?? '')),
                    'first_name' => trim((string) ($row->first_name ?? '')),
                    'middle_name' => trim((string) ($row->middle_initial ?? '')),
                    'program_name' => (string) ($row->program_name ?? 'N/A'),
                    'exam_title' => (string) ($row->exam_title ?? ''),
                    'exam_type' => (string) ($row->exam_type ?? ''),
                    'schedule_status' => (string) ($row->schedule_status ?? ''),
                    'schedule_type' => (string) ($row->schedule_type ?? ''),
                    'scheduled_date' => (string) ($row->scheduled_date ?? ''),
                    'scheduled_time' => (string) ($row->scheduled_time ?? ''),
                    'scheduled_location' => (string) ($row->scheduled_location ?? ''),
                    'scheduled_name' => (string) ($row->scheduled_name ?? ''),
                    'schedule_label' => implode(' | ', array_filter([
                        (string) ($row->scheduled_name ?? ''),
                        !empty($row->scheduled_date) ? \Carbon\Carbon::parse($row->scheduled_date)->format('F j, Y') : '',
                        !empty($row->scheduled_time) ? \Carbon\Carbon::parse($row->scheduled_time)->format('g:i A') : '',
                        (string) ($row->scheduled_location ?? ''),
                    ])),
                ];
            })
            ->values()
            ->all();
    }

    private function sortScreeningPagesByProgramThenSchedule(array $pages): array
    {
        usort($pages, function (array $left, array $right) {
            $leftProgram = strtolower((string) ($left['program_sort_key'] ?? ''));
            $rightProgram = strtolower((string) ($right['program_sort_key'] ?? ''));
            if ($leftProgram !== $rightProgram) {
                return strcmp($leftProgram, $rightProgram);
            }

            $leftDate = (string) ($left['schedule']['date'] ?? '');
            $rightDate = (string) ($right['schedule']['date'] ?? '');
            if ($leftDate !== $rightDate) {
                return strcmp($leftDate, $rightDate);
            }

            $leftTime = (string) ($left['schedule']['time'] ?? '');
            $rightTime = (string) ($right['schedule']['time'] ?? '');
            if ($leftTime !== $rightTime) {
                return strcmp($leftTime, $rightTime);
            }

            $leftSchedule = strtolower((string) ($left['schedule']['schedule_name'] ?? ''));
            $rightSchedule = strtolower((string) ($right['schedule']['schedule_name'] ?? ''));
            if ($leftSchedule !== $rightSchedule) {
                return strcmp($leftSchedule, $rightSchedule);
            }

            return ((int) ($left['page_sequence'] ?? 0)) <=> ((int) ($right['page_sequence'] ?? 0));
        });

        return array_values($pages);
    }

    private function isScreeningScheduleType(string $scheduleType): bool
    {
        return strtolower(trim($scheduleType)) === 'screening';
    }

    private function buildScheduledStudentsPdfPages(
        string $scheduleType,
        array $schedule,
        array $rows,
        string $semesterLabel
    ): array {
        if (!$this->isScreeningScheduleType($scheduleType)) {
            return $this->buildScheduledStudentsProgramPages(
                $schedule,
                $rows,
                $semesterLabel,
                null,
                false
            );
        }

        $groupedRows = collect($rows)
            ->groupBy(function (array $row) {
                $programName = trim((string) ($row['program_name'] ?? ''));
                return $programName !== '' ? $programName : 'N/A';
            })
            ->sortKeys(SORT_NATURAL | SORT_FLAG_CASE);

        $pages = [];
        foreach ($groupedRows as $programName => $programRows) {
            $pages = array_merge(
                $pages,
                $this->buildScheduledStudentsProgramPages(
                    $schedule,
                    $programRows->values()->all(),
                    $semesterLabel,
                    (string) $programName,
                    true
                )
            );
        }

        return $pages;
    }

    private function buildScheduledStudentsProgramPages(
        array $schedule,
        array $rows,
        string $semesterLabel,
        ?string $programName,
        bool $isScreening
    ): array {
        $normalizedRows = array_values($rows);
        $rowChunks = array_chunk($normalizedRows, 40);
        if (empty($rowChunks)) {
            $rowChunks = [[]];
        }

        $baseTitleLines = $isScreening
            ? [
                (strtoupper(trim((string) ($programName ?? 'N/A')) ?: 'N/A') . ' SCREENING EXAM'),
                'LIST OF STUDENT APPLICANTS',
            ]
            : [
                'COLLEGE ADMISSION TEST',
                'LIST OF STUDENT APPLICANTS',
            ];

        return collect($rowChunks)
            ->values()
            ->map(function (array $chunkRows, int $chunkIndex) use ($schedule, $semesterLabel, $baseTitleLines, $programName, $isScreening) {
                return [
                    'schedule' => $schedule,
                    'semesterLabel' => $semesterLabel,
                    'leftRows' => array_slice($chunkRows, 0, 30),
                    'rightRows' => array_slice($chunkRows, 30, 10),
                    'report_title_lines' => $baseTitleLines,
                    'program_name' => $programName,
                    'program_sort_key' => strtolower((string) ($programName ?? '')),
                    'page_sequence' => $chunkIndex,
                    'is_screening' => $isScreening,
                ];
            })
            ->values()
            ->all();
    }

    private function parseMonthFilter(mixed $rawValue): ?int
    {
        if ($rawValue === null) {
            return null;
        }

        $value = trim((string) $rawValue);
        if ($value === '') {
            return null;
        }

        if (!preg_match('/^\d{1,2}$/', $value)) {
            throw new \InvalidArgumentException('Month filter must be a number from 1 to 12.');
        }

        $month = (int) $value;
        if ($month < 1 || $month > 12) {
            throw new \InvalidArgumentException('Month filter must be between 1 and 12.');
        }

        return $month;
    }

    private function formatActivity(ActivityLog $log): array
    {
        $actorName = 'System';
        if ($log->actor) {
            $first = trim((string) ($log->actor->first_name ?? ''));
            $last = trim((string) ($log->actor->last_name ?? ''));
            $actorName = trim($first . ' ' . $last);
            if ($actorName === '') {
                $actorName = 'User #' . (int) $log->actor->id;
            }
        }

        return [
            'id' => (int) $log->id,
            'actor_user_id' => $log->actor_user_id ? (int) $log->actor_user_id : null,
            'actor_name' => $actorName,
            'actor_role' => (string) ($log->actor_role ?? ''),
            'action_type' => (string) $log->action_type,
            'entity_type' => (string) $log->entity_type,
            'entity_id' => $log->entity_id ? (int) $log->entity_id : null,
            'title' => (string) $log->title,
            'description' => (string) $log->description,
            'meta' => is_array($log->meta) ? $log->meta : null,
            'created_at' => optional($log->created_at)?->toISOString(),
        ];
    }

    private function actorCollegeId(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        return (int) (DB::table('employees')
            ->where('user_id', $userId)
            ->value($this->employeeOrgUnitColumn()) ?? 0);
    }

    private function collegeDeanActivitiesBaseQuery(int $collegeId): Builder
    {
        $employeeOrgUnitColumn = $this->employeeOrgUnitColumn();
        $programOrgUnitColumn = $this->programOrgUnitColumn();

        $instructorUserIds = DB::table('employees')
            ->join('users', 'users.id', '=', 'employees.user_id')
            ->where("employees.{$employeeOrgUnitColumn}", $collegeId)
            ->where(function ($query) {
                $query->whereRaw("FIND_IN_SET('instructor', REPLACE(users.role, ' ', '')) > 0")
                    ->orWhere('users.role', 'instructor');
            })
            ->pluck('users.id');

        $programIds = DB::table('programs')
            ->where($programOrgUnitColumn, $collegeId)
            ->pluck('id');

        return ActivityLog::query()
            ->where(function ($query) use ($instructorUserIds, $programIds, $collegeId) {
                if ($instructorUserIds->isNotEmpty()) {
                    $query->where(function ($instructorActivity) use ($instructorUserIds) {
                        $instructorActivity->whereIn('actor_user_id', $instructorUserIds)
                            ->where(function ($role) {
                                $role->whereRaw("FIND_IN_SET('instructor', REPLACE(actor_role, ' ', '')) > 0")
                                    ->orWhere('actor_role', 'instructor');
                            });
                    });
                }

                $screeningCondition = function ($screeningActivity) use ($programIds, $collegeId) {
                    $screeningActivity->where('action_type', 'screening_exam_taken')
                        ->where(function ($scope) use ($programIds, $collegeId) {
                            if ($programIds->isNotEmpty()) {
                                $scope->whereIn('entity_id', $programIds)
                                    ->orWhereIn('meta->program_id', $programIds);
                            }

                            $scope->orWhere('meta->program_college_id', $collegeId)
                                ->orWhere('meta->college_id', $collegeId);
                        });
                };

                if ($instructorUserIds->isNotEmpty()) {
                    $query->orWhere($screeningCondition);
                    return;
                }

                $query->where($screeningCondition);
            });
    }

    public function entranceExamineeResults(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$this->hasAnyRole($user->role, ['entrance_examiner', 'college_dean', 'instructor'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only entrance examiners, college deans, and instructors can access this report.',
            ], 403);
        }

        $ownedExamIds = $this->ownedExamIdsForUser((int) $user->id, $user);
        if (empty($ownedExamIds)) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        $orgUnitTable = $this->orgUnitTable();
        $orgUnitNameColumn = $this->orgUnitNameColumn();
        $employeeOrgUnitColumn = $this->employeeOrgUnitColumn();

        $rows = DB::table('answer_sheets as ans')
            ->join('users as u', 'u.id', '=', 'ans.user_id')
            ->join('exams as e', 'e.id', '=', 'ans.exam_id')
            ->leftJoin('programs as p', 'p.id', '=', 'e.program_id')
            ->leftJoin('employees as emp', 'emp.id', '=', 'e.created_by')
            ->leftJoin($orgUnitTable . ' as d', 'd.id', '=', 'emp.' . $employeeOrgUnitColumn)
            ->leftJoin('exam_results as er', 'er.answer_sheet_id', '=', 'ans.id')
            ->leftJoin('subjects as s', 's.id', '=', 'er.subject_id')
            ->where('u.role', 'student')
            ->where('ans.status', 'checked')
            ->whereIn('ans.exam_id', $ownedExamIds)
            ->groupBy(
                'ans.id',
                'e.Exam_Title',
                'e.Exam_Type',
                'p.Program_Name',
                'd.' . $orgUnitNameColumn,
                'ans.total_score',
                'u.last_name',
                'u.first_name',
                'u.middle_initial',
                'u.extension_name'
            )
            ->selectRaw("
                ans.id as answer_sheet_id,
                e.Exam_Title as exam_name,
                e.Exam_Type as exam_type,
                COALESCE(p.Program_Name, 'N/A') as program_name,
                COALESCE(d.{$orgUnitNameColumn}, 'N/A') as college_name,
                ans.total_score as total,
                MAX(ans.updated_at) as checked_at,
                u.last_name,
                u.first_name,
                u.middle_initial,
                u.extension_name,
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
            ->orderBy('u.last_name')
            ->orderBy('u.first_name')
            ->get()
            ->map(function ($row) {
                $nameParts = [
                    trim((string) $row->last_name),
                    trim((string) $row->first_name),
                ];

                if (!empty($row->middle_initial)) {
                    $nameParts[] = trim((string) $row->middle_initial);
                }

                if (!empty($row->extension_name)) {
                    $nameParts[] = trim((string) $row->extension_name);
                }

                $fullName = implode(', ', $nameParts);

                return [
                    'answer_sheet_id' => (int) $row->answer_sheet_id,
                    'student_full_name' => $fullName,
                    'exam_name' => $row->exam_name,
                    'exam_type' => (string) ($row->exam_type ?? ''),
                    'program_name' => (string) ($row->program_name ?? 'N/A'),
                    'college_name' => (string) ($row->college_name ?? 'N/A'),
                    'math' => (int) $row->math,
                    'english' => (int) $row->english,
                    'science' => (int) $row->science,
                    'social_science' => (int) $row->social_science,
                    'total' => (int) ($row->total ?? 0),
                    'score' => (int) ($row->total ?? 0),
                    'items' => 100,
                    'checked_at' => $row->checked_at,
                ];
            })
            ->values();
        if ($this->hasScannedByColumn() && $this->hasAnyRole($user->role, ['instructor']) && !$this->hasAnyRole($user->role, ['college_dean', 'entrance_examiner'])) {
            $rows = DB::table('answer_sheets as ans')
                ->join('users as u', 'u.id', '=', 'ans.user_id')
                ->join('exams as e', 'e.id', '=', 'ans.exam_id')
                ->leftJoin('programs as p', 'p.id', '=', 'e.program_id')
                ->leftJoin('employees as emp', 'emp.id', '=', 'e.created_by')
                ->leftJoin($orgUnitTable . ' as d', 'd.id', '=', 'emp.' . $employeeOrgUnitColumn)
                ->leftJoin('exam_results as er', 'er.answer_sheet_id', '=', 'ans.id')
                ->leftJoin('subjects as s', 's.id', '=', 'er.subject_id')
                ->where('u.role', 'student')
                ->where('ans.status', 'checked')
                ->where('ans.scanned_by', $user->id)
                ->whereIn('ans.exam_id', $ownedExamIds)
                ->groupBy(
                    'ans.id',
                    'e.Exam_Title',
                    'e.Exam_Type',
                    'p.Program_Name',
                    'd.' . $orgUnitNameColumn,
                    'ans.total_score',
                    'u.last_name',
                    'u.first_name',
                    'u.middle_initial',
                    'u.extension_name'
                )
                ->selectRaw("
                    ans.id as answer_sheet_id,
                    e.Exam_Title as exam_name,
                    e.Exam_Type as exam_type,
                COALESCE(p.Program_Name, 'N/A') as program_name,
                COALESCE(d.{$orgUnitNameColumn}, 'N/A') as college_name,
                ans.total_score as total,
                MAX(ans.updated_at) as checked_at,
                u.last_name,
                u.first_name,
                u.middle_initial,
                u.extension_name,
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
                ->orderBy('u.last_name')
                ->orderBy('u.first_name')
                ->get()
                ->map(function ($row) {
                    $nameParts = [
                        trim((string) $row->last_name),
                        trim((string) $row->first_name),
                    ];

                    if (!empty($row->middle_initial)) {
                        $nameParts[] = trim((string) $row->middle_initial);
                    }

                    if (!empty($row->extension_name)) {
                        $nameParts[] = trim((string) $row->extension_name);
                    }

                    $fullName = implode(', ', $nameParts);

                    return [
                        'answer_sheet_id' => (int) $row->answer_sheet_id,
                        'student_full_name' => $fullName,
                        'exam_name' => $row->exam_name,
                        'exam_type' => (string) ($row->exam_type ?? ''),
                    'program_name' => (string) ($row->program_name ?? 'N/A'),
                    'college_name' => (string) ($row->college_name ?? 'N/A'),
                    'math' => (int) $row->math,
                    'english' => (int) $row->english,
                    'science' => (int) $row->science,
                    'social_science' => (int) $row->social_science,
                    'total' => (int) ($row->total ?? 0),
                    'score' => (int) ($row->total ?? 0),
                    'items' => 100,
                    'checked_at' => $row->checked_at,
                ];
            })
            ->values();
        }

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function entranceStudentsWhoTookExams(Request $request)
    {
        $rows = DB::table('student_exam_schedules as ses')
            ->join('users as u', 'u.id', '=', 'ses.user_id')
            ->join('exams as e', 'e.id', '=', 'ses.exam_id')
            ->leftJoin('answer_sheets as ans', function ($join) {
                $join->on('ans.user_id', '=', 'ses.user_id')
                    ->on('ans.exam_id', '=', 'ses.exam_id')
                    ->whereIn('ans.status', ['scanned', 'checked']);
            })
            ->where('u.role', 'student')
            ->where('e.Exam_Type', 'Entrance')
            ->where('ses.status', '!=', 'attended')
            ->whereNull('ans.id')
            ->groupBy(
                'ses.id',
                'u.last_name',
                'u.first_name',
                'u.middle_initial',
                'u.extension_name',
                'e.Exam_Title',
                'ses.status'
            )
            ->selectRaw("
                ses.id as id,
                u.last_name,
                u.first_name,
                u.middle_initial,
                u.extension_name,
                e.Exam_Title as exam_name,
                ses.status as exam_status
            ")
            ->orderBy('u.last_name')
            ->orderBy('u.first_name')
            ->get()
            ->map(function ($row) {
                $parts = [
                    trim((string) $row->last_name),
                    trim((string) $row->first_name),
                ];

                if (!empty($row->middle_initial)) {
                    $parts[] = trim((string) $row->middle_initial);
                }

                if (!empty($row->extension_name)) {
                    $parts[] = trim((string) $row->extension_name);
                }

                return [
                    'id' => (int) $row->id,
                    'student_full_name' => implode(', ', $parts),
                    'exam_name' => (string) $row->exam_name,
                    'exam_status' => (string) $row->exam_status,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function entranceExamineeResultDetail(Request $request, int $answerSheetId)
    {
        $user = Auth::user();
        if (!$user || !$this->hasAnyRole($user->role, ['entrance_examiner', 'college_dean', 'instructor'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only entrance examiners, college deans, and instructors can access this report detail.',
            ], 403);
        }

        $ownedExamIds = $this->ownedExamIdsForUser((int) $user->id, $user);

        $sheetQuery = AnswerSheet::query()
            ->with(['exam', 'user'])
            ->where('id', $answerSheetId)
            ->where('status', 'checked')
            ->whereIn('exam_id', $ownedExamIds);

        if ($this->hasScannedByColumn() && $this->hasAnyRole($user->role, ['instructor']) && !$this->hasAnyRole($user->role, ['college_dean', 'entrance_examiner'])) {
            $sheetQuery->where('scanned_by', $user->id);
        }

        $sheet = $sheetQuery->first();

        if (!$sheet) {
            return response()->json([
                'success' => false,
                'message' => 'Checked answer sheet not found.',
            ], 404);
        }

        $answerKey = AnswerKey::query()
            ->where('exam_id', $sheet->exam_id)
            ->latest('id')
            ->first();

        if (!$answerKey) {
            return response()->json([
                'success' => false,
                'message' => 'Answer key not found for this exam.',
            ], 404);
        }

        $studentAnswers = $this->normalizeAnswers((array) ($sheet->scanned_data ?? []));
        $correctAnswers = $this->normalizeAnswers((array) ($answerKey->answers ?? []));

        $items = [];
        for ($question = 1; $question <= 100; $question++) {
            $questionKey = (string) $question;
            $studentAnswer = $studentAnswers[$questionKey] ?? null;
            $correctAnswer = $correctAnswers[$questionKey] ?? null;

            $items[] = [
                'question' => $question,
                'is_correct' => $studentAnswer !== null && $correctAnswer !== null && $studentAnswer === $correctAnswer,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'answer_sheet_id' => (int) $sheet->id,
                'student_full_name' => $sheet->user ? $this->formatFullName($sheet->user) : '',
                'exam_name' => (string) ($sheet->exam?->Exam_Title ?? ''),
                'items' => $items,
            ],
        ]);
    }

    public function studentExamResults(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'student') {
            return response()->json([
                'success' => false,
                'message' => 'Only students can access this report.',
            ], 403);
        }

        $rows = DB::table('answer_sheets as ans')
            ->join('exams as e', 'e.id', '=', 'ans.exam_id')
            ->leftJoin('exam_results as er', 'er.answer_sheet_id', '=', 'ans.id')
            ->leftJoin('subjects as s', 's.id', '=', 'er.subject_id')
            ->where('ans.user_id', $user->id)
            ->where('ans.status', 'checked')
            ->groupBy('ans.id', 'e.Exam_Title', 'e.Exam_Type', 'ans.total_score', 'ans.updated_at')
            ->selectRaw("
                ans.id as answer_sheet_id,
                e.Exam_Title as exam_name,
                e.Exam_Type as exam_type,
                ans.total_score as total,
                ans.updated_at as checked_at,
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
            ->orderByDesc('ans.updated_at')
            ->get()
            ->map(function ($row) {
                $total = (int) ($row->total ?? 0);
                $examType = (string) ($row->exam_type ?? '');
                $isEntrance = $this->isEntranceExamType($examType);
                $isPassed = $total >= self::PASSING_SCORE;
                return [
                    'answer_sheet_id' => (int) $row->answer_sheet_id,
                    'exam_name' => (string) $row->exam_name,
                    'exam_type' => $examType,
                    'is_entrance_exam' => $isEntrance,
                    'math' => (int) $row->math,
                    'english' => (int) $row->english,
                    'science' => (int) $row->science,
                    'social_science' => (int) $row->social_science,
                    'total' => $total,
                    'result' => $isPassed ? 'Passed' : 'Failed',
                    'can_recommend_program' => $isPassed && $isEntrance,
                    'checked_at' => $row->checked_at,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    private function normalizeAnswers(array $answers): array
    {
        $normalized = [];

        foreach ($answers as $question => $value) {
            $key = (string) ((int) $question);
            if ($key === '0') {
                continue;
            }

            if (is_array($value)) {
                $value = reset($value);
            }

            if (!is_scalar($value) && $value !== null) {
                continue;
            }

            $answer = strtoupper(trim((string) $value));
            if ($answer === '' || $answer === '-' || $answer === '0' || $answer === 'NONE') {
                $normalized[$key] = null;
                continue;
            }

            $normalized[$key] = preg_replace('/[^A-Z0-9]/', '', $answer);
        }

        return $normalized;
    }

    private function isEntranceExamType(?string $examType): bool
    {
        $value = strtolower(trim((string) $examType));
        return in_array($value, self::ENTRANCE_TYPE_ALIASES, true);
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

    private function ownedExamIdsForUser(int $userId, ?User $user = null): array
    {
        if ($userId <= 0) {
            return [];
        }

        if ($user && $this->hasAnyRole($user->role, ['college_dean'])) {
            $employeeOrgUnitColumn = $this->employeeOrgUnitColumn();
            $programOrgUnitColumn = $this->programOrgUnitColumn();
            $departmentId = (int) DB::table('employees')
                ->where('user_id', $userId)
                ->value($employeeOrgUnitColumn);

            if ($departmentId <= 0) {
                return [];
            }

            return DB::table('exams as e')
                ->leftJoin('employees as emp', 'emp.id', '=', 'e.created_by')
                ->leftJoin('programs as p', 'p.id', '=', 'e.program_id')
                ->where(function ($query) use ($departmentId, $employeeOrgUnitColumn, $programOrgUnitColumn) {
                    $query->where('p.' . $programOrgUnitColumn, $departmentId)
                        ->orWhere('emp.' . $employeeOrgUnitColumn, $departmentId);
                })
                ->distinct()
                ->pluck('e.id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        $employeeId = DB::table('employees')
            ->where('user_id', $userId)
            ->value('id');

        return DB::table('exams')
            ->where(function ($query) use ($employeeId, $userId) {
                if ($employeeId) {
                    $query->where('created_by', $employeeId)
                        ->orWhere('created_by', $userId);
                    return;
                }

                $query->where('created_by', $userId);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function hasScannedByColumn(): bool
    {
        try {
            return Schema::hasColumn('answer_sheets', 'scanned_by');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
