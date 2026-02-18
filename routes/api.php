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
use App\Http\Controllers\ExpedienteController;
use App\Http\Controllers\GoogleCalendarController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\IdentityController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\ConsultaContactoController;
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
    ->name('questionnaire.public.show.user');
Route::get('patient/public-questionnaire/{token}', [QuestionnaireLinkController::class, 'showPublicQuestionnaire'])
    ->name('questionnaire.public.show.patient');
Route::post('user/questionnaires/{token}/submit', [QuestionnaireController::class, 'submitResponses'])
    ->name('questionnaire.public.submit.user');
Route::post('patient/questionnaires/{token}/submit', [QuestionnaireController::class, 'submitResponses'])
    ->name('questionnaire.public.submit.patient');
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

Route::get('user/google/calendar/callback', [GoogleCalendarController::class, 'handleCallback']);

// Búsqueda pública de psicólogos por ubicación
Route::get('psychologists/search', [\App\Http\Controllers\Api\OfficeController::class, 'search']);

// Rutas para Pacientes

Route::post('/stripe/webhook', [StripeController::class, 'webhook']);
Route::post('/stripe/subscription/webhook', [StripeController::class, 'handle']);
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
Route::get('patient/numberPatients', [PatientController::class, 'getNumberPatient']);
Route::post('patient/enviar-consulta', [ConsultaContactoController::class, 'store']);


Route::get('patient/pages/home', [HomeController::class, 'getImages']);
Route::get('patient/pages/buenfin', [HomeController::class, 'buenfin']);



require __DIR__ . '/api/catalogos.php';
require __DIR__ . '/api/contratos.php';
require __DIR__ . '/api/professional.php';
require __DIR__ . '/api/deviceToken.php';
require __DIR__ . '/api/timeline.php';
require __DIR__ . '/api/attachments.php';
require __DIR__ . '/api/patient.php';