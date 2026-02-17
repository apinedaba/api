<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\User;
use Stripe\Stripe;

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
            "key" => "generos"
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
        $result = $sesiones
            ->map(fn($key) => ['label' => $key, 'value' => $key])
            ->filter() // Por si hay claves sin traducción
            ->values();
        $response = [
            "type" => "autocomplete",
            "values" => $result,
            "label" => "Enfoque",
            "key" => "enfoque"
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
            "key" => "especialidades"
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
            "type" => "autocomplete",
            "values" => $pais,
            "label" => "País",
            "key" => "pais"
        ];

        return $response;
    }
    public function estado()
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
            ->map(
                fn($p) => (
                    [
                        "pais" => $p['pais'] ?? null,
                        "estado" => $p['state'] ?? null
                    ]
                )
            )
            ->filter()
            ->unique()
            ->values()
            ->map(fn($key) => [
                'value' => $key,
                'pais' => $key['pais'],
                'estado' => $key['estado']
            ]);

        $response = [
            "type" => "autocomplete",
            "values" => $pais,
            "label" => "Estado",
            "key" => "estado"
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
            $this->estado()

        ];
        return response()->json($data, 200);
    }

    public function getPrices(): JsonResponse
    {
        Stripe::setApiKey(config('services.stripe.secret_key') ?? env('STRIPE_SECRET_KEY'));
        $allPrices = \Stripe\Price::all();
        $prices = collect($allPrices->data)
            ->filter(fn($price) => $price->active)
            ->values();
        return response()->json($prices, 200);
    }

    public function getPriceById($priceId)
    {
        Stripe::setApiKey(config('services.stripe.secret_key') ?? env('STRIPE_SECRET_KEY'));

        $price = \Stripe\Price::retrieve($priceId);

        return response()->json($price);
    }
}
