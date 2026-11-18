<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::public.join-quiz')->name('join');

Route::livewire('/quiz', 'pages::public.quiz-room')->name('quiz.room');

Route::livewire('/monitor/{token}', 'pages::admin.quiz-monitor')->name('quiz.monitor');



