<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MinderSupportAppointment extends Model
{
    protected $fillable = [
        'user_id',
        'topic',
        'description',
        'scheduled_at',
        'duration_minutes',
        'status',
        'meeting_url',
        'admin_notes',
        'cancelled_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'duration_minutes' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
