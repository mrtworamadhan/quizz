<?php

use Livewire\Component;
use App\Models\Participant;
use App\Models\QuizState;
use App\Models\Question;
use App\Models\Answer;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

new class extends Component {
    public $participant;
    public $quizStatus = 'waiting';
    public $currentQuestion = null;
    public $questionStartTime;
    public $feedbackStatus = null;
    public $feedbackScore = 0;
    public $timeLeft = 0;
    public $selectedOption = null;

    public function mount()
    {
        $uuid = Session::get('participant_uuid');
        if (!$uuid) return redirect()->route('join');

        $this->participant = Participant::where('uuid', $uuid)->first();
        if (!$this->participant) {
            Session::forget('participant_uuid');
            return redirect()->route('join');
        }

        if ($this->participant->finished_at) {
            $this->quizStatus = 'finished';
            return;
        }

        $this->checkQuizStatus();
    }

    public function checkQuizStatus()
    {
        if ($this->quizStatus !== 'waiting') return;

        $this->quizStatus = Cache::remember('quiz_state_status', 3, function () {
            $state = QuizState::find(1);
            return $state ? $state->status : 'waiting';
        });

        if ($this->quizStatus === 'started' && !$this->currentQuestion) {
            $this->loadNextQuestion();
        }
    }

    public function loadNextQuestion()
    {
        $this->feedbackStatus = null;
        $this->feedbackScore = 0;
        $this->selectedOption = null;
        $this->timeLeft = 0;

        $completedAnswersCount = $this->participant->answers()->whereNotNull('time_taken_ms')->count();
        $nextOrder = $completedAnswersCount + 1;

        $this->currentQuestion = Cache::remember('question_order_' . $nextOrder, 3600, function () use ($nextOrder) {
            return Question::where('order', $nextOrder)->first();
        });

        if (!$this->currentQuestion) {
            $this->participant->update(['finished_at' => now()]);
            $this->quizStatus = 'finished';
            return;
        }

        $draftAnswer = Answer::where('participant_id', $this->participant->id)
            ->where('question_id', $this->currentQuestion->id)
            ->first();

        if (!$draftAnswer) {
            $draftAnswer = Answer::create([
                'participant_id' => $this->participant->id,
                'question_id' => $this->currentQuestion->id,
                'started_at' => now(),
            ]);
            $this->timeLeft = $this->currentQuestion->time_limit_seconds;
            $this->questionStartTime = microtime(true);
        } else {
            $timePassedSeconds = $draftAnswer->started_at->diffInSeconds(now());
            $calculatedTimeLeft = $this->currentQuestion->time_limit_seconds - $timePassedSeconds;

            if ($calculatedTimeLeft <= 0) {
                $this->questionStartTime = microtime(true) - ($this->currentQuestion->time_limit_seconds);
                $this->submitAnswer(true);
                return;
            } else {
                $this->timeLeft = $calculatedTimeLeft;
                $this->questionStartTime = microtime(true) - $timePassedSeconds;
            }
        }
    }

    public function submitAnswer($isForced = false)
    {
        if (!$this->currentQuestion) return;

        $draftAnswer = Answer::where('participant_id', $this->participant->id)
            ->where('question_id', $this->currentQuestion->id)
            ->first();

        if (!$draftAnswer || $draftAnswer->time_taken_ms !== null) return;

        $timeNow = microtime(true);
        $timeTakenMs = round(($timeNow - $this->questionStartTime) * 1000);

        $isCorrect = false;
        $score = 0;
        $maxTimeMs = $this->currentQuestion->time_limit_seconds * 1000;

        if ($timeTakenMs > $maxTimeMs) {
            $timeTakenMs = $maxTimeMs;
        }

        if (!$isForced && $this->selectedOption !== null && $this->selectedOption == $this->currentQuestion->correct_answer) {
            $isCorrect = true;
            $speedBonus = 0;
            if ($timeTakenMs < $maxTimeMs) {
                $timeRemaining = $maxTimeMs - $timeTakenMs;
                $speedBonus = ($timeRemaining / $maxTimeMs) * 500;
            }
            $score = 500 + round($speedBonus);
        }

        $draftAnswer->update([
            'submitted_answer' => $this->selectedOption,
            'is_correct' => $isCorrect,
            'time_taken_ms' => $timeTakenMs,
            'score_awarded' => $score,
        ]);

        $this->participant->increment('total_score', $score);
        $this->feedbackScore = $score;

        if ($isForced && $this->selectedOption === null) {
            $this->feedbackStatus = 'timeout';
        } else {
            $this->feedbackStatus = $isCorrect ? 'benar' : 'salah';
        }
    }
};
?>

