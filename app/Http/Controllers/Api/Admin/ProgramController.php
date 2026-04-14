<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class ProgramController extends Controller
{
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

    private function orgUnitTableForProgramForeignKey(string $fk): string
    {
        if ($fk === 'department_id') {
            return 'departments';
        }

        return 'colleges';
    }

    private function employeeOrgUnitId($user): int
    {
        return (int) ($user?->employee?->college_id ?? $user?->employee?->department_id ?? 0);
    }

    public function index()
    {
        $user = Auth::user();
        $canViewPrograms = $this->hasRole($user?->role, 'admin')
            || $this->hasRole($user?->role, 'college_dean')
            || $this->hasRole($user?->role, 'entrance_examiner');

        if (!$canViewPrograms) {
            return response()->json([
                'message' => 'Only admins, college deans, and entrance examiners can view the program list.',
            ], 403);
        }

        $query = Program::with('college')->latest();
        $programOrgUnitFk = $this->programOrgUnitForeignKey();

        if ($this->hasRole($user?->role, 'college_dean') && !$this->hasRole($user?->role, 'admin')) {
            $collegeId = $this->employeeOrgUnitId($user);
            if ($collegeId <= 0) {
                return response()->json([
                    'message' => 'College dean does not have an assigned college.',
                ], 422);
            }

            $query->where($programOrgUnitFk, $collegeId);
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$this->hasRole($user?->role, 'admin')) {
            return response()->json([
                'message' => 'Only admins can create programs.',
            ], 403);
        }

        $programOrgUnitFk = $this->programOrgUnitForeignKey();
        $orgUnitTable = $this->orgUnitTableForProgramForeignKey($programOrgUnitFk);
        $validated = $request->validate([
            'Program_Name' => 'required|string|max:255|unique:programs,Program_Name',
            'college_id' => 'required|exists:' . $orgUnitTable . ',id'
        ]);

        $program = Program::create([
            'Program_Name' => $validated['Program_Name'],
            $programOrgUnitFk => (int) $validated['college_id'],
        ]);
        return response()->json($program->load('college'), 201);
    }

    public function update(Request $request, Program $program)
    {
        $user = Auth::user();
        if (!$this->hasRole($user?->role, 'admin')) {
            return response()->json([
                'message' => 'Only admins can update programs.',
            ], 403);
        }

        $programOrgUnitFk = $this->programOrgUnitForeignKey();
        $orgUnitTable = $this->orgUnitTableForProgramForeignKey($programOrgUnitFk);
        $validated = $request->validate([
            'Program_Name' => 'required|string|max:255|unique:programs,Program_Name,' . $program->id,
            'college_id' => 'required|exists:' . $orgUnitTable . ',id'
        ]);

        $program->update([
            'Program_Name' => $validated['Program_Name'],
            $programOrgUnitFk => (int) $validated['college_id'],
        ]);
        return response()->json($program->load('college'));
    }

    public function destroy(Program $program)
    {
        $user = Auth::user();
        if (!$this->hasRole($user?->role, 'admin')) {
            return response()->json([
                'message' => 'Only admins can delete programs.',
            ], 403);
        }

        $program->delete();
        return response()->json(null, 204);
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
