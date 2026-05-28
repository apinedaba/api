<?php

namespace App\Services;

use App\Models\ContentSectionTemporality;
use App\Models\ContentSection;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class TemporalityService
{
    const CACHE_TTL = 3600; // 1 hora

    /**
     * Normalizar datos: convertir strings JSON a arrays recursivamente
     */
    private function normalizeData($data)
    {
        if (is_string($data)) {
            $decoded = json_decode($data, true);
            return $decoded !== null ? $this->normalizeData($decoded) : $data;
        }

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->normalizeData($value);
            }
        }

        return $data;
    }

    /**
     * Obtener la temporalidad activa para una sección
     * Prioridad: 1. Manual (is_active=true), 2. Programada en rango, 3. null
     */
    public function getActiveTemporality(string $sectionKey): ?ContentSectionTemporality
    {
        $section = ContentSection::where('key', $sectionKey)->first();
        if (!$section) {
            return null;
        }

        return ContentSectionTemporality::getActiveForSection($section->id);
    }

    /**
     * Obtener datos de una temporalidad activa, o fallback a original
     */
    public function getActiveContent(string $sectionKey): array
    {
        // Primero intentar obtener temporalidad activa
        $temporality = $this->getActiveTemporality($sectionKey);

        if ($temporality) {
            $data = $temporality->data;
            // Normalizar datos (parse strings JSON anidados)
            $data = $this->normalizeData($data);
            return is_array($data) ? $data : [];
        }

        // Fallback a contenido original
        $section = ContentSection::where('key', $sectionKey)->first();
        if (!$section) {
            return [];
        }

        $data = $section->data;
        // Normalizar datos
        $data = $this->normalizeData($data);
        return is_array($data) ? $data : [];
    }

    /**
     * Activar una temporalidad manualmente
     * Desactiva la anterior si la hay
     */
    public function activateTemporality(int $temporalityId, int $adminId): bool
    {
        $temporality = ContentSectionTemporality::findOrFail($temporalityId);
        $sectionId = $temporality->content_section_id;

        // Desactivar todas las temporalidades activas de esta sección
        ContentSectionTemporality::forSection($sectionId)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        // Activar esta
        $temporality->update([
            'is_active' => true,
            'updated_by' => $adminId,
        ]);

        // Invalidar cache
        Cache::forget("temporality:active:{$sectionId}");

        return true;
    }

    /**
     * Desactivar una temporalidad
     */
    public function deactivateTemporality(int $temporalityId): bool
    {
        $temporality = ContentSectionTemporality::findOrFail($temporalityId);

        $temporality->update([
            'is_active' => false,
        ]);

        Cache::forget("temporality:active:{$temporality->content_section_id}");

        return true;
    }

    /**
     * Crear una nueva temporalidad (copia del contenido actual)
     */
    public function createTemporality(
        string $sectionKey,
        string $name,
        string $slug,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        ?string $notes = null,
        int $adminId = null
    ): ContentSectionTemporality {
        $section = ContentSection::where('key', $sectionKey)->firstOrFail();

        // Validar slug único
        $existing = ContentSectionTemporality::where('slug', $slug)->first();
        if ($existing) {
            throw new \InvalidArgumentException("El slug '{$slug}' ya existe");
        }

        // Copiar datos actuales
        $data = $section->data;

        $temporality = ContentSectionTemporality::create([
            'content_section_id' => $section->id,
            'name' => $name,
            'slug' => $slug,
            'data' => $data,
            'is_active' => false,
            'is_programmed' => !is_null($startDate) || !is_null($endDate),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'notes' => $notes,
            'created_by' => $adminId,
            'updated_by' => $adminId,
        ]);

        Cache::forget("temporality:active:{$section->id}");

        return $temporality;
    }

    /**
     * Actualizar datos de una temporalidad
     */
    public function updateTemporality(int $temporalityId, array $data, int $adminId): ContentSectionTemporality
    {
        $temporality = ContentSectionTemporality::findOrFail($temporalityId);

        $temporality->update([
            'data' => $data,
            'updated_by' => $adminId,
        ]);

        // Refrescar modelo desde BD para asegurar casting correcto
        $temporality->refresh();

        Cache::forget("temporality:active:{$temporality->content_section_id}");

        return $temporality;
    }

    /**
     * Actualizar propiedades de una temporalidad (nombre, fechas, etc)
     */
    public function updateTemporalityProperties(
        int $temporalityId,
        array $properties,
        int $adminId
    ): ContentSectionTemporality {
        $temporality = ContentSectionTemporality::findOrFail($temporalityId);

        $allowedFields = ['name', 'slug', 'start_date', 'end_date', 'notes'];
        $updateData = array_intersect_key($properties, array_flip($allowedFields));
        $updateData['updated_by'] = $adminId;

        // Si cambió el rango de fechas, actualizar is_programmed
        if (isset($properties['start_date']) || isset($properties['end_date'])) {
            $startDate = $properties['start_date'] ?? $temporality->start_date;
            $endDate = $properties['end_date'] ?? $temporality->end_date;
            $updateData['is_programmed'] = !is_null($startDate) || !is_null($endDate);
        }

        $temporality->update($updateData);

        Cache::forget("temporality:active:{$temporality->content_section_id}");

        return $temporality;
    }

    /**
     * Eliminar una temporalidad
     */
    public function deleteTemporality(int $temporalityId): bool
    {
        $temporality = ContentSectionTemporality::findOrFail($temporalityId);
        $sectionId = $temporality->content_section_id;

        // Si estaba activa, desactivar
        if ($temporality->is_active) {
            $temporality->update(['is_active' => false]);
        }

        $temporality->delete();

        Cache::forget("temporality:active:{$sectionId}");

        return true;
    }

    /**
     * Desactivar temporalidades programadas que han vencido
     * Llamado por cron job
     */
    public function deactivateExpiredTemporalities(): int
    {
        $count = ContentSectionTemporality::where('is_active', true)
            ->where('is_programmed', true)
            ->where('end_date', '<', now())
            ->update(['is_active' => false]);

        if ($count > 0) {
            Cache::flush(); // Limpiar todo el cache de temporalidades
        }

        return $count;
    }

    /**
     * Obtener todas las temporalidades de una sección
     */
    public function getTemporalities(string $sectionKey, $withTrashed = false)
    {
        $section = ContentSection::where('key', $sectionKey)->firstOrFail();

        $query = ContentSectionTemporality::forSection($section->id)
            ->orderBy('created_at', 'desc');

        return $query->get();
    }
}
