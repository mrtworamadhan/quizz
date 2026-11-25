<?php

use Livewire\Component;
use App\Models\Participant;
use App\Models\QuizState;
use App\Models\Answer;
use Illuminate\Support\Facades\DB;

new class extends Component
{
    public $quizStatus = 'waiting';
    public $participantCount = 0;
    public $activeAnswersCount = 0;
    public $leaderboard = [];
    public $monitorToken;

    public $showModal = false;
    public $selectedParticipant = null;
    public $participantAnswers = [];

    public function mount($token)
    {
        $state = QuizState::find(1);

        if (!$state || $state->monitor_token !== $token) {
            abort(403, 'Akses Ditolak: Link monitor tidak valid atau token salah.');
        }

        $this->monitorToken = $token;
        $this->refreshData();
    }

    public function startQuiz()
    {
        QuizState::updateOrCreate(
            ['id' => 1],
            [
                'status' => 'started',
                'started_at' => now()
            ]
        );
        
        $this->quizStatus = 'started';
        $this->refreshData();
    }

    public function calculateAndStopQuiz()
    {
        // 1. Eksekusi perhitungan massal skor akhir untuk semua peserta
        // Ini lebih aman dan cepat karena dilakukan dengan 1 query database (Bulk Update)
        DB::statement('
            UPDATE participants p
            SET total_score = (
                SELECT COALESCE(SUM(score_awarded), 0)
                FROM answers a
                WHERE a.participant_id = p.id
            ),
            finished_at = NOW()
            WHERE finished_at IS NULL
        ');

        // 2. Ubah status kuis menjadi selesai
        QuizState::updateOrCreate(
            ['id' => 1],
            ['status' => 'finished']
        );
        
        $this->quizStatus = 'finished';
        $this->refreshData();
    }

    public function refreshData()
    {
        if ($this->showModal) return; 

        $state = QuizState::find(1);
        if ($state) {
            $this->quizStatus = $state->status;
        }

        $this->participantCount = Participant::count();

        // Jika kuis sedang berjalan, hanya hitung total jawaban masuk agar UI tidak kaku
        if ($this->quizStatus === 'started') {
            $this->activeAnswersCount = Answer::count();
        }

        // Tampilkan leaderboard HANYA JIKA kuis sudah selesai dikalkulasi
        if ($this->quizStatus === 'finished') {
            $this->leaderboard = Participant::orderBy('total_score', 'desc')->take(10)->get();
        }
    }

    public function viewDetails($participantId)
    {
        $this->selectedParticipant = Participant::find($participantId);
        
        if ($this->selectedParticipant) {
            $this->participantAnswers = Answer::with('question')
                ->where('participant_id', $participantId)
                ->get()
                ->sortBy(function($answer) {
                    return $answer->question->order; 
                });
                
            $this->showModal = true;
        }
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->selectedParticipant = null;
        $this->participantAnswers = [];
    }
};
?>

<div class="min-h-screen bg-gray-100 p-8 font-sans" wire:poll.5s="refreshData">
    
    <!-- Header Admin/Monitor -->
    <div class="bg-white rounded-xl shadow p-6 mb-8 flex justify-between items-center border-l-8 border-blue-800">
        <div>
            <h1 class="text-3xl font-black text-gray-800">Control Room Kuis</h1>
            <p class="text-gray-500 font-bold">Bawaslu Siap, Generasi Sigap</p>
        </div>
        <div class="text-right">
            <p class="text-sm text-gray-500 uppercase font-bold">Total Peserta Bergabung</p>
            <p class="text-4xl font-black text-orange-500">{{ $participantCount }} <span class="text-lg text-gray-400">Siswa</span></p>
        </div>
    </div>

    <!-- KONDISI 1: SEBELUM MULAI -->
    @if($quizStatus === 'waiting')
        <div class="flex flex-col items-center justify-center bg-white rounded-xl shadow-lg p-16 text-center h-[50vh]">
            <h2 class="text-2xl font-bold text-gray-700 mb-8">Kuis belum dimulai. Tunggu hingga semua peserta bergabung.</h2>
            <button wire:click="startQuiz" onclick="return confirm('Yakin ingin memulai kuis sekarang?')" class="bg-green-500 hover:bg-green-600 text-white font-black text-4xl py-6 px-16 rounded-2xl shadow-xl">
                START KUIS SEKARANG
            </button>
        </div>
    
    <!-- KONDISI 2: SEDANG BERJALAN -->
    @elseif($quizStatus === 'started')
        <div class="flex flex-col items-center justify-center bg-white rounded-xl shadow-lg p-16 text-center h-[50vh]">
            
            <div class="animate-pulse mb-8 flex justify-center">
                <div class="w-24 h-24 border-8 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
            </div>
            
            <h2 class="text-3xl font-black text-gray-800 mb-2">KUIS SEDANG BERLANGSUNG</h2>
            <p class="text-xl text-gray-600 mb-8">Harap tenang, peserta sedang mengerjakan soal...</p>
            
            <div class="bg-blue-50 px-8 py-4 rounded-xl border border-blue-100 mb-10">
                <p class="text-sm text-blue-800 font-bold uppercase mb-1">Total Interaksi / Jawaban Masuk</p>
                <p class="text-5xl font-black text-blue-600">{{ number_format($activeAnswersCount, 0, ',', '.') }}</p>
            </div>

            <button wire:click="calculateAndStopQuiz" wire:loading.attr="disabled" onclick="return confirm('Apakah waktu kuis sudah habis? Sistem akan merekap nilai semua peserta secara massal.')" class="bg-red-500 hover:bg-red-600 text-white font-black text-2xl py-4 px-10 rounded-xl shadow-lg transition-transform hover:-translate-y-1">
                <span wire:loading.remove wire:target="calculateAndStopQuiz">HITUNG SKOR & AKHIRI KUIS</span>
                <span wire:loading wire:target="calculateAndStopQuiz">MEMPROSES DATA... MOHON TUNGGU</span>
            </button>
        </div>

    <!-- KONDISI 3: SELESAI (LEADERBOARD) -->
    @elseif($quizStatus === 'finished')
        <div class="bg-white rounded-xl shadow-lg p-8">
            <div class="flex justify-between items-center mb-6 border-b pb-4">
                <h2 class="text-2xl font-black text-gray-800">🏆 HASIL AKHIR & LEADERBOARD</h2>
                <span class="bg-green-100 text-green-800 font-bold px-4 py-2 rounded-lg text-sm">Skor Telah Dikalkulasi</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-800 text-white uppercase text-sm">
                            <th class="p-4 rounded-tl-lg text-center">Rank</th>
                            <th class="p-4">Nama Peserta</th>
                            <th class="p-4">Asal Kelas</th>
                            <th class="p-4 text-right">Total Poin Akhir</th>
                            <th class="p-4 text-center rounded-tr-lg">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($leaderboard as $index => $player)
                            <tr class="border-b hover:bg-orange-50 transition-colors {{ $index === 0 ? 'bg-orange-100 font-bold' : '' }}">
                                <td class="p-4 text-center text-lg">{{ $index + 1 }}</td>
                                <td class="p-4 text-lg text-gray-800">{{ $player->name }}</td>
                                <td class="p-4 text-gray-600">{{ $player->class_name }}</td>
                                <td class="p-4 text-right text-2xl font-black text-orange-600">
                                    {{ number_format($player->total_score, 0, ',', '.') }}
                                </td>
                                <td class="p-4 text-center">
                                    <button wire:click="viewDetails({{ $player->id }})" class="bg-blue-100 hover:bg-blue-600 text-blue-700 hover:text-white font-bold py-1 px-4 rounded transition-colors text-sm">
                                        Lihat Detail
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="p-8 text-center text-gray-500">Belum ada skor yang tercatat...</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- POP-UP MODAL DETAIL JAWABAN PESERTA -->
    @if($showModal && $selectedParticipant)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60 p-4">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden">
                <div class="bg-blue-800 text-white p-6 flex justify-between items-center">
                    <div>
                        <h3 class="text-2xl font-bold">Detail Jawaban: {{ $selectedParticipant->name }}</h3>
                        <p class="text-blue-200">{{ $selectedParticipant->class_name }} | Total Poin: <span class="font-bold text-white">{{ number_format($selectedParticipant->total_score, 0, ',', '.') }}</span></p>
                    </div>
                    <button wire:click="closeModal" class="text-blue-200 hover:text-white font-bold text-3xl">&times;</button>
                </div>

                <div class="p-6 overflow-y-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-100 text-gray-600 text-sm border-b-2 border-gray-300">
                                <th class="p-3 text-center">No. Soal</th>
                                <th class="p-3 text-center">Jawab</th>
                                <th class="p-3 text-center">Kunci</th>
                                <th class="p-3 text-center">Status</th>
                                <th class="p-3 text-right">Waktu (Detik)</th>
                                <th class="p-3 text-right">Poin</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($participantAnswers as $ans)
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="p-3 text-center font-bold text-gray-700">{{ $ans->question->order }}</td>
                                    <td class="p-3 text-center font-bold text-gray-800">{{ $ans->submitted_answer ?? '-' }}</td>
                                    <td class="p-3 text-center text-gray-500">{{ $ans->question->correct_answer }}</td>
                                    <td class="p-3 text-center">
                                        @if($ans->is_correct)
                                            <span class="bg-green-100 text-green-700 px-2 py-1 rounded text-xs font-bold">BENAR</span>
                                        @else
                                            <span class="bg-red-100 text-red-700 px-2 py-1 rounded text-xs font-bold">SALAH</span>
                                        @endif
                                    </td>
                                    <td class="p-3 text-right text-gray-600">
                                        {{ number_format($ans->time_taken_ms / 1000, 1) }} s
                                    </td>
                                    <td class="p-3 text-right font-bold {{ $ans->score_awarded > 0 ? 'text-green-600' : 'text-gray-400' }}">
                                        +{{ $ans->score_awarded }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="p-4 text-center text-gray-500">Peserta ini belum menjawab soal apa pun.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="bg-gray-50 p-4 border-t text-right">
                    <button wire:click="closeModal" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-6 rounded-lg transition-colors">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>