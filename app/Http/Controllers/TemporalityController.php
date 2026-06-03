<?php

namespace App\Http\Controllers;

use App\Services\TemporalityService;
use App\Models\ContentSectionTemporality;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class TemporalityController extends Controller
{
    public function __construct(
        private TemporalityService $temporalityService
    ) {}

    /**
     * GET /content-temporalities/{sectionKey}
     * Obtener todas las temporalidades de una sección
     */
    public function index(string $sectionKey): JsonResponse
    {
        try {
            $temporalities = $this->temporalityService->getTemporalities($sectionKey)
                ->map(function ($t) {
                    return [
                        'id' => $t->id,
                        'name' => $t->name,
                        'slug' => $t->slug,
                        'is_active' => $t->is_active,
                        'is_programmed' => $t->is_programmed,
                        'start_date' => $t->start_date?->toIso8601String(),
                        'end_date' => $t->end_date?->toIso8601String(),
                        'notes' => $t->notes,
                        'created_by' => $t->created_by,
                        'updated_by' => $t->updated_by,
                        'created_at' => $t->created_at->toIso8601String(),
                        'updated_at' => $t->updated_at->toIso8601String(),
                    ];
                });

            return response()->json([
                'success' => true,
                'temporalities' => $temporalities,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * GET /content-temporalities/{sectionKey}/{id}
     * Obtener una temporalidad específica con su contenido
     */
    public function show(string $sectionKey, int $id): JsonResponse
    {
        try {
            $temporality = ContentSectionTemporality::findOrFail($id);

            return response()->json([
                'success' => true,
                'temporality' => [
                    'id' => $temporality->id,
                    'name' => $temporality->name,
                    'slug' => $temporality->slug,
                    'data' => $temporality->data,
                    'is_active' => $temporality->is_active,
                    'is_programmed' => $temporality->is_programmed,
                    'start_date' => $temporality->start_date?->toIso8601String(),
                    'end_date' => $temporality->end_date?->toIso8601String(),
                    'notes' => $temporality->notes,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Temporalidad no encontrada',
            ], 404);
        }
    }

    /**
     * POST /content-temporalities
     * Crear nueva temporalidad
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'section_key' => ['required', 'string'],
                'name' => ['required', 'string', 'max:255'],
                'slug' => ['required', 'string', 'max:255', 'unique:content_section_temporalities'],
                'start_date' => ['nullable', 'date_format:Y-m-d H:i:s'],
                'end_date' => ['nullable', 'date_format:Y-m-d H:i:s'],
                'notes' => ['nullable', 'string'],
            ]);

            $temporality = $this->temporalityService->createTemporality(
                sectionKey: $validated['section_key'],
                name: $validated['name'],
                slug: $validated['slug'],
                startDate: $validated['start_date'] ? \Carbon\Carbon::parse($validated['start_date']) : null,
                endDate: $validated['end_date'] ? \Carbon\Carbon::parse($validated['end_date']) : null,
                notes: $validated['notes'] ?? null,
                adminId: auth()->id()
            );

            return response()->json([
                'success' => true,
                'message' => 'Temporalidad creada correctamente',
                'temporality' => [
                    'id' => $temporality->id,
                    'name' => $temporality->name,
                    'slug' => $temporality->slug,
                ],
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * PUT /content-temporalities/{id}
     * Actualizar datos de una temporalidad
     */
    public function updateData(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'data' => ['required', 'array'],
            ]);

            $temporality = $this->temporalityService->updateTemporality(
                temporalityId: $id,
                data: $validated['data'],
                adminId: auth()->id()
            );

            return response()->json([
                'success' => true,
                'message' => 'Temporalidad actualizada',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * PATCH /content-temporalities/{id}
     * Actualizar propiedades (nombre, fechas, notas)
     */
    public function updateProperties(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => ['nullable', 'string', 'max:255'],
                'start_date' => ['nullable', 'date_format:Y-m-d H:i:s'],
                'end_date' => ['nullable', 'date_format:Y-m-d H:i:s'],
                'notes' => ['nullable', 'string'],
            ]);

            $temporality = $this->temporalityService->updateTemporalityProperties(
                temporalityId: $id,
                properties: $validated,
                adminId: auth()->id()
            );

            return response()->json([
                'success' => true,
                'message' => 'Propiedades actualizadas',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * POST /content-temporalities/{id}/activate
     * Activar una temporalidad manualmente
     */
    public function activate(int $id): JsonResponse
    {
        try {
            $this->temporalityService->activateTemporality($id, auth()->id());

            return response()->json([
                'success' => true,
                'message' => 'Temporalidad activada',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * POST /content-temporalities/{id}/deactivate
     * Desactivar una temporalidad
     */
    public function deactivate(int $id): JsonResponse
    {
        try {
            $this->temporalityService->deactivateTemporality($id);

            return response()->json([
                'success' => true,
                'message' => 'Temporalidad desactivada',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * DELETE /content-temporalities/{id}
     * Eliminar una temporalidad
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->temporalityService->deleteTemporality($id);

            return response()->json([
                'success' => true,
                'message' => 'Temporalidad eliminada',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
