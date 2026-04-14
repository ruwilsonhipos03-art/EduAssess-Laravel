<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Exam;
use App\Models\Program;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ExamController extends Controller
{
    private const SCREENING_TYPE_ALIASES = ['entrance', 'entrance exam', 'screening', 'screening exam'];
    private const TERM_TYPE_ALIASES = ['term', 'term exam', 'departmental', 'normal', 'normal exam'];

    private function currentEmployeeId(): ?int
    {
        return Employee::where('user_id', Auth::id())->value('id');
    }

    private function hasRole(?string $roles, string $role): bool
    {
        if (!$roles) {
            return false;
        }

        $roleList = array_map('trim', explode(',', $roles));
        return in_array($role, $roleList, true);
    }

    private function isCollegeDean(): bool
    {
        $roles = Auth::user()?->role;
        return $this->hasRole($roles, 'college_dean') && !$this->hasRole($roles, 'admin');
    }

    private function currentCollegeId(): int
    {
        return (int) (Auth::user()?->employee?->college_id ?? Auth::user()?->employee?->department_id ?? 0);
    }

    private function programOrgUnitColumn(): string
    {
        if (Schema::hasColumn('programs', 'department_id')) {
            return 'department_id';
        }

        return 'college_id';
    }

    private function assertProgramForCollegeDean(?int $programId): void
    {
        if (!$this->isCollegeDean()) {
            return;
        }

        $collegeId = $this->currentCollegeId();
        if ($collegeId <= 0) {
            throw ValidationException::withMessages([
                'program_id' => 'College dean does not have an assigned college.',
            ]);
        }

        if (!$programId) {
            throw ValidationException::withMessages([
                'program_id' => 'Program is required when creating an exam as college dean.',
            ]);
        }

        $isAllowedProgram = Program::query()
            ->where('id', $programId)
            ->where($this->programOrgUnitColumn(), $collegeId)
            ->exists();

        if (!$isAllowedProgram) {
            throw ValidationException::withMessages([
                'program_id' => 'Selected program is not under your college.',
            ]);
        }
    }

    private function ownedExamsQuery()
    {
        $userId = Auth::id();
        $employeeId = $this->currentEmployeeId();

        return Exam::query()->where(function ($query) use ($userId, $employeeId) {
            if ($employeeId) {
                // Keep legacy records (created_by was user_id in old code) visible/editable,
                // but only when created_by does not reference an existing employee row.
                $query->where('created_by', $employeeId)
                    ->orWhere(function ($legacy) use ($userId) {
                        $legacy->where('created_by', $userId)
                            ->whereDoesntHave('creator');
                    });
                return;
            }

            $query->where('created_by', $userId)
                ->whereDoesntHave('creator');
        });
    }

    private function examTypeAliasesForScope(?string $scope): array
    {
        $normalized = strtolower(trim((string) $scope));

        if (in_array($normalized, ['screening', 'entrance'], true)) {
            return self::SCREENING_TYPE_ALIASES;
        }

        if (in_array($normalized, ['term', 'normal'], true)) {
            return self::TERM_TYPE_ALIASES;
        }

        return [];
    }

    private function applyExamTypeScope($query, ?string $scope): void
    {
        $aliases = $this->examTypeAliasesForScope($scope);
        if (empty($aliases)) {
            return;
        }

        $query->where(function ($types) use ($aliases) {
            foreach ($aliases as $index => $alias) {
                if ($index === 0) {
                    $types->whereRaw('LOWER(TRIM(Exam_Type)) = ?', [$alias]);
                    continue;
                }

                $types->orWhereRaw('LOWER(TRIM(Exam_Type)) = ?', [$alias]);
            }
        });
    }

    private function addExaminerFirstName(Collection $exams): Collection
    {
        $createdByIds = $exams
            ->pluck('created_by')
            ->filter(fn ($id) => !is_null($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($createdByIds->isEmpty()) {
            return $exams->map(function ($exam) {
                $exam->setAttribute('examiner_first_name', null);
                return $exam;
            });
        }

        $employeeFirstNames = Employee::query()
            ->with('user:id,first_name')
            ->whereIn('id', $createdByIds->all())
            ->get()
            ->mapWithKeys(fn (Employee $employee) => [
                (int) $employee->id => trim((string) ($employee->user?->first_name ?? '')),
            ]);

        $userFirstNames = User::query()
            ->whereIn('id', $createdByIds->all())
            ->pluck('first_name', 'id')
            ->map(fn ($name) => trim((string) $name));

        return $exams->map(function ($exam) use ($employeeFirstNames, $userFirstNames) {
            $createdBy = (int) ($exam->created_by ?? 0);
            $firstName = (string) ($employeeFirstNames[$createdBy] ?? $userFirstNames[$createdBy] ?? '');
            $exam->setAttribute('examiner_first_name', $firstName !== '' ? $firstName : null);
            return $exam;
        });
    }

    private function validateSubjectRanges(array $rows): void
    {
        foreach ($rows as $index => $row) {
            $start = (int) ($row['Starting_Number'] ?? 1);
            $end = (int) ($row['Ending_Number'] ?? 100);

            if ($end <= $start) {
                throw ValidationException::withMessages([
                    "exam_subjects.$index.Ending_Number" => 'Ending number must be greater than starting number.',
                ]);
            }
        }
    }

    /**
     * Display only exams created by the currently logged-in user.
     */
    public function index(Request $request)
    {
        $query = Exam::with(['creator.user', 'examSubjects.subject', 'program'])
            ->whereIn('id', $this->ownedExamsQuery()->select('id'))
            ->orderBy('created_at', 'desc');

        if ($this->isCollegeDean()) {
            $this->applyExamTypeScope($query, $request->query('scope'));
        }

        $exams = $query->get();

        return $this->addExaminerFirstName($exams);
    }

    /**
     * Store a new exam linked to the logged-in user.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'Exam_Title' => 'required|string|max:255',
            'Exam_Type'  => 'required|string|max:100',
            'description' => 'nullable|string|max:1000',
            'program_id' => 'nullable|integer|exists:programs,id',
            'exam_subjects' => 'nullable|array|min:1',
            'exam_subjects.*.subject_id' => 'required|distinct|exists:subjects,id',
            'exam_subjects.*.Starting_Number' => 'nullable|integer|min:1',
            'exam_subjects.*.Ending_Number' => 'nullable|integer|min:1',
        ]);
        $this->validateSubjectRanges($validated['exam_subjects'] ?? []);
        $this->assertProgramForCollegeDean(isset($validated['program_id']) ? (int) $validated['program_id'] : null);

        $employeeId = $this->currentEmployeeId();
        if (!$employeeId) {
            return response()->json([
                'message' => 'No employee profile is linked to your account. Please contact the administrator.',
            ], 422);
        }

        $exam = DB::transaction(function () use ($validated, $employeeId) {
            $exam = Exam::create([
                'Exam_Title' => $validated['Exam_Title'],
                'Exam_Type'  => $validated['Exam_Type'],
                'description' => isset($validated['description']) ? trim((string) $validated['description']) : null,
                'program_id' => isset($validated['program_id']) ? (int) $validated['program_id'] : null,
                'created_by' => $employeeId,
            ]);

            $subjects = collect($validated['exam_subjects'] ?? [])
                ->map(function ($row) {
                    $start = (int) ($row['Starting_Number'] ?? 1);
                    $end = (int) ($row['Ending_Number'] ?? 100);
                    return [
                        'subject_id' => (int) $row['subject_id'],
                        'Starting_Number' => $start,
                        'Ending_Number' => $end,
                        'user_id' => Auth::id(),
                    ];
                })
                ->values()
                ->all();

            if (!empty($subjects)) {
                $exam->examSubjects()->createMany($subjects);
            }

            return $exam;
        });

        $loaded = $exam->load(['creator.user', 'examSubjects.subject', 'program']);
        $withExaminer = $this->addExaminerFirstName(collect([$loaded]))->first();

        $actor = Auth::user();
        ActivityLogger::log(
            Auth::id() ? (int) Auth::id() : null,
            (string) ($actor?->role ?? ''),
            'exam_created',
            'exam',
            (int) $exam->id,
            'Exam created',
            'Created exam "' . (string) $exam->Exam_Title . '".',
            [
                'exam_type' => (string) $exam->Exam_Type,
                'program_id' => $exam->program_id ? (int) $exam->program_id : null,
                'program_name' => (string) ($exam->program?->Program_Name ?? ''),
            ]
        );

        return response()->json($withExaminer, 201);
    }

    /**
     * Update an exam, but only if it belongs to the logged-in user.
     */
    public function update(Request $request, $id)
    {
        $exam = $this->ownedExamsQuery()->findOrFail($id);

        $validated = $request->validate([
            'Exam_Title' => 'required|string|max:255',
            'Exam_Type'  => 'required|string|max:100',
            'description' => 'nullable|string|max:1000',
            'program_id' => 'nullable|integer|exists:programs,id',
            'exam_subjects' => 'nullable|array|min:1',
            'exam_subjects.*.subject_id' => 'required|distinct|exists:subjects,id',
            'exam_subjects.*.Starting_Number' => 'nullable|integer|min:1',
            'exam_subjects.*.Ending_Number' => 'nullable|integer|min:1',
        ]);
        $this->validateSubjectRanges($validated['exam_subjects'] ?? []);
        $this->assertProgramForCollegeDean(isset($validated['program_id']) ? (int) $validated['program_id'] : null);

        DB::transaction(function () use ($exam, $validated) {
            $exam->update([
                'Exam_Title' => $validated['Exam_Title'],
                'Exam_Type' => $validated['Exam_Type'],
                'description' => isset($validated['description']) ? trim((string) $validated['description']) : null,
                'program_id' => isset($validated['program_id']) ? (int) $validated['program_id'] : null,
            ]);

            if (array_key_exists('exam_subjects', $validated)) {
                $exam->examSubjects()->where('user_id', Auth::id())->delete();

                $subjects = collect($validated['exam_subjects'] ?? [])
                    ->map(function ($row) {
                        $start = (int) ($row['Starting_Number'] ?? 1);
                        $end = (int) ($row['Ending_Number'] ?? 100);
                        return [
                            'subject_id' => (int) $row['subject_id'],
                            'Starting_Number' => $start,
                            'Ending_Number' => $end,
                            'user_id' => Auth::id(),
                        ];
                    })
                    ->values()
                    ->all();

                if (!empty($subjects)) {
                    $exam->examSubjects()->createMany($subjects);
                }
            }
        });

        $loaded = $exam->fresh()->load(['creator.user', 'examSubjects.subject', 'program']);
        $withExaminer = $this->addExaminerFirstName(collect([$loaded]))->first();

        $actor = Auth::user();
        ActivityLogger::log(
            Auth::id() ? (int) Auth::id() : null,
            (string) ($actor?->role ?? ''),
            'exam_updated',
            'exam',
            (int) $exam->id,
            'Exam updated',
            'Updated exam "' . (string) $exam->Exam_Title . '".',
            [
                'exam_type' => (string) $exam->Exam_Type,
                'program_id' => $exam->program_id ? (int) $exam->program_id : null,
                'program_name' => (string) ($exam->program?->Program_Name ?? ''),
            ]
        );

        return response()->json($withExaminer);
    }

    /**
     * Delete an exam, but only if it belongs to the logged-in user.
     */
    public function destroy($id)
    {
        $exam = $this->ownedExamsQuery()->findOrFail($id);
        $examTitle = (string) ($exam->Exam_Title ?? 'Unknown');
        $examType = (string) ($exam->Exam_Type ?? '');
        $examProgramId = $exam->program_id ? (int) $exam->program_id : null;
        $exam->delete();

        $actor = Auth::user();
        ActivityLogger::log(
            Auth::id() ? (int) Auth::id() : null,
            (string) ($actor?->role ?? ''),
            'exam_deleted',
            'exam',
            (int) $id,
            'Exam deleted',
            'Deleted exam "' . $examTitle . '".',
            [
                'exam_type' => $examType,
                'program_id' => $examProgramId,
            ]
        );

        return response()->json(null, 204);
    }
}
