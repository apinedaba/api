<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfessionalReferral extends Model
{
    public const STATUS_REGISTERED = 'registered';
    public const STATUS_TRIALING = 'trialing';
    public const STATUS_QUALIFIED = 'qualified';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'referrer_user_id',
        'referred_user_id',
        'professional_referral_code_id',
        'code',
        'status',
        'registered_at',
        'first_paid_at',
        'qualified_at',
        'cancelled_at',
        'last_status_checked_at',
        'metadata',
    ];

    protected $casts = [
        'registered_at' => 'datetime',
        'first_paid_at' => 'datetime',
        'qualified_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'last_status_checked_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_user_id');
    }

    public function referred(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    public function referralCode(): BelongsTo
    {
        return $this->belongsTo(ProfessionalReferralCode::class, 'professional_referral_code_id');
    }
}
