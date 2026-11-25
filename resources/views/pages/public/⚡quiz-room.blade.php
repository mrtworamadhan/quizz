<?php

use Livewire\Component;
use App\Models\Participant;
use App\Models\QuizState;
use App\Models\Question;
use App\Models\Answer;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;

new class extends Component {
    public $participant;
    public $quizStatus = 'waiting';
    public $currentQuestion = null;
    public $questionStartTime;
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
        $this->selectedOption = null;
        $this->timeLeft = 0;

        $completedAnswersCount = $this->participant->answers()->whereNotNull('time_taken_ms')->count();
        $nextOrder = $completedAnswersCount + 1;

        $this->currentQuestion = Cache::remember('question_order_' . $nextOrder, 3600, function () use ($nextOrder) {
            return Question::where('order', $nextOrder)->first();
        });

        if (!$this->currentQuestion) {
            $finalScore = $this->participant->answers()->sum('score_awarded');
            
            $this->participant->update([
                'finished_at' => now(),
                'total_score' => $finalScore
            ]);
            
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

        $this->loadNextQuestion();
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
        <div wire:key="question-{{ $currentQuestion->id }}" x-data="{ localSelected: null }" class="max-w-3xl mx-auto mt-4 md:mt-10 mb-20">
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden border-t-8 border-orange-500">
                
                <div class="bg-gray-50 p-6 border-b flex flex-col md:flex-row justify-between items-center gap-4">
                    <span class="text-lg font-black text-gray-700 bg-gray-200 px-4 py-2 rounded-lg">
                        Soal No. {{ $currentQuestion->order }}
                    </span>
                    <div x-data="{ seconds: {{ $timeLeft }}, interval: null }" x-init="
                        clearInterval(interval);
                        interval = setInterval(() => {
                            if (seconds > 0) { seconds--; } 
                            else { clearInterval(interval); $wire.submitAnswer(true); }
                        }, 1000);
                    " class="flex items-center gap-3 bg-red-100 border-2 border-red-200 text-red-600 px-6 py-2 rounded-full shadow-inner">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="font-black text-3xl tabular-nums" x-text="seconds"></span>
                        <span class="text-sm font-bold uppercase mt-1">Detik</span>
                    </div>
                </div>

                <div class="p-6 md:p-8">
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
        </div>

        @elseif($quizStatus === 'finished')
        <div class="flex flex-col items-center justify-center min-h-[80vh] py-10">
            
            <!-- KARTU SKOR AKHIR -->
            <div class="bg-white p-8 rounded-2xl shadow-xl text-center max-w-2xl w-full border-t-8 border-green-500 mb-8">
                <h1 class="text-4xl font-black text-green-600 mb-4">Selesai!</h1>
                <p class="text-gray-600 text-lg mb-6">Kerja bagus, <span class="font-bold">{{ $participant->name }}</span>! Kamu telah menyelesaikan semua soal.</p>
                <div class="bg-gray-100 rounded-xl p-6">
                    <p class="text-sm text-gray-500 uppercase font-bold">Skor Akhir Kamu</p>
                    <p class="text-6xl font-black text-gray-800 mt-2">{{ number_format($participant->total_score, 0, ',', '.') }}</p>
                </div>
            </div>

            <!-- KARTU REKAP DETAIL JAWABAN -->
            <div class="w-full max-w-2xl bg-white rounded-2xl shadow-lg overflow-hidden">
                <div class="bg-gray-800 p-5 text-center">
                    <h2 class="text-xl font-bold text-white">Detail Jawaban Kamu</h2>
                </div>
                
                <div class="p-4 md:p-6 divide-y divide-gray-200">
                    <!-- Kita gunakan PHP murni di sini agar tidak membebani state Livewire -->
                    @php
                        $recapAnswers = App\Models\Answer::with('question')
                            ->where('participant_id', $participant->id)
                            ->get()
                            ->sortBy(function($ans) {
                                return $ans->question->order ?? 999;
                            });
                    @endphp

                    @forelse($recapAnswers as $ans)
                        <div class="py-5">
                            <p class="font-bold text-gray-700 mb-3 leading-relaxed">
                                <span class="bg-gray-200 text-gray-800 px-2 py-1 rounded text-sm mr-2 shadow-sm">Soal {{ $ans->question->order ?? '-' }}</span>
                                {{ $ans->question->question_text ?? 'Soal tidak ditemukan' }}
                            </p>
                            
                            <div class="flex flex-col md:flex-row md:items-center gap-3 text-sm">
                                
                                <!-- Status Jawaban Peserta -->
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-gray-500">Jawaban Kamu:</span>
                                    @if($ans->is_correct)
                                        <span class="bg-green-100 text-green-700 font-bold px-3 py-1.5 rounded-full flex items-center gap-1 shadow-sm">
                                            ✓ {{ $ans->submitted_answer ?? 'Kosong/Waktu Habis' }}
                                        </span>
                                    @else
                                        <span class="bg-red-100 text-red-700 font-bold px-3 py-1.5 rounded-full flex items-center gap-1 shadow-sm">
                                            ✗ {{ $ans->submitted_answer ?? 'Kosong/Waktu Habis' }}
                                        </span>
                                    @endif
                                </div>

                                <!-- Kunci Jawaban (Hanya Muncul Jika Salah) -->
                                @if(!$ans->is_correct)
                                    <div class="flex items-center gap-2 md:ml-4 border-t md:border-t-0 md:border-l border-gray-200 pt-3 md:pt-0 md:pl-4 mt-1 md:mt-0">
                                        <span class="font-semibold text-gray-500">Kunci:</span>
                                        <span class="bg-blue-100 text-blue-700 font-bold px-3 py-1.5 rounded-full shadow-sm">
                                            {{ $ans->question->correct_answer ?? '-' }}
                                        </span>
                                    </div>
                                @endif
                                
                                <!-- Poin yang Didapat -->
                                <div class="md:ml-auto font-black text-right mt-2 md:mt-0 {{ $ans->score_awarded > 0 ? 'text-orange-500' : 'text-gray-400' }}">
                                    +{{ $ans->score_awarded }} Poin
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center text-gray-500 font-bold">
                            Belum ada jawaban yang tercatat.
                        </div>
                    @endforelse
                </div>
                
                <div class="bg-gray-50 p-4 text-center border-t">
                    <p class="text-sm text-gray-400 italic">"Bawaslu Siap, Generasi Sigap"</p>
                </div>
            </div>

        </div>
    @endif
</div>