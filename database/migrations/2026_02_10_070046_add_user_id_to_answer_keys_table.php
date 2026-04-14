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
        Schema::table('answer_keys', function (Blueprint $table) {
            if (!Schema::hasColumn('answer_keys', 'user_id')) {
                $table->foreignId('user_id')->after('id')->constrained()->onDelete('cascade');
            }
            if (!Schema::hasColumn('answer_keys', 'exam_id')) {
                $table->foreignId('exam_id')->after('user_id')->constrained()->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('answer_keys', function (Blueprint $table) {
            if (Schema::hasColumn('answer_keys', 'exam_id')) {
                $table->dropConstrainedForeignId('exam_id');
            }
            if (Schema::hasColumn('answer_keys', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }
        });
    }
};
