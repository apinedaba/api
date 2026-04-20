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
        /*
         * ---------------------------------------------------------
         * 1. Normalización de parámetros (query o cuerpo)
         * ---------------------------------------------------------
         */
        $params = $request->all();
        $page = (int) ($params['page'] ?? 1);
        $perPage = (int) ($params['perPage'] ?? 10);
        $search = trim($params['search'] ?? '');
        $precioMax = $params['precioMax'] ?? null;
        $pais = $this->firstQueryValue($params['pais'] ?? null);
        $idioma = $this->firstQueryValue($params['idioma'] ?? null);
        $especialidades = $this->queryValues($params['especialidades'] ?? $params['especialidad'] ?? null);
        $cities = $this->queryValues($params['city'] ?? $params['ciudad'] ?? null);
        $lat = $this->normalizeCoordinate($params['lat'] ?? $params['latitude'] ?? null, -90, 90);
        $lng = $this->normalizeCoordinate($params['lng'] ?? $params['longitude'] ?? null, -180, 180);
        $radius = $this->normalizeRadius($params['radius'] ?? null);
        $hasGeoFilter = $lat !== null && $lng !== null;
        $estados = (array) $request->query('estado', []);
        $estados = implode(',', $estados);
        $estados = array_filter(explode(',', $estados), fn($estado) => $estado !== "");



        $generos = $this->queryValues($params['generos'] ?? null);

        $enfoquesParam = $params['enfoques'] ?? $params['enfoque'] ?? null;
        $enfoques = $this->queryValues($enfoquesParam);

        /*
         * ---------------------------------------------------------
         * 2. Estados válidos de una suscripción
         * ---------------------------------------------------------
         */
        /*
         * ---------------------------------------------------------
         * 3. Query base: filtros esenciales
         * ---------------------------------------------------------
         */
        $q = User::query()
            ->with(['activeSessionPackages', 'activeDiscountCoupons', 'activeOffice'])
            ->select('users.*')
            ->publiclyVisible();

        if ($hasGeoFilter || !empty($cities) || !empty($estados)) {
            $q->leftJoin('offices as active_offices', function ($join) {
                $join->on('active_offices.user_id', '=', 'users.id')
                    ->where('active_offices.is_active', true);
            });
        }

        if ($hasGeoFilter) {
            $distanceSql = $this->distanceSql();

            $q->whereNotNull('active_offices.latitude')
                ->whereNotNull('active_offices.longitude')
                ->selectRaw("{$distanceSql} as distance_km", [$lat, $lng, $lat])
                ->whereRaw("{$distanceSql} <= ?", [$lat, $lng, $lat, $radius]);
        }

        /*
         * ---------------------------------------------------------
         * 4. Filtros opcionales
         * ---------------------------------------------------------
         */
        if ($search !== '') {
            $q->where('name', 'like', "%{$search}%");
        }

        if ($pais) {
            $q->where('address->pais', $pais);
        }

        if ($idioma) {
            $q->whereJsonContains('personales->idiomas', $idioma);
        }

        if (!empty($especialidades)) {
            $q->where(function ($specialtyQuery) use ($especialidades) {
                foreach ($especialidades as $especialidad) {
                    $specialtyQuery->orWhereJsonContains('educacion->especialidades', $especialidad);
                }
            });
        }

        if (!empty($estados)) {
            $q->where(function ($stateQuery) use ($estados) {
                $stateQuery->whereIn('address->state', $estados)
                    ->orWhereIn('address->estado', $estados)
                    ->orWhereIn('active_offices.state', $estados);
            });
        }

        if (!empty($cities)) {
            $q->where(function ($cityQuery) use ($cities) {
                foreach ($cities as $city) {
                    $cityQuery->orWhere('active_offices.city', 'like', "%{$city}%");
                }
            });
        }

        if ($generos) {
            // OR entre géneros, pero AND contra los demás filtros
            $q->where(function ($qq) use ($generos) {
                foreach ($generos as $g) {
                    $qq->orWhere('personales->genero', $g);
                }
            });
        }

        if ($enfoques) {
            $q->where(function ($qq) use ($enfoques) {
                foreach ($enfoques as $e) {
                    $qq->orWhere('educacion->enfoque', $e);
                }
            });
        }

        /*
         * ---------------------------------------------------------
         * 5. Filtro: precio máximo usando JSON_TABLE
         * ---------------------------------------------------------
         */
        if ($precioMax) {
            $precioMax = (int) $precioMax;

            $q->where(function ($priceQuery) use ($precioMax) {
                $priceQuery
                    ->whereIn('users.id', function ($sq) use ($precioMax) {
                        $sq
                            ->select('u.id')
                            ->from('users as u')
                            ->join(
                                DB::raw("
                                JSON_TABLE(
                                    u.configurations,
                                    '\$.sesiones[*]'
                                    COLUMNS (precio VARCHAR(20) PATH '\$.precio')
                                ) jt
                            "),
                                DB::raw('1'),
                                DB::raw('1=1')
                            )
                            ->groupBy('u.id')
                            ->havingRaw('MIN(CAST(jt.precio AS UNSIGNED)) <= ?', [$precioMax]);
                    })
                    ->orWhereHas('activeSessionPackages', function ($packageQuery) use ($precioMax) {
                        $packageQuery->where('package_session_price', '<=', $precioMax);
                    });
            });
        }

        /*
         * ---------------------------------------------------------
         * 6. Rotacion justa del catalogo
         * ---------------------------------------------------------
         * Evita sesgo por tipo de licencia/suscripcion. Usamos una
         * semilla estable por dia para que no cambie en cada refresh,
         * pero si rote la exposicion con el tiempo.
         */
        if ($hasGeoFilter) {
            $q->orderBy('distance_km')
                ->orderBy('users.id');
        } else {
            $seedSource = $params['seed'] ?? now()->format('Y-m-d');
            $seed = sprintf('%u', crc32('mindmeet-catalog-' . $seedSource));
            $q->orderByRaw('RAND(?)', [$seed])
                ->orderBy('users.id');
        }

        /*
         * ---------------------------------------------------------
         * 7. Paginación final
         * ---------------------------------------------------------
         */
        $result = $q->paginate($perPage, ['*'], 'page', $page);

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
        $base = User::query()->publiclyVisible();

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

    private function firstQueryValue(mixed $value): ?string
    {
        return $this->queryValues($value)[0] ?? null;
    }

    private function queryValues(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $values = is_array($value) ? $value : explode(',', (string) $value);

        return collect($values)
            ->flatten()
            ->map(fn($item) => trim((string) $item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeCoordinate(mixed $value, float $min, float $max): ?float
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        $coordinate = (float) $value;

        if ($coordinate < $min || $coordinate > $max) {
            return null;
        }

        return $coordinate;
    }

    private function normalizeRadius(mixed $value): int
    {
        if (!is_numeric($value)) {
            return 25;
        }

        return max(1, min((int) $value, 250));
    }

    private function distanceSql(): string
    {
        return '(6371 * acos(LEAST(1, GREATEST(-1,
            cos(radians(?))
            * cos(radians(active_offices.latitude))
            * cos(radians(active_offices.longitude) - radians(?))
            + sin(radians(?))
            * sin(radians(active_offices.latitude))
        ))))';
    }
}
