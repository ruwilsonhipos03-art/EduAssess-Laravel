<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('answer_keys', 'exam_subject_id')) {
            // Avoid Doctrine dependency by using raw SQL
            DB::statement('ALTER TABLE answer_keys MODIFY exam_subject_id BIGINT UNSIGNED NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('answer_keys', 'exam_subject_id')) {
            DB::statement('ALTER TABLE answer_keys MODIFY exam_subject_id BIGINT UNSIGNED NOT NULL');
        }
    }
};
