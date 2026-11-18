<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $guarded = ['id'];

    // Mengubah kolom 'options' (JSON di database) menjadi bentuk Array di PHP
    protected $casts = [
        'options' => 'array',
    ];

    // Relasi: Satu soal memiliki banyak jawaban dari peserta
    public function answers()
    {
        return $this->hasMany(Answer::class);
    }
}