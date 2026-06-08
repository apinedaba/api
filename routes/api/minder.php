<?php

use App\Http\Controllers\Minder\MinderGroupController;
use App\Http\Controllers\Minder\MinderConsultationRequestController;
use App\Http\Controllers\Minder\MinderMessageController;
use App\Http\Controllers\Minder\MinderReactionController;
use App\Http\Controllers\Minder\MinderSupportController;
use App\Http\Controllers\Minder\MinderSupportAppointmentController;
use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────────────────────────
//  Comunidad Minder — solo psicólogos verificados (approved + activo)
// ─────────────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'handle_invalid_token', 'user', 'minder_access'])
    ->prefix('user/minder')
    ->group(function () {

        // Grupos
        Route::get('groups', [MinderGroupController::class, 'index']);
        Route::post('groups', [MinderGroupController::class, 'store']);
        Route::get('groups/{minderGroup}', [MinderGroupController::class, 'show']);
        Route::post('groups/{minderGroup}/join', [MinderGroupController::class, 'join']);
        Route::delete('groups/{minderGroup}/leave', [MinderGroupController::class, 'leave']);

        // Mensaje directo 1-a-1 entre psicólogos (crea o recupera el grupo DM)
        Route::post('dm', [MinderGroupController::class, 'startDirectMessage']);
        Route::get('search-users', [MinderGroupController::class, 'searchUsers']);

        // Solicitudes de consulta profesional. El chat se crea al aceptar.
        Route::get('consultation-requests', [MinderConsultationRequestController::class, 'index']);
        Route::post('consultation-requests', [MinderConsultationRequestController::class, 'store'])
            ->middleware('throttle:minder-messages');
        Route::patch('consultation-requests/{consultationRequest}/accept', [MinderConsultationRequestController::class, 'accept']);
        Route::patch('consultation-requests/{consultationRequest}/reject', [MinderConsultationRequestController::class, 'reject']);
        Route::delete('consultation-requests/{consultationRequest}', [MinderConsultationRequestController::class, 'cancel']);

        // Mensajes del grupo
        Route::get('groups/{minderGroup}/messages', [MinderMessageController::class, 'index']);
        Route::post('groups/{minderGroup}/messages', [MinderMessageController::class, 'store'])
            ->middleware('throttle:minder-messages');

        // Hilos de respuesta
        Route::get('groups/{minderGroup}/messages/{message}/thread', [MinderMessageController::class, 'thread']);
        Route::post('groups/{minderGroup}/messages/{message}/thread', [MinderMessageController::class, 'storeThread'])
            ->middleware('throttle:minder-messages');

        // Reacciones
        Route::post('groups/{minderGroup}/messages/{message}/reactions', [MinderReactionController::class, 'store']);
        Route::delete('groups/{minderGroup}/messages/{message}/reactions', [MinderReactionController::class, 'destroy']);

        // Denuncias de mensajes
        Route::post('groups/{minderGroup}/messages/{message}/report', [MinderMessageController::class, 'report']);

        // Soporte Mindmeet (canal directo con el equipo)
        Route::get('support', [MinderSupportController::class, 'show']);
        Route::post('support/messages', [MinderSupportController::class, 'store'])
            ->middleware('throttle:minder-messages');
        Route::get('support-appointments', [MinderSupportAppointmentController::class, 'index']);
        Route::post('support-appointments', [MinderSupportAppointmentController::class, 'store']);
        Route::delete('support-appointments/{appointment}', [MinderSupportAppointmentController::class, 'cancel']);
    });
