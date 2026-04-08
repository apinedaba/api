<?php

namespace App\Models;

use App\Models\Patient_Medication;
use App\Models\PatientUser;
use App\Models\Expediente;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Patient extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'phone', 'password', 'address', 'contacto', 'historial', 'activo', 'status', 'historiaClinica', 'relevantes', 'personales', 'image'];

    protected $hidden = ['password', 'created_at', 'updated_at', 'remember_token', 'email_verified_at'];

    protected $casts = [
        'relationships' => 'array',
        'contacto' => 'array',
        'relevantes' => 'array',
        'historiaClinica' => 'array',
        'personales' => 'array',
        'address' => 'array',
    ];

    public function psychologist()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function medications()
    {
        return $this->hasMany(Patient_Medication::class);
    }

    public function connections()
    {
        return $this->hasMany(PatientUser::class, 'patient', 'id');
    }

    public function expediente()
    {
        return $this->hasOne(Expediente::class, 'patient_id', 'id');
    }

    public function deviceTokens(): MorphMany
    {
        return $this->morphMany(DeviceToken::class, 'notifiable');
    }

    public function notificationBroadcastChannel(): string
    {
        return "patient.{$this->id}";
    }
}
