<?php

namespace App\Http\Controllers\Red;

use App\Http\Controllers\Controller;
use App\Models\RedPregunta;
use App\Models\RedRespuesta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RedPreguntaController extends Controller
{
    /**
     * GET /user/red/preguntas
     * Lista paginada con filtros: q (búsqueda), tag, orden
     */
    public function index(Request $request): JsonResponse
    {
        $user   = $request->user();
        $q      = $request->string('q')->trim()->toString();
        $tag    = $request->string('tag')->trim()->toString();
        $orden  = $request->string('orden', 'reciente')->toString();
        $perPage = min((int) $request->integer('per_page', 20), 50);

        $query = RedPregunta::where('is_active', true)
            ->with(['autor:id,name,image,personales'])
            ->withCount(['respuestas']);

        // Búsqueda por texto
        if ($q !== '') {
            $query->where(function ($q2) use ($q) {
                $q2->where('titulo', 'like', "%{$q}%")
                    ->orWhere('descripcion', 'like', "%{$q}%");
            });
        }

        // Filtro por tag
        if ($tag !== '') {
            $query->whereJsonContains('tags', $tag);
        }

        // Ordenamiento
        match ($orden) {
            'actividad'     => $query->orderByDesc(
                RedRespuesta::selectRaw('MAX(created_at)')
                    ->whereColumn('pregunta_id', 'red_preguntas.id')
                    ->toBase()
            ),
            'sin_respuesta' => $query->whereDoesntHave('respuestas')->latest(),
            'mas_votadas'   => $query->orderByDesc(
                RedRespuesta::selectRaw('COALESCE(SUM(v.id),0)')
                    ->from('red_respuestas as r2')
                    ->join('red_votos as v', 'v.respuesta_id', '=', 'r2.id')
                    ->whereColumn('r2.pregunta_id', 'red_preguntas.id')
                    ->toBase()
            ),
            default         => $query->latest(),   // 'reciente'
        };

        $preguntas = $query->paginate($perPage);

        $items = $preguntas->getCollection()->map(function (RedPregunta $p) use ($user) {
            return $this->formatPregunta($p, $user->id, includeDescripcion: true);
        });

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $preguntas->currentPage(),
                'last_page'    => $preguntas->lastPage(),
                'total'        => $preguntas->total(),
                'per_page'     => $preguntas->perPage(),
            ],
        ]);
    }

    /**
     * POST /user/red/preguntas
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'titulo'      => 'required|string|min:10|max:200',
            'descripcion' => 'required|string|min:20|max:5000',
            'tags'        => 'nullable|array|max:5',
            'tags.*'      => 'string|max:40',
        ]);

        $pregunta = RedPregunta::create([
            'user_id'     => $request->user()->id,
            'titulo'      => $validated['titulo'],
            'descripcion' => $validated['descripcion'],
            'tags'        => $validated['tags'] ?? [],
        ]);

        $pregunta->load('autor:id,name,image,personales');
        $pregunta->loadCount('respuestas');

        return response()->json([
            'data'    => $this->formatPregunta($pregunta, $request->user()->id),
            'message' => 'Pregunta publicada exitosamente.',
        ], 201);
    }

    /**
     * GET /user/red/preguntas/{pregunta}
     * Detalle con respuestas ordenadas (mejor primero, luego por votos)
     */
    public function show(Request $request, RedPregunta $pregunta): JsonResponse
    {
        abort_if(! $pregunta->is_active, 404);

        $user = $request->user();

        // Incrementar vistas (no contar al propio autor)
        if ($pregunta->user_id !== $user->id) {
            $pregunta->increment('views_count');
        }

        $pregunta->load(['autor:id,name,image,personales']);
        $pregunta->loadCount('respuestas');

        // Respuestas con votos y autor
        $respuestas = $pregunta->respuestas()
            ->with(['autor:id,name,image,personales'])
            ->withCount('votos')
            ->get()
            ->map(function (RedRespuesta $r) use ($pregunta, $user) {
                return $this->formatRespuesta($r, $user->id, $pregunta->mejor_respuesta_id);
            })
            ->sortByDesc(fn($r) => [
                $r['es_mejor_respuesta'] ? 1 : 0,
                $r['votos_count'],
            ])
            ->values();

        return response()->json([
            'data' => [
                ...$this->formatPregunta($pregunta, $user->id),
                'respuestas' => $respuestas,
            ],
        ]);
    }

    /**
     * DELETE /user/red/preguntas/{pregunta}
     * Solo el autor puede eliminar
     */
    public function destroy(Request $request, RedPregunta $pregunta): JsonResponse
    {
        abort_if($pregunta->user_id !== $request->user()->id, 403, 'No puedes eliminar esta pregunta.');

        $pregunta->delete();

        return response()->json(['message' => 'Pregunta eliminada.']);
    }

    /**
     * POST /user/red/preguntas/{pregunta}/mejor-respuesta/{respuesta}
     * Solo el autor de la pregunta puede marcar la mejor respuesta
     */
    public function marcarMejorRespuesta(
        Request $request,
        RedPregunta $pregunta,
        RedRespuesta $respuesta
    ): JsonResponse {
        abort_if($pregunta->user_id !== $request->user()->id, 403, 'Solo el autor puede marcar la mejor respuesta.');
        abort_if($respuesta->pregunta_id !== $pregunta->id, 422, 'La respuesta no pertenece a esta pregunta.');
        abort_if($respuesta->is_deleted, 422, 'No se puede marcar una respuesta eliminada.');

        $pregunta->update(['mejor_respuesta_id' => $respuesta->id]);

        return response()->json([
            'message'             => 'Mejor respuesta marcada.',
            'mejor_respuesta_id'  => $respuesta->id,
        ]);
    }

    // ─── Helpers privados ─────────────────────────────────────────

    private function formatPregunta(RedPregunta $p, int $userId, bool $includeDescripcion = false): array
    {
        $autor = $p->autor;
        $especialidad = null;

        if ($autor) {
            $personales = is_array($autor->personales) ? $autor->personales : [];
            $especialidad = $personales['especialidad'] ?? null;
        }

        $ultima = $p->todasRespuestas()->latest()->value('created_at');

        $data = [
            'id'                  => $p->id,
            'titulo'              => $p->titulo,
            'tags'                => $p->tags ?? [],
            'mejor_respuesta_id'  => $p->mejor_respuesta_id,
            'views_count'         => $p->views_count,
            'respuestas_count'    => $p->respuestas_count ?? 0,
            'ultima_respuesta_at' => $ultima,
            'created_at'          => $p->created_at,
            'es_autor'            => $p->user_id === $userId,
            'autor' => $autor ? [
                'id'           => $autor->id,
                'nombre'       => $autor->name,
                'imagen'       => $autor->image,
                'especialidad' => $especialidad,
            ] : null,
        ];

        if ($includeDescripcion) {
            $data['descripcion'] = $p->descripcion;
        }

        return $data;
    }

    private function formatRespuesta(RedRespuesta $r, int $userId, ?int $mejorRespuestaId): array
    {
        $autor = $r->autor;
        $personales = $autor ? (is_array($autor->personales) ? $autor->personales : []) : [];

        return [
            'id'                 => $r->id,
            'contenido'          => $r->contenido,
            'votos_count'        => $r->votos_count ?? 0,
            'yo_vote'            => $r->votos()->where('user_id', $userId)->exists(),
            'es_mejor_respuesta' => $r->id === $mejorRespuestaId,
            'es_autor'           => $r->user_id === $userId,
            'created_at'         => $r->created_at,
            'autor' => $autor ? [
                'id'           => $autor->id,
                'nombre'       => $autor->name,
                'imagen'       => $autor->image,
                'especialidad' => $personales['especialidad'] ?? null,
            ] : null,
        ];
    }
}
