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
        'title',
        'start',
        'end',
        'statusUser',
        'statusPatient',
        'state'
    ];

    public function patient_user()
    {
        return $this->belongsTo(PatientUser::class, 'patient_user', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, "user", "id");
    }
}
