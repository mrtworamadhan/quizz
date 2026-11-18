<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Menambahkan Index di tabel Participants
        Schema::table('participants', function (Blueprint $table) {
            // Index untuk mempercepat query cek session peserta
            $table->index('uuid'); 
            
            // Index untuk mempercepat kalkulasi Leaderboard di layar Monitor
            $table->index('total_score'); 
        });

        // 2. Menambahkan Index di tabel Answers
        Schema::table('answers', function (Blueprint $table) {
            // Mempercepat pencarian: "Jawaban apa yang sudah diisi oleh peserta X di soal Y?"
            $table->index(['participant_id', 'question_id'], 'answers_participant_question_idx');
        });
    }

    public function down(): void
    {
        // Menghapus Index jika migrasi di-rollback
        Schema::table('participants', function (Blueprint $table) {
            $table->dropIndex(['uuid']);
            $table->dropIndex(['total_score']);
        });

        Schema::table('answers', function (Blueprint $table) {
            $table->dropIndex('answers_participant_question_idx');
        });
    }
};