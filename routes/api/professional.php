<?php
use App\Http\Controllers\ProfessionalController;

Route::prefix('patient/profesional')->group(function () {
  Route::get('/', [ProfessionalController::class, 'index']);
  Route::get('/filters', [ProfessionalController::class, 'filters']);
});