<?php
use App\Http\Controllers\PatientAttachmentController;

// Adjuntos del expediente
Route::prefix('user')->middleware(['auth:sanctum', 'handle_invalid_token', 'user'])->group(function () {
    Route::get('/patient/{id}/attachments', [PatientAttachmentController::class, 'index']);
    Route::post('/patient/{id}/attachments', [PatientAttachmentController::class, 'store']);
    Route::delete('/attachments/{id}', [PatientAttachmentController::class, 'delete']);
});
