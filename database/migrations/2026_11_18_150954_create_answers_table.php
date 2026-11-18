<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('submitted_answer')->nullable(); // Boleh kosong jika waktu habis
            $table->boolean('is_correct')->default(false);
            $table->integer('time_taken_ms')->nullable(); // Waktu pengerjaan (milidetik)
            $table->integer('score_awarded')->default(0); // Poin di satu soal ini
            $table->timestamps();
            
            // Aturan penting: 1 peserta hanya bisa menjawab 1 kali untuk soal yang sama
            $table->unique(['participant_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('answers');
    }
};
