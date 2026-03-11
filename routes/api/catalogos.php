<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CatalogosController;

Route::prefix('patient/catalogos')->group(function () {
    Route::get('/', [CatalogosController::class, 'getCatalogs']);
    Route::get('/prices', [CatalogosController::class, 'getPrices']);
    Route::get('/price/{priceId}', [CatalogosController::class, 'getPriceById']);
});
Route::middleware(['auth:sanctum', 'handle_invalid_token', 'user'])->prefix('user/catalogos')->group(function () {
    Route::get('/prices', [CatalogosController::class, 'getPrices']);
    Route::get('/prices/{priceId}', [CatalogosController::class, 'getPriceById']);
});
