<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class GuardianAccount extends Authenticatable
{
    use HasApiTokens, Notifiable;
    protected $fillable = ['name', 'email', 'phone', 'password', 'email_verified_at'];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = ['password' => 'hashed', 'email_verified_at' => 'datetime'];
    public function patients()
    {
        return $this->belongsToMany(Patient::class, 'guardian_patient')
            ->withPivot(['relationship', 'can_manage', 'can_sign', 'representation_reason', 'status'])->withTimestamps();
    }
}
