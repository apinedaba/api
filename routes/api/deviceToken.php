<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DeviceTokenController;
use App\Http\Controllers\TestPushController;

Route::middleware(['auth:sanctum', 'handle_invalid_token', 'user'])->group(function () {
  Route::post('user/me/device-tokens', [DeviceTokenController::class, 'store']);
  Route::post('user/push/test/{id}', [TestPushController::class, 'send']); // test manual
  Route::post('user/push/register', [DeviceTokenController::class, 'register']);
});