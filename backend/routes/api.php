<?php

use Illuminate\Support\Facades\Route;
Use App\Http\Controllers\Api\V1\HealthController;

// Route API akan didaftarkan di sini bertahap sesuai fase roadmap.
// Prefix /api/v1 diterapkan di bootstrap/app.php, bukan di sini.

Route::get('/health', [HealthController::class, 'check']);