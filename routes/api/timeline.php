<?php

use App\Http\Controllers\PatientTimelineController;
use Illuminate\Support\Facades\Route;

Route::prefix('user')->middleware(['auth:sanctum', 'handle_invalid_token', 'user'])->group(function () {
    // Timeline completo del paciente
    Route::get('/patient/{id}/timeline', [PatientTimelineController::class, 'index']);

    // Crear nota
    Route::post('/sessions/{sessionId}/notes', [PatientTimelineController::class, 'storeNote']);

    // Subir adjunto
    Route::post('/sessions/{sessionId}/attachments', [PatientTimelineController::class, 'storeAttachment']);

    // Eliminar nota
    Route::delete('/notes/{id}', [PatientTimelineController::class, 'deleteNote']);

    // Eliminar archivo
    Route::delete('/attachments/{id}', [PatientTimelineController::class, 'deleteAttachment']);

    Route::get('/attachments/view/{id}', [PatientTimelineController::class, 'streamAttachment']);
});
