<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfessionalAnalyticsEvent extends Model
{
    protected $fillable = [
        'user_id',
        'consulta_contacto_id',
        'appointment_id',
        'payment_id',
        'patient_id',
        'event_key',
        'event_type',
        'source',
        'medium',
        'campaign',
        'landing_page',
        'path',
        'referrer',
        'session_id',
        'ip_hash',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function consultaContacto()
    {
        return $this->belongsTo(ConsultaContacto::class);
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }
}
