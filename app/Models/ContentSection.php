<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Administrator;

class ContentSection extends Model
{
    protected $table = 'content_sections';

    protected $fillable = [
        'key',
        'data',
        'version',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'data' => 'array',
        'version' => 'integer',
    ];

    /**
     * Relación: Usuario administrador que creó el contenido
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Administrator::class, 'created_by');
    }

    /**
     * Relación: Usuario administrador que actualizó el contenido
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(Administrator::class, 'updated_by');
    }

    /**
     * Relación: Historial de versiones
     */
    public function versions(): HasMany
    {
        return $this->hasMany(ContentSectionVersion::class);
    }

    /**
     * Relación: Temporalidades (eventos con contenido temporal)
     */
    public function temporalities(): HasMany
    {
        return $this->hasMany(ContentSectionTemporality::class);
    }

    /**
     * Obtener una sección por clave
     */
    public static function getByKey(string $key): ?self
    {
        return self::where('key', $key)->first();
    }

    /**
     * Obtener datos de una sección
     */
    public static function getData(string $key): ?array
    {
        $section = self::getByKey($key);
        return $section?->data;
    }

    /**
     * Guardar versión anterior antes de actualizar
     */
    public function saveVersion(int $userId = null, string $reason = null): void
    {
        if ($this->version > 1) { // No guardar versiones de la versión 1
            ContentSectionVersion::create([
                'content_section_id' => $this->id,
                'version_number' => $this->version - 1,
                'data' => $this->getOriginal('data'), // Datos antes del cambio
                'changed_by' => $userId,
                'change_reason' => $reason,
            ]);
        }
    }

    /**
     * Restaurar a una versión anterior
     */
    public function restoreToVersion(int $versionNumber, int $userId = null): bool
    {
        $version = $this->versions()->where('version_number', $versionNumber)->first();

        if (!$version) {
            return false;
        }

        $this->saveVersion($userId, "Restored from version {$versionNumber}");
        $this->update([
            'data' => $version->data,
            'version' => $versionNumber + 1,
            'updated_by' => $userId,
        ]);

        return true;
    }

    /**
     * Obtener el historial de cambios
     */
    public function getChangeHistory()
    {
        return $this->versions()
            ->with('changedBy')
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
