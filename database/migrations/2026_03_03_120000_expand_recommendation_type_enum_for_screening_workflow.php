<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('recommendations')) {
            return;
        }

        DB::statement(
            "ALTER TABLE `recommendations` MODIFY `type` ENUM('system', 'student_choice', 'final_program', 'continue_screening') NOT NULL"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('recommendations')) {
            return;
        }

        DB::table('recommendations')
            ->whereIn('type', ['final_program', 'continue_screening'])
            ->update(['type' => 'student_choice']);

        DB::statement(
            "ALTER TABLE `recommendations` MODIFY `type` ENUM('system', 'student_choice') NOT NULL"
        );
    }
};
