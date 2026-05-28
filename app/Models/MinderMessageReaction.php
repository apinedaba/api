<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MinderMessageReaction extends Model
{
    use HasFactory;

    protected $table = 'minder_message_reactions';

    protected $fillable = [
        'message_id',
        'user_id',
        'emoji',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(MinderMessage::class, 'message_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
