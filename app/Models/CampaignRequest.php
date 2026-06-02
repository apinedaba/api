<?php

namespace App\Models;

use App\Enums\CampaignRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'marketing_package_id',
        'group_campaign_id',
        'target_audience',
        'locations',
        'status',
        'starts_at',
        'ends_at',
        'campaign_url',
    ];

    protected $casts = [
        'target_audience' => 'array',
        'locations'       => 'array',
        'status'          => CampaignRequestStatus::class,
        'starts_at'       => 'datetime',
        'ends_at'         => 'datetime',
    ];

    // ─── Relations ───────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function marketingPackage(): BelongsTo
    {
        return $this->belongsTo(MarketingPackage::class);
    }

    public function groupCampaign(): BelongsTo
    {
        return $this->belongsTo(GroupCampaign::class);
    }

    public function isBlockingNewCampaign(): bool
    {
        return in_array($this->status?->value, [
            CampaignRequestStatus::Paid->value,
            CampaignRequestStatus::Active->value,
        ], true);
    }
}
