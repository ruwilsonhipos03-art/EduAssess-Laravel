<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Recommendation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserManagementController extends Controller
{
    private const PASSING_SCORE = 75;
    private const SCREENING_TYPE_ALIASES = ['entrance', 'entrance exam', 'screening', 'screening exam'];
    private const ENTRANCE_TYPE_ALIASES = ['entrance', 'entrance exam'];

    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$this->hasAnyRole($user->role, ['admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only administrators can access user management.',
            ], 403);
        }

        $employeeOrgUnitColumn = $this->employeeOrgUnitColumn();
        $programOrgUnitColumn = $this->programOrgUnitColumn();
        $orgUnitTable = $this->orgUnitTable();
        $orgUnitNameColumn = $this->orgUnitNameColumn($orgUnitTable);

        $screeningTypeSql = collect(self::SCREENING_TYPE_ALIASES)
            ->map(fn (string $value) => "'" . str_replace("'", "''", strtolower($value)) . "'")
            ->implode(', ');
        $entranceTypeSql = collect(self::ENTRANCE_TYPE_ALIASES)
            ->map(fn (string $value) => "'" . str_replace("'", "''", strtolower($value)) . "'")
            ->implode(', ');

        $screeningSummary = DB::table('answer_sheets as ans')
            ->join('exams as ex', 'ex.id', '=', 'ans.exam_id')
            ->whereNotNull('ans.user_id')
            ->groupBy('ans.user_id')
            ->selectRaw("
                ans.user_id,
                MAX(CASE
                    WHEN LOWER(TRIM(COALESCE(ex.Exam_Type, ''))) IN ({$entranceTypeSql})
                     AND ans.status IN ('scanned', 'checked')
                    THEN 1 ELSE 0
                END) as has_entrance_exam_taken,
                MAX(CASE
                    WHEN LOWER(TRIM(COALESCE(ex.Exam_Type, ''))) IN ({$screeningTypeSql})
                     AND ans.status IN ('scanned', 'checked')
                    THEN 1 ELSE 0
                END) as has_screening_attempt,
                MAX(CASE
                    WHEN LOWER(TRIM(COALESCE(ex.Exam_Type, ''))) IN ({$screeningTypeSql})
                     AND ans.status = 'checked'
                     AND COALESCE(ans.total_score, 0) >= ?
                    THEN 1 ELSE 0
                END) as has_screening_pass
            ", [self::PASSING_SCORE]);

        $rows = DB::table('users as u')
            ->leftJoin('employees as emp', 'emp.user_id', '=', 'u.id')
            ->leftJoin('students as st', 'st.user_id', '=', 'u.id')
            ->leftJoin('programs as emp_program', 'emp_program.id', '=', 'emp.program_id')
            ->leftJoin('programs as student_program', 'student_program.id', '=', 'st.program_id')
            ->leftJoin($orgUnitTable . ' as emp_org', 'emp_org.id', '=', 'emp.' . $employeeOrgUnitColumn)
            ->leftJoin($orgUnitTable . ' as emp_program_org', 'emp_program_org.id', '=', 'emp_program.' . $programOrgUnitColumn)
            ->leftJoin($orgUnitTable . ' as student_program_org', 'student_program_org.id', '=', 'student_program.' . $programOrgUnitColumn)
            ->leftJoin('offices as o', 'o.id', '=', 'emp.office_id')
            ->leftJoinSub($screeningSummary, 'screening_summary', function ($join) {
                $join->on('screening_summary.user_id', '=', 'u.id');
            })
            ->orderBy('u.last_name')
            ->orderBy('u.first_name')
            ->orderBy('u.id')
            ->get([
                'u.id',
                'u.first_name',
                'u.middle_initial',
                'u.last_name',
                'u.extension_name',
                'u.username',
                'u.email',
                'u.email_verified_at',
                'u.role',
                'u.created_at',
                'emp.id as employee_id',
                'emp.' . $employeeOrgUnitColumn . ' as college_id',
                'emp.office_id',
                'emp.Employee_Number as employee_number',
                'st.id as student_profile_id',
                'st.Student_Number as student_number',
                DB::raw('COALESCE(emp_program.id, student_program.id) as program_id'),
                DB::raw("COALESCE(emp_program.Program_Name, student_program.Program_Name, 'N/A') as program_name"),
                DB::raw("COALESCE(emp_org.{$orgUnitNameColumn}, emp_program_org.{$orgUnitNameColumn}, student_program_org.{$orgUnitNameColumn}, 'N/A') as college_name"),
                DB::raw("COALESCE(o.Office_Name, 'N/A') as office_name"),
                DB::raw('CASE WHEN u.email_verified_at IS NULL THEN 0 ELSE 1 END as has_verified_email'),
                DB::raw('COALESCE(screening_summary.has_entrance_exam_taken, 0) as has_entrance_exam_taken'),
                DB::raw('COALESCE(screening_summary.has_screening_attempt, 0) as has_screening_attempt'),
                DB::raw('COALESCE(screening_summary.has_screening_pass, 0) as has_screening_pass'),
            ])
            ->map(function ($row) {
                $roles = $this->normalizeRoles((string) ($row->role ?? ''));
                $isStudent = in_array('student', $roles, true);
                $hasVerifiedEmail = (int) ($row->has_verified_email ?? 0) === 1;
                $hasEntranceExamTaken = (int) ($row->has_entrance_exam_taken ?? 0) === 1;
                $hasScreeningAttempt = (int) ($row->has_screening_attempt ?? 0) === 1;
                $hasScreeningPass = (int) ($row->has_screening_pass ?? 0) === 1;
                $isApplicant = $isStudent && (!$hasScreeningAttempt || !$hasScreeningPass);

                return [
                    'id' => (int) $row->id,
                    'full_name' => $this->formatNameParts(
                        (string) ($row->last_name ?? ''),
                        (string) ($row->first_name ?? ''),
                        (string) ($row->middle_initial ?? ''),
                        (string) ($row->extension_name ?? '')
                    ),
                    'first_name' => (string) ($row->first_name ?? ''),
                    'middle_initial' => (string) ($row->middle_initial ?? ''),
                    'last_name' => (string) ($row->last_name ?? ''),
                    'extension_name' => (string) ($row->extension_name ?? ''),
                    'username' => (string) ($row->username ?? ''),
                    'email' => (string) ($row->email ?? ''),
                    'email_verified_at' => $row->email_verified_at,
                    'role' => (string) ($row->role ?? ''),
                    'roles' => $roles,
                    'created_at' => $row->created_at,
                    'employee_id' => $row->employee_id ? (int) $row->employee_id : null,
                    'college_id' => $row->college_id ? (int) $row->college_id : null,
                    'office_id' => $row->office_id ? (int) $row->office_id : null,
                    'employee_number' => (string) ($row->employee_number ?? ''),
                    'student_profile_id' => $row->student_profile_id ? (int) $row->student_profile_id : null,
                    'student_number' => (string) ($row->student_number ?? ''),
                    'program_id' => $row->program_id ? (int) $row->program_id : null,
                    'program_name' => (string) ($row->program_name ?? 'N/A'),
                    'college_name' => (string) ($row->college_name ?? 'N/A'),
                    'office_name' => (string) ($row->office_name ?? 'N/A'),
                    'has_verified_email' => $hasVerifiedEmail,
                    'has_entrance_exam_taken' => $hasEntranceExamTaken,
                    'has_screening_attempt' => $hasScreeningAttempt,
                    'has_screening_pass' => $hasScreeningPass,
                    'is_applicant' => $isApplicant,
                    'user_kind' => $this->resolveUserKind($roles, (bool) $row->employee_id, (bool) $row->student_profile_id),
                ];
            })
            ->values();

        $studentChoicesByUser = Recommendation::query()
            ->with('program:id,Program_Name')
            ->where('type', 'student_choice')
            ->orderBy('rank')
            ->get()
            ->groupBy('user_id');

        $rows = $rows->map(function (array $row) use ($studentChoicesByUser) {
            $choices = collect($studentChoicesByUser->get($row['id'], collect()))
                ->sortBy('rank')
                ->values();

            return array_merge($row, [
                'program_choice_1' => $choices[0]->program_id ?? null,
                'program_choice_2' => $choices[1]->program_id ?? null,
                'program_choice_3' => $choices[2]->program_id ?? null,
                'program_choice_names' => $choices
                    ->map(fn ($choice) => (string) ($choice->program?->Program_Name ?? ''))
                    ->filter()
                    ->values()
                    ->all(),
            ]);
        })->values();

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    private function resolveUserKind(array $roles, bool $hasEmployeeRecord, bool $hasStudentRecord): string
    {
        if (in_array('admin', $roles, true)) {
            return 'admin';
        }

        if ($hasEmployeeRecord) {
            return 'employee';
        }

        if ($hasStudentRecord || in_array('student', $roles, true)) {
            return 'student';
        }

        return 'user';
    }

    private function normalizeRoles(string $roles): array
    {
        return collect(explode(',', $roles))
            ->map(fn ($role) => trim((string) $role))
            ->filter()
            ->values()
            ->all();
    }

    private function hasAnyRole(?string $roles, array $allowedRoles): bool
    {
        if (!$roles) {
            return false;
        }

        foreach ($this->normalizeRoles($roles) as $role) {
            if (in_array($role, $allowedRoles, true)) {
                return true;
            }
        }

        return false;
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

    private function orgUnitTable(): string
    {
        if (Schema::hasTable('colleges')) {
            return 'colleges';
        }

        return 'departments';
    }

    private function orgUnitNameColumn(string $table): string
    {
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

        return 'college_id';
    }

    private function programOrgUnitColumn(): string
    {
        if (Schema::hasColumn('programs', 'department_id')) {
            return 'department_id';
        }

        return 'college_id';
    }
}
