<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendStudentScheduleEmail;
use App\Models\Exam;
use App\Models\ExamSchedule;
use App\Models\Program;
use App\Models\Recommendation;
use App\Models\Student;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\ExamScheduleCounterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class StudentController extends Controller
{
    private const TYPE_STUDENT_CHOICE = 'student_choice';

    private function generateUsernameFromEmail(string $email): string
    {
        $base = strtolower((string) preg_replace('/[^a-z0-9]+/', '', strstr($email, '@', true) ?: 'student'));
        $base = $base !== '' ? substr($base, 0, 20) : 'student';
        $candidate = $base;
        $suffix = 1;

        while (User::where('username', $candidate)->exists()) {
            $candidate = substr($base, 0, max(1, 20 - strlen((string) $suffix))) . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function syncProgramChoices(int $userId, array $validated): void
    {
        Recommendation::query()
            ->where('user_id', $userId)
            ->where('type', self::TYPE_STUDENT_CHOICE)
            ->delete();

        $choices = [
            1 => isset($validated['program_id']) ? (int) $validated['program_id'] : 0,
            2 => isset($validated['program_choice_2']) ? (int) $validated['program_choice_2'] : 0,
            3 => isset($validated['program_choice_3']) ? (int) $validated['program_choice_3'] : 0,
        ];

        $seen = [];
        foreach ($choices as $rank => $programId) {
            if ($programId <= 0 || in_array($programId, $seen, true)) {
                continue;
            }

            Recommendation::create([
                'user_id' => $userId,
                'program_id' => $programId,
                'rank' => $rank,
                'type' => self::TYPE_STUDENT_CHOICE,
            ]);

            $seen[] = $programId;
        }
    }

    private function validateDistinctProgramChoices(array $validated): void
    {
        $choices = collect([
            $validated['program_id'] ?? null,
            $validated['program_choice_2'] ?? null,
            $validated['program_choice_3'] ?? null,
        ])
            ->filter(fn ($value) => !is_null($value) && $value !== '')
            ->map(fn ($value) => (int) $value)
            ->values();

        if ($choices->count() !== $choices->unique()->count()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'program_id' => 'Program choices must be different from each other.',
            ]);
        }
    }

    public function store(Request $request)
    {
        $adminUser = $request->user();
        if (!$adminUser || !str_contains((string) $adminUser->role, 'admin')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only administrators can create student accounts.',
            ], 403);
        }

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'middle_initial' => 'nullable|string|max:2',
            'last_name' => 'required|string|max:255',
            'extension_name' => 'nullable|string|max:10',
            'email' => 'required|string|email|max:255|unique:users,email',
            'program_id' => 'required|integer|exists:programs,id',
            'program_choice_2' => 'nullable|integer|exists:programs,id',
            'program_choice_3' => 'nullable|integer|exists:programs,id',
        ]);
        $this->validateDistinctProgramChoices($validated);

        try {
            $payload = DB::transaction(function () use ($validated, $adminUser) {
                $now = now();
                $availableSchedule = ExamSchedule::query()
                    ->where('schedule_type', 'entrance')
                    ->where(function ($query) use ($now) {
                        $query->where('date', '>', $now->toDateString())
                            ->orWhere(function ($inner) use ($now) {
                                $inner->where('date', '=', $now->toDateString())
                                    ->where('time', '>=', $now->format('H:i:s'));
                            });
                    })
                    ->whereRaw('(
                        SELECT COUNT(DISTINCT ses.user_id)
                        FROM student_exam_schedules ses
                        WHERE ses.exam_schedule_id = exam_schedules.id
                    ) < capacity')
                    ->orderBy('date', 'asc')
                    ->orderBy('time', 'asc')
                    ->lockForUpdate()
                    ->first();

                if (!$availableSchedule) {
                    throw new \Exception('All exam slots are currently full. Please add more schedules first.');
                }

                $entranceExam = Exam::query()
                    ->whereIn(DB::raw('LOWER(Exam_Type)'), ['entrance', 'entrance exam'])
                    ->orderBy('created_at', 'desc')
                    ->first();

                if (!$entranceExam) {
                    throw new \Exception('No entrance exam is configured yet.');
                }

                $program = Program::query()->findOrFail((int) $validated['program_id']);

                $generatedUsername = $this->generateUsernameFromEmail((string) $validated['email']);
                $temporaryPassword = $generatedUsername;

                $user = User::create([
                    'first_name' => $validated['first_name'],
                    'middle_initial' => $validated['middle_initial'] ?? null,
                    'last_name' => $validated['last_name'],
                    'extension_name' => $validated['extension_name'] ?? null,
                    'email' => $validated['email'],
                    'username' => $generatedUsername,
                    'password' => Hash::make($temporaryPassword),
                    'role' => 'student',
                ]);

                $student = $user->studentProfile()->create([
                    'Student_Number' => Student::generateStudentNumber(),
                    'program_id' => (int) $validated['program_id'],
                ]);

                $this->syncProgramChoices((int) $user->id, $validated);

                DB::table('student_exam_schedules')->insert([
                    'user_id' => $user->id,
                    'exam_id' => $entranceExam->id,
                    'exam_schedule_id' => $availableSchedule->id,
                    'status' => 'scheduled',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                app(ExamScheduleCounterService::class)->refreshScheduleCount((int) $availableSchedule->id);

                ActivityLogger::log(
                    (int) $adminUser->id,
                    'admin',
                    'student_created',
                    'user',
                    (int) $user->id,
                    'Admin created student account',
                    trim($user->last_name . ', ' . $user->first_name) . ' was added by admin.',
                    [
                        'student_number' => (string) $student->Student_Number,
                        'email' => (string) $user->email,
                        'username' => (string) $generatedUsername,
                        'program_name' => (string) ($program->Program_Name ?? ''),
                    ]
                );

                return [
                    'user_id' => (int) $user->id,
                    'student_number' => (string) $student->Student_Number,
                    'email' => (string) $user->email,
                    'first_name' => (string) $user->first_name,
                    'full_name' => trim(implode(' ', array_filter([
                        (string) $user->first_name,
                        (string) ($user->middle_initial ?? ''),
                        (string) $user->last_name,
                        (string) ($user->extension_name ?? ''),
                    ]))),
                    'program_name' => (string) ($program->Program_Name ?? ''),
                    'schedule' => [
                        'exam_title' => (string) $entranceExam->Exam_Title,
                        'exam_type' => (string) $entranceExam->Exam_Type,
                        'date' => \Carbon\Carbon::parse($availableSchedule->date)->format('F j, Y'),
                        'time' => (string) $availableSchedule->time,
                        'location' => (string) $availableSchedule->location,
                    ],
                ];
            });

            SendStudentScheduleEmail::dispatch($payload);

            return response()->json([
                'status' => 'success',
                'message' => 'Student account created successfully. Schedule email has been queued.',
                'data' => [
                    'user_id' => $payload['user_id'],
                    'student_number' => $payload['student_number'],
                    'schedule' => $payload['schedule'],
                    'email_queued' => true,
                ],
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Student account creation failed.', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function update(Request $request, Student $student)
    {
        $adminUser = $request->user();
        if (!$adminUser || !str_contains((string) $adminUser->role, 'admin')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only administrators can update student accounts.',
            ], 403);
        }

        $user = $student->user()->firstOrFail();

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'middle_initial' => 'nullable|string|max:2',
            'last_name' => 'required|string|max:255',
            'extension_name' => 'nullable|string|max:10',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'program_id' => 'required|integer|exists:programs,id',
            'program_choice_2' => 'nullable|integer|exists:programs,id',
            'program_choice_3' => 'nullable|integer|exists:programs,id',
        ]);
        $this->validateDistinctProgramChoices($validated);

        DB::transaction(function () use ($validated, $student, $user, $adminUser) {
            $program = Program::query()->findOrFail((int) $validated['program_id']);

            $user->update([
                'first_name' => $validated['first_name'],
                'middle_initial' => $validated['middle_initial'] ?? null,
                'last_name' => $validated['last_name'],
                'extension_name' => $validated['extension_name'] ?? null,
                'email' => $validated['email'],
            ]);

            $student->update([
                'program_id' => (int) $validated['program_id'],
            ]);

            $this->syncProgramChoices((int) $user->id, $validated);

            ActivityLogger::log(
                (int) $adminUser->id,
                'admin',
                'student_updated',
                'student',
                (int) $student->id,
                'Admin updated student account',
                trim($user->last_name . ', ' . $user->first_name) . ' student details were updated.',
                [
                    'student_number' => (string) $student->Student_Number,
                    'email' => (string) $user->email,
                    'program_name' => (string) ($program->Program_Name ?? ''),
                ]
            );
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Student account updated successfully.',
        ]);
    }

    public function destroy(Request $request, Student $student)
    {
        $adminUser = $request->user();
        if (!$adminUser || !str_contains((string) $adminUser->role, 'admin')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only administrators can delete student accounts.',
            ], 403);
        }

        $user = $student->user;

        ActivityLogger::log(
            (int) $adminUser->id,
            'admin',
            'student_deleted',
            'student',
            (int) $student->id,
            'Admin deleted student account',
            trim(($user?->last_name ?? '') . ', ' . ($user?->first_name ?? '')) . ' student account was deleted.',
            [
                'student_number' => (string) ($student->Student_Number ?? ''),
                'email' => (string) ($user?->email ?? ''),
            ]
        );

        if ($user) {
            $user->delete();
        } else {
            $student->delete();
        }

        return response()->json(null, 204);
    }
}
