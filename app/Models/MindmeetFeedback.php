<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MindmeetFeedback extends Model
{
    use HasFactory;

    protected $table = 'mindmeet_feedback';

    protected $fillable = [
        'user_id',
        'rating',
        'team_message',
        'improvement_feedback',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
