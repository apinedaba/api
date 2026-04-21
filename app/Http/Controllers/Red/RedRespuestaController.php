<?php

namespace App\Http\Controllers\Red;

use App\Http\Controllers\Controller;
use App\Models\RedPregunta;
use App\Models\RedRespuesta;
use App\Models\RedVoto;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RedRespuestaController extends Controller
{
    /**
     * GET /user/red/preguntas/{pregunta}/respuestas
     */
    public function index(Request $request, RedPregunta $pregunta): JsonResponse
    {
        abort_if(! $pregunta->is_active, 404);

        $user = $request->user();

        $respuestas = $pregunta->respuestas()
            ->with(['autor:id,name,image,personales'])
            ->withCount('votos')
            ->get()
            ->map(fn(RedRespuesta $r) => $this->formatRespuesta($r, $user->id, $pregunta->mejor_respuesta_id))
            ->sortByDesc(fn($r) => [$r['es_mejor_respuesta'] ? 1 : 0, $r['votos_count']])
            ->values();

        return response()->json(['data' => $respuestas]);
    }

    /**
     * POST /user/red/preguntas/{pregunta}/respuestas
     */
    public function store(Request $request, RedPregunta $pregunta): JsonResponse
    {
        abort_if(! $pregunta->is_active, 404);

        $validated = $request->validate([
            'contenido' => 'required|string|min:10|max:5000',
        ]);

        $respuesta = RedRespuesta::create([
            'pregunta_id' => $pregunta->id,
            'user_id'     => $request->user()->id,
            'contenido'   => $validated['contenido'],
        ]);

        $respuesta->load('autor:id,name,image,personales');
        $respuesta->loadCount('votos');

        return response()->json([
            'data'    => $this->formatRespuesta($respuesta, $request->user()->id, $pregunta->mejor_respuesta_id),
            'message' => 'Respuesta publicada exitosamente.',
        ], 201);
    }

    /**
     * DELETE /user/red/respuestas/{respuesta}
     * Solo el autor puede eliminar su respuesta
     */
    public function destroy(Request $request, RedRespuesta $respuesta): JsonResponse
    {
        abort_if($respuesta->user_id !== $request->user()->id, 403, 'No puedes eliminar esta respuesta.');

        $respuesta->update(['is_deleted' => true]);

        return response()->json(['message' => 'Respuesta eliminada.']);
    }

    /**
     * POST /user/red/respuestas/{respuesta}/votos
     * Toggle de voto: si ya votó, quita el voto; si no, lo agrega
     */
    public function votar(Request $request, RedRespuesta $respuesta): JsonResponse
    {
        abort_if($respuesta->is_deleted, 422, 'No puedes votar una respuesta eliminada.');

        $user = $request->user();

        // No puede votar su propia respuesta
        abort_if($respuesta->user_id === $user->id, 403, 'No puedes votar tu propia respuesta.');

        $votoExistente = RedVoto::where('respuesta_id', $respuesta->id)
            ->where('user_id', $user->id)
            ->first();

        if ($votoExistente) {
            $votoExistente->delete();
            $accion = 'quitado';
        } else {
            RedVoto::create([
                'respuesta_id' => $respuesta->id,
                'user_id'      => $user->id,
            ]);
            $accion = 'agregado';
        }

        $votos = $respuesta->votos()->count();

        return response()->json([
            'message'     => "Voto {$accion}.",
            'votos_count' => $votos,
            'yo_vote'     => $accion === 'agregado',
        ]);
    }

    // ─── Helper privado ───────────────────────────────────────────

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
