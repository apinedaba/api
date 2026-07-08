<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfessionalReferralReward extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_APPLIED = 'applied';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'referrer_user_id',
        'professional_referral_reward_rule_id',
        'milestone_key',
        'required_qualified_referrals',
        'reward_type',
        'reward_months',
        'status',
        'earned_at',
        'approved_at',
        'applied_at',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'earned_at' => 'datetime',
        'approved_at' => 'datetime',
        'applied_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_user_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(ProfessionalReferralRewardRule::class, 'professional_referral_reward_rule_id');
    }
}
