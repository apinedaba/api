<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class RedPregunta extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'red_preguntas';

    protected $fillable = [
        'user_id',
        'category_id',
        'titulo',
        'descripcion',
        'tags',
        'mejor_respuesta_id',
        'views_count',
        'is_active',
        'status',
        'close_reason',
        'close_note',
        'closed_at',
        'edited_at',
    ];

    protected $casts = [
        'tags'       => 'array',
        'is_active'  => 'boolean',
        'views_count' => 'integer',
        'closed_at' => 'datetime',
        'edited_at' => 'datetime',
    ];

    // ─── Relaciones ───────────────────────────────────────────────

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')
            ->select(['id', 'name', 'image', 'personales']);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(RedCategory::class, 'category_id');
    }

    public function respuestas(): HasMany
    {
        return $this->hasMany(RedRespuesta::class, 'pregunta_id')
            ->where('is_deleted', false);
    }

    public function todasRespuestas(): HasMany
    {
        return $this->hasMany(RedRespuesta::class, 'pregunta_id');
    }

    public function mejorRespuesta(): BelongsTo
    {
        return $this->belongsTo(RedRespuesta::class, 'mejor_respuesta_id');
    }

    public function preferencias(): HasMany
    {
        return $this->hasMany(RedQuestionPreference::class, 'pregunta_id');
    }

    public function votos(): HasManyThrough
    {
        return $this->hasManyThrough(
            RedVoto::class,
            RedRespuesta::class,
            'pregunta_id',
            'respuesta_id'
        )->where('red_respuestas.is_deleted', false);
    }

    // ─── Helpers ──────────────────────────────────────────────────

    public function esAutor(int $userId): bool
    {
        return $this->user_id === $userId;
    }

    public function ultimaActividad(): ?string
    {
        $ultima = $this->todasRespuestas()
            ->latest()
            ->value('created_at');

        return $ultima ?? $this->created_at;
    }
}
