<?php

namespace App\Http\Controllers\Api\Instructor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InstructorManagementController extends Controller
{
    public function students(Request $request)
    {
        $context = $this->instructorContext($request);
        if ($context['error']) {
            return $context['error'];
        }

        $instructorId = (int) $context['user']->id;
        $subjectIds = $this->assignedSubjectIds($instructorId);

        if (empty($subjectIds)) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        $rows = DB::table('subject_student_assignments as ssa')
            ->join('users as u', 'u.id', '=', 'ssa.student_user_id')
            ->leftJoin('students as st', 'st.user_id', '=', 'u.id')
            ->leftJoin('programs as p', 'p.id', '=', 'st.program_id')
            ->leftJoin('colleges as d', 'd.id', '=', 'p.college_id')
            ->leftJoin('subjects as subj', 'subj.id', '=', 'ssa.subject_id')
            ->where('u.role', 'student')
            ->whereIn('ssa.subject_id', $subjectIds)
            ->groupBy(
                'u.id',
                'u.first_name',
                'u.middle_initial',
                'u.last_name',
                'u.extension_name',
                'u.username',
                'u.email',
                'st.Student_Number',
                'p.id',
                'p.Program_Name',
                'd.College_Name'
            )
            ->selectRaw("
                u.id,
                u.first_name,
                u.middle_initial,
                u.last_name,
                u.extension_name,
                u.username,
                u.email,
                st.Student_Number,
                p.id as program_id,
                p.Program_Name as program_name,
                d.College_Name as College_Name,
                GROUP_CONCAT(DISTINCT subj.Subject_Name SEPARATOR ', ') as subject_names
            ")
            ->orderBy('u.last_name')
            ->orderBy('u.first_name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rows->map(fn ($row) => [
                'id' => (int) $row->id,
                'student_number' => (string) ($row->Student_Number ?? ''),
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
        $context = $this->instructorContext($request);
        if ($context['error']) {
            return $context['error'];
        }

        $instructorId = (int) $context['user']->id;

        $rows = DB::table('subject_instructor_assignments as sia')
            ->join('subjects as s', 's.id', '=', 'sia.subject_id')
            ->leftJoin('subject_student_assignments as ssa', 'ssa.subject_id', '=', 's.id')
            ->where('sia.instructor_user_id', $instructorId)
            ->groupBy('s.id', 's.Subject_Name')
            ->selectRaw('s.id, s.Subject_Name, COUNT(DISTINCT ssa.student_user_id) as students_count')
            ->orderBy('s.Subject_Name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rows->map(fn ($row) => [
                'id' => (int) $row->id,
                'subject_name' => (string) ($row->Subject_Name ?? ''),
                'students_count' => (int) ($row->students_count ?? 0),
            ])->values(),
        ]);
    }

    public function subjectStudents(Request $request, int $subjectId)
    {
        $context = $this->instructorContext($request);
        if ($context['error']) {
            return $context['error'];
        }

        $instructorId = (int) $context['user']->id;

        $isAssigned = DB::table('subject_instructor_assignments')
            ->where('subject_id', $subjectId)
            ->where('instructor_user_id', $instructorId)
            ->exists();

        if (!$isAssigned) {
            return response()->json([
                'success' => false,
                'message' => 'Subject is not assigned to this instructor.',
            ], 404);
        }

        $subjectName = (string) (DB::table('subjects')->where('id', $subjectId)->value('Subject_Name') ?? '');

        $rows = DB::table('subject_student_assignments as ssa')
            ->join('users as u', 'u.id', '=', 'ssa.student_user_id')
            ->leftJoin('students as st', 'st.user_id', '=', 'u.id')
            ->leftJoin('programs as p', 'p.id', '=', 'st.program_id')
            ->leftJoin('colleges as d', 'd.id', '=', 'p.college_id')
            ->where('ssa.subject_id', $subjectId)
            ->where('u.role', 'student')
            ->orderBy('u.last_name')
            ->orderBy('u.first_name')
            ->select([
                'u.id',
                'u.first_name',
                'u.middle_initial',
                'u.last_name',
                'u.extension_name',
                'u.username',
                'u.email',
                'st.Student_Number',
                'p.Program_Name as program_name',
                'd.College_Name as College_Name',
            ])
            ->get();

        return response()->json([
            'success' => true,
            'subject_id' => $subjectId,
            'subject_name' => $subjectName,
            'data' => $rows->map(fn ($row) => [
                'id' => (int) $row->id,
                'student_number' => (string) ($row->Student_Number ?? ''),
                'full_name' => $this->fullName($row),
                'username' => (string) ($row->username ?? ''),
                'email' => (string) ($row->email ?? ''),
                'program_name' => (string) ($row->program_name ?? ''),
                'College_Name' => (string) ($row->College_Name ?? ''),
            ])->values(),
        ]);
    }

    private function instructorContext(Request $request): array
    {
        $user = $request->user();
        if (!$user || !$this->hasRole($user->role, 'instructor')) {
            return [
                'error' => response()->json([
                    'success' => false,
                    'message' => 'Only instructors can access this resource.',
                ], 403),
            ];
        }

        return [
            'error' => null,
            'user' => $user,
        ];
    }

    private function assignedSubjectIds(int $instructorUserId): array
    {
        return DB::table('subject_instructor_assignments')
            ->where('instructor_user_id', $instructorUserId)
            ->pluck('subject_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
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
}
