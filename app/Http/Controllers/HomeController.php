<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\ContentSectionService;
use App\Services\TemporalityService;
use Illuminate\Http\Request;
use App\Models\User;

class HomeController extends Controller
{
    public function __construct(
        private ContentSectionService $contentSectionService,
        private TemporalityService $temporalityService
    ) {}

    function getImages()
    {
        // Obtener datos del home: si hay temporalidad activa, usar esa; sino, usar original
        $homeData = $this->temporalityService->getActiveContent('home');

        if (empty($homeData)) {
            return response()->json([], 404);
        }

        // Asegurar que sections es un array
        if (!isset($homeData['sections']) || !is_array($homeData['sections'])) {
            return response()->json($homeData);
        }

        // Procesar secciones dinámicas
        foreach ($homeData['sections'] as $index => $section) {
            if (isset($section['type']) && $section['type'] === 'slider') {
                // Cargar profesionales para sliders
                $professionals = $this->getProfessionalsByFilter(
                    $section['filterType'] ?? 'especialidades',
                    $section['filterValue'] ?? [],
                    $section['limit'] ?? 6
                );
                $homeData['sections'][$index]['professionals'] = $professionals;
            }
            // Las secciones de tipo 'promotions' y 'psicoPlus' usarán los datos existentes
        }

        return response()->json($homeData);
    }

    function buenfin()
    {
        // Obtener datos de buenfin: si hay temporalidad activa, usar esa; sino, usar original
        $buenfinData = $this->temporalityService->getActiveContent('buenfin');

        if (!$buenfinData) {
            return response()->json([], 404);
        }

        return response()->json($buenfinData);
    }

    /**
     * Obtiene profesionales filtrados por especialidad o enfoque
     */
    private function getProfessionalsByFilter($filterType = null, $filterValues = [], $limit = 6)
    {
        $query = User::query()
            ->publiclyVisible()
            ->whereRaw("JSON_VALID(educacion)");

        if (!empty($filterType) && !empty($filterValues)) {

            $query->where(function ($q) use ($filterType, $filterValues) {

                foreach ($filterValues as $value) {

                    if ($filterType === 'especialidades') {
                        $q->orWhereJsonContains('educacion->especialidades', $value);
                    }

                    if ($filterType === 'enfoques') {
                        $q->orWhere('educacion->enfoque', $value);
                    }
                }
            });
        }

        return $this->formatProfessionals(
            $query->inRandomOrder()
                ->limit($limit)
                ->get()
        );
    }

    private function formatProfessionals($collection)
    {
        return $collection->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'image' => $user->image,
                'contacto' => $user->contacto,
                'personales' => $user->personales,
                'educacion' => $user->educacion,
                'configurations' => $user->configurations,
                'address' => $user->address,
            ];
        });
    }
}
