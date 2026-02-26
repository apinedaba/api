<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PsychologistReview extends Model
{
    protected $fillable = [
            
            'patient_id',
            'psychologist_id',
            'name',
            'email',
            'email_hash',
            'device_id',
            'rating',
            'comment',
            'approved'
            
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function psychologist()
    {
        return $this->belongsTo(User::class, 'psychologist_id');
    }
}