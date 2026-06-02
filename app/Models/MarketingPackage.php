<?php

namespace App\Models;

use App\Enums\MarketingPackageType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'type',
        'price',
        'max_slots',
        'stripe_product_id',
        'is_active',
    ];

    protected $casts = [
        'type'      => MarketingPackageType::class,
        'price'     => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // ─── Relations ───────────────────────────────────────────────────────────

    public function campaignRequests(): HasMany
    {
        return $this->hasMany(CampaignRequest::class);
    }

    public function groupCampaigns(): HasMany
    {
        return $this->hasMany(GroupCampaign::class);
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
