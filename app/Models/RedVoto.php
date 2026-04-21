<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RedVoto extends Model
{
    use HasFactory;

    protected $table = 'red_votos';

    protected $fillable = [
        'respuesta_id',
        'user_id',
    ];

    public function respuesta(): BelongsTo
    {
        return $this->belongsTo(RedRespuesta::class, 'respuesta_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
