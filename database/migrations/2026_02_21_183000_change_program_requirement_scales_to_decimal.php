<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE program_requirements MODIFY math_scale DECIMAL(4,2) NOT NULL DEFAULT 1.00');
        DB::statement('ALTER TABLE program_requirements MODIFY english_scale DECIMAL(4,2) NOT NULL DEFAULT 1.00');
        DB::statement('ALTER TABLE program_requirements MODIFY science_scale DECIMAL(4,2) NOT NULL DEFAULT 1.00');
        DB::statement('ALTER TABLE program_requirements MODIFY social_science_scale DECIMAL(4,2) NOT NULL DEFAULT 1.00');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE program_requirements MODIFY math_scale TINYINT UNSIGNED NOT NULL DEFAULT 1');
        DB::statement('ALTER TABLE program_requirements MODIFY english_scale TINYINT UNSIGNED NOT NULL DEFAULT 1');
        DB::statement('ALTER TABLE program_requirements MODIFY science_scale TINYINT UNSIGNED NOT NULL DEFAULT 1');
        DB::statement('ALTER TABLE program_requirements MODIFY social_science_scale TINYINT UNSIGNED NOT NULL DEFAULT 1');
    }
};
