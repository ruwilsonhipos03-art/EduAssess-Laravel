<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardStatsController extends Controller
{
    private const PASSING_SCORE = 75;

    private function employeeCollegeColumn(): ?string
    {
        if (Schema::hasColumn('employees', 'college_id')) {
            return 'college_id';
        }

        if (Schema::hasColumn('employees', 'department_id')) {
            return 'department_id';
        }

        return null;
    }

    private function programCollegeColumn(): ?string
    {
        if (Schema::hasColumn('programs', 'college_id')) {
            return 'college_id';
        }

        if (Schema::hasColumn('programs', 'department_id')) {
            return 'department_id';
        }

        return null;
    }

    private function orgUnitTable(): ?string
    {
        if (Schema::hasTable('colleges')) {
            return 'colleges';
        }

        if (Schema::hasTable('departments')) {
            return 'departments';
        }

        return null;
    }

    public function admin()
    {
        $orgUnitTable = $this->orgUnitTable();

        $totalEmployees = DB::table('users')
            ->where('role', '!=', 'admin') // Exclude the main admin
            ->where(function ($query) {
                $query->whereRaw("FIND_IN_SET('college_dean', role) > 0")
                    ->orWhereRaw("FIND_IN_SET('instructor', role) > 0")
                    ->orWhereRaw("FIND_IN_SET('entrance_examiner', role) > 0");
            })
            ->count();

        $recentActivities = ActivityLog::query()
            ->with('actor:id,first_name,last_name')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(function (ActivityLog $log) {
                $actorName = 'System';
                if ($log->actor) {
                    $actorName = trim((string) $log->actor->first_name . ' ' . (string) $log->actor->last_name);
                    if ($actorName === '') {
                        $actorName = 'User #' . (int) $log->actor->id;
                    }
                }

                return [
                    'id' => (int) $log->id,
                    'actor_name' => $actorName,
                    'actor_role' => (string) ($log->actor_role ?? ''),
                    'action_type' => (string) $log->action_type,
                    'title' => (string) $log->title,
                    'description' => (string) $log->description,
                    'meta' => $log->meta,
                    'created_at' => optional($log->created_at)->toISOString(),
                ];
            })
            ->values();

        return response()->json([
            'total_employees' => $totalEmployees,
            'total_students' => DB::table('users')->where('role', 'student')->count(),
            'colleges' => $orgUnitTable ? DB::table($orgUnitTable)->count() : 0,
            'programs' => DB::table('programs')->count(),
            'recent_activities' => $recentActivities,
        ]);
    }

    public function collegeDean()
    {
        $userId = Auth::id();
        $collegeColumn = $this->employeeCollegeColumn();
        $programCollegeColumn = $this->programCollegeColumn();

        if (!$collegeColumn || !$programCollegeColumn) {
            return response()->json([
                'message' => 'Required college assignment columns are missing from employees/programs tables.',
            ], 422);
        }

        $employee = DB::table('employees')
            ->where('user_id', $userId)
            ->selectRaw("id, {$collegeColumn} as college_id")
            ->first();
        $departmentId = $employee?->college_id;
        $employeeId = $employee?->id;

        $examsCreated = DB::table('exams')
            ->where(function ($query) use ($employeeId, $userId) {
                if ($employeeId) {
                    $query->where('created_by', $employeeId)
                        ->orWhere('created_by', $userId);
                    return;
                }

                $query->where('created_by', $userId);
            })
            ->count();

        $subjects = DB::table('exam_subjects')
            ->where('user_id', $userId)
            ->distinct('subject_id')
            ->count('subject_id');

        $studentIdsQuery = DB::table('students')
            ->join('programs', 'programs.id', '=', 'students.program_id')
            ->where("programs.{$programCollegeColumn}", $departmentId)
            ->select('students.user_id')
            ->distinct();

        $totalExaminees = (clone $studentIdsQuery)->count();

        $checkedSheets = DB::table('answer_sheets')
            ->whereIn('user_id', $studentIdsQuery)
            ->where('status', 'checked')
            ->whereNotNull('total_score')
            ->count();

        $passedSheets = DB::table('answer_sheets')
            ->whereIn('user_id', $studentIdsQuery)
            ->where('status', 'checked')
            ->where('total_score', '>=', self::PASSING_SCORE)
            ->count();

        $recentActivities = $this->collegeDeanActivitiesBaseQuery((int) $departmentId)
            ->with('actor:id,first_name,last_name')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(function (ActivityLog $log) {
                $actorName = 'System';
                if ($log->actor) {
                    $actorName = trim((string) $log->actor->first_name . ' ' . (string) $log->actor->last_name);
                    if ($actorName === '') {
                        $actorName = 'User #' . (int) $log->actor->id;
                    }
                }

                return [
                    'id' => (int) $log->id,
                    'actor_name' => $actorName,
                    'actor_role' => (string) ($log->actor_role ?? ''),
                    'action_type' => (string) $log->action_type,
                    'title' => (string) $log->title,
                    'description' => (string) $log->description,
                    'created_at' => optional($log->created_at)->toISOString(),
                ];
            })
            ->values();

        return response()->json([
            'exams_created' => $examsCreated,
            'total_examinees' => $totalExaminees,
            'subjects' => $subjects,
            'passing_rate' => $this->asPercent($passedSheets, $checkedSheets),
            'recent_activities' => $recentActivities,
        ]);
    }

    public function entrance()
    {
        $entranceExamIds = DB::table('exams')
            ->whereIn(DB::raw('LOWER(TRIM(Exam_Type))'), ['entrance', 'entrance exam', 'screening', 'screening exam'])
            ->pluck('id');

        $takenStudentIdsFromSchedules = DB::table('student_exam_schedules')
            ->whereIn('exam_id', $entranceExamIds)
            ->whereIn('status', ['attended', 'missed'])
            ->distinct()
            ->pluck('user_id');

        $takenStudentIdsFromSheets = DB::table('answer_sheets')
            ->whereIn('exam_id', $entranceExamIds)
            ->whereIn('status', ['scanned', 'checked'])
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id');

        $takenStudentIds = $takenStudentIdsFromSchedules
            ->merge($takenStudentIdsFromSheets)
            ->unique()
            ->values();

        $totalScheduledStudents = DB::table('student_exam_schedules')
            ->whereIn('exam_id', $entranceExamIds)
            ->where('status', 'scheduled')
            ->when($takenStudentIds->isNotEmpty(), function ($query) use ($takenStudentIds) {
                $query->whereNotIn('user_id', $takenStudentIds);
            })
            ->distinct('user_id')
            ->count('user_id');

        $checkedUserIds = DB::table('answer_sheets')
            ->whereIn('exam_id', $entranceExamIds)
            ->where('status', 'checked')
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id');

        $pendingResultUserIds = DB::table('answer_sheets')
            ->whereIn('exam_id', $entranceExamIds)
            ->where('status', 'scanned')
            ->whereNotNull('user_id')
            ->when($checkedUserIds->isNotEmpty(), function ($query) use ($checkedUserIds) {
                $query->whereNotIn('user_id', $checkedUserIds);
            })
            ->distinct()
            ->pluck('user_id');

        $pendingResults = $pendingResultUserIds->count();

        $totalStudentsPassed = DB::table('answer_sheets')
            ->where('status', 'checked')
            ->where('total_score', '>=', self::PASSING_SCORE)
            ->whereNotNull('user_id')
            ->distinct('user_id')
            ->count('user_id');

        $totalStudents = DB::table('users')
            ->where('role', 'student')
            ->count();

        return response()->json([
            'scheduled_students' => $totalScheduledStudents,
            'pending_results' => $pendingResults,
            'passed_students' => $totalStudentsPassed,
            'total_students' => $totalStudents,
        ]);
    }

    public function instructor()
    {
        $userId = Auth::id();

        $employeeId = DB::table('employees')
            ->where('user_id', $userId)
            ->value('id');

        $subjects = DB::table('subject_instructor_assignments')
            ->where('instructor_user_id', $userId)
            ->distinct('subject_id')
            ->count('subject_id');

        $totalStudents = DB::table('subject_student_assignments')
            ->where('instructor_user_id', $userId)
            ->distinct('student_user_id')
            ->count('student_user_id');

        $checkedSheets = 0;
        $passedSheets = 0;

        if ($employeeId) {
            $examIds = DB::table('exams')
                ->where('created_by', $employeeId)
                ->pluck('id');

            if ($examIds->isNotEmpty()) {
                $checkedSheets = DB::table('answer_sheets')
                    ->whereIn('exam_id', $examIds)
                    ->where('status', 'checked')
                    ->whereNotNull('total_score')
                    ->distinct('user_id')
                    ->count('user_id');

                $passedSheets = DB::table('answer_sheets')
                    ->whereIn('exam_id', $examIds)
                    ->where('status', 'checked')
                    ->where('total_score', '>=', self::PASSING_SCORE)
                    ->distinct('user_id')
                    ->count('user_id');
            }
        }

        $recentActivities = ActivityLog::query()
            ->with('actor:id,first_name,last_name')
            ->whereIn('action_type', ['student_subject_assigned', 'instructor_subject_assigned'])
            ->where('meta->instructor_user_id', $userId)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(function (ActivityLog $log) {
                $actorName = 'System';
                if ($log->actor) {
                    $actorName = trim((string) $log->actor->first_name . ' ' . (string) $log->actor->last_name);
                    if ($actorName === '') {
                        $actorName = 'User #' . (int) $log->actor->id;
                    }
                }

                return [
                    'id' => (int) $log->id,
                    'actor_name' => $actorName,
                    'actor_role' => (string) ($log->actor_role ?? ''),
                    'action_type' => (string) $log->action_type,
                    'title' => (string) $log->title,
                    'description' => (string) $log->description,
                    'created_at' => optional($log->created_at)->toISOString(),
                ];
            })
            ->values();

        return response()->json([
            'total_students' => $totalStudents,
            'subjects' => $subjects,
            'passing_rate' => $this->asPercent($passedSheets, $checkedSheets),
            'recent_activities' => $recentActivities,
        ]);
    }

    public function student()
    {
        $userId = Auth::id();

        $takenExamIdsFromSchedules = DB::table('student_exam_schedules')
            ->where('user_id', $userId)
            ->whereIn('status', ['attended', 'missed'])
            ->distinct()
            ->pluck('exam_id');

        $takenExamIdsFromSheets = DB::table('answer_sheets')
            ->where('user_id', $userId)
            ->whereIn('status', ['scanned', 'checked'])
            ->distinct()
            ->pluck('exam_id');

        $examsTaken = $takenExamIdsFromSchedules
            ->merge($takenExamIdsFromSheets)
            ->filter()
            ->unique()
            ->count();

        $examsCompleted = DB::table('answer_sheets')
            ->where('user_id', $userId)
            ->where('status', 'checked')
            ->distinct('exam_id')
            ->count('exam_id');

        $totalSubjects = DB::table('subject_student_assignments')
            ->where('student_user_id', $userId)
            ->distinct('subject_id')
            ->count('subject_id');

        $checkedSheets = DB::table('answer_sheets')
            ->where('user_id', $userId)
            ->where('status', 'checked')
            ->whereNotNull('total_score')
            ->count();

        $passedSheets = DB::table('answer_sheets')
            ->where('user_id', $userId)
            ->where('status', 'checked')
            ->where('total_score', '>=', self::PASSING_SCORE)
            ->count();

        return response()->json([
            'passing_rate' => $this->asPercent($passedSheets, $checkedSheets),
            'exams_completed' => $examsCompleted,
            'exams_taken' => $examsTaken,
            'total_subjects' => $totalSubjects,
        ]);
    }

    private function asPercent(int $passed, int $total): float
    {
        if ($total <= 0) {
            return 0;
        }

        return round(($passed / $total) * 100, 2);
    }

    private function collegeDeanActivitiesBaseQuery(int $collegeId): Builder
    {
        if ($collegeId <= 0) {
            return ActivityLog::query()->whereRaw('1 = 0');
        }

        $employeeOrgUnitColumn = $this->employeeCollegeColumn() ?? 'college_id';
        $programOrgUnitColumn = $this->programCollegeColumn() ?? 'college_id';

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
}
