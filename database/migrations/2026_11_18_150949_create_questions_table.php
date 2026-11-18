<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->integer('order')->unique(); // Urutan tampil (1, 2, 3...)
            $table->text('question_text');
            $table->json('options'); // Menyimpan A, B, C, D
            $table->string('correct_answer'); // Kunci jawaban yang benar
            $table->integer('time_limit_seconds')->default(20); // Durasi per soal
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
