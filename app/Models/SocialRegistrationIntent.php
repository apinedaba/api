<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SocialRegistrationIntent extends Model
{
    use HasFactory;

    protected $fillable = [
        'token_hash',
        'provider_name',
        'provider_id',
        'name',
        'email',
        'avatar',
        'expires_at',
        'completed_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
