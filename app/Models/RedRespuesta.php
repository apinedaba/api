<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RedRespuesta extends Model
{
    use HasFactory;

    protected $table = 'red_respuestas';

    protected $fillable = [
        'pregunta_id',
        'user_id',
        'contenido',
        'is_deleted',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
    ];

    // ─── Relaciones ───────────────────────────────────────────────

    public function pregunta(): BelongsTo
    {
        return $this->belongsTo(RedPregunta::class, 'pregunta_id');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')
            ->select(['id', 'name', 'image', 'personales']);
    }

    public function votos(): HasMany
    {
        return $this->hasMany(RedVoto::class, 'respuesta_id');
    }

    // ─── Helpers ──────────────────────────────────────────────────

    public function esAutor(int $userId): bool
    {
        return $this->user_id === $userId;
    }

    public function yoVote(int $userId): bool
    {
        return $this->votos()->where('user_id', $userId)->exists();
    }
}
