<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MinderGroup extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'avatar',
        'type',
        'is_dm',
        'is_active',
        'max_members',
        'created_by',
        'rules',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_dm'     => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $group) {
            if (empty($group->slug)) {
                $group->slug = Str::slug($group->name) . '-' . Str::random(6);
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'minder_group_members', 'group_id', 'user_id')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    public function groupMembers(): HasMany
    {
        return $this->hasMany(MinderGroupMember::class, 'group_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MinderMessage::class, 'group_id');
    }

    public function bans(): HasMany
    {
        return $this->hasMany(MinderBan::class, 'group_id');
    }

    public function isActiveBan(int $userId): bool
    {
        return $this->bans()
            ->where('user_id', $userId)
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();
    }
}
