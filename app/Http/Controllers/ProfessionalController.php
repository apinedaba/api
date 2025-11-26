<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProfessionalController extends Controller
{
    /**
     * GET /profesional (server-side filtering + pagination)
     * Query params:
     * - page, perPage
     * - search, precioMax
     * - generos (CSV), enfoques (CSV)
     * - pais, idioma, especialidad
     */
    public function index(Request $request)
    {
        // 1) Normaliza origen de parámetros
        $params = $request->query();  // ?page=1&...
        if (empty($params) && $request->has('params')) {
            $params = $request->input('params', []);  // { params: { ... } }
        }

        if (!$request->has('params')) {
            $q = User::inRandomOrder()
                ->where('isProfileComplete', true)
                ->where('activo', true)
                ->where(function ($q) {
                    $q
                        ->whereHas('subscription', function ($s) {
                            $s->whereIn('status', ['active', 'trialing', 'trial']);
                        })
                        ->orWhere('has_lifetime_access', true);
                });
            $result = $q->orderByDesc('id')->paginate(12);
            return response()->json([
                'data' => $result->items(),
                'total' => $result->total(),
                'page' => $result->currentPage(),
                'pages' => $result->lastPage(),
            ]);
        }

        // 2) Lee valores desde el arreglo normalizado
        $page = (int) ($params['params']['page'] ?? 1);
        $perPage = (int) ($params['params']['perPage'] ?? 12);
        $search = (string) ($params['params']['search'] ?? '');
        $precioMax = $params['params']['precioMax'] ?? null;
        $pais = $params['params']['pais'] ?? null;
        $idioma = $params['params']['idioma'] ?? null;
        $especialidad = $params['params']['especialidad'] ?? null;

        $generos = !empty($params['params']['generos'])
            ? array_values(array_filter(array_map('trim', explode(',', $params['params']['generos']))))
            : [];
        $enfoques = !empty($params['params']['enfoques'])
            ? array_values(array_filter(array_map('trim', explode(',', $params['params']['enfoques']))))
            : [];
        $q = User::query()
            ->where('isProfileComplete', true)
            ->where('activo', true);

        if ($search !== '') {
            $q->where('name', 'like', "%{$search}%");
        }
        if (!empty($pais))
            $q->where('address->pais', $pais);
        if (!empty($idioma))
            $q->whereJsonContains('personales->idiomas', $idioma);
        if (!empty($especialidad))
            $q->whereJsonContains('educacion->especialidades', $especialidad);

        if ($generos) {
            $q->where(function ($qq) use ($generos) {
                foreach ($generos as $g)
                    $qq->orWhere('personales->genero', $g);
            });
        }
        if ($enfoques) {
            $q->where(function ($qq) use ($enfoques) {
                foreach ($enfoques as $e)
                    $qq->orWhere('educacion->enfoque', $e);
            });
        }

        // precioMax (si no usas JSON_TABLE, omite este bloque por ahora)
        if (!empty($precioMax)) {
            $precioMax = (int) $precioMax;
            $q->whereIn('users.id', function ($sq) use ($precioMax) {
                $sq
                    ->select('u.id')
                    ->from('users as u')
                    ->join(
                        DB::raw("JSON_TABLE(u.configurations, '\$.sesiones[*]' COLUMNS (precio VARCHAR(20) PATH '\$.precio')) jt"),
                        DB::raw('1'),
                        DB::raw('1=1')
                    )
                    ->groupBy('u.id')
                    ->havingRaw('MIN(CAST(jt.precio AS UNSIGNED)) <= ?', [$precioMax]);
            });
        }
        $seed = $params['seed'] ?? 'default-seed';
        $result = $q
            ->orderByRaw('RAND(?)', [$seed])  // mismo orden para esa semilla
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $result->items(),
            'total' => $result->total(),
            'page' => $result->currentPage(),
            'pages' => $result->lastPage(),
        ]);
    }

    /**
     * GET /profesional/filters
     * Devuelve la "plantilla" de filtros lista para pintar en el front.
     */
    public function filters(Request $request)
    {
        $base = User::query()
            ->where('isProfileComplete', true)
            ->where('activo', true);

        // Distintos de campos string en JSON
        $generos = (clone $base)
            ->selectRaw("DISTINCT JSON_UNQUOTE(JSON_EXTRACT(personales, '\$.genero')) AS genero")
            ->pluck('genero')
            ->filter()
            ->values();

        $enfoques = (clone $base)
            ->selectRaw("DISTINCT JSON_UNQUOTE(JSON_EXTRACT(educacion, '\$.enfoque')) AS enfoque")
            ->pluck('enfoque')
            ->filter()
            ->values();

        $paises = (clone $base)
            ->selectRaw("DISTINCT JSON_UNQUOTE(JSON_EXTRACT(address, '\$.pais')) AS pais")
            ->pluck('pais')
            ->filter()
            ->values();

        // idiomas (array) → flatten en PHP
        $idiomasRaw = (clone $base)->pluck('personales');
        $idiomas = collect($idiomasRaw)
            ->flatMap(function ($json) {
                if (!$json)
                    return [];
                $arr = is_array($json) ? $json : json_decode($json, true);
                return $arr['idiomas'] ?? [];
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        // especialidades (array) → flatten en PHP
        $especialidadesRaw = (clone $base)->pluck('educacion');
        $especialidades = collect($especialidadesRaw)
            ->flatMap(function ($json) {
                if (!$json)
                    return [];
                $arr = is_array($json) ? $json : json_decode($json, true);
                return $arr['especialidades'] ?? [];
            })
            ->filter()
            ->unique()
            ->values()
            ->map(fn($v) => ['label' => $v, 'value' => $v])  // opcional formateado para tu Autocomplete
            ->all();

        // Armar definición
        $filters = [
            [
                'key' => 'generos',
                'type' => 'checkbox',
                'label' => 'Género',
                'values' => collect($generos)->map(fn($g) => ['label' => $g, 'value' => $g])->values(),
            ],
            [
                'key' => 'enfoques',
                'type' => 'autocomplete',
                'label' => 'Enfoque',
                'values' => collect($enfoques)->map(fn($e) => ['label' => $e, 'value' => $e])->values(),
            ],
            [
                'key' => 'pais',
                'type' => 'autocomplete',
                'label' => 'País',
                'values' => $paises,
            ],
            [
                'key' => 'idioma',
                'type' => 'select',
                'label' => 'Idioma',
                'values' => $idiomas,
            ],
            [
                'key' => 'especialidad',
                'type' => 'autocomplete',
                'label' => 'Especialidad',
                'values' => $especialidades,
            ],
            [
                'key' => 'precioMax',
                'type' => 'number',
                'label' => 'Precio máximo',
                'values' => [],
            ],
        ];

        return response()->json(['filters' => $filters], 200);
    }

    private function csvToArray($csv)
    {
        if (!$csv)
            return [];
        return collect(explode(',', $csv))
            ->map(fn($v) => trim($v))
            ->filter()
            ->values()
            ->all();
    }
}
