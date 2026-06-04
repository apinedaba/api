<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToOrganization
{
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function scopeForOrganization(Builder $query, Organization|int|null $organization): Builder
    {
        $organizationId = $organization instanceof Organization ? $organization->id : $organization;

        if (!$organizationId) {
            return $query;
        }

        return $query->where($query->getModel()->getTable() . '.organization_id', $organizationId);
    }
}
