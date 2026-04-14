<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Employee;
use App\Models\Program;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class EmployeeController extends Controller
{
    private function orgUnitTable(): string
    {
        return Schema::hasTable('colleges') ? 'colleges' : 'departments';
    }

    private function employeeCollegeForeignKey(): string
    {
        if (Schema::hasColumn('employees', 'college_id')) {
            return 'college_id';
        }

        if (Schema::hasColumn('employees', 'department_id')) {
            return 'department_id';
        }

        return 'college_id';
    }

    private function programOrgUnitForeignKey(): string
    {
        if (Schema::hasColumn('programs', 'college_id')) {
            return 'college_id';
        }

        if (Schema::hasColumn('programs', 'department_id')) {
            return 'department_id';
        }

        return 'college_id';
    }

    private function validateRoleAssignments(array $validated): void
    {
        $roles = $validated['roles'] ?? [];

        if (in_array('college_dean', $roles, true) && empty($validated['college_id'])) {
            throw ValidationException::withMessages([
                'college_id' => 'College is required for a college dean.',
            ]);
        }

        if (in_array('entrance_examiner', $roles, true) && empty($validated['office_id'])) {
            throw ValidationException::withMessages([
                'office_id' => 'Office is required for an entrance examiner.',
            ]);
        }

        if (in_array('instructor', $roles, true)) {
            if (empty($validated['college_id'])) {
                throw ValidationException::withMessages([
                    'college_id' => 'College is required for an instructor.',
                ]);
            }

            if (empty($validated['program_id'])) {
                throw ValidationException::withMessages([
                    'program_id' => 'Program is required for an instructor.',
                ]);
            }

            $program = Program::query()->find($validated['program_id']);
            $programCollegeId = $program?->{$this->programOrgUnitForeignKey()} ?? null;
            if ((int) $programCollegeId !== (int) $validated['college_id']) {
                throw ValidationException::withMessages([
                    'program_id' => 'Selected program must belong to the selected college.',
                ]);
            }
        }
    }

    public function index()
    {
        return response()->json([
            'data' => Employee::with(['user', 'department', 'office', 'program'])->latest()->get()
        ]);
    }

    public function store(Request $request)
    {
        $adminUser = $request->user();
        $orgTable = $this->orgUnitTable();
        $validated = $request->validate([
            'first_name'      => 'required|string|max:255',
            'middle_initial'  => 'nullable|string|max:2',
            'last_name'       => 'required|string|max:255',
            'extension_name'  => 'nullable|string|max:10',
            // Email removed from here
            'username'        => 'required|unique:users,username',
            'password'        => 'required|min:8',
            'college_id'   => 'nullable|exists:' . $orgTable . ',id',
            'office_id'       => 'nullable|exists:offices,id',
            'program_id'      => 'nullable|exists:programs,id',
            'roles'           => 'required|array|min:1',
            'roles.*'         => 'string|in:college_dean,instructor,entrance_examiner',
        ]);

        $this->validateRoleAssignments($validated);

        return DB::transaction(function () use ($validated, $adminUser) {
            $lastEmployee = Employee::orderBy('id', 'desc')->first();
            $nextId = $lastEmployee ? ((int) str_replace('EM-', '', $lastEmployee->Employee_Number)) + 1 : 1;
            $generatedID = 'EM-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

            $roleString = implode(',', $validated['roles']);

            $user = User::create([
                'first_name'     => $validated['first_name'],
                'middle_initial' => $validated['middle_initial'],
                'last_name'      => $validated['last_name'],
                'extension_name' => $validated['extension_name'],
                'username'       => $validated['username'],
                // Email removed from here
                'password'       => Hash::make($validated['password']),
                'role'           => $roleString,
            ]);

            $employee = $user->employee()->create([
                'Employee_Number' => $generatedID,
                $this->employeeCollegeForeignKey() => $validated['college_id'],
                'office_id'       => $validated['office_id'],
                'program_id'      => $validated['program_id'] ?? null,
            ]);

            ActivityLogger::log(
                (int) ($adminUser?->id ?? 0),
                'admin',
                'employee_created',
                'employee',
                (int) $employee->id,
                'Admin created employee account',
                trim($user->last_name . ', ' . $user->first_name) . ' was added as an employee account.',
                [
                    'employee_number' => (string) $generatedID,
                    'username' => (string) $user->username,
                    'roles' => $validated['roles'],
                ]
            );

            return response()->json($user->load('employee.college', 'employee.office', 'employee.program'), 201);
        });
    }

    public function update(Request $request, $id)
    {
        $adminUser = $request->user();
        $employee = Employee::findOrFail($id);
        $user = $employee->user;
        $orgTable = $this->orgUnitTable();
        $collegeFk = $this->employeeCollegeForeignKey();

        $validated = $request->validate([
            'first_name'      => 'required|string',
            'last_name'       => 'required|string',
            // Email removed from here
            'username'        => 'required|unique:users,username,' . $user->id,
            'password'        => 'nullable|min:8',
            'college_id'   => 'nullable|exists:' . $orgTable . ',id',
            'office_id'       => 'nullable|exists:offices,id',
            'program_id'      => 'nullable|exists:programs,id',
            'roles'           => 'required|array|min:1',
            'roles.*'         => 'string|in:college_dean,instructor,entrance_examiner',
        ]);

        $this->validateRoleAssignments($validated);

        DB::transaction(function () use ($validated, $employee, $user, $collegeFk, $adminUser) {
            $roleString = implode(',', $validated['roles']);

            $user->update([
                'first_name' => $validated['first_name'],
                'last_name'  => $validated['last_name'],
                'username'   => $validated['username'],
                'role'       => $roleString,
            ]);

            if (!empty($validated['password'])) {
                $user->update(['password' => Hash::make($validated['password'])]);
            }

            $employee->update([
                $collegeFk => $validated['college_id'],
                'office_id'       => $validated['office_id'],
                'program_id'      => $validated['program_id'] ?? null,
            ]);

            ActivityLogger::log(
                (int) ($adminUser?->id ?? 0),
                'admin',
                'employee_updated',
                'employee',
                (int) $employee->id,
                'Admin updated employee account',
                trim($user->last_name . ', ' . $user->first_name) . ' employee details were updated.',
                [
                    'employee_number' => (string) ($employee->Employee_Number ?? ''),
                    'username' => (string) $user->username,
                    'roles' => $validated['roles'],
                ]
            );
        });

        return response()->json($employee->load('user', 'department', 'office', 'program'));
    }

    public function destroy($id)
    {
        $employee = Employee::findOrFail($id);
        $user = $employee->user;

        ActivityLogger::log(
            (int) (request()->user()?->id ?? 0),
            'admin',
            'employee_deleted',
            'employee',
            (int) $employee->id,
            'Admin deleted employee account',
            trim(($user?->last_name ?? '') . ', ' . ($user?->first_name ?? '')) . ' employee account was deleted.',
            [
                'employee_number' => (string) ($employee->Employee_Number ?? ''),
                'username' => (string) ($user?->username ?? ''),
            ]
        );

        if ($employee->user) {
            $employee->user->delete();
        }
        $employee->delete();
        return response()->json(null, 204);
    }
}
