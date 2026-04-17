<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerCommissionItem extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'seller_referral_id',
        'vendedor_id',
        'user_id',
        'milestone',
        'amount',
        'status',
        'eligible_at',
        'cut_date',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'eligible_at' => 'date',
        'cut_date' => 'date',
        'paid_at' => 'datetime',
    ];

    public function referral(): BelongsTo
    {
        return $this->belongsTo(SellerReferral::class, 'seller_referral_id');
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(Vendedor::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
