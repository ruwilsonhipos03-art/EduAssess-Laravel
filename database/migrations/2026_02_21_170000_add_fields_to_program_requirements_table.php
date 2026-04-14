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
        Schema::table('program_requirements', function (Blueprint $table) {
            $table->foreignId('program_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('total_score')->default(0)->after('program_id');
            $table->unsignedTinyInteger('math_scale')->default(1)->after('total_score');
            $table->unsignedTinyInteger('english_scale')->default(1)->after('math_scale');
            $table->unsignedTinyInteger('science_scale')->default(1)->after('english_scale');
            $table->unsignedTinyInteger('social_science_scale')->default(1)->after('science_scale');

            $table->unique('program_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('program_requirements', function (Blueprint $table) {
            $table->dropUnique(['program_id']);
            $table->dropConstrainedForeignId('program_id');
            $table->dropColumn([
                'total_score',
                'math_scale',
                'english_scale',
                'science_scale',
                'social_science_scale',
            ]);
        });
    }
};
