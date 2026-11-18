<?php

namespace App\Filament\Resources\Questions\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class QuestionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('order')
                    ->label('Urutan Soal (Contoh: 1, 2, 3)')
                    ->numeric()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->columnSpan(1),

                // Input untuk batas waktu soal ini
                TextInput::make('time_limit_seconds')
                    ->label('Batas Waktu (Detik)')
                    ->numeric()
                    ->default(20)
                    ->required()
                    ->columnSpan(1),

                // Input teks pertanyaan
                Textarea::make('question_text')
                    ->label('Teks Pertanyaan')
                    ->required()
                    ->columnSpanFull(),

                // Input JSON untuk pilihan ganda (A, B, C, D)
                KeyValue::make('options')
                    ->label('Pilihan Jawaban')
                    ->keyLabel('Abjad (A, B, C, D)')
                    ->valueLabel('Teks Jawaban')
                    ->addActionLabel('Tambah Pilihan')
                    ->required()
                    ->columnSpanFull(),

                // Dropdown untuk memilih kunci jawaban
                Select::make('correct_answer')
                    ->label('Kunci Jawaban Benar')
                    ->options([
                        'A' => 'A',
                        'B' => 'B',
                        'C' => 'C',
                        'D' => 'D',
                    ])
                    ->required()
                    ->columnSpan(1),
            ]);
    }
}
