<?php

namespace App\Filament\Resources\Questions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class QuestionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order')
                    ->label('No.')
                    ->sortable(),
                
                TextColumn::make('question_text')
                    ->label('Pertanyaan')
                    ->limit(50) // Membatasi teks agar tabel tidak terlalu lebar
                    ->searchable(),
                
                TextColumn::make('time_limit_seconds')
                    ->label('Waktu')
                    ->formatStateUsing(fn ($state) => $state . ' dtk'),
                
                TextColumn::make('correct_answer')
                    ->label('Kunci')
                    ->badge()
                    ->color('success'),
            ])
            ->defaultSort('order', 'asc') // Urutkan berdasarkan nomor soal secara default
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
