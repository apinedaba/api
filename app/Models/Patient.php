<?php

namespace App\Models;

use App\Models\Patient_Medication;
use App\Models\PatientUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Patient extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'address', 'contacto', 'historial', 'activo', 'status', 'historiaClinica', 'relevantes', 'personales', 'image'];

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
}
