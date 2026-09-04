<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\TestPermissionController;

// Route API akan didaftarkan di sini bertahap sesuai fase roadmap.
// Prefix /api/v1 diterapkan di bootstrap/app.php, bukan di sini.

Route::get('/health', [HealthController::class, 'check']);

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

Route::middleware(['auth:sanctum', 'permission:user.manage'])
    ->get('/test-permission', [TestPermissionController::class, 'check']);