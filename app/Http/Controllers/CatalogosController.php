<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\User;
class CatalogosController extends Controller
{
    public function generos()
    {
        $generos = User::whereNotNull('personales')
            ->where('isProfileComplete', 1)
            ->where('activo', 1)
            ->pluck('personales')
            ->map(fn($p) => $p['genero'] ?? null)
            ->filter()
            ->unique()
            ->values();

        $response = [
            "type" => "checkbox",
            "values" => $generos,
            "label" => "Generos",
            "key" =>"generos"
        ];

        return $response;
    }

    public function enfoque()
    {
        $sesiones = User::whereNotNull('educacion')
            ->where('isProfileComplete', 1)
            ->where('activo', 1)
            ->pluck('educacion')
            ->map(fn($p) => $p['enfoque'] ?? null)
            ->filter()
            ->unique()
            ->values();

        $response = [
            "type" => "checkbox",
            "values" => $sesiones,
            "label" => "Enfoque",
            "key" =>"enfoque"
        ];

        return $response;
    }
    public function especialidades()
    {
        $json = file_get_contents(resource_path('json/especialidades.json'));
        $catalogo = collect(json_decode($json, true))->keyBy('value');


        // 2. Extraer todas las especialidades usadas por profesionales activos
        $especialidades = User::whereNotNull('educacion')
            ->where('isProfileComplete', 1)
            ->where('activo', 1)
            ->pluck('educacion')
            ->flatMap(fn($educacion) => collect($educacion['especialidades'] ?? []))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        // 3. Armar respuesta usando los datos del catálogo
        $result = $especialidades
            ->map(fn($key) => $catalogo->get($key))
            ->filter() // Por si hay claves sin traducción
            ->values();

        $response = [
            "type" => "autocomplete",
            "values" => $result,
            "label" => "Especialidades",
            "key"=>"especialidades"
        ];

        return $response;
    }
    public function pais()
    {
        $catalogo = [
            "MX" => "México",
            "AR" => "Argentina",
            "CO" => "Colombia",
            "CL" => "Chile",
            "PE" => "Perú",
            "UY" => "Uruguay",
            "EC" => "Ecuador",
            "BO" => "Bolivia"
        ];
        $pais = User::whereNotNull('address')
            ->where('isProfileComplete', 1)
            ->where('activo', 1)
            ->pluck('address')
            ->map(fn($p) => $p['pais'] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->map(fn($key) => [
                'value' => $key,
                'label' => $catalogo[$key] ?? ucfirst(str_replace('_', ' ', $key))
            ]);

        $response = [
            "type" => "checkbox",
            "values" => $pais,
            "label" => "País",
            "key" =>"pais"
        ];

        return $response;
    }

    public function getCatalogs(): JsonResponse
    {
        $data = [
            $this->generos(),
            $this->enfoque(),
            $this->especialidades(),
            $this->pais(),

        ];
        return response()->json($data, 200);
    }

}
