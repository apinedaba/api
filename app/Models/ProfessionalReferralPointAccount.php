<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProfessionalReferralPointAccount extends Model
{
    protected $fillable = [
        'user_id',
        'balance_points',
        'lifetime_earned_points',
        'lifetime_redeemed_points',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(ProfessionalReferralPointTransaction::class, 'user_id', 'user_id');
    }
}
