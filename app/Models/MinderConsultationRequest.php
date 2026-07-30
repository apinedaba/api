<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MinderConsultationRequest extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'sender_id',
        'recipient_id',
        'red_pregunta_id',
        'red_respuesta_id',
        'minder_group_id',
        'subject',
        'message',
        'status',
        'accepted_at',
        'resolved_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(RedPregunta::class, 'red_pregunta_id');
    }

    public function answer(): BelongsTo
    {
        return $this->belongsTo(RedRespuesta::class, 'red_respuesta_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(MinderGroup::class, 'minder_group_id');
    }
}
