<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentSectionTemporality extends Model
{
    protected $table = 'content_section_temporalities';

    protected $fillable = [
        'content_section_id',
        'name',
        'slug',
        'data',
        'is_active',
        'is_programmed',
        'start_date',
        'end_date',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'data' => 'json',
        'is_active' => 'boolean',
        'is_programmed' => 'boolean',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    /**
     * Relación: ContentSection asociada
     */
    public function contentSection(): BelongsTo
    {
        return $this->belongsTo(ContentSection::class);
    }

    /**
     * Relación: Administrador que creó
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Administrator::class, 'created_by');
    }

    /**
     * Relación: Administrador que actualizó
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(Administrator::class, 'updated_by');
    }

    /**
     * Scopes para facilitar consultas
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeProgrammed($query)
    {
        return $query->where('is_programmed', true);
    }

    public function scopeForSection($query, $sectionId)
    {
        return $query->where('content_section_id', $sectionId);
    }

    public function scopeCurrentlyActive($query)
    {
        return $query->where(function ($q) {
            // Activas manualmente
            $q->where('is_active', true)
                // O programadas y dentro del rango
                ->orWhere(function ($q2) {
                    $q2->where('is_programmed', true)
                        ->where('start_date', '<=', now())
                        ->where('end_date', '>=', now());
                });
        });
    }

    /**
     * Obtener la temporalidad activa para una sección
     * Prioridad: 1. Manual (is_active=true), 2. Programada en rango, 3. null
     */
    public static function getActiveForSection($sectionId): ?self
    {
        // Primero buscar manualmente activada
        $active = self::forSection($sectionId)
            ->where('is_active', true)
            ->first();

        if ($active) {
            return $active;
        }

        // Si no, buscar programada en rango
        return self::forSection($sectionId)
            ->where('is_programmed', true)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->first();
    }
}
