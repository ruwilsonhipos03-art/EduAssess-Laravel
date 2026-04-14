<?php

namespace Database\Seeders;

use App\Models\ExamSchedule;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class StudentScheduleSeeder extends Seeder
{
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
            $this->upsertStudentAndSchedule($payload, (int) $entranceExamId, (int) $entranceScheduleId);
        }

        foreach ($screeningStudents as $payload) {
            $this->upsertStudentAndSchedule($payload, (int) $screeningExamId, (int) $screeningScheduleId);
        }

        $this->command?->info('Done: 4 entrance and 5 screening students are seeded/scheduled.');
    }

    private function upsertStudentAndSchedule(array $payload, int $examId, int $scheduleId): void
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

        $student = Student::query()->where('user_id', $user->id)->first();
        if (!$student) {
            Student::query()->create([
                'user_id' => $user->id,
                'Student_Number' => Student::generateStudentNumber(),
                'program_id' => null,
            ]);
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
}
