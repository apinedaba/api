<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SellerReferral extends Model
{
    public const SOURCE_SELLER_QR = 'seller_qr';
    public const SOURCE_RECOVERY = 'recovery';
    public const SOURCE_MANUAL = 'manual';

    public const PIPELINE_ASSIGNED = 'assigned';
    public const PIPELINE_CONTACTED = 'contacted';
    public const PIPELINE_FOLLOW_UP = 'follow_up';
    public const PIPELINE_ONBOARDING = 'onboarding';
    public const PIPELINE_AWAITING_PAYMENT = 'awaiting_payment';
    public const PIPELINE_RECOVERED = 'recovered';
    public const PIPELINE_NO_RESPONSE = 'no_response';
    public const PIPELINE_NOT_INTERESTED = 'not_interested';

    protected $fillable = [
        'vendedor_id',
        'user_id',
        'referral_code',
        'status',
        'registered_at',
        'trial_ends_at',
        'first_activated_at',
        'last_status_checked_at',
        'metadata',
        'source',
        'pipeline_status',
        'assigned_at',
        'claimed_until',
        'last_contacted_at',
        'next_follow_up_at',
        'contact_attempts',
        'last_contact_channel',
        'contact_note',
        'converted_at',
    ];

    protected $casts = [
        'registered_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'first_activated_at' => 'datetime',
        'last_status_checked_at' => 'datetime',
        'metadata' => 'array',
        'assigned_at' => 'datetime',
        'claimed_until' => 'datetime',
        'last_contacted_at' => 'datetime',
        'next_follow_up_at' => 'datetime',
        'converted_at' => 'datetime',
    ];

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(Vendedor::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function commissionItems(): HasMany
    {
        return $this->hasMany(SellerCommissionItem::class);
    }

    public function isRecoveryOpportunity(): bool
    {
        return in_array($this->source, [self::SOURCE_RECOVERY, self::SOURCE_MANUAL], true);
    }
}
