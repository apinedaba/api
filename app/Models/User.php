<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

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
        'image',
        'stripe_id',
        'verification_code',
        'code_expires_at',
        'has_lifetime_access',
        'activo',
        'cedula_selfie_url',
        'ine_selfie_url',
        'identity_verification_status'
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
        'updated_at',
        'verification_code',
    ];


    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'code_expires_at' => 'datetime',
        'contacto' => 'array',
        'address' => 'array',
        'educacion' => 'array',
        'personales' => 'array',
        'configurations' => 'array',
        'horarios' => 'array',
        'image' => 'string',
        'isProfileComplete' => 'boolean',
        'activo' => 'boolean',
        'has_lifetime_access' => 'boolean',
    ];
    public function patientUsers()
    {
        return $this->hasMany(PatientUser::class, 'user', 'id')->with('patient');
    }
    public function appointment()
    {
        return $this->hasMany(Appointment::class, "user", "id");
    }
    public function subscription()
    {
        return $this->hasOne(Subscription::class);
    }
    public function googleAccount(): HasOne
    {
        return $this->hasOne(GoogleAccount::class);
    }
    public function escuelas()
    {
        return $this->hasMany(ValidacionCedulaManual::class);
    }

    /**
     * Relación con los consultorios
     */
    public function offices(): HasMany
    {
        return $this->hasMany(Office::class);
    }

    /**
     * Consultorio activo del usuario
     */
    public function activeOffice(): HasOne
    {
        return $this->hasOne(Office::class)->where('is_active', true);
    }

    public function deviceTokens(): MorphMany
    {
        return $this->morphMany(DeviceToken::class, 'notifiable');
    }

    public function notificationBroadcastChannel(): string
    {
        return "user.{$this->id}";
    }
}
