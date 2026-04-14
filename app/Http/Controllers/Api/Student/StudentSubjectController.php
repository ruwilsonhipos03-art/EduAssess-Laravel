<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\SubjectInstructorAssignment;
use App\Models\SubjectStudentAssignment;
use Illuminate\Http\Request;

class StudentSubjectController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'student') {
            return response()->json([
                'success' => false,
                'message' => 'Only students can access this resource.',
            ], 403);
        }

        $rows = SubjectStudentAssignment::query()
            ->with([
                'subject:id,Subject_Name',
                'instructor:id,first_name,middle_initial,last_name,extension_name',
            ])
            ->where('student_user_id', (int) $user->id)
            ->latest('id')
            ->get();

        $subjectIds = $rows->pluck('subject_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $instructorBySubject = [];

        if ($subjectIds->isNotEmpty()) {
            $instructorAssignments = SubjectInstructorAssignment::query()
                ->with('instructor:id,first_name,middle_initial,last_name,extension_name')
                ->whereIn('subject_id', $subjectIds->all())
                ->orderByDesc('id')
                ->get()
                ->groupBy('subject_id')
                ->map(fn ($group) => $group->first());

            foreach ($instructorAssignments as $subjectId => $assignment) {
                $instructorBySubject[(int) $subjectId] = [
                    'instructor_name' => $this->fullName($assignment?->instructor),
                    'instructor_assigned_at' => optional($assignment?->created_at)?->toISOString(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $rows->map(function (SubjectStudentAssignment $row) {
                $subjectId = (int) $row->subject_id;
                $subjectInstructor = $instructorBySubject[$subjectId] ?? [];
                $storedInstructorName = $this->fullName($row->instructor);
                $storedInstructorAssignedAt = optional($row->updated_at ?? $row->created_at)?->toISOString();

                return [
                    'id' => (int) $row->id,
                    'subject_id' => $subjectId,
                    'subject_name' => (string) ($row->subject?->Subject_Name ?? ''),
                    'instructor_user_id' => $row->instructor_user_id ? (int) $row->instructor_user_id : null,
                    'instructor_name' => $storedInstructorName !== ''
                        ? $storedInstructorName
                        : (string) ($subjectInstructor['instructor_name'] ?? ''),
                    'instructor_assigned_at' => $storedInstructorName !== ''
                        ? $storedInstructorAssignedAt
                        : ($subjectInstructor['instructor_assigned_at'] ?? null),
                    'assigned_at' => optional($row->created_at)?->toISOString(),
                ];
            })->values(),
        ]);
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
