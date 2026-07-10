<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProfessionalReferralCode extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const REWARD_FREE_MONTHS = 'free_months';
    public const REWARD_MENTEPUNTOS = 'mentepuntos';

    protected $fillable = [
        'user_id',
        'code',
        'status',
        'reward_preference',
        'clicks_count',
        'last_used_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(ProfessionalReferral::class);
    }
}
