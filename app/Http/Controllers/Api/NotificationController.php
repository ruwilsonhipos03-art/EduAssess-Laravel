<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NotificationController extends Controller
{
    public function summary(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $role = (string) ($user->role ?? '');
        $tabs = [];

        if ($role === 'student') {
            $tabs = $this->studentTabs((int) $user->id, $user);
        } else if ($this->hasAnyRole($role, ['admin', 'college_dean', 'instructor', 'entrance_examiner'])) {
            $tabs = $this->staffTabs($user);
        }

        return response()->json([
            'role' => $role,
            'tabs' => $tabs,
        ]);
    }

    private function studentTabs(int $userId, User $user): array
    {
        $tabs = [];

        $tabs['schedules'] = [
            'latest_at' => DB::table('student_exam_schedules')
                ->where('user_id', $userId)
                ->max('updated_at'),
        ];

        $tabs['exams'] = [
            'latest_at' => DB::table('answer_sheets')
                ->where('user_id', $userId)
                ->whereIn('status', ['scanned', 'checked'])
                ->max('updated_at'),
        ];

        $tabs['reports'] = [
            'latest_at' => DB::table('answer_sheets')
                ->where('user_id', $userId)
                ->where('status', 'checked')
                ->max('updated_at'),
        ];

        $tabs['subjects'] = [
            'latest_at' => DB::table('subject_student_assignments')
                ->where('student_user_id', $userId)
                ->max('updated_at'),
        ];

        $tabs['profile'] = [
            'needs_action' => $user->email_verified_at === null,
        ];

        return $tabs;
    }

    private function staffTabs(User $user): array
    {
        $tabs = [];
        $examIds = $this->ownedExamIdsForUser((int) $user->id, $user);

        if (!empty($examIds)) {
            $tabs['reports'] = [
                'latest_at' => DB::table('answer_sheets')
                    ->whereIn('exam_id', $examIds)
                    ->where('status', 'checked')
                    ->max('updated_at'),
            ];
        } else {
            $tabs['reports'] = ['latest_at' => null];
        }

        return $tabs;
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

    private function ownedExamIdsForUser(int $userId, ?User $user = null): array
    {
        if ($userId <= 0) {
            return [];
        }

        if ($user && $this->hasAnyRole($user->role, ['admin'])) {
            return DB::table('exams')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
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
}
