<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OnDemandRequest extends Model
{
    public const ACTIVE_STATUSES = ['matching', 'offered', 'candidates_ready', 'awaiting_payment'];

    protected $fillable = [
        'public_uuid', 'patient_id', 'accepted_professional_id', 'appointment_id',
        'status', 'urgency', 'modality', 'specialties', 'maximum_budget',
        'preferred_from', 'preferred_until', 'reason', 'location', 'safety_screening',
        'matched_at', 'accepted_at', 'cancelled_at', 'expires_at', 'meta',
    ];

    protected $casts = [
        'specialties' => 'array',
        'maximum_budget' => 'decimal:2',
        'preferred_from' => 'datetime',
        'preferred_until' => 'datetime',
        'location' => 'array',
        'safety_screening' => 'array',
        'matched_at' => 'datetime',
        'accepted_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'expires_at' => 'datetime',
        'meta' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $request) => $request->public_uuid ??= (string) Str::uuid());
    }

    public function patient() { return $this->belongsTo(Patient::class); }
    public function acceptedProfessional() { return $this->belongsTo(User::class, 'accepted_professional_id'); }
    public function appointment() { return $this->belongsTo(Appointment::class); }
    public function offers() { return $this->hasMany(OnDemandOffer::class); }
}
