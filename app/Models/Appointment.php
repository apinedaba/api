<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Patient;
use App\Models\PatientUser;
use App\Models\Payment;
use App\Models\SessionAttachment;
use App\Models\SessionNote;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Appointment extends Model
{
    use BelongsToOrganization, HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'public_uuid',
        'user',
        'patient',
        'clinic_id',
        'title',
        'start',
        'end',
        'statusUser',
        'statusPatient',
        'state',
        'lifecycle_status',
        'started_at',
        'completed_at',
        'session_start_code_hash',
        'session_start_code_encrypted',
        'session_start_code_attempts',
        'session_start_code_verified_at',
        'comments',
        'objective',
        'session_description',
        'pre_session_note',
        'interventions',
        'action_plan',
        'observations',
        'psychometric_scales',
        'mental_exam',
        'payment_status',
        'video_call_room',
        'cart_id',
        'link',
        'google_event_id',
        'recurrence_id',
        'recurrence_frequency',
        'recurrence_interval',
        'recurrence_until',
        'recurrence_position',
        'synced_with_google',
        'extendedProps',
        'notification_meta',
    ];

    protected static function booted(): void
    {
        static::creating(function (Appointment $appointment) {
            if (Schema::hasColumn($appointment->getTable(), 'public_uuid') && ! $appointment->public_uuid) {
                $appointment->public_uuid = (string) Str::uuid();
            }
        });
    }

    protected $casts = [
        'start' => 'datetime',
        'end' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'session_start_code_attempts' => 'integer',
        'session_start_code_verified_at' => 'datetime',
        'recurrence_until' => 'date',
        'synced_with_google' => 'boolean',
        'extendedProps' => 'array',
        'notification_meta' => 'array',
        'psychometric_scales' => 'array',
        'mental_exam' => 'array',
    ];

    protected $hidden = [
        'session_start_code_hash',
        'session_start_code_encrypted',
    ];

    public function sessionFormat(): ?string
    {
        $value = $this->cart?->formato
            ?: data_get($this->extendedProps, 'formato')
            ?: data_get($this->extendedProps, 'tipo');

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Str::of($value)->lower()->ascii()->trim()->toString();
    }

    public function isInPerson(): bool
    {
        return in_array($this->sessionFormat(), [
            'presencial',
            'consultorio',
            'in person',
            'in-person',
        ], true);
    }

    public function isProfessionallyCompleted(): bool
    {
        $completed = [
            'completed',
            'complete',
            'completada',
            'completado',
            'concluida',
            'terminada',
            'finalizada',
        ];

        return in_array(Str::of((string) $this->statusUser)->lower()->ascii()->trim()->toString(), $completed, true)
            || in_array(Str::of((string) $this->lifecycle_status)->lower()->ascii()->trim()->toString(), $completed, true);
    }

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

    public function clinic()
    {
        return $this->belongsTo(Clinic::class, 'clinic_id');
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
