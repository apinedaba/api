<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    protected  $fillable = [
        'user',
        'patient',
        'fecha',
        'hora',
        'statusUser',
        'statusPatient',
        'state'
    ];

    public function patient_user()
    {
        return $this->belongsTo(PatientUser::class, 'patient_user', 'id');
    }
}
