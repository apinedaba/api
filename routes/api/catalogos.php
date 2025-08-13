<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CatalogosController;

Route::prefix('patient/catalogos')->group(function () {
    Route::get('/', [CatalogosController::class, 'getCatalogs']);        
});