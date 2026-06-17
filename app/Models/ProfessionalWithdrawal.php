<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProfessionalWithdrawal extends Model
{
    use HasFactory;

    public const STATUS_REQUESTED = 'requested';
    public const STATUS_TRANSFERRED = 'transferred';
    public const STATUS_PAYOUT_CREATED = 'payout_created';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'amount',
        'currency',
        'status',
        'stripe_connect_account_id',
        'stripe_transfer_id',
        'stripe_payout_id',
        'requested_at',
        'transferred_at',
        'payout_created_at',
        'paid_at',
        'failed_at',
        'failure_code',
        'failure_message',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'float',
        'requested_at' => 'datetime',
        'transferred_at' => 'datetime',
        'payout_created_at' => 'datetime',
        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): BelongsToMany
    {
        return $this->belongsToMany(Payment::class, 'professional_withdrawal_payments')
            ->withPivot('amount')
            ->withTimestamps();
    }

    public function logs(): HasMany
    {
        return $this->hasMany(StripeTransactionLog::class);
    }
}
