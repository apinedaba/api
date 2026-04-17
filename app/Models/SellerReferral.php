<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SellerReferral extends Model
{
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
    ];

    protected $casts = [
        'registered_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'first_activated_at' => 'datetime',
        'last_status_checked_at' => 'datetime',
        'metadata' => 'array',
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
}
