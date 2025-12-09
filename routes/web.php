<?php

use App\Http\Controllers\AppointmentCartController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ShareController;
use App\Http\Controllers\UserController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
 * |--------------------------------------------------------------------------
 * | Web Routes
 * |--------------------------------------------------------------------------
 * |
 * | Here is where you can register web routes for your application. These
 * | routes are loaded by the RouteServiceProvider within a group which
 * | contains the "web" middleware group. Now create something great!
 * |
 */

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});
Route::get('/phpinfo-test', function () {
    phpinfo();
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/psicologos', [UserController::class, 'getAllUsers'])->name('psicologos');
    Route::get('/psicologo/{id}', [UserController::class, 'show'])->name('psicologoShow');
    Route::delete('/psicologo/{id}', [UserController::class, 'desactive'])->name('psicologo.desactive');
    Route::post('/psicologo/{id}', [UserController::class, 'active'])->name('psicologo.active');

    Route::get('/carts', [AppointmentCartController::class, 'getAllCarts'])->name('carts');
    Route::get('/cart/{patient}', [AppointmentCartController::class, 'getCartByPatient'])->name('cartByPatient');

    Route::get('/pacientes', [PatientController::class, 'getAllPatients'])->name('pacientes');
    Route::get('/paciente/{id}', [PatientController::class, 'getPatientById'])->name('paciente');
});
Route::get('/share/profesional/{id}/{slug?}', [ShareController::class, 'professional'])
    ->whereNumber('id')
    ->name('share.professional');
require __DIR__ . '/auth.php';
