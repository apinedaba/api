<?php

use App\Http\Controllers\Red\RedPreguntaController;
use App\Http\Controllers\Red\RedRespuestaController;
use App\Http\Controllers\Red\RedReportController;
use App\Http\Controllers\Red\RedQuestionPreferenceController;
use App\Http\Controllers\Red\RedTaxonomyController;
use App\Http\Controllers\Red\RedProfessionalProfileController;
use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────────────────────────
//  Mentes en Red — solo psicólogos verificados (mismo acceso que Minder)
// ─────────────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'handle_invalid_token', 'user', 'minder_access'])
    ->prefix('user/red')
    ->group(function () {

        // ── Preguntas ──────────────────────────────────────────────
        Route::get('taxonomy', [RedTaxonomyController::class, 'index']);
        Route::get('professionals/{user}', [RedProfessionalProfileController::class, 'show']);
        Route::get('preguntas', [RedPreguntaController::class, 'index']);
        Route::post('preguntas', [RedPreguntaController::class, 'store'])
            ->middleware('throttle:red-preguntas');
        Route::get('preguntas/mis-preguntas/sin-leer', [RedPreguntaController::class, 'misPreguntasSinLeer']);
        Route::get('preguntas/{pregunta}', [RedPreguntaController::class, 'show']);
        Route::delete('preguntas/{pregunta}', [RedPreguntaController::class, 'destroy']);
        Route::put('preguntas/{pregunta}', [RedPreguntaController::class, 'update']);
        Route::patch('preguntas/{pregunta}/close', [RedPreguntaController::class, 'close']);
        Route::patch('preguntas/{pregunta}/reopen', [RedPreguntaController::class, 'reopen']);
        Route::post('preguntas/{pregunta}/report', [RedReportController::class, 'reportQuestion']);
        Route::post('preguntas/{pregunta}/saved', [RedQuestionPreferenceController::class, 'toggleSaved']);
        Route::post('preguntas/{pregunta}/following', [RedQuestionPreferenceController::class, 'toggleFollowing']);
        Route::patch('preguntas/{pregunta}/marcar-vista', [RedPreguntaController::class, 'marcarVista']);
        Route::post(
            'preguntas/{pregunta}/mejor-respuesta/{respuesta}',
            [RedPreguntaController::class, 'marcarMejorRespuesta']
        );

        // ── Respuestas ─────────────────────────────────────────────
        Route::get('preguntas/{pregunta}/respuestas', [RedRespuestaController::class, 'index']);
        Route::post('preguntas/{pregunta}/respuestas', [RedRespuestaController::class, 'store'])
            ->middleware('throttle:red-respuestas');
        Route::delete('respuestas/{respuesta}', [RedRespuestaController::class, 'destroy']);
        Route::put('respuestas/{respuesta}', [RedRespuestaController::class, 'update']);
        Route::post('respuestas/{respuesta}/report', [RedReportController::class, 'reportAnswer']);

        // ── Votos ──────────────────────────────────────────────────
        Route::post('respuestas/{respuesta}/votos', [RedRespuestaController::class, 'votar']);
    });
