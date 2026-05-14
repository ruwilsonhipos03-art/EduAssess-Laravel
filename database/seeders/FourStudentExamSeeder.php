<?php

namespace Database\Seeders;

use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class FourStudentExamSeeder extends Seeder
{
    public function run(): void
    {
        $bsitProgram = DB::table('programs')
            ->where(function ($query) {
                $query->whereRaw('LOWER(TRIM(Program_Name)) = ?', ['bsit'])
                    ->orWhereRaw('LOWER(TRIM(Program_Name)) LIKE ?', ['%information technology%']);
            })
            ->orderBy('id')
            ->first(['id', 'Program_Name']);

        if (!$bsitProgram) {
            $this->command?->warn('FourStudentExamSeeder skipped: BSIT program not found.');
            return;
        }

        $entranceExamId = DB::table('exams')
            ->whereIn(DB::raw('LOWER(TRIM(Exam_Type))'), ['entrance', 'entrance exam'])
            ->orderByDesc('id')
            ->value('id');

        $screeningExamId = DB::table('exams')
            ->whereIn(DB::raw('LOWER(TRIM(Exam_Type))'), ['screening', 'screening exam'])
            ->where('program_id', (int) $bsitProgram->id)
            ->orderByDesc('id')
            ->value('id');

        if (!$entranceExamId || !$screeningExamId) {
            $this->command?->warn('FourStudentExamSeeder skipped: missing entrance or BSIT screening exam.');
            return;
        }

        $entranceScheduleId = $this->resolveScheduleId('entrance');
        $screeningScheduleId = $this->resolveScheduleId('screening');

        if (!$entranceScheduleId || !$screeningScheduleId) {
            $this->command?->warn('FourStudentExamSeeder skipped: missing entrance or screening schedule.');
            return;
        }

        $entranceStudents = [
            [
                'first_name' => 'Ruwilson',
                'last_name' => 'Hipo',
                'username' => 'entrance_ruwilson_01',
                'email' => 'ruwilsonhipo03@gmail.com',
            ],
            [
                'first_name' => 'Entrance',
                'last_name' => 'StudentTwo',
                'username' => 'entrance_student_02_fixed',
                'email' => 'entrance.student02.fixed@example.com',
            ],
        ];

        $screeningStudents = [
            [
                'first_name' => 'Ruwilson',
                'last_name' => 'Hipos',
                'username' => 'screening_ruwilson_01',
                'email' => 'ruwilsonhipos2003@gmail.com',
            ],
            [
                'first_name' => 'Screening',
                'last_name' => 'StudentTwo',
                'username' => 'screening_student_02_fixed',
                'email' => 'screening.student02.fixed@example.com',
            ],
        ];

        DB::transaction(function () use (
            $entranceStudents,
            $screeningStudents,
            $entranceExamId,
            $screeningExamId,
            $entranceScheduleId,
            $screeningScheduleId,
            $bsitProgram
        ) {
            foreach ($entranceStudents as $payload) {
                $user = $this->upsertStudentUser($payload, null);
                $this->upsertSchedule($user->id, (int) $entranceExamId, (int) $entranceScheduleId);
                $this->upsertProgramChoices($user->id, (int) $bsitProgram->id);
            }

            foreach ($screeningStudents as $payload) {
                $user = $this->upsertStudentUser($payload, (int) $bsitProgram->id);
                $this->upsertSchedule($user->id, (int) $screeningExamId, (int) $screeningScheduleId);
                $this->upsertProgramChoices($user->id, (int) $bsitProgram->id);
            }
        });

        $this->command?->info('FourStudentExamSeeder done: seeded 2 entrance + 2 screening students with BSIT first choice.');
    }

    private function resolveScheduleId(string $scheduleType): ?int
    {
        return DB::table('exam_schedules')
            ->where('schedule_type', $scheduleType)
            ->orderByDesc('id')
            ->value('id');
    }

    private function upsertStudentUser(array $payload, ?int $programId): User
    {
        $user = User::query()->updateOrCreate(
            ['username' => $payload['username']],
            [
                'first_name' => $payload['first_name'],
                'middle_name' => null,
                'last_name' => $payload['last_name'],
                'extension_name' => null,
                'email' => $payload['email'],
                'email_verified_at' => now(),
                'password' => Hash::make('Student123!'),
                'role' => 'student',
            ]
        );

        $studentNumber = Student::query()
            ->where('user_id', $user->id)
            ->value('Student_Number');

        Student::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'Student_Number' => $studentNumber ?: Student::generateStudentNumber(),
                'program_id' => $programId,
            ]
        );

        return $user;
    }

    private function upsertSchedule(int $userId, int $examId, int $scheduleId): void
    {
        DB::table('student_exam_schedules')->updateOrInsert(
            [
                'user_id' => $userId,
                'exam_id' => $examId,
                'exam_schedule_id' => $scheduleId,
            ],
            [
                'status' => 'scheduled',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function upsertProgramChoices(int $userId, int $bsitProgramId): void
    {
        $otherPrograms = DB::table('programs')
            ->where('id', '!=', $bsitProgramId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->shuffle()
            ->values();

        $choices = collect([$bsitProgramId])
            ->merge($otherPrograms->take(2))
            ->take(3)
            ->values();

        if ($choices->count() < 3) {
            $allPrograms = DB::table('programs')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values();

            $choices = $this->padChoices($choices, $allPrograms)->take(3)->values();
        }

        DB::table('recommendations')
            ->where('user_id', $userId)
            ->where('type', 'student_choice')
            ->delete();

        foreach ($choices as $index => $programId) {
            DB::table('recommendations')->insert([
                'user_id' => $userId,
                'program_id' => (int) $programId,
                'rank' => $index + 1,
                'type' => 'student_choice',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function padChoices(Collection $choices, Collection $pool): Collection
    {
        $result = $choices->values();
        foreach ($pool as $programId) {
            if ($result->contains($programId)) {
                continue;
            }
            $result->push((int) $programId);
            if ($result->count() >= 3) {
                break;
            }
        }
        return $result;
    }
}


