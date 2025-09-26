<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppointmentCart extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'user_id',
        'fecha',
        'hora',
        'tipoSesion',
        'duracion',
        'precio',
        'estado',
        'formato', // Nueva columna agregada
        'payment_intent_id',
        'appointment_id',

    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
