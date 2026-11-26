<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Actions\Action;
use App\Models\QuizState;
use App\Models\Participant;
use App\Models\Answer;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Infolist; // Perbaikan: Gunakan Infolist class yang benar
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Filament\Notifications\Notification; // Pastikan Notification di-import di atas

class QuizControlPanel extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $navigationLabel = 'Kontrol Kuis';
    protected static ?string $title = 'Panel Kontrol Kuis';

    protected string $view = 'filament.pages.quiz-control-panel';

    public $quizState;

    public function mount()
    {
        $this->quizState = QuizState::firstOrCreate(
            ['id' => 1],
            ['status' => 'waiting', 'monitor_token' => Str::random(10)]
        );
    }

    // --- INFOLIST BAWAAN FILAMENT ---
    public function quizInfolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->quizState)
            ->schema([
                Section::make('Informasi & Akses Kuis')
                    ->description('Pantau status saat ini dan dapatkan tautan kontrol untuk panitia lapangan.')
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        // Tampilan Status dengan Badge
                        TextEntry::make('status')
                            ->label('Status Kuis Saat Ini')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'waiting' => 'warning',
                                'started' => 'success',
                                'finished' => 'danger',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'waiting' => 'MENUNGGU PESERTA',
                                'started' => 'KUIS SEDANG BERJALAN',
                                'finished' => 'KUIS TELAH SELESAI',
                                default => strtoupper($state),
                            }),

                        // Tampilan Link Monitor
                        TextEntry::make('monitor_token')
                            ->label('Link Monitor Panitia (Rahasia)')
                            ->formatStateUsing(fn (string $state) => url('/monitor/' . $state))
                            ->copyable()
                            ->copyMessage('Link berhasil disalin ke clipboard!')
                            ->copyMessageDuration(1500)
                            ->tooltip('Klik tautan ini untuk menyalin')
                            ->color('primary')
                            ->weight('bold'),
                            
                        // Tampilan QR Code untuk Peserta (Link Join)
                        TextEntry::make('qr_code')
                            ->label('Barcode URL Peserta (Scan untuk Gabung)')
                            ->default('https://evencapt.com') // Link yang Anda minta
                            ->html() // Mengizinkan render HTML
                            ->formatStateUsing(function (string $state) {
                                // Menggunakan API Google Chart untuk generate QR dengan aman dan ringan
                                $url = urlencode($state);
                                return '<div class="mt-2 p-2 bg-white rounded-lg inline-block shadow-sm">
                                            <img src="https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=' . $url . '&choe=UTF-8" alt="QR Code Evencapt" class="w-48 h-48 border border-gray-200 rounded"/>
                                        </div>
                                        <p class="mt-2 text-sm font-bold text-gray-700">Arahkan peserta ke: <a href="https://evencapt.com" target="_blank" class="text-primary-600 underline">evencapt.com</a></p>';
                            }),
                    ])
                    ->columns(1),
            ]);
    }

    // --- HEADER ACTIONS (TOMBOL KONTROL) ---
    protected function getHeaderActions(): array
    {
        return [
            Action::make('setWaiting')
                ->label('Set ke Waiting')
                ->color('warning')
                ->requiresConfirmation()
                ->action(fn () => $this->changeStatus('waiting')),

            Action::make('setStarted')
                ->label('Paksa Mulai (Start)')
                ->color('success')
                ->requiresConfirmation()
                ->action(fn () => $this->changeStatus('started')),

            Action::make('setFinished')
                ->label('Paksa Akhiri (Finish)')
                ->color('gray')
                ->requiresConfirmation()
                ->action(fn () => $this->changeStatus('finished')),

            Action::make('resetData')
                ->label('Reset Data Simulasi')
                ->color('danger')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->modalHeading('Hapus Semua Data Peserta?')
                ->modalDescription('PERINGATAN: Semua data peserta, jawaban, dan skor akan dihapus permanen ke 0. Gunakan hanya setelah uji coba (gladi resik) selesai.')
                ->action(function () {
                    DB::statement('SET FOREIGN_KEY_CHECKS=0;');
                    Answer::truncate();
                    Participant::truncate();
                    DB::statement('SET FOREIGN_KEY_CHECKS=1;');

                    $this->changeStatus('waiting');
                    $this->quizState->update(['monitor_token' => Str::random(10)]);
                    
                    Notification::make()
                        ->title('Data berhasil di-reset!')
                        ->success()
                        ->send();
                }),
        ];
    }

    private function changeStatus($status)
    {
        $this->quizState->update(['status' => $status]);
        
        if ($status === 'started') {
             $this->quizState->update(['started_at' => now()]);
        }
        
        $this->quizState->refresh();
    }
}