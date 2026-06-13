<?php

namespace App\Http\Controllers\Red;

use App\Http\Controllers\Controller;
use App\Models\RedPregunta;
use App\Models\RedQuestionPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RedQuestionPreferenceController extends Controller
{
    public function toggleSaved(Request $request, RedPregunta $pregunta): JsonResponse
    {
        return $this->toggle($request, $pregunta, 'is_saved');
    }

    public function toggleFollowing(Request $request, RedPregunta $pregunta): JsonResponse
    {
        return $this->toggle($request, $pregunta, 'is_following');
    }

    private function toggle(Request $request, RedPregunta $pregunta, string $field): JsonResponse
    {
        abort_if(! $pregunta->is_active, 404);

        $preference = RedQuestionPreference::firstOrCreate([
            'pregunta_id' => $pregunta->id,
            'user_id' => $request->user()->id,
        ]);

        $preference->update([$field => ! $preference->{$field}]);

        if (! $preference->is_saved && ! $preference->is_following) {
            $preference->delete();
        }

        return response()->json([
            'is_saved' => (bool) $preference->is_saved,
            'is_following' => (bool) $preference->is_following,
        ]);
    }
}
