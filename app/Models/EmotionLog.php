<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmotionLog extends Model
{
    use HasFactory;
    protected $fillable = [
        'patient_id',
        'date',
        'time',
        'situation',
        'emotion',
        'intensity',
        'behavior',
        'adaptive_response',
        'feeling',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }
}