<div class="min-h-screen bg-gray-100 p-4 font-sans">
    @if($quizStatus === 'waiting')
        <div wire:poll.5s.visible="checkQuizStatus" class="flex flex-col items-center justify-center min-h-[80vh]">
            <div class="bg-white p-10 rounded-2xl shadow-xl text-center max-w-lg w-full border-t-8 border-orange-500">
                <h1 class="text-3xl font-black text-gray-800 mb-2">generaSi Sigap</h1>
                <p class="text-lg text-orange-600 font-bold mb-8 italic">"Bawaslu Siap, Generasi Sigap"</p>
                <div class="animate-pulse flex space-x-4 justify-center items-center mb-6">
                    <div class="h-4 w-4 bg-orange-500 rounded-full"></div>
                    <div class="h-4 w-4 bg-gray-600 rounded-full"></div>
                    <div class="h-4 w-4 bg-orange-500 rounded-full"></div>
                </div>
                <h2 class="text-xl font-bold text-gray-700">Halo, {{ $participant->name }}!</h2>
                <p class="text-gray-500 mt-2">Mohon bersiap. Kuis akan segera dimulai oleh panitia...</p>
            </div>
        </div>
    @elseif($quizStatus === 'started' && $currentQuestion)
        <div wire:key="question-{{ $currentQuestion->id }}" class="max-w-3xl mx-auto mt-4 md:mt-10 mb-20">
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden border-t-8 border-orange-500">
                <div class="bg-gray-50 p-6 border-b flex flex-col md:flex-row justify-between items-center gap-4">
                    <span class="text-lg font-black text-gray-700 bg-gray-200 px-4 py-2 rounded-lg">
                        Soal No. {{ $currentQuestion->order }}
                    </span>
                    <div x-data="{ seconds: {{ $timeLeft }}, interval: null }" x-init="
                        clearInterval(interval);
                        interval = setInterval(() => {
                            if (seconds > 0) {
                                seconds--;
                            } else {
                                clearInterval(interval);
                                $wire.submitAnswer(true);
                            }
                        }, 1000);
                    " class="flex items-center gap-3 bg-red-100 border-2 border-red-200 text-red-600 px-6 py-2 rounded-full shadow-inner">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="font-black text-3xl tabular-nums" x-text="seconds"></span>
                        <span class="text-sm font-bold uppercase mt-1">Detik</span>
                    </div>
                </div>
                <div class="p-6 md:p-8" x-data="{ localSelected: null }">
                    <h2 class="text-xl md:text-2xl font-bold text-gray-800 mb-8 leading-relaxed">
                        {{ $currentQuestion->question_text }}
                    </h2>

                    <div class="grid grid-cols-1 gap-4">
                        @foreach($currentQuestion->options as $key => $optionText)
                            <button @click="localSelected = '{{ $key }}'" 
                                    class="w-full text-left p-4 md:p-5 rounded-xl border-2 transition-all duration-200 group flex items-center"
                                    :class="localSelected === '{{ $key }}' ? 'border-orange-500 bg-orange-50 ring-2 ring-orange-200' : 'border-gray-200 hover:border-orange-300 hover:bg-gray-50'">
                                
                                <span class="inline-block w-8 h-8 rounded-full text-center font-bold leading-8 mr-4 transition-colors"
                                    :class="localSelected === '{{ $key }}' ? 'bg-orange-500 text-white' : 'bg-gray-200 text-gray-600 group-hover:bg-orange-200'">
                                    {{ $key }}
                                </span>
                                
                                <span class="font-semibold text-lg"
                                    :class="localSelected === '{{ $key }}' ? 'text-orange-800' : 'text-gray-700'">
                                    {{ $optionText }}
                                </span>
                            </button>
                        @endforeach
                    </div>

                    <div class="mt-8 pt-6 border-t border-gray-100 text-right">
                        <button x-on:click="$wire.set('selectedOption', localSelected); $wire.submitAnswer()" 
                                x-bind:disabled="!localSelected" 
                                class="w-full md:w-auto font-black py-4 px-10 rounded-xl shadow-lg transition-all duration-200 transform"
                                :class="localSelected ? 'bg-orange-500 hover:bg-orange-600 text-white hover:-translate-y-1 hover:shadow-orange-500/30 cursor-pointer' : 'bg-gray-300 text-gray-500 cursor-not-allowed'">
                            
                            <span wire:loading.remove>SIMPAN & LANJUT ➔</span>
                            <span wire:loading>Menyimpan...</span>
                        </button>
                    </div>
                </div>
            </div>
            @if($feedbackStatus)
                <div x-data="{}" x-init="setTimeout(() => $wire.loadNextQuestion(), 1800)" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-80 backdrop-blur-sm transition-opacity">
                    @if($feedbackStatus === 'benar')
                        <div class="bg-green-500 rounded-3xl p-10 text-center shadow-2xl transform scale-105 animate-bounce">
                            <div class="text-white text-7xl mb-4">🎉</div>
                            <h2 class="text-4xl font-black text-white mb-2 uppercase tracking-wider">Tepat Sekali!</h2>
                            <p class="text-green-100 text-xl font-bold">Kamu mendapatkan <br> <span class="text-4xl text-yellow-300 font-black">+{{ $feedbackScore }}</span> Poin!</p>
                        </div>
                    @elseif($feedbackStatus === 'salah')
                        <div class="bg-red-500 rounded-3xl p-10 text-center shadow-2xl transform scale-105">
                            <div class="text-white text-7xl mb-4">😢</div>
                            <h2 class="text-4xl font-black text-white mb-2 uppercase tracking-wider">Kurang Tepat!</h2>
                            <p class="text-red-100 text-xl font-bold">Tetap semangat di soal berikutnya!</p>
                        </div>
                    @elseif($feedbackStatus === 'timeout')
                        <div class="bg-gray-700 rounded-3xl p-10 text-center shadow-2xl transform scale-105">
                            <div class="text-white text-7xl mb-4">⏰</div>
                            <h2 class="text-4xl font-black text-white mb-2 uppercase tracking-wider">Waktu Habis!</h2>
                            <p class="text-gray-300 text-xl font-bold">Kamu terlambat menjawab soal ini.</p>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    @elseif($quizStatus === 'finished')
        <div class="flex flex-col items-center justify-center min-h-[80vh]">
            <div class="bg-white p-10 rounded-2xl shadow-xl text-center max-w-lg w-full border-t-8 border-green-500">
                <h1 class="text-4xl font-black text-green-600 mb-4">Selesai!</h1>
                <p class="text-gray-600 text-lg mb-6">Kerja bagus, {{ $participant->name }}! Kamu telah menyelesaikan kuis ini.</p>
                <div class="bg-gray-100 rounded-lg p-6 mb-6">
                    <p class="text-sm text-gray-500 uppercase font-bold">Skor Sementara Kamu</p>
                    <p class="text-5xl font-black text-gray-800 mt-2">{{ number_format($participant->total_score, 0, ',', '.') }}</p>
                </div>
                <p class="text-sm text-gray-400 italic">"Bawaslu Siap, Generasi Sigap"</p>
            </div>
        </div>
    @endif
</div>