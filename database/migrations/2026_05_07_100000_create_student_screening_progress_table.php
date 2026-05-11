<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_screening_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('current_rank')->default(1);
            $table->timestamps();

            $table->unique('user_id');
            $table->index('current_rank');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_screening_progress');
    }
};
