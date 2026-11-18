<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Actions\Action;
use App\Models\QuizState;
use App\Models\Participant;
use App\Models\Answer;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
    public function quizInfolist(Schema $infolist): Schema
    {
        return $infolist
            ->record($this->quizState) // Bind data quizState ke infolist
            ->components([
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

                        // Tampilan Link dengan fitur Copy otomatis
                        TextEntry::make('monitor_token')
                            ->label('Link Monitor Panitia (Rahasia)')
                            ->formatStateUsing(fn (string $state) => url('/monitor/' . $state))
                            ->copyable()
                            ->copyMessage('Link berhasil disalin ke clipboard!')
                            ->copyMessageDuration(1500)
                            ->tooltip('Klik tautan ini untuk menyalin')
                            ->color('primary')
                            ->weight('bold'),
                    ])
                    ->columns(1), // Dibuat satu kolom agar lebar dan rapi
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
        
        $this->quizState->refresh();
    }
}
