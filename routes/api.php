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
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/auth/logout', [LogoutController::class, 'logoutUser']);
    Route::get('/auth/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/verifyCedula', [CedulaCheck::class, 'checkCedula']);
    Route::resource('/education', EducationUserController::class);
    Route::resource('/address', AddressController::class);
    Route::resource('/profile', ProfileController::class);
});

Route::post('/auth/register', [RegisterController::class, 'registerUser']);
Route::post('/auth/login', [LoginController::class, 'loginUser']);