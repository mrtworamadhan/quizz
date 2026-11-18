<?php

namespace App\Exports;

use App\Models\Question;
use Illuminate\Support\Collection; // 1. TAMBAHAN BARU: Import class Collection
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ParticipantAnswersExport implements FromCollection, WithHeadings, WithMapping
{
    protected $questions;
    protected $records;

    public function __construct($records)
    {
        $this->records = $records;
        // Ambil semua soal urut dari nomor 1
        $this->questions = Question::orderBy('order', 'asc')->get();
    }

    // 2. TAMBAHAN BARU: Menambahkan ": Collection" di akhir deklarasi fungsi
    public function collection(): Collection
    {
        return $this->records;
    }

    public function headings(): array
    {
        $headers = [
            'Nama Peserta',
            'Asal Kelas',
            'Total Poin Akhir',
            'Waktu Selesai',
        ];

        foreach ($this->questions as $question) {
            $headers[] = 'Jawab S' . $question->order;
            $headers[] = 'Poin S' . $question->order;
            $headers[] = 'Waktu S' . $question->order . ' (Detik)';
        }

        return $headers;
    }

    public function map($participant): array
    {
        $row = [
            $participant->name,
            $participant->class_name,
            $participant->total_score,
            $participant->finished_at ? $participant->finished_at->format('d M Y H:i') : '-',
        ];

        $answers = $participant->answers->keyBy('question_id');

        foreach ($this->questions as $question) {
            $answer = $answers->get($question->id);

            if ($answer) {
                $row[] = $answer->submitted_answer ?? '(Timeout)'; 
                $row[] = $answer->score_awarded; 
                $row[] = $answer->time_taken_ms ? number_format($answer->time_taken_ms / 1000, 1) : '-';
            } else {
                $row[] = '-';
                $row[] = '-';
                $row[] = '-';
            }
        }

        return $row;
    }
}