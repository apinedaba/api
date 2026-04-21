<?php

use App\Http\Controllers\Red\RedPreguntaController;
use App\Http\Controllers\Red\RedRespuestaController;
use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────────────────────────
//  Mentes en Red — solo psicólogos verificados (mismo acceso que Minder)
// ─────────────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'handle_invalid_token', 'user', 'minder_access'])
    ->prefix('user/red')
    ->group(function () {

        // ── Preguntas ──────────────────────────────────────────────
        Route::get('preguntas', [RedPreguntaController::class, 'index']);
        Route::post('preguntas', [RedPreguntaController::class, 'store'])
            ->middleware('throttle:red-preguntas');
        Route::get('preguntas/{pregunta}', [RedPreguntaController::class, 'show']);
        Route::delete('preguntas/{pregunta}', [RedPreguntaController::class, 'destroy']);
        Route::post(
            'preguntas/{pregunta}/mejor-respuesta/{respuesta}',
            [RedPreguntaController::class, 'marcarMejorRespuesta']
        );

        // ── Respuestas ─────────────────────────────────────────────
        Route::get('preguntas/{pregunta}/respuestas', [RedRespuestaController::class, 'index']);
        Route::post('preguntas/{pregunta}/respuestas', [RedRespuestaController::class, 'store'])
            ->middleware('throttle:red-respuestas');
        Route::delete('respuestas/{respuesta}', [RedRespuestaController::class, 'destroy']);

        // ── Votos ──────────────────────────────────────────────────
        Route::post('respuestas/{respuesta}/votos', [RedRespuestaController::class, 'votar']);
    });
