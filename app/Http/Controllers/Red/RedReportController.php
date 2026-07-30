<?php

namespace App\Http\Controllers\Red;

use App\Http\Controllers\Controller;
use App\Models\RedPregunta;
use App\Models\RedReport;
use App\Models\RedRespuesta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RedReportController extends Controller
{
    public function reportQuestion(Request $request, RedPregunta $pregunta): JsonResponse
    {
        abort_if(! $pregunta->is_active || $pregunta->trashed(), 404);
        abort_if($pregunta->user_id === $request->user()->id, 422, 'No puedes reportar tu propia pregunta.');

        return $this->store($request, RedReport::TARGET_QUESTION, $pregunta->id);
    }

    public function reportAnswer(Request $request, RedRespuesta $respuesta): JsonResponse
    {
        abort_if($respuesta->is_deleted, 404);
        abort_if($respuesta->user_id === $request->user()->id, 422, 'No puedes reportar tu propia respuesta.');

        return $this->store($request, RedReport::TARGET_ANSWER, $respuesta->id);
    }

    private function store(Request $request, string $targetType, int $targetId): JsonResponse
    {
        $validated = $request->validate([
            'reason' => [
                'required',
                Rule::in(['patient_privacy', 'inappropriate', 'misinformation', 'spam', 'harassment', 'other']),
            ],
            'details' => 'nullable|string|max:1000',
        ]);

        $alreadyPending = RedReport::where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->where('reported_by', $request->user()->id)
            ->where('status', 'pending')
            ->exists();

        abort_if($alreadyPending, 422, 'Ya reportaste este contenido y está pendiente de revisión.');

        $report = RedReport::create([
            'target_type' => $targetType,
            'target_id' => $targetId,
            'reported_by' => $request->user()->id,
            'reason' => $validated['reason'],
            'details' => $validated['details'] ?? null,
        ]);

        return response()->json([
            'data' => $report,
            'message' => 'Reporte enviado. El equipo de MindMeet lo revisará.',
        ], 201);
    }
}
