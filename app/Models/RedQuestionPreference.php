<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RedQuestionPreference extends Model
{
    protected $fillable = [
        'pregunta_id',
        'user_id',
        'is_saved',
        'is_following',
    ];

    protected $casts = [
        'is_saved' => 'boolean',
        'is_following' => 'boolean',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(RedPregunta::class, 'pregunta_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
