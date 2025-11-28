<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\PatientAuthController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SocialiteController;
use App\Http\Controllers\Auth\UserAuthController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\AiDiagnoseController;
use App\Http\Controllers\AppointmentCartController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AvailabilitiController;
use App\Http\Controllers\CedulaCheck;
use App\Http\Controllers\ChatPublicController;
use App\Http\Controllers\EducationUserController;
use App\Http\Controllers\EmotionLogController;
use App\Http\Controllers\GoogleCalendarController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\IdentityController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PatientMedicationController;
use App\Http\Controllers\PatientUserController;
use App\Http\Controllers\PaymentsController;
use App\Http\Controllers\PhotoUploadController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PsychologistReviewController;
use App\Http\Controllers\QuestionnaireController;
use App\Http\Controllers\QuestionnaireLinkController;
use App\Http\Controllers\SintomasController;
use App\Http\Controllers\StripeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserStepsController;
use App\Http\Middleware\HandleInvalidToken;
use App\Models\Sintomas;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

// Rutas publicas
Route::post('user/login', [UserAuthController::class, 'login']);
Route::resource('ai/diagnose', AiDiagnoseController::class);
Route::post('user/register', [RegisterController::class, 'registerUser']);
Route::post('user/verify-registration-code', [RegisterController::class, 'verifyCode']);
Route::post('user/resend-registration-code', [RegisterController::class, 'resendCode'])
    ->middleware('throttle:resend');

Route::get('user/auth/{provider}/redirect/professional', [SocialiteController::class, 'redirectProfessional']);
Route::get('user/auth/{provider}/callback/professional', [SocialiteController::class, 'callbackProfessional']);
Route::get('user/public-questionnaire/{token}', [QuestionnaireLinkController::class, 'showPublicQuestionnaire'])
    ->name('questionnaire.public.show');
Route::post('user/questionnaires/{token}/submit', [QuestionnaireController::class, 'submitResponses'])
    ->name('questionnaire.public.submit');
// Rutas para el reseteo de contraseña
Route::post('user/forgot-password', [PasswordResetController::class, 'sendResetCode']);
Route::post('user/verify-code', [PasswordResetController::class, 'verifyCode']);
Route::post('user/reset-password', [PasswordResetController::class, 'resetPassword']);
Route::post('patient/forgot-password', [PasswordResetController::class, 'sendResetCodePatient']);
Route::post('patient/verify-code', [PasswordResetController::class, 'verifyCodePatient']);
Route::post('patient/reset-password', [PasswordResetController::class, 'resetPasswordPatient']);
Route::get('user/email/verify/{id}/{hash}', function ($id, $hash) {
    $user = User::findOrFail($id);

    if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
        return response()->json(['message' => 'Enlace inválido'], 400);
    }

    if ($user->hasVerifiedEmail()) {
        return response()->json(['message' => 'Email ya verificado'], 200);
    }

    if ($user->markEmailAsVerified()) {
        event(new Verified($user));
    }

    return response()->json(['message' => 'Email verificado correctamente'], 200);
})->middleware(['signed'])->name('verification.verify');

