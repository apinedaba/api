<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProfessionalReferralRewardRule extends Model
{
    public const TYPE_FREE_MONTHS = 'free_months';

    protected $fillable = [
        'name',
        'required_qualified_referrals',
        'reward_type',
        'reward_months',
        'is_active',
        'sort_order',
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function rewards(): HasMany
    {
        return $this->hasMany(ProfessionalReferralReward::class);
    }
}
