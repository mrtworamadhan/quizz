<?php

use Illuminate\Support\Facades\Route;
use App\Models\Participant;
use App\Models\Question;
use App\Models\Answer;

Route::livewire('/', 'pages::public.join-quiz')->name('join');

Route::livewire('/quiz', 'pages::public.quiz-room')->name('quiz.room');

Route::livewire('/monitor/{token}', 'pages::admin.quiz-monitor')->name('quiz.monitor');

Route::post('/test-load-submit', function () {
    // 1. Ambil 1 peserta acak & 1 soal acak untuk simulasi
    $participant = Participant::inRandomOrder()->first();
    $question = Question::inRandomOrder()->first();
    
    if (!$participant || !$question) return response('OK');

    // 2. Simulasi beban insert/update database persis seperti di quiz-room
    Answer::updateOrCreate(
        ['participant_id' => $participant->id, 'question_id' => $question->id],
        [
            'submitted_answer' => 'A',
            'is_correct' => true,
            'time_taken_ms' => rand(1000, 5000), // Random waktu jawab
            'score_awarded' => 500,
        ]
    );

    // Simulasi beban update skor peserta
    $participant->increment('total_score', 500);

    return response()->json(['success' => true]);
});


