<?php

namespace App\Http\Controllers;

use App\Services\HomeContentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use InvalidArgumentException;
use Inertia\Inertia;
use Illuminate\Http\JsonResponse;
use Cloudinary\Api\Upload\UploadApi;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class HomeContentController extends Controller
{
    public function __construct(
        protected HomeContentService $homeContentService
    ) {}

    public function index()
    {
        $home = $this->homeContentService->read();

        return Inertia::render('HomeContent', [
            'editor' => $this->homeContentService->splitForEditor($home),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'hero' => ['nullable', 'string'],
            'homeSlider' => ['required', 'string'],
            'promotions' => ['required', 'string'],
            'especialidades' => ['required', 'string'],
            'sections' => ['required', 'string'],
            'uploadedImages' => ['nullable', 'string'],
            'extraBlocks' => ['required', 'string'],
        ]);

        try {
            $home = $this->homeContentService->updateFromPayload($data);
            $this->homeContentService->write($home);
        } catch (InvalidArgumentException $exception) {
            return Redirect::back()->withErrors([
                'json' => $exception->getMessage(),
            ])->withInput();
        }

        return Redirect::route('home-content.index')->with('success', 'Contenido del home actualizado correctamente.');
    }

    public function uploadImage(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'image' => ['required', 'image', 'max:5120'], // 5MB max
                'category' => ['nullable', 'string'],
            ]);

            $file = $request->file('image');
            $category = $request->input('category', 'general');

            // Validación de dimensiones según la categoría
            $dimensions = $this->getImageDimensions($file->getRealPath());
            $this->validateImageDimensions($dimensions, $category);

            // Subir a Cloudinary usando UploadApi (librería oficial)
            $result = (new UploadApi())->upload($file->getRealPath(), [
                'folder' => 'mindmeet-home',
            ]);

            $imageUrl = $result['secure_url'];
            $publicId = $result['public_id'] ?? null;

            // Agregar la imagen al historial
            $home = $this->homeContentService->read();
            if (!isset($home['uploadedImages'])) {
                $home['uploadedImages'] = ['recent' => []];
            }
            if (!isset($home['uploadedImages']['recent'])) {
                $home['uploadedImages']['recent'] = [];
            }

            // Agregar nueva imagen al inicio del historial (máximo 50 imágenes)
            array_unshift($home['uploadedImages']['recent'], [
                'url' => $imageUrl,
                'publicId' => $publicId,
                'uploadedAt' => now()->toIso8601String(),
                'category' => $category,
                'dimensions' => $dimensions,
            ]);

            // Mantener solo las últimas 50 imágenes
            $home['uploadedImages']['recent'] = array_slice($home['uploadedImages']['recent'], 0, 50);

            $this->homeContentService->write($home);

            return response()->json([
                'success' => true,
                'url' => $imageUrl,
                'publicId' => $publicId,
                'dimensions' => $dimensions,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation error in uploadImage', ['errors' => $e->errors()]);
            return response()->json([
                'success' => false,
                'error' => 'Validación fallida: ' . collect($e->errors())->flatten()->first(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Unexpected error in uploadImage', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtiene lista de profesionales disponibles de la BD para que el admin los asigne a secciones
     */
    public function getProfessionals(): JsonResponse
    {
        try {
            $professionals = User::where('activo', true)
                ->where('identity_verification_status', 'approved')
                ->get()
                ->map(function ($user) {
                    // Obtener especialidades desde session_packages
                    $specialties = DB::table('session_packages')
                        ->where('user_id', $user->id)
                        ->where('is_active', true)
                        ->pluck('categoria')
                        ->unique()
                        ->map(fn($cat) => is_array($cat) ? implode(', ', $cat) : $cat)
                        ->values()
                        ->toArray();

                    // Calcular rating promedio y contar reviews
                    $reviewsData = DB::table('psychologist_reviews')
                        ->where('professional_id', $user->id)
                        ->where('approved', true)
                        ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as total_reviews')
                        ->first();

                    // Obtener precio base (más barato disponible)
                    $minPrice = DB::table('session_packages')
                        ->where('user_id', $user->id)
                        ->where('is_active', true)
                        ->min('base_session_price');

                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'image' => $user->image ?? '/default-avatar.png',
                        'specialty' => !empty($specialties) ? implode(', ', array_filter($specialties)) : 'Especialidad no definida',
                        'rating' => $reviewsData ? round((float)$reviewsData->avg_rating, 1) : 0,
                        'reviews' => (int)($reviewsData?->total_reviews ?? 0),
                        'price' => $minPrice ?? 0,
                    ];
                })
                ->sortByDesc('rating')
                ->values();

            return response()->json([
                'success' => true,
                'professionals' => $professionals,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in getProfessionals', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Error al obtener profesionales: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function getImageDimensions(string $filePath): array
    {
        $imageInfo = getimagesize($filePath);
        return [
            'width' => $imageInfo[0],
            'height' => $imageInfo[1],
            'ratio' => round($imageInfo[0] / $imageInfo[1], 2),
        ];
    }

    private function validateImageDimensions(array $dimensions, string $category): void
    {
        $rules = [
            'hero' => [
                'minWidth' => 1200,
                'minHeight' => 400,
                'expectedRatio' => '3.0', // 16:5.33 aprox
                'tolerance' => 0.5,
            ],
            'slider' => [
                'minWidth' => 800,
                'minHeight' => 400,
                'expectedRatio' => '2.0', // 16:8 aprox
                'tolerance' => 0.8,
            ],
            'promotions' => [
                'minWidth' => 600,
                'minHeight' => 300,
                'expectedRatio' => '2.0', // 16:8 aprox
                'tolerance' => 0.8,
            ],
            'profesionales' => [
                'minWidth' => 300,
                'minHeight' => 300,
                'expectedRatio' => '1.0', // Cuadrado
                'tolerance' => 0.2,
            ],
        ];

        $rule = $rules[$category] ?? $rules['general'] ?? null;
        if (!$rule) return;

        if ($dimensions['width'] < $rule['minWidth']) {
            throw new \Exception("Imagen muy estrecha. Mínimo: {$rule['minWidth']}px de ancho (actual: {$dimensions['width']}px)");
        }

        if ($dimensions['height'] < $rule['minHeight']) {
            throw new \Exception("Imagen muy pequeña. Mínimo: {$rule['minHeight']}px de alto (actual: {$dimensions['height']}px)");
        }

        if (isset($rule['expectedRatio'])) {
            $ratio = (float) $rule['expectedRatio'];
            $tolerance = $rule['tolerance'];
            $actualRatio = $dimensions['ratio'];

            if (abs($actualRatio - $ratio) > $tolerance) {
                throw new \Exception("Aspecto de imagen incorrecto. Esperado: ~{$ratio}:1 (actual: {$actualRatio}:1)");
            }
        }
    }
}
