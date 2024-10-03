<?php

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




//Rutas para profesionales
Route::post('user/login', [UserAuthController::class, 'login']);
Route::middleware(['auth:sanctum', 'handle_invalid_token', 'user'])->group(function () {
    Route::get('user/info', function (Request $request) {
        return $request->user();
    });
    Route::post('user/logout', [UserAuthController::class, 'logout']);
    Route::post('user/verifyCedula', [CedulaCheck::class, 'checkCedula']);
    Route::resource('user/education', EducationUserController::class);
    Route::resource('user/address', AddressController::class);
    Route::resource('user/profile', ProfileController::class);
});





//Rutas para Pacientes
Route::post('patient/login', [PatientAuthController::class, 'login']);
Route::middleware(['auth:sanctum', 'handle_invalid_token', 'patient'])->group(function () {
    Route::get('patient/info', function (Request $request) {
        return $request->user();
    });
    Route::post('patient/logout', [PatientAuthController::class, 'logout']);
});


 Route::post('patient/register', [RegisterController::class, 'registerPatient']);

 