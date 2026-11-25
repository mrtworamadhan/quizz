<?php

use Illuminate\Support\Facades\Route;
use App\Models\Participant;
use App\Models\Question;
use App\Models\Answer;

Route::livewire('/', 'pages::public.join-quiz')->name('join');

Route::livewire('/quiz', 'pages::public.quiz-room')->name('quiz.room');

Route::livewire('/monitor/{token}', 'pages::admin.quiz-monitor')->name('quiz.monitor');

Route::get('/test-load-submit', function () {
    // 1. Ambil acak untuk simulasi
    $participant = \App\Models\Participant::inRandomOrder()->first();
    $question = \App\Models\Question::inRandomOrder()->first();
    
    if (!$participant || !$question) return response('OK');

    // 2. Simulasi beban database
    \App\Models\Answer::updateOrCreate(
        ['participant_id' => $participant->id, 'question_id' => $question->id],
        [
            'submitted_answer' => 'A',
            'is_correct' => true,
            'time_taken_ms' => rand(1000, 5000), 
            'score_awarded' => 500,
        ]
    );

    $participant->increment('total_score', 500);

    return response()->json(['success' => true]);
});


