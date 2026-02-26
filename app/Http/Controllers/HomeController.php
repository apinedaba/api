<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

class HomeController extends Controller
{
    function getImages()
    {
        $homeData = json_decode(file_get_contents(storage_path('app/home.json')), true);

        // Procesar secciones dinámicas
        if (isset($homeData['sections']) && is_array($homeData['sections'])) {
            foreach ($homeData['sections'] as $index => $section) {
                if ($section['type'] === 'slider') {
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
        }

        return response()->json($homeData);
    }

    function buenfin()
    {
        return response()->json(json_decode(file_get_contents(storage_path('app/buenfin.json')))); // o usa directamente resources si lo cargas ahí

    }

    /**
     * Obtiene profesionales filtrados por especialidad o enfoque
     */
    private function getProfessionalsByFilter($filterType = null, $filterValues = [], $limit = 6)
    {
        $query = User::query()
            ->where('isProfileComplete', 1)
            ->where('activo', 1)
            ->where('identity_verification_status', 'approved')
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
