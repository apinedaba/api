<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CatalogosController;

Route::prefix('patient/catalogos')->group(function () {
    Route::get('/', [CatalogosController::class, 'getCatalogs']);
});
Route::prefix('user/catalogos')->group(function () {
    Route::get('/prices', [CatalogosController::class, 'getPrices']);
    Route::get('/price/{priceId}', [CatalogosController::class, 'getPriceById']);
});