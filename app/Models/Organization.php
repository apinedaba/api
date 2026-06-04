<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory;

    public const TYPE_INDIVIDUAL = 'individual';
    public const TYPE_CLINIC = 'clinic';

    protected $fillable = [
        'name',
        'slug',
        'type',
        'logo',
        'settings',
        'owner_id',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function activeMemberships(): HasMany
    {
        return $this->memberships()->where('status', OrganizationMembership::STATUS_ACTIVE);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_user')
            ->using(OrganizationMembership::class)
            ->withPivot(['id', 'role', 'permissions', 'status'])
            ->withTimestamps();
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }
}
