<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MinderSupportMessage extends Model
{
    use HasFactory;

    protected $table = 'minder_support_messages';

    protected $fillable = [
        'thread_id',
        'sender_type',
        'sender_id',
        'body',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(MinderSupportThread::class, 'thread_id');
    }

    public function sender(): MorphTo
    {
        return $this->morphTo();
    }
}
