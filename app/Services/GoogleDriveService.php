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

    // Mapa de categorías: si el nombre del archivo o carpeta contiene
    // estas palabras clave, se asigna la categoría correspondiente.
    private array $categorias = [
        'tcc'          => ['tcc', 'cognitivo', 'conductual', 'beck', 'reestructuración', 'mindfulness'],
        'evaluacion'   => ['evaluación', 'evaluacion', 'test', 'escala', 'diagnóstico', 'diagnostico', 'batería', 'bateria', 'scid', 'banfe'],
        'trauma'       => ['trauma', 'emdr', 'tept', 'ptsd', 'disociación', 'disociacion'],
        'intervencion' => ['intervención', 'intervencion', 'crisis', 'motivacional', 'adicciones', 'suicid', 'ansiedad'],
    ];

    public function __construct()
    {
        $client = new Client();
        $client->setAuthConfig(base_path(env('GOOGLE_DRIVE_CREDENTIALS', 'storage/app/google/credentials.json')));
        $client->addScope(Drive::DRIVE_READONLY);
        $client->setApplicationName('MindMeet México');

        $this->drive    = new Drive($client);
        $this->folderId = env('GOOGLE_DRIVE_FOLDER_ID', '');
    }

    /**
     * Obtiene todos los documentos de la carpeta de Drive.
     * Resultado cacheado 60 minutos para no exceder cuotas de la API.
     */
    public function getDocumentos(?string $categoria = null, ?string $busqueda = null): array
    {
        $cacheKey = 'drive_documentos_' . md5($this->folderId);

        $documentos = Cache::remember($cacheKey, now()->addMinutes(60), function () {
            return $this->fetchDocumentosFromDrive();
        });

        // Filtrar en PHP (no en la API) para aprovechar el caché
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
     * Llama a la API de Drive y transforma los archivos al formato del frontend.
     */
    private function fetchDocumentosFromDrive(): array
    {
        try {
            $response = $this->drive->files->listFiles([
                'q'          => "'{$this->folderId}' in parents and trashed = false",
                'fields'     => 'files(id, name, description, mimeType, webViewLink, size, createdTime, properties)',
                'pageSize'   => 100,
                'orderBy'    => 'name',
            ]);

            return array_map(
                fn($file) => $this->transformFile($file),
                $response->getFiles()
            );
        } catch (\Exception $e) {
            Log::error('Google Drive API error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Transforma un archivo de Drive al formato esperado por el frontend.
     * Los metadatos (autor, año, resumen) se leen de las "propiedades personalizadas"
     * del archivo en Drive. Puedes editarlas desde Drive o desde un script.
     */
    private function transformFile(\Google\Service\Drive\DriveFile $file): array
    {
        $props    = $file->getProperties() ?? [];
        $nombre   = $file->getName();
        $categoria = $this->detectarCategoria($nombre);

        // Estima páginas desde el tamaño del archivo (PDF ~75KB por página)
        $paginas = isset($props['paginas'])
            ? $props['paginas'] . ' pág.'
            : $this->estimarPaginas((int) ($file->getSize() ?? 0));

        return [
            'id'              => $file->getId(),
            'titulo'          => $props['titulo'] ?? $this->limpiarNombre($nombre),
            'resumen'         => $props['resumen'] ?? 'Sin descripción disponible.',
            'cuerpo'          => $props['cuerpo']  ?? ($props['resumen'] ?? 'Sin descripción disponible.'),
            'autor'           => $props['autor']   ?? 'Autor desconocido',
            'anio'            => $props['anio']    ?? substr($file->getCreatedTime(), 0, 4),
            'paginas'         => $paginas,
            'categoria'       => $categoria,
            'categoria_nombre' => $this->categoriaNombre($categoria),
            'mime_type'       => $file->getMimeType(),
            'drive_url'       => $file->getWebViewLink(),
            'favorito'        => false, // se sobreescribe en el controlador
        ];
    }

    /**
     * Genera una URL de descarga directa (válida para el usuario final).
     */
    public function getDownloadUrl(string $driveId): string
    {
        // Para PDFs: fuerza descarga directa
        return "https://drive.google.com/uc?export=download&id={$driveId}";
    }

    /**
     * Genera un enlace de vista previa embebido (para mostrar en iframe).
     */
    public function getPreviewUrl(string $driveId): string
    {
        return "https://drive.google.com/file/d/{$driveId}/preview";
    }

    /**
     * Devuelve las categorías disponibles con conteo.
     */
    public function getCategorias(): array
    {
        $docs = $this->getDocumentos();

        $conteos = array_count_values(array_column($docs, 'categoria'));

        return [
            ['slug' => 'tcc',          'nombre' => 'TCC',          'color' => '#00c4b8', 'total' => $conteos['tcc'] ?? 0],
            ['slug' => 'evaluacion',   'nombre' => 'Evaluación',   'color' => '#3fc0e8', 'total' => $conteos['evaluacion'] ?? 0],
            ['slug' => 'trauma',       'nombre' => 'Trauma',       'color' => '#e05a7a', 'total' => $conteos['trauma'] ?? 0],
            ['slug' => 'intervencion', 'nombre' => 'Intervención', 'color' => '#30bfb5', 'total' => $conteos['intervencion'] ?? 0],
        ];
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function detectarCategoria(string $nombre): string
    {
        $nombreLower = mb_strtolower($nombre);
        foreach ($this->categorias as $cat => $palabras) {
            foreach ($palabras as $palabra) {
                if (str_contains($nombreLower, $palabra)) {
                    return $cat;
                }
            }
        }
        return 'intervencion'; // categoría por defecto
    }

    private function categoriaNombre(string $cat): string
    {
        return match ($cat) {
            'tcc'          => 'TCC',
            'evaluacion'   => 'Evaluación',
            'trauma'       => 'Trauma',
            'intervencion' => 'Intervención',
            default        => ucfirst($cat),
        };
    }

    private function limpiarNombre(string $nombre): string
    {
        // Quita extensión y guiones/guiones bajos
        return ucfirst(trim(preg_replace('/[-_]+/', ' ', pathinfo($nombre, PATHINFO_FILENAME))));
    }

    private function estimarPaginas(int $bytes): string
    {
        if ($bytes === 0) return 'N/D';
        $paginas = max(1, (int) round($bytes / 75000));
        return $paginas . ' pág.';
    }
}
