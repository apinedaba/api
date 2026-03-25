<?php

namespace App\Http\Controllers;

use App\Models\DocumentacionFavorito;
use App\Services\GoogleDriveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentacionController extends Controller
{
    public function __construct(private GoogleDriveService $drive) {}

    /**
     * GET /api/documentacion
     * Parámetros opcionales: ?categoria=tcc&busqueda=activación
     */
    public function index(Request $request): JsonResponse
    {
        $docs = $this->drive->getDocumentos(
            categoria: $request->query('categoria'),
            busqueda: $request->query('busqueda'),
        );

        // Marca los favoritos del psicólogo autenticado
        $favIds = DocumentacionFavorito::where('psicologo_id', auth()->id())
            ->pluck('drive_id')
            ->toArray();

        $docs = array_map(function ($doc) use ($favIds) {
            $doc['favorito'] = in_array($doc['id'], $favIds);
            return $doc;
        }, $docs);

        return response()->json(['data' => $docs]);
    }

    /**
     * GET /api/documentacion/categorias
     */
    public function categorias(): JsonResponse
    {
        return response()->json([
            'data' => $this->drive->getCategorias(),
        ]);
    }

    /**
     * GET /api/documentacion/favoritos
     */
    public function favoritos(): JsonResponse
    {
        $favIds = DocumentacionFavorito::where('psicologo_id', auth()->id())
            ->pluck('drive_id')
            ->toArray();

        $docs = $this->drive->getDocumentos();

        $favoritos = array_filter($docs, fn($d) => in_array($d['id'], $favIds));

        $favoritos = array_map(function ($doc) {
            $doc['favorito'] = true;
            return $doc;
        }, array_values($favoritos));

        return response()->json(['data' => $favoritos]);
    }

    /**
     * POST /api/documentacion/{driveId}/favorito
     * Toggle: si existe lo elimina, si no existe lo crea.
     */
    public function toggleFavorito(string $driveId): JsonResponse
    {
        $existing = DocumentacionFavorito::where('psicologo_id', auth()->id())
            ->where('drive_id', $driveId)
            ->first();

        if ($existing) {
            $existing->delete();
            $esFavorito = false;
        } else {
            DocumentacionFavorito::create([
                'psicologo_id' => auth()->id(),
                'drive_id'     => $driveId,
            ]);
            $esFavorito = true;
        }

        return response()->json([
            'favorito' => $esFavorito,
            'drive_id' => $driveId,
        ]);
    }

    /**
     * GET /api/documentacion/{driveId}/download
     * Devuelve la URL de descarga directa del archivo en Drive.
     */
    public function download(string $driveId): JsonResponse
    {
        return response()->json([
            'url'        => $this->drive->getDownloadUrl($driveId),
            'preview_url' => $this->drive->getPreviewUrl($driveId),
        ]);
    }
}
