<?php

namespace App\Models;

use App\Models\Patient;
use App\Models\PatientUser;
use App\Models\Payment;
use App\Models\SessionAttachment;
use App\Models\SessionNote;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'google_event_id',
        'extendedProps',
    ];

    protected $casts = [
        'extendedProps' => 'array',
    ];

    public function patient_user()
    {
        return $this->belongsTo(PatientUser::class, 'patient_user', 'id');
    }

    public function patient()
    {
        return $this->hasOne(Patient::class, 'id', 'patient');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user', 'id');
    }

    public function cart()
    {
        return $this->belongsTo(\App\Models\AppointmentCart::class, 'cart_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'appointment_id');
    }

    // Relación: una sesión tiene muchas notas

    public function notes()
    {
        return $this->hasMany(SessionNote::class, 'session_id');
    }

    // Relación: una sesión tiene muchos adjuntos
    public function attachments()
    {
        return $this->hasMany(SessionAttachment::class, 'session_id');
    }
}
