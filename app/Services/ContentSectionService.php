<?php

namespace App\Services;

use App\Models\ContentSection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Exception;

class ContentSectionService
{
    private const CACHE_TTL = 3600; // 1 hora
    private const CACHE_KEY_PREFIX = 'content_section:';

    /**
     * Obtener datos de una sección (con cache)
     */
    public function get(string $key): ?array
    {
        try {
            // Intentar obtener del cache
            $cacheKey = self::CACHE_KEY_PREFIX . $key;

            return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($key) {
                // Intentar obtener de la BD
                $section = ContentSection::getByKey($key);

                if ($section) {
                    return $section->data;
                }

                // Fallback: intentar cargar del archivo
                return $this->loadFromFile($key);
            });
        } catch (Exception $e) {
            Log::error("Error getting content section '{$key}'", [
                'error' => $e->getMessage(),
            ]);

            // Último recurso: cargar del archivo
            return $this->loadFromFile($key);
        }
    }

    /**
     * Actualizar una sección
     */
    public function update(string $key, array $data, int $userId = null, string $reason = null): ContentSection
    {
        try {
            $section = ContentSection::getByKey($key);

            if ($section) {
                // Guardar versión anterior
                $section->saveVersion($userId, $reason);
                $section->version++;
            } else {
                // Crear nueva sección
                $section = new ContentSection([
                    'key' => $key,
                    'version' => 1,
                ]);
            }

            // Actualizar datos
            $section->data = $data;
            $section->updated_by = $userId;
            $section->save();

            // Invalidar cache
            $this->invalidateCache($key);

            // Sincronizar con archivo como respaldo
            $this->syncToFile($key, $data);

            return $section;
        } catch (Exception $e) {
            Log::error("Error updating content section '{$key}'", [
                'error' => $e->getMessage(),
            ]);

            // Fallback: guardar en archivo si la BD falla
            $this->syncToFile($key, $data);

            throw $e;
        }
    }

    /**
     * Cargar datos de un archivo JSON
     */
    private function loadFromFile(string $key): ?array
    {
        try {
            $path = $this->getFilePath($key);

            if (!File::exists($path)) {
                return null;
            }

            $content = File::get($path);
            return json_decode($content, true);
        } catch (Exception $e) {
            Log::warning("Could not load content section from file: {$key}", [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Sincronizar con archivo como respaldo
     */
    private function syncToFile(string $key, array $data): void
    {
        try {
            $path = $this->getFilePath($key);
            $directory = dirname($path);

            if (!File::isDirectory($directory)) {
                File::makeDirectory($directory, 0755, true);
            }

            File::put(
                $path,
                json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
        } catch (Exception $e) {
            Log::warning("Could not sync content section to file: {$key}", [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Obtener la ruta del archivo para una sección
     */
    private function getFilePath(string $key): string
    {
        return storage_path("app/content/{$key}.json");
    }

    /**
     * Invalidar cache de una sección
     */
    public function invalidateCache(string $key): void
    {
        Cache::forget(self::CACHE_KEY_PREFIX . $key);
    }

    /**
     * Invalidar todo el cache
     */
    public function invalidateAllCache(): void
    {
        Cache::flush(); // Opción más agresiva, o usar tags de cache
    }

    /**
     * Restaurar a una versión anterior
     */
    public function restoreVersion(string $key, int $versionNumber, int $userId = null): bool
    {
        try {
            $section = ContentSection::getByKey($key);

            if (!$section) {
                return false;
            }

            $success = $section->restoreToVersion($versionNumber, $userId);

            if ($success) {
                $this->invalidateCache($key);
                $this->syncToFile($key, $section->data);
            }

            return $success;
        } catch (Exception $e) {
            Log::error("Error restoring content section version: {$key}", [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Obtener historial de cambios
     */
    public function getHistory(string $key)
    {
        $section = ContentSection::getByKey($key);
        return $section ? $section->getChangeHistory() : collect();
    }
}
