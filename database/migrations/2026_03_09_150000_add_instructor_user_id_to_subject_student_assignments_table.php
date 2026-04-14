<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subject_student_assignments', function (Blueprint $table) {
            $table->foreignId('instructor_user_id')
                ->nullable()
                ->after('student_user_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subject_student_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('instructor_user_id');
        });
    }
};

