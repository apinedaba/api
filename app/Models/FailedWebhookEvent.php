<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FailedWebhookEvent extends Model
{
    protected $fillable = [
        'event_type',
        'stripe_id',
        'payload',
        'error_message',
        'error_trace',
        'attempt_count',
        'next_retry_at',
        'resolved',
        'resolved_at',
        'metadata',
    ];

    protected $casts = [
        'payload' => 'array',
        'metadata' => 'array',
        'next_retry_at' => 'datetime',
        'resolved_at' => 'datetime',
        'resolved' => 'boolean',
    ];
}
