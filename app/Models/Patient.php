<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

class Patient extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'address', 'contacto', 'historial', 'activo', 'status', 'historiaClinica','relevantes','personales'];

    protected $hidden = ['password', 'created_at', 'updated_at', 'remember_token', 'email_verified_at'];


}
