<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Contracts\Auth\MustVerifyEmail;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'isProfileComplete',
        'personales',
        'address',
        'contacto',
        'educacion',
        'configurations',
        'horarios',
        'plan',
        'image'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'validado',
        'activo',
        'created_at',
        'updated_at',
    ];


    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'contacto' => 'array',
        'address' => 'array',
        'educacion' => 'array',
        'personales' => 'array',
        'configurations' => 'array',
        'horarios' => 'array',
        'image' => 'string',
        'isProfileComplete' => 'boolean',
    ];
    public function patientUsers()
    {
        return $this->hasMany(PatientUser::class, 'user', 'id')->with('patient');
    }
    public function appointment()
    {
        return $this->hasMany(Appointment::class, "user", "id");
    }
}
