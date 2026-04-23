<?php

namespace Database\Seeders;

use App\Models\ExamSchedule;
use App\Models\Recommendation;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class StudentScheduleSeeder extends Seeder
{
    private const RECOMMENDATION_TYPE_STUDENT_CHOICE = 'student_choice';

    public function run(): void
    {
        $entranceExamId = DB::table('exams')
            ->whereIn(DB::raw('LOWER(TRIM(Exam_Type))'), ['entrance', 'entrance exam'])
            ->value('id');

        $screeningExamId = DB::table('exams')
            ->whereIn(DB::raw('LOWER(TRIM(Exam_Type))'), ['screening', 'screening exam'])
            ->value('id');

        if (!$entranceExamId || !$screeningExamId) {
            $this->command?->warn('Seeder stopped: missing entrance or screening exam in `exams` table.');
            return;
        }

        $programChoices = $this->resolveProgramChoices((int) $screeningExamId);
        if (count($programChoices) === 0) {
            $this->command?->warn('No programs found. Students will be seeded without program choices.');
        }

        $entranceScheduleId = DB::table('exam_schedules')
            ->where('schedule_type', 'entrance')
            ->value('id');

        if (!$entranceScheduleId) {
            $entranceScheduleId = ExamSchedule::query()->create([
                'date' => now()->toDateString(),
                'time' => '09:00:00',
                'location' => 'Main Campus - Entrance Hall',
                'schedule_name' => 'Auto Entrance Schedule',
                'capacity' => 200,
                'schedule_type' => 'entrance',
            ])->id;
        }

        $screeningScheduleId = DB::table('exam_schedules')
            ->where('schedule_type', 'screening')
            ->value('id');

        if (!$screeningScheduleId) {
            $screeningScheduleId = ExamSchedule::query()->create([
                'date' => now()->toDateString(),
                'time' => '13:00:00',
                'location' => 'Main Campus - Screening Room',
                'schedule_name' => 'Auto Screening Schedule',
                'capacity' => 200,
                'schedule_type' => 'screening',
            ])->id;
        }

        $entranceStudents = [
            ['first_name' => 'Mark', 'last_name' => 'Lopez', 'username' => 'entrance_student_01', 'email' => 'entrance.student01@example.com'],
            ['first_name' => 'Liza', 'last_name' => 'Dela Cruz', 'username' => 'entrance_student_02', 'email' => 'entrance.student02@example.com'],
            ['first_name' => 'Noel', 'last_name' => 'Ramirez', 'username' => 'entrance_student_03', 'email' => 'entrance.student03@example.com'],
            ['first_name' => 'Ana', 'last_name' => 'Garcia', 'username' => 'entrance_student_04', 'email' => 'entrance.student04@example.com'],
        ];

        $screeningStudents = [
            ['first_name' => 'Jessa', 'last_name' => 'Santos', 'username' => 'screening_student_01', 'email' => 'screening.student01@example.com'],
            ['first_name' => 'Ivan', 'last_name' => 'Mendoza', 'username' => 'screening_student_02', 'email' => 'screening.student02@example.com'],
            ['first_name' => 'Paolo', 'last_name' => 'Reyes', 'username' => 'screening_student_03', 'email' => 'screening.student03@example.com'],
            ['first_name' => 'Ruwilson', 'last_name' => 'Hipos', 'username' => 'screening_student_04', 'email' => 'ruwilsonhipos2003@gmail.com'],
            ['first_name' => 'Mica', 'last_name' => 'Torres', 'username' => 'screening_student_05', 'email' => 'screening.student05@example.com'],
        ];

        foreach ($entranceStudents as $payload) {
            $this->upsertStudentAndSchedule($payload, (int) $entranceExamId, (int) $entranceScheduleId, $programChoices);
        }

        foreach ($screeningStudents as $payload) {
            $this->upsertStudentAndSchedule($payload, (int) $screeningExamId, (int) $screeningScheduleId, $programChoices);
        }

        $this->command?->info('Done: 4 entrance and 5 screening students are seeded/scheduled.');
    }

    private function upsertStudentAndSchedule(array $payload, int $examId, int $scheduleId, array $programChoices): void
    {
        $user = User::query()->updateOrCreate(
            ['username' => $payload['username']],
            [
                'first_name' => $payload['first_name'],
                'middle_initial' => null,
                'last_name' => $payload['last_name'],
                'extension_name' => null,
                'email' => $payload['email'],
                'password' => Hash::make('Student123!'),
                'role' => 'student',
            ]
        );

        $topProgramId = $programChoices[0] ?? null;
        $student = Student::query()->where('user_id', $user->id)->first();
        if (!$student) {
            $student = Student::query()->create([
                'user_id' => $user->id,
                'Student_Number' => Student::generateStudentNumber(),
                'program_id' => $topProgramId,
            ]);
        } elseif ($topProgramId && (int) $student->program_id !== (int) $topProgramId) {
            $student->update(['program_id' => (int) $topProgramId]);
        }

        if (!empty($programChoices)) {
            $this->syncStudentProgramChoices((int) $user->id, $programChoices);
        }

        DB::table('student_exam_schedules')->updateOrInsert(
            [
                'user_id' => $user->id,
                'exam_id' => $examId,
                'exam_schedule_id' => $scheduleId,
            ],
            [
                'status' => 'scheduled',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function resolveProgramChoices(int $screeningExamId): array
    {
        $screeningProgramId = (int) (DB::table('exams')->where('id', $screeningExamId)->value('program_id') ?? 0);

        $otherProgramIds = DB::table('programs')
            ->when($screeningProgramId > 0, fn ($query) => $query->where('id', '!=', $screeningProgramId))
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $choices = [];
        if ($screeningProgramId > 0) {
            $choices[] = $screeningProgramId;
        }

        foreach ($otherProgramIds as $programId) {
            if (!in_array($programId, $choices, true)) {
                $choices[] = $programId;
            }

            if (count($choices) >= 3) {
                break;
            }
        }

        return $choices;
    }

    private function syncStudentProgramChoices(int $userId, array $programChoices): void
    {
        $choices = collect($programChoices)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->take(3);

        if ($choices->isEmpty()) {
            return;
        }

        Recommendation::query()
            ->where('user_id', $userId)
            ->where('type', self::RECOMMENDATION_TYPE_STUDENT_CHOICE)
            ->delete();

        $now = now();
        $rows = $choices->map(fn (int $programId, int $index) => [
            'user_id' => $userId,
            'program_id' => $programId,
            'rank' => $index + 1,
            'type' => self::RECOMMENDATION_TYPE_STUDENT_CHOICE,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        DB::table('recommendations')->insert($rows);
    }
}