// Grupo 2: Requiere autenticación Y una suscripción activa.
// Aquí van todas las funcionalidades principales de la plataforma.
Route::middleware(['auth:sanctum', 'handle_invalid_token', 'user'])->group(function () {
    // Info básica y gestión de cuenta
    Route::get('user/info', function (Request $request) {
        return $request->user()->load('subscription');
    });
    Route::post('user/logout', [UserAuthController::class, 'logout']);
    Route::post('user/email/resend', [UserAuthController::class, 'resendVerifyEmail'])->middleware(['throttle:6,1'])->name('verification.resend');

    // Onboarding y configuración de perfil
    Route::get('user/steps-form/{id}', [UserStepsController::class, 'getStepsForm']);
    Route::patch('user/save-step/{id}', [UserStepsController::class, 'saveStep']);
    Route::post('user/complete-profile/{id}', [UserStepsController::class, 'completeProfile']);

    Route::post('user/sep/cedula', [CedulaCheck::class, 'buscarCedula']);

    Route::resource('user/education', EducationUserController::class);
    Route::resource('user/address', AddressController::class);
    Route::resource('user/profile', ProfileController::class);
    Route::post('user/profile/avatar/upload-profile-image', [ProfileController::class, 'upload']);
    Route::post('user/upload/photo', [PhotoUploadController::class, 'upload']);
    Route::post('user/identity/upload', [IdentityController::class, 'store']);

    // --- Rutas de gestión de suscripción (DEBEN ESTAR AQUÍ) ---
    Route::get('user/subscription/status', [StripeController::class, 'getSubscriptionStatus']);
    Route::post('user/subscription/checkout-session', [StripeController::class, 'createSubscriptionCheckoutSession']);
    Route::get('user/subscription/portal', [StripeController::class, 'createCustomerPortalSession']);
    Route::get('user/cart-pays', [AppointmentCartController::class, 'pays']);
    Route::resource('user/payments', PaymentsController::class);
    // Gestión de pacientes
    Route::resource('user/patient', PatientController::class);
    Route::resource('user/catalog/patients', PatientUserController::class);
    Route::prefix('user/patients/{patient}/medications')->group(function () {
        Route::get('/', [PatientMedicationController::class, 'index']);
        Route::post('/', [PatientMedicationController::class, 'store']);
        Route::put('/{medication}', [PatientMedicationController::class, 'update']);
        Route::delete('/{medication}', [PatientMedicationController::class, 'destroy']);
    });
    Route::put('user/patients/{id}/relationships', [PatientController::class, 'updateRelationships']);

    // Agenda y citas
    Route::get('user/appointments/patient', [AppointmentController::class, 'getAppoinmentsByPatient']);
    Route::get('user/appointments/slots', [AppointmentController::class, 'getAvailableSlots']);
    Route::resource('user/appointments', AppointmentController::class);

    // Funcionalidades avanzadas (cuestionarios, chat, etc.)
    Route::apiResource('user/questionnaires', QuestionnaireController::class);
    Route::post('user/questionnaires/{questionnaireId}/generate-link', [QuestionnaireLinkController::class, 'generateLink']);
    Route::get('user/questionnaires/patient/{patient}', [QuestionnaireController::class, 'getQuestionnairesByPatient']);
    Route::get('user/public-questionnaire/{token}/{user}', [QuestionnaireLinkController::class, 'showQuestionnaireResponse'])->name('questionnaire.show.response');
    Route::get('user/chat-publico/{user}/{patient}', [ChatPublicController::class, 'index']);
    Route::post('user/chat-publico', [ChatPublicController::class, 'agregarComentarioPublico']);

    // Datos clínicos del paciente
    Route::get('user/emotion-logs', [EmotionLogController::class, 'index']);
    Route::get('user/sintomas/{user}/{patient}', [SintomasController::class, 'index']);
    Route::post('user/sintomas', [SintomasController::class, 'agregarSintoma']);
    Route::get('user/google/connection-status', [GoogleCalendarController::class, 'checkConnectionStatus']);
});
Route::get('user/google/calendar/callback', [GoogleCalendarController::class, 'handleCallback']);

// Rutas para Pacientes

Route::middleware(['auth:sanctum', 'handle_invalid_token', 'patient'])->prefix('patient')->group(function () {
    Route::get('info', function (Request $request) {
        return $request->user();
    });
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
Route::post('/stripe/webhook', [StripeController::class, 'webhook']);
Route::post('/stripe/subscription/webhook', [StripeController::class, 'handleWebhook']);
Route::get('patient/psychologists/{id}/reviews', [PsychologistReviewController::class, 'index']);
Route::get('patient/availability', [AvailabilitiController::class, 'index']);
Route::post('patient/register', [RegisterController::class, 'registerPatient']);
Route::get('patient/profesional/{id}', [UserController::class, 'getProfessionalById']);
Route::post('patient/profesional/{id}/disponibilidad', [AppointmentController::class, 'getAvailableSlots']);
Route::post('patient/check-email', [RegisterController::class, 'checkPatientEmail']);
Route::get('user/auth/{provider}/redirect/professional', [SocialiteController::class, 'redirectProfessional']);
Route::get('user/auth/{provider}/callback/professional', [SocialiteController::class, 'callbackProfessional']);
Route::get('patient/auth/{provider}/redirect/patient', [SocialiteController::class, 'redirectPatient']);
Route::get('patient/auth/{provider}/callback/patient', [SocialiteController::class, 'callbackPatient']);

Route::get('patient/pages/home', [HomeController::class, 'getImages']);
Route::get('patient/pages/buenfin', [HomeController::class, 'buenfin']);

require __DIR__ . '/api/catalogos.php';
require __DIR__ . '/api/contratos.php';
require __DIR__ . '/api/professional.php';
require __DIR__ . '/api/deviceToken.php';
