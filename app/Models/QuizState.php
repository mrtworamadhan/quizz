<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizState extends Model
{
    protected $guarded = ['id'];
    
    // Casting agar selalu menjadi instance Carbon (waktu)
    protected $casts = [
        'started_at' => 'datetime',
    ];
}
