<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Patient_Medication extends Model
{
    use HasFactory;
     protected $table = 'patient_medications';
     protected $fillable = [
        'patient_id',
        'medication_name',
        'dosage',
        'frequency',
        'currently_active',
        'start_date',
        'end_date',
        'notes',
        'user_id',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
