<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ShareController;
use App\Http\Controllers\GoogleCalendarController;


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/share/profesional/{id}/{slug?}', [ShareController::class, 'professional'])
    ->whereNumber('id')
    ->name('share.professional');

Route::get('/user/google/calendar/callback', [GoogleCalendarController::class, 'handleCallback'])->middleware('auth');
