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
        Schema::table('answer_sheets', function (Blueprint $table) {
            $table->dropForeign(['exam_id']);
            $table->dropForeign(['user_id']);

            $table->foreign('exam_id')->references('id')->on('exams')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::table('student_exam_schedules', function (Blueprint $table) {
            $table->dropForeign(['exam_id']);
            $table->dropForeign(['exam_schedule_id']);

            $table->foreign('exam_id')->references('id')->on('exams')->onDelete('cascade');
            $table->foreign('exam_schedule_id')->references('id')->on('exam_schedules')->onDelete('cascade');
        });

        Schema::table('exam_results', function (Blueprint $table) {
            $table->dropForeign(['subject_id']);
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('cascade');
        });

        Schema::table('recommendations', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['program_id']);

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('program_id')->references('id')->on('programs')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('answer_sheets', function (Blueprint $table) {
            $table->dropForeign(['exam_id']);
            $table->dropForeign(['user_id']);

            $table->foreign('exam_id')->references('id')->on('exams');
            $table->foreign('user_id')->references('id')->on('users');
        });

        Schema::table('student_exam_schedules', function (Blueprint $table) {
            $table->dropForeign(['exam_id']);
            $table->dropForeign(['exam_schedule_id']);

            $table->foreign('exam_id')->references('id')->on('exams');
            $table->foreign('exam_schedule_id')->references('id')->on('exam_schedules');
        });

        Schema::table('exam_results', function (Blueprint $table) {
            $table->dropForeign(['subject_id']);
            $table->foreign('subject_id')->references('id')->on('subjects');
        });

        Schema::table('recommendations', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['program_id']);

            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('program_id')->references('id')->on('programs');
        });
    }
};

