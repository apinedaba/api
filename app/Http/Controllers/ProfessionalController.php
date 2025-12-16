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
        $params = $request->query('params', $request->input('params', []));

        $page = (int) ($params['page'] ?? 1);
        $perPage = (int) ($params['perPage'] ?? 12);
        $search = trim($params['search'] ?? '');
        $precioMax = $params['precioMax'] ?? null;
        $pais = $params['pais'] ?? null;
        $idioma = $params['idioma'] ?? null;
        $especial = $params['especialidad'] ?? null;

        $generos = !empty($params['generos'])
            ? array_filter(array_map('trim', explode(',', $params['generos'])))
            : [];

        $enfoques = !empty($params['enfoques'])
            ? array_filter(array_map('trim', explode(',', $params['enfoques'])))
            : [];

        /*
         * ---------------------------------------------------------
         * 2. Estados válidos de una suscripción
         * ---------------------------------------------------------
         */
        $suscripcionesValidas = ['active', 'trial', 'trialing'];

        /*
         * ---------------------------------------------------------
         * 3. Query base: filtros esenciales
         * ---------------------------------------------------------
         */
        $q = User::query()
            ->where('isProfileComplete', true)
            ->where('activo', true)
            ->where('identity_verification_status', 'approved')
            ->where(function ($q2) use ($suscripcionesValidas) {
                $q2
                    ->whereHas('subscription', function ($s) use ($suscripcionesValidas) {
                        $s->whereIn('stripe_status', $suscripcionesValidas);
                    })
                    ->orWhere('has_lifetime_access', true);
            });

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

        if ($especial) {
            $q->whereJsonContains('educacion->especialidades', $especial);
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

            $q->whereIn('users.id', function ($sq) use ($precioMax) {
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
            });
        }

        /*
         * ---------------------------------------------------------
         * 6. Orden aleatorio estable (por seed)
         * ---------------------------------------------------------
         */
        $seed = $params['seed'] ?? random_int(1, 999999);
        $q->orderByRaw('RAND(?)', [$seed]);

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
            'seed' => $seed
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
