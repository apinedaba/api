<?php
// routes/api.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{ContractTemplateController, ContractInstanceController};

Route::prefix('user/contracts')->middleware('auth:sanctum')->group(function() {
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
  Route::post('/instances/{instance}/upload', [ContractInstanceController::class, 'upload']);       // Camino 2
});
