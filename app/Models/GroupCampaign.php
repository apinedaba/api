<?php

namespace App\Models;

use App\Enums\GroupCampaignStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GroupCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'marketing_package_id',
        'current_slots',
        'status',
    ];

    protected $casts = [
        'status' => GroupCampaignStatus::class,
    ];

    // ─── Relations ───────────────────────────────────────────────────────────

    public function marketingPackage(): BelongsTo
    {
        return $this->belongsTo(MarketingPackage::class);
    }

    public function campaignRequests(): HasMany
    {
        return $this->hasMany(CampaignRequest::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Indica si la campaña grupal todavía acepta nuevos participantes.
     *
     * Cuenta las solicitudes en estado activo (pendientes de pago + pagadas + activas)
     * para prevenir overbooking antes de que el webhook confirme el slot pagado.
     */
    public function hasAvailableSlots(): bool
    {
        $maxSlots = $this->marketingPackage?->max_slots;

        if ($maxSlots === null) {
            return true;
        }

        $occupied = $this->campaignRequests()
            ->whereIn('status', ['pending_payment', 'paid', 'active'])
            ->count();

        return $occupied < $maxSlots;
    }
}
