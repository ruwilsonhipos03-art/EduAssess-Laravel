<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_schedules', function (Blueprint $table) {
            $table->string('schedule_type', 30)->default('entrance')->after('capacity');
        });

        DB::table('exam_schedules')
            ->whereNull('schedule_type')
            ->update(['schedule_type' => 'entrance']);
    }

    public function down(): void
    {
        Schema::table('exam_schedules', function (Blueprint $table) {
            $table->dropColumn('schedule_type');
        });
    }
};
