<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PsychologistReview extends Model
{
    protected $fillable = [
        'patient_id',
        'psychologist_id',
        'appointment_id',
        'name',
        'email',
        'email_hash',
        'device_id',
        'rating',
        'comment',
        'approved',
        'is_anonymous',
        'professional_response',
        'published_at',
        'meta',
    ];

    protected $casts = [
        'rating' => 'integer',
        'approved' => 'boolean',
        'is_anonymous' => 'boolean',
        'published_at' => 'datetime',
        'meta' => 'array',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function psychologist()
    {
        return $this->belongsTo(User::class, 'psychologist_id');
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }
}
