<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnDemandProfessionalSetting extends Model
{
    protected $fillable = [
        'user_id', 'is_available', 'modalities', 'minimum_price', 'maximum_price',
        'response_window_minutes', 'available_until', 'next_available_at', 'meta',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'modalities' => 'array',
        'minimum_price' => 'decimal:2',
        'maximum_price' => 'decimal:2',
        'available_until' => 'datetime',
        'next_available_at' => 'datetime',
        'meta' => 'array',
    ];

    public function professional()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
