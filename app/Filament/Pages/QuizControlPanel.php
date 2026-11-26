<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Actions\Action;
use App\Models\QuizState;
use App\Models\Participant;
use App\Models\Answer;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Perbaikan Import Infolist Filament v3
use Filament\Infolists\Infolist;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\TextEntry;

class QuizControlPanel extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $navigationLabel = 'Kontrol Kuis';
    protected static ?string $title = 'Panel Kontrol Kuis';

    protected string $view = 'filament.pages.quiz-control-panel';

    // 1. INI KUNCI AGAR TOMBOL BERFUNGSI: Tambahkan ?QuizState
    // Agar Livewire 3 tidak mengubah model ini menjadi array saat tombol diklik
    public ?QuizState $quizState = null;

    public function mount()
    {
        $this->quizState = QuizState::firstOrCreate(
            ['id' => 1],
            ['status' => 'waiting', 'monitor_token' => Str::random(10)]
        );
    }

    // 2. SETTING BARCODE DI BELAKANG LAYAR (Dalam Infolist)
    // Perbaikan: Gunakan Infolist (bukan Schema) sebagai parameternya
    public function quizInfolist(Schema $infolist): Schema
    {
        return $infolist
            ->record($this->quizState) 
            ->components([
                Section::make('Informasi & Akses Kuis')
                    ->description('Pantau status saat ini dan dapatkan tautan kontrol untuk panitia lapangan.')
                    ->icon('heroicon-o-information-circle')
                    ->schema([
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

                        TextEntry::make('monitor_token')
                            ->label('Link Monitor Panitia (Rahasia)')
                            ->formatStateUsing(fn (string $state) => url('/monitor/' . $state))
                            ->copyable()
                            ->copyMessage('Link disalin!')
                            ->color('primary')
                            ->weight('bold'),
                            
                        // PENAMBAHAN QR CODE (Menggunakan Google Chart API yang sangat ringan)
                        TextEntry::make('qr_code')
                            ->label('Barcode URL Peserta (Scan untuk Gabung)')
                            ->default('https://evencapt.com')
                            ->html()
                            ->formatStateUsing(function (string $state) {
                                $url = urlencode($state);
                                return '<div class="mt-2">
                                            <img src="https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=' . $url . '&choe=UTF-8" alt="QR Code Evencapt" class="w-48 h-48 border border-gray-200 rounded-lg shadow-sm"/>
                                            <p class="mt-2 text-sm font-bold text-gray-700">Arahkan peserta ke: <a href="' . $state . '" target="_blank" class="text-primary-600 underline">evencapt.com</a></p>
                                        </div>';
                            }),
                    ])
                    ->columns(1), 
            ]);
    }

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
                ->modalDescription('Semua data akan dihapus permanen. Lanjutkan?')
                ->action(function () {
                    DB::statement('SET FOREIGN_KEY_CHECKS=0;');
                    Answer::truncate();
                    Participant::truncate();
                    DB::statement('SET FOREIGN_KEY_CHECKS=1;');

                    $this->changeStatus('waiting');
                    $this->quizState->update(['monitor_token' => Str::random(10)]);
                    
                    \Filament\Notifications\Notification::make()
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
        
        // Memaksa Livewire/Filament untuk me-refresh data tampilan
        $this->quizState->refresh();
    }
}