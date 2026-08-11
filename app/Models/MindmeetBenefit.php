<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MindmeetBenefit extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'title',
        'partner_name',
        'category',
        'description',
        'terms',
        'coupon_code',
        'image_url',
        'image_public_id',
        'redirect_url',
        'contact_label',
        'contact_url',
        'starts_at',
        'ends_at',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scopeAvailable(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where('is_active', true)
            ->where(function (Builder $dateQuery) use ($now) {
                $dateQuery->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $dateQuery) use ($now) {
                $dateQuery->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            });
    }
}
