<?php

namespace App\Filament\Resources\Participants\Tables;

use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class ParticipantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Peserta')
                    ->searchable()
                    ->sortable(),
                    
                TextColumn::make('class_name')
                    ->label('Asal Kelas')
                    ->searchable()
                    ->sortable(),
                    
                TextColumn::make('total_score')
                    ->label('Total Poin')
                    ->badge()
                    ->color('success')
                    ->numeric(
                        decimalPlaces: 0,
                        decimalSeparator: ',',
                        thousandsSeparator: '.',
                    )
                    ->sortable(),
                    
                TextColumn::make('finished_at')
                    ->label('Waktu Selesai')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->defaultSort('total_score', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('export_excel')
                        ->label('Export Detail Jawaban')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('success') // Memberi warna hijau agar menarik
                        // Aksi saat diklik: $records berisi data-data yang dicentang panitia
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            
                            $namaFile = 'Laporan-Detail-Bawaslu-' . date('Y-m-d') . '.xlsx';
                            
                            // Gunakan Maatwebsite Excel secara langsung untuk mendownload
                            return \Maatwebsite\Excel\Facades\Excel::download(
                                new \App\Exports\ParticipantAnswersExport($records), 
                                $namaFile
                            );
                            
                        }),
                ]),
            ]);
    }
}
