<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VendedorController;

Route::prefix('vendedores')->middleware(['auth:sanctum', 'handle_invalid_token', 'vendedor'])->group(function () {
    Route::get('/', [VendedorController::class, 'index']);
});