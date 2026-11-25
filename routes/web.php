<?php

use Illuminate\Support\Facades\Route;
use App\Models\Participant;
use App\Models\Question;
use App\Models\Answer;

Route::livewire('/', 'pages::public.join-quiz')->name('join');

Route::livewire('/quiz', 'pages::public.quiz-room')->name('quiz.room');

Route::livewire('/monitor/{token}', 'pages::admin.quiz-monitor')->name('quiz.monitor');



