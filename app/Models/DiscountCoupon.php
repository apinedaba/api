<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DiscountCoupon extends Model
{
    protected $fillable = [
        'user_id',
        'code',
        'name',
        'description',
        'discount_type',
        'discount_value',
        'applies_to',
        'starts_at',
        'ends_at',
        'max_redemptions',
        'redeemed_count',
        'is_active',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'max_redemptions' => 'integer',
        'redeemed_count' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'is_currently_available',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeCurrentlyAvailable(Builder $query): Builder
    {
        $now = Carbon::now();

        return $query
            ->where('is_active', true)
            ->where(function (Builder $dateQuery) use ($now) {
                $dateQuery->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $dateQuery) use ($now) {
                $dateQuery->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->where(function (Builder $usageQuery) {
                $usageQuery
                    ->whereNull('max_redemptions')
                    ->orWhereColumn('redeemed_count', '<', 'max_redemptions');
            });
    }

    public function getIsCurrentlyAvailableAttribute(): bool
    {
        $now = Carbon::now();

        if (!$this->is_active) {
            return false;
        }

        if ($this->starts_at && $this->starts_at->gt($now)) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->lt($now)) {
            return false;
        }

        if ($this->max_redemptions !== null && $this->redeemed_count >= $this->max_redemptions) {
            return false;
        }

        return true;
    }

    public function appliesToLeadType(string $leadType): bool
    {
        if ($this->applies_to === 'all') {
            return true;
        }

        return match ($leadType) {
            'package' => $this->applies_to === 'packages',
            default => $this->applies_to === 'sessions',
        };
    }

    public function calculateDiscountForAmount(float $amount): float
    {
        if ($amount <= 0) {
            return 0;
        }

        $discount = (float) $this->discount_value;
        $discountAmount = $this->discount_type === 'percent'
            ? $amount * ($discount / 100)
            : $discount;

        return round(min($amount, max($discountAmount, 0)), 2);
    }
}
