<?php

use App\Http\Controllers\AppointmentCartController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\Auth\PatientAuthController;
use App\Http\Controllers\AvailabilitiController;
use App\Http\Controllers\EmotionLogController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PatientUserController;
use App\Http\Controllers\PsychologistReviewController;
use App\Http\Controllers\QuestionnaireController;
use App\Http\Controllers\StripeController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CatalogosController;
use Illuminate\Http\Request;
Route::middleware('auth:patient')->get('patient/me', function (Request $request) {
    return $request->user();
});

Route::middleware(['auth:patient'])->prefix('patient')->group(function () {

    Route::put('profile', [PatientController::class, 'updateFromUser']);
    // Cuestionarios asignados al paciente autenticado
    Route::get('questionnaires', [QuestionnaireController::class, 'getQuestionnairesForPatient']);
    Route::get('appointments/slots', [AppointmentController::class, 'getAvailableSlots']);
    Route::get('appointments/patient', [AppointmentController::class, 'getAppoinmentsByPatient']);
    Route::get('appointments/{id}', [AppointmentController::class, 'showABP']);
    Route::get('profesional/current', [PatientUserController::class, 'getCurrentProfesional']);
    Route::post('logout', [PatientAuthController::class, 'logout']);
    Route::get('emotion-logs', [EmotionLogController::class, 'index']);
    Route::post('emotion-logs', [EmotionLogController::class, 'store']);
    Route::post('psychologists/{id}/reviews', [PsychologistReviewController::class, 'store']);
    Route::post('availability', [AvailabilitiController::class, 'store']);
    Route::post('cart', [AppointmentCartController::class, 'store']);
    Route::get('cart', [AppointmentCartController::class, 'show']);
    Route::get('cart/reserva/{id}', [AppointmentCartController::class, 'cartById']);
    Route::post('stripe/create-intent', [StripeController::class, 'createPaymentIntent']);
    Route::post('stripe/confirmar-pago', [StripeController::class, 'confirmarPago']);
    // OXXO con Elements (nuevo / ajustado)
    Route::post('/stripe/oxxo-intent', [StripeController::class, 'createOxxoIntent']);
    // (opcional) Checkout OXXO por si lo usas en otro lado
    Route::post('/stripe/oxxo-checkout', [StripeController::class, 'oxxoCheckout']);
});

Route::post('patient/login', [PatientAuthController::class, 'login']);
