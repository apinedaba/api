<?php

namespace App\Http\Controllers\Red;

use App\Events\RedPreguntaActualizada;
use App\Http\Controllers\Controller;
use App\Models\RedPregunta;
use App\Models\RedRespuesta;
use App\Models\User;
use App\Notifications\NuevaPreguntaEnRed;
use App\Rules\NoIdentifiableContact;
use App\Services\RedReputationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

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
        $category = $request->integer('category');
        $status = $request->string('status')->trim()->toString();
        $answers = $request->string('answers')->trim()->toString();
        $orden  = $request->string('orden', 'reciente')->toString();
        $solo_mias = $request->boolean('solo_mias', false); // Nuevo filtro
        $collection = $request->string('collection')->toString();
        $perPage = min((int) $request->integer('per_page', 20), 50);

        $query = RedPregunta::where('is_active', true)
            ->with(['autor:id,name,image,personales', 'category'])
            ->withCount(['respuestas', 'votos'])
            ->withMax(['respuestas as ultima_respuesta_at'], 'created_at')
            ->withExists([
                'preferencias as is_saved' => fn($query) => $query
                    ->where('user_id', $user->id)
                    ->where('is_saved', true),
                'preferencias as is_following' => fn($query) => $query
                    ->where('user_id', $user->id)
                    ->where('is_following', true),
            ]);

        // Filtro: solo mis preguntas
        if ($solo_mias) {
            $query->where('user_id', $user->id);
        }

        if ($collection === 'library') {
            $query->whereHas('preferencias', fn($preference) => $preference
                ->where('user_id', $user->id)
                ->where(fn($flags) => $flags->where('is_saved', true)->orWhere('is_following', true)));
        }

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

        if ($category) {
            $query->where('category_id', $category);
        }

        if (in_array($status, ['open', 'closed'], true)) {
            $query->where('status', $status);
        }

        match ($answers) {
            'answered' => $query->whereHas('respuestas'),
            'unanswered' => $query->whereDoesntHave('respuestas'),
            'accepted' => $query->whereNotNull('mejor_respuesta_id'),
            default => null,
        };

        // Ordenamiento
        match ($orden) {
            'actividad'     => $query->orderByDesc(
                RedRespuesta::selectRaw('MAX(created_at)')
                    ->whereColumn('pregunta_id', 'red_preguntas.id')
                    ->where('is_deleted', false)
                    ->toBase()
            ),
            'sin_respuesta' => $query->whereDoesntHave('respuestas')->latest(),
            'mas_votadas'   => $query->orderByDesc('votos_count'),
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
            'titulo'      => ['required', 'string', 'min:10', 'max:200', new NoIdentifiableContact],
            'descripcion' => ['required', 'string', 'min:20', 'max:5000', new NoIdentifiableContact],
            'category_id' => ['required', 'integer', Rule::exists('red_categories', 'id')->where('is_active', true)],
            'tags'        => 'nullable|array|max:5',
            'tags.*'      => ['string', 'max:40', Rule::exists('red_tags', 'name')->where('is_active', true)],
            'privacy_acknowledged' => 'accepted',
        ]);

        $pregunta = RedPregunta::create([
            'user_id'     => $request->user()->id,
            'category_id' => $validated['category_id'],
            'titulo'      => $validated['titulo'],
            'descripcion' => $validated['descripcion'],
            'tags'        => $validated['tags'] ?? [],
        ]);
        app(RedReputationService::class)->forget($request->user()->id);

        $pregunta->load(['autor:id,name,image,personales', 'category']);
        $pregunta->loadCount('respuestas');

        // Notificar a todos los psicólogos verificados (excepto al autor)
        $psicologosVerificados = User::where('identity_verification_status', 'approved')
            ->where('activo', true)
            ->where('id', '!=', $request->user()->id)
            ->get();

        Notification::send($psicologosVerificados, new NuevaPreguntaEnRed($pregunta));

        broadcast(new RedPreguntaActualizada('nueva_pregunta', $pregunta->id));

        return response()->json([
            'data'    => $this->formatPregunta($pregunta, $request->user()->id),
            'message' => 'Pregunta publicada exitosamente.',
        ], 201);
    }

    public function update(Request $request, RedPregunta $pregunta): JsonResponse
    {
        abort_if($pregunta->user_id !== $request->user()->id, 403, 'No puedes editar esta pregunta.');

        $validated = $request->validate([
            'titulo' => ['required', 'string', 'min:10', 'max:200', new NoIdentifiableContact],
            'descripcion' => ['required', 'string', 'min:20', 'max:5000', new NoIdentifiableContact],
            'category_id' => ['required', 'integer', Rule::exists('red_categories', 'id')->where('is_active', true)],
            'tags' => 'nullable|array|max:5',
            'tags.*' => ['string', 'max:40', Rule::exists('red_tags', 'name')->where('is_active', true)],
            'privacy_acknowledged' => 'accepted',
        ]);

        $pregunta->update([
            'titulo' => $validated['titulo'],
            'descripcion' => $validated['descripcion'],
            'category_id' => $validated['category_id'],
            'tags' => $validated['tags'] ?? [],
            'edited_at' => now(),
        ]);

        return response()->json(['data' => $this->formatPregunta($pregunta->fresh(['autor', 'category']), $request->user()->id, true)]);
    }

    public function close(Request $request, RedPregunta $pregunta): JsonResponse
    {
        abort_if($pregunta->user_id !== $request->user()->id, 403, 'No puedes cerrar esta pregunta.');

        $validated = $request->validate([
            'reason' => 'required|in:resolved,duplicate,outdated,other',
            'note' => 'nullable|string|max:500',
        ]);

        $pregunta->update([
            'status' => 'closed',
            'close_reason' => $validated['reason'],
            'close_note' => $validated['note'] ?? null,
            'closed_at' => now(),
        ]);

        return response()->json(['message' => 'Pregunta cerrada.']);
    }

    public function reopen(Request $request, RedPregunta $pregunta): JsonResponse
    {
        abort_if($pregunta->user_id !== $request->user()->id, 403, 'No puedes reabrir esta pregunta.');

        $pregunta->update([
            'status' => 'open',
            'close_reason' => null,
            'close_note' => null,
            'closed_at' => null,
        ]);

        return response()->json(['message' => 'Pregunta reabierta.']);
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

        $pregunta->load(['autor:id,name,image,personales', 'category']);
        $pregunta->loadCount('respuestas');
        $pregunta->loadCount('votos');
        $pregunta->loadMax('respuestas as ultima_respuesta_at', 'created_at');
        $pregunta->loadExists([
            'preferencias as is_saved' => fn($query) => $query
                ->where('user_id', $user->id)
                ->where('is_saved', true),
            'preferencias as is_following' => fn($query) => $query
                ->where('user_id', $user->id)
                ->where('is_following', true),
        ]);

        // Respuestas con votos y autor
        $respuestas = $pregunta->respuestas()
            ->with(['autor:id,name,image,personales'])
            ->withCount('votos')
            ->withExists(['votos as yo_vote' => fn($query) => $query->where('user_id', $user->id)])
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

        $preguntaId = $pregunta->id;
        $authorId = $pregunta->user_id;
        $pregunta->delete();
        app(RedReputationService::class)->forget($authorId);

        broadcast(new RedPreguntaActualizada('pregunta_eliminada', $preguntaId));

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

        $previousAnswerUserId = $pregunta->mejorRespuesta?->user_id;
        $pregunta->update(['mejor_respuesta_id' => $respuesta->id]);
        if ($previousAnswerUserId && $previousAnswerUserId !== $respuesta->user_id) {
            app(RedReputationService::class)->forget($previousAnswerUserId);
        }
        app(RedReputationService::class)->forget($respuesta->user_id);

        broadcast(new RedPreguntaActualizada('mejor_respuesta', $pregunta->id));

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

        $data = [
            'id'                  => $p->id,
            'titulo'              => $p->titulo,
            'tags'                => $p->tags ?? [],
            'category'            => $p->category ? $p->category->only(['id', 'name', 'slug', 'color']) : null,
            'mejor_respuesta_id'  => $p->mejor_respuesta_id,
            'views_count'         => $p->views_count,
            'respuestas_count'    => $p->respuestas_count ?? 0,
            'ultima_respuesta_at' => $p->ultima_respuesta_at,
            'votos_count'         => $p->votos_count ?? 0,
            'is_saved'            => (bool) ($p->is_saved ?? false),
            'is_following'        => (bool) ($p->is_following ?? false),
            'created_at'          => $p->created_at,
            'status'              => $p->status ?? 'open',
            'close_reason'        => $p->close_reason,
            'close_note'          => $p->close_note,
            'closed_at'           => $p->closed_at,
            'edited_at'           => $p->edited_at,
            'es_autor'            => $p->user_id === $userId,
            'autor' => $autor ? [
                'id'           => $autor->id,
                'nombre'       => $autor->name,
                'imagen'       => $autor->image,
                'especialidad' => $especialidad,
                'reputation' => app(RedReputationService::class)->summary($autor->id),
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
            'yo_vote'            => (bool) ($r->yo_vote ?? false),
            'es_mejor_respuesta' => $r->id === $mejorRespuestaId,
            'es_autor'           => $r->user_id === $userId,
            'created_at'         => $r->created_at,
            'autor' => $autor ? [
                'id'           => $autor->id,
                'nombre'       => $autor->name,
                'imagen'       => $autor->image,
                'especialidad' => $personales['especialidad'] ?? null,
                'reputation' => app(RedReputationService::class)->summary($autor->id),
            ] : null,
        ];
    }

    /**
     * GET /user/red/mis-preguntas/sin-leer
     * Devuelve contador de respuestas nuevas por pregunta
     */
    public function misPreguntasSinLeer(Request $request): JsonResponse
    {
        $user = $request->user();

        // Obtener preguntas del usuario con conteo de respuestas
        $preguntas = RedPregunta::where('user_id', $user->id)
            ->where('is_active', true)
            ->with('respuestas:id,pregunta_id,created_at')
            ->get(['id', 'user_id', 'ultima_respuesta_vista_at'])
            ->map(function (RedPregunta $p) {
                $respuestas_nuevas = $p->respuestas->filter(function (RedRespuesta $r) use ($p) {
                    return !$p->ultima_respuesta_vista_at ||
                        $r->created_at > $p->ultima_respuesta_vista_at;
                })->count();

                return [
                    'id' => $p->id,
                    'respuestas_nuevas' => $respuestas_nuevas,
                ];
            })
            ->filter(fn($p) => $p['respuestas_nuevas'] > 0);

        return response()->json([
            'data' => $preguntas,
            'total_con_respuestas_nuevas' => $preguntas->count(),
        ]);
    }

    /**
     * PATCH /user/red/preguntas/{pregunta}/marcar-vista
     * Marca que el autor ya vio las respuestas de su pregunta.
     */
    public function marcarVista(Request $request, RedPregunta $pregunta): JsonResponse
    {
        // Solo tiene efecto para el autor; otros usuarios simplemente reciben 200 sin cambios.
        if ($pregunta->user_id !== $request->user()->id) {
            return response()->json(['message' => 'OK.']);
        }

        $pregunta->ultima_respuesta_vista_at = now();
        $pregunta->save();

        return response()->json(['message' => 'Marcada como vista.']);
    }
}
