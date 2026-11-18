<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Participant extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'finished_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    // Relasi: Satu peserta memiliki banyak jawaban
    public function answers()
    {
        return $this->hasMany(Answer::class);
    }
}
