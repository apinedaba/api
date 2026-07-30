<?php

namespace App\Http\Controllers;

use App\Models\MindmeetFeedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MindmeetFeedbackController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $feedback = MindmeetFeedback::query()
            ->where('user_id', $request->user()->id)
            ->first();

        return response()->json([
            'has_submitted' => $feedback !== null,
            'data' => $feedback,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (MindmeetFeedback::where('user_id', $request->user()->id)->exists()) {
            throw ValidationException::withMessages([
                'feedback' => 'Ya registraste tu evaluación de MindMeet.',
            ]);
        }

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'team_message' => ['nullable', 'string', 'max:2000'],
            'improvement_feedback' => ['nullable', 'string', 'max:2000'],
        ]);

        $feedback = MindmeetFeedback::create([
            ...$data,
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Gracias por compartir tu experiencia con MindMeet.',
            'data' => $feedback,
        ], 201);
    }
}
