<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Payment;
use App\Models\Patient;
class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user',
        'patient',
        'title',
        'start',
        'end',
        'statusUser',
        'statusPatient',
        'state',
        'comments',
        'video_call_room',
        'cart_id',
        'link',
        'tipo',
        'costo',
        'payments'
    ];
    protected $casts = [
        'payments' => 'array',
    ];
    public function patient_user()
    {
        return $this->belongsTo(PatientUser::class, 'patient_user', 'id');
    }

    public function patient()
    {
        return $this->hasOne(Patient::class, "id", "patient");
    }


    public function user()
    {
        return $this->belongsTo(User::class, "user", "id");
    }

    public function cart()
    {
        return $this->belongsTo(\App\Models\AppointmentCart::class, 'cart_id');
    }

    public function payments() 
    {
        return $this->hasMany(Payment::class, 'appointment_id');
    }
}
