<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RedReport extends Model
{
    public const TARGET_QUESTION = 'question';
    public const TARGET_ANSWER = 'answer';

    protected $fillable = [
        'target_type',
        'target_id',
        'reported_by',
        'reason',
        'details',
        'status',
        'resolution_action',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(Administrator::class, 'resolved_by');
    }

    public function target(): RedPregunta|RedRespuesta|null
    {
        return match ($this->target_type) {
            self::TARGET_QUESTION => RedPregunta::withTrashed()->find($this->target_id),
            self::TARGET_ANSWER => RedRespuesta::find($this->target_id),
            default => null,
        };
    }
}
