<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Clinic extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_user_id',
        'name',
        'slug',
        'account_type',
        'status',
        'base_psychologist_limit',
        'addon_psychologist_slots',
        'contact',
        'settings',
        'description',
    ];

    protected $casts = [
        'contact' => 'array',
        'settings' => 'array',
        'base_psychologist_limit' => 'integer',
        'addon_psychologist_slots' => 'integer',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(ClinicMembership::class);
    }

    public function psychologists(): HasManyThrough
    {
        return $this->hasManyThrough(
            User::class,
            ClinicMembership::class,
            'clinic_id',
            'id',
            'id',
            'user_id'
        );
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function psychologistCapacity(): int
    {
        if ($this->hasUnlimitedPsychologistCapacity()) {
            return PHP_INT_MAX;
        }

        return max(0, (int) $this->base_psychologist_limit + (int) $this->addon_psychologist_slots);
    }

    public function hasUnlimitedPsychologistCapacity(): bool
    {
        if ((bool) data_get($this->settings, 'unlimited_psychologists', false)) {
            return true;
        }

        $owner = $this->relationLoaded('owner') ? $this->owner : $this->owner()->first();

        return (bool) $owner?->hasMultiClinicPlan();
    }

    public function activePsychologistCount(): int
    {
        return $this->memberships()
            ->where('role', 'psychologist')
            ->count();
    }

    public function remainingPsychologistSlots(): int
    {
        if ($this->hasUnlimitedPsychologistCapacity()) {
            return PHP_INT_MAX;
        }

        return max(0, $this->psychologistCapacity() - $this->activePsychologistCount());
    }
}
