<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GoogleDriveService
{
    private Drive $drive;
    private string $folderId;

    public function __construct()
    {
        $client = new Client();
        $credentialsPath = config('google_drive.credentials', env('GOOGLE_DRIVE_CREDENTIALS', 'storage/app/google/credentials.json'));
        $client->setAuthConfig(base_path($credentialsPath));
        $client->addScope(Drive::DRIVE_READONLY);
        $client->setApplicationName(config('app.name', 'MindMeet'));

        $this->drive    = new Drive($client);
        $this->folderId = config('google_drive.folder_id', env('GOOGLE_DRIVE_FOLDER_ID', ''));
    }

    /**
     * Obtiene todos los documentos de la carpeta raíz y sus subcarpetas.
     * Resultado cacheado según configuración (default 60 min).
     */
    public function getDocumentos(?string $categoria = null, ?string $busqueda = null): array
    {
        $cacheKey     = 'drive_documentos_' . md5($this->folderId);
        $cacheMinutes = config('google_drive.cache_minutes', 60);

        $documentos = Cache::remember($cacheKey, now()->addMinutes($cacheMinutes), function () {
            return $this->fetchRecursivo($this->folderId, null);
        });

        if ($categoria && $categoria !== 'all') {
            $documentos = array_filter($documentos, fn($d) => $d['categoria'] === $categoria);
        }

        if ($busqueda) {
            $q = mb_strtolower($busqueda);
            $documentos = array_filter($documentos, function ($d) use ($q) {
                return str_contains(mb_strtolower($d['titulo']), $q)
                    || str_contains(mb_strtolower($d['resumen']), $q);
            });
        }

        return array_values($documentos);
    }

    /**
     * Recorre recursivamente carpeta y subcarpetas.
     * El nombre de cada subcarpeta se convierte en la categoría de sus archivos.
     *
     * @param string      $folderId        ID de la carpeta a explorar
     * @param string|null $categoriaNombre Nombre de la carpeta padre (null = raíz → "General")
     */
    private function fetchRecursivo(string $folderId, ?string $categoriaNombre): array
    {
        $documentos = [];

        try {
            $response = $this->drive->files->listFiles([
                'q'        => "'{$folderId}' in parents and trashed = false",
                'fields'   => 'files(id, name, description, mimeType, webViewLink, size, createdTime, properties)',
                'pageSize' => 200,
                'orderBy'  => 'name',
            ]);

            foreach ($response->getFiles() as $file) {
                if ($file->getMimeType() === 'application/vnd.google-apps.folder') {
                    // Es subcarpeta → entrar recursivamente usando su nombre como categoría
                    $subDocs    = $this->fetchRecursivo($file->getId(), $file->getName());
                    $documentos = array_merge($documentos, $subDocs);
                } else {
                    // Es archivo → transformar con la categoría de su carpeta contenedora
                    $documentos[] = $this->transformFile($file, $categoriaNombre);
                }
            }
        } catch (\Exception $e) {
            Log::error('Google Drive API error en carpeta ' . $folderId . ': ' . $e->getMessage());
        }

        return $documentos;
    }

    /**
     * Transforma un archivo de Drive al formato esperado por el frontend.
     * La categoría viene del nombre de la subcarpeta donde está el archivo.
     */
    private function transformFile(\Google\Service\Drive\DriveFile $file, ?string $categoriaNombre): array
    {
        $props = $file->getProperties() ?? [];
        $nombre = $file->getName();

        $categoriaSlug   = $categoriaNombre ? $this->slugify($categoriaNombre) : 'general';
        $categoriaMostrar = $categoriaNombre ?? 'General';

        $paginas = isset($props['paginas'])
            ? $props['paginas'] . ' pág.'
            : $this->estimarPaginas((int) ($file->getSize() ?? 0));

        return [
            'id'               => $file->getId(),
            'titulo'           => $props['titulo'] ?? $this->limpiarNombre($nombre),
            'resumen'          => $props['resumen'] ?? 'Sin descripción disponible.',
            'cuerpo'           => $props['cuerpo']  ?? ($props['resumen'] ?? 'Sin descripción disponible.'),
            'autor'            => $props['autor']   ?? 'Autor desconocido',
            'anio'             => $props['anio']    ?? substr($file->getCreatedTime(), 0, 4),
            'paginas'          => $paginas,
            'categoria'        => $categoriaSlug,
            'categoria_nombre' => $categoriaMostrar,
            'mime_type'        => $file->getMimeType(),
            'drive_url'        => $file->getWebViewLink(),
            'favorito'         => false,
        ];
    }

    /**
     * Devuelve las categorías únicas encontradas en Drive.
     * Se generan automáticamente desde los nombres de las subcarpetas.
     */
    public function getCategorias(): array
    {
        $docs = $this->getDocumentos();

        $mapa = [];
        foreach ($docs as $doc) {
            $slug   = $doc['categoria'];
            $nombre = $doc['categoria_nombre'];
            if (!isset($mapa[$slug])) {
                $mapa[$slug] = [
                    'slug'   => $slug,
                    'nombre' => $nombre,
                    'color'  => $this->colorParaCategoria($slug),
                    'total'  => 0,
                ];
            }
            $mapa[$slug]['total']++;
        }

        return array_values($mapa);
    }

    /**
     * Descarga el archivo desde Drive usando la Service Account
     * y devuelve su contenido, nombre y tipo MIME.
     */
    public function downloadFileRaw(string $driveId): array
    {
        try {
            $fileMeta = $this->drive->files->get($driveId, ['fields' => 'name, mimeType']);
            $response = $this->drive->files->get($driveId, ['alt' => 'media']);

            return [
                'name'      => $fileMeta->getName(),
                'mime_type' => $fileMeta->getMimeType(),
                'content'   => $response->getBody()->getContents(),
            ];
        } catch (\Exception $e) {
            Log::error('Error descargando archivo de Drive: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Genera una URL de previsualización embebida (para iframe).
     */
    public function getPreviewUrl(string $driveId): string
    {
        return "https://drive.google.com/file/d/{$driveId}/preview";
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Convierte el nombre de una carpeta a slug.
     * Ej: "Terapia Cognitiva" → "terapia-cognitiva"
     */
    private function slugify(string $texto): string
    {
        $texto = mb_strtolower($texto, 'UTF-8');
        $texto = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ', 'à', 'è', 'ì', 'ò', 'ù'],
            ['a', 'e', 'i', 'o', 'u', 'u', 'n', 'a', 'e', 'i', 'o', 'u'],
            $texto
        );
        $texto = preg_replace('/[^a-z0-9]+/', '-', $texto);
        return trim($texto, '-');
    }

    /**
     * Asigna un color consistente a cada categoría basado en su slug.
     * El mismo nombre de carpeta siempre tendrá el mismo color.
     */
    private function colorParaCategoria(string $slug): string
    {
        $colores = [
            '#00c4b8',
            '#3fc0e8',
            '#30bfb5',
            '#007ca9',
            '#e05a7a',
            '#f59e0b',
            '#8b5cf6',
            '#10b981',
        ];
        $index = abs(crc32($slug)) % count($colores);
        return $colores[$index];
    }

    private function limpiarNombre(string $nombre): string
    {
        return ucfirst(trim(preg_replace('/[-_]+/', ' ', pathinfo($nombre, PATHINFO_FILENAME))));
    }

    private function estimarPaginas(int $bytes): string
    {
        if ($bytes === 0) return 'N/D';
        $paginas = max(1, (int) round($bytes / 75000));
        return $paginas . ' pág.';
    }
}
