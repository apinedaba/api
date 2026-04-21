<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MinderMessage extends Model
{
    use HasFactory;

    protected $table = 'minder_messages';

    protected $fillable = [
        'group_id',
        'user_id',
        'body',
        'parent_id',
        'is_deleted',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(MinderGroup::class, 'group_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(MinderMessageReaction::class, 'message_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(MinderReport::class, 'message_id');
    }
}
