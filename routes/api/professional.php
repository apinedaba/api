<?php
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\ProfessionalController;
use App\Http\Controllers\UserController;

Route::prefix('patient/profesional')->group(function () {
  Route::get('/', [ProfessionalController::class, 'index']);
  Route::get('/filters', [ProfessionalController::class, 'filters']);
  Route::get('{id}', [UserController::class, 'getProfessionalById']);
  Route::post('{id}/disponibilidad', [AppointmentController::class, 'getAvailableSlots']);
});