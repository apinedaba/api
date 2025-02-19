<?php

use App\Http\Controllers\AiDiagnoseController;
use App\Http\Controllers\ChatPublicController;
use App\Http\Controllers\SintomasController;
use App\Models\Sintomas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CedulaCheck;
use App\Http\Controllers\EducationUserController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\Auth\UserAuthController;
use App\Http\Controllers\Auth\PatientAuthController;
use App\Http\Middleware\HandleInvalidToken;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PatientUserController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\QuestionnaireController;
use App\Http\Controllers\QuestionnaireLinkController;

//Rutas para profesionales
Route::post('user/login', [UserAuthController::class, 'login']);

Route::resource('ai/diagnose', AiDiagnoseController::class);


Route::post('user/register', [RegisterController::class, 'registerUser']);
Route::middleware(['auth:sanctum', 'handle_invalid_token', 'user'])->group(function () {
    Route::get('user/info', function (Request $request) {
        return $request->user();
    });
    Route::post('user/logout', [UserAuthController::class, 'logout']);
    Route::post('user/verifyCedula', [CedulaCheck::class, 'checkCedula']);
    Route::resource('user/education', EducationUserController::class);
    Route::resource('user/address', AddressController::class);
    Route::resource('user/profile', ProfileController::class);
    Route::resource('user/patient', PatientController::class);
    Route::resource('user/catalog/patients', PatientUserController::class);
    Route::resource('user/dict', UserController::class);
    Route::get('user/appointments/patient', [AppointmentController::class, 'getAppoinmentsByPatient']);
    Route::get('user/appointments/slots', [AppointmentController::class, 'getAvailableSlots']);
    Route::resource('user/appointments', AppointmentController::class);
    // Rutas para los cuestionarios
    Route::apiResource('user/questionnaires', QuestionnaireController::class);
    // Rutas para los enlaces dinámicos
    Route::post('user/questionnaires/{questionnaireId}/generate-link', [QuestionnaireLinkController::class, 'generateLink']);
    Route::get('user/questionnaires/patient/{patient}', [QuestionnaireController::class, 'getQuestionnairesByPatient']);
    Route::get('user/public-questionnaire/{token}/{user}', [QuestionnaireLinkController::class, 'showQuestionnaireResponse'])
        ->name('questionnaire.show.response');
    // Rutas para el chat público
    Route::get('user/chat-publico/{user}/{patient}', [ChatPublicController::class, 'index']);
    Route::post('user/chat-publico', [ChatPublicController::class, 'agregarComentarioPublico']);

    Route::get('user/sintomas/{user}/{patient}', [SintomasController::class, 'index']);
    Route::post('user/sintomas', [SintomasController::class, 'agregarSintoma']);

});

Route::get('user/public-questionnaire/{token}', [QuestionnaireLinkController::class, 'showPublicQuestionnaire'])
    ->name('questionnaire.public.show');
Route::post('user/questionnaires/{token}/submit', [QuestionnaireController::class, 'submitResponses'])
    ->name('questionnaire.public.submit');




//Rutas para Pacientes
Route::post('patient/login', [PatientAuthController::class, 'login']);
Route::middleware(['auth:sanctum', 'handle_invalid_token', 'patient'])->group(function () {
    Route::get('patient/info', function (Request $request) {
        return $request->user();
    });
    Route::get('patient/appointments/slots', [AppointmentController::class, 'getAvailableSlots']);
    Route::post('patient/logout', [PatientAuthController::class, 'logout']);
});


Route::post('patient/register', [RegisterController::class, 'registerPatient']);
Route::get('patient/profesional', [UserController::class, 'getProfessional']);
Route::get('patient/appointments/slots/{id}', [AppointmentController::class, 'getAvailableSlots']);