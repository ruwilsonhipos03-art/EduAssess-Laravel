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
        Schema::create('answer_sheets', function (Blueprint $table) {
            $table->id();
            $table->string('qr_payload')->unique();
            $table->foreignId('exam_id')->constrained();
            $table->foreignId('user_id')->nullable()->constrained(); // Nullable for Step 9 scan
            $table->string('image_path')->nullable();
            $table->json('scanned_data')->nullable(); // OMR raw output
            $table->integer('total_score')->nullable();
            $table->enum('status', ['generated', 'scanned', 'checked'])->default('generated');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('answer_sheets');
    }
};
