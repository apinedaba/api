<?php
// routes/api.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{ContractTemplateController, ContractInstanceController, ContractAssignmentController};

Route::prefix('user/contracts')->middleware(['auth:sanctum', 'handle_invalid_token', 'user'])->group(function () {
  // Plantillas
  Route::get('/templates', [ContractTemplateController::class, 'index']);
  Route::post('/templates', [ContractTemplateController::class, 'store']);
  Route::put('/templates/{template}', [ContractTemplateController::class, 'update']);
  Route::delete('/templates/{template}', [ContractTemplateController::class, 'destroy']);
  Route::get('/templates/{template}', [ContractTemplateController::class, 'show']);

  // Instancias
  Route::post('/instances', [ContractInstanceController::class, 'store']);
  Route::post('/instances/{instance}/send', [ContractInstanceController::class, 'send']);
  Route::get('/instances/{instance}', [ContractInstanceController::class, 'show']);
  Route::post('/instances/{instance}/signature', [ContractInstanceController::class, 'signature']); // Canvas → PNG
  Route::post('/instances/{instance}/finalize', [ContractInstanceController::class, 'finalize']);   // DOMPDF + Cloudinary
  Route::post('/instances/{instance}/upload', [ContractInstanceController::class, 'upload']);

  // Camino 2
  Route::get('/assignments', [ContractAssignmentController::class, 'index']);
  Route::get('/assignments/{id}', [ContractAssignmentController::class, 'show']);
  Route::post('/assignments', [ContractAssignmentController::class, 'store']);
  Route::post('/assignments/{id}/signature', [ContractAssignmentController::class, 'signature']);
  Route::post('/assignments/{id}/finalize', [ContractAssignmentController::class, 'finalize']);
  Route::get('/assignments/{id}/pdf', [ContractAssignmentController::class, 'downloadPdf']);

});


Route::prefix('patient/contracts')->middleware(['auth:sanctum', 'handle_invalid_token', 'patient'])->group(function () {
  Route::get('/assignments', [ContractAssignmentController::class, 'index']);
  Route::get('/assignments/{id}', [ContractAssignmentController::class, 'show']);
  Route::post('/assignments', [ContractAssignmentController::class, 'store']);
  Route::post('/assignments/{id}/signature', [ContractAssignmentController::class, 'signature']);
  Route::post('/assignments/{id}/finalize', [ContractAssignmentController::class, 'finalize']);
  Route::get('/assignments/{id}/pdf', [ContractAssignmentController::class, 'downloadPdf']);

});
