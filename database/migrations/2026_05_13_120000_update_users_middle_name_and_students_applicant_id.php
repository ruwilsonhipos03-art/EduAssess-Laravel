<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'middle_initial') && !Schema::hasColumn('users', 'middle_name')) {
            DB::statement('ALTER TABLE `users` CHANGE `middle_initial` `middle_name` VARCHAR(255) NULL');
        }

        if (!Schema::hasColumn('students', 'applicant_id')) {
            Schema::table('students', function (Blueprint $table) {
                $table->string('applicant_id', 20)->nullable()->after('user_id');
            });
            DB::statement('ALTER TABLE `students` ADD UNIQUE `students_applicant_id_unique` (`applicant_id`)');
        }

        if (Schema::hasColumn('students', 'Student_Number')) {
            DB::statement('ALTER TABLE `students` MODIFY `Student_Number` VARCHAR(255) NULL');
        }

        $students = DB::table('students')->select('id')->orderBy('id')->get();
        $counter = 1;
        foreach ($students as $student) {
            DB::table('students')->where('id', $student->id)->update([
                'applicant_id' => str_pad((string) $counter, 6, '0', STR_PAD_LEFT),
            ]);
            $counter++;
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'middle_name') && !Schema::hasColumn('users', 'middle_initial')) {
            DB::statement('ALTER TABLE `users` CHANGE `middle_name` `middle_initial` VARCHAR(255) NULL');
        }

        if (Schema::hasColumn('students', 'applicant_id')) {
            DB::statement('ALTER TABLE `students` DROP INDEX `students_applicant_id_unique`');
            Schema::table('students', function (Blueprint $table) {
                $table->dropColumn('applicant_id');
            });
        }

        if (Schema::hasColumn('students', 'Student_Number')) {
            DB::statement('ALTER TABLE `students` MODIFY `Student_Number` VARCHAR(255) NOT NULL');
        }
    }
};
