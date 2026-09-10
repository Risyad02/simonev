<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\TestPermissionController;
use App\Http\Controllers\Api\V1\MasterData\UnitOfMeasureController;
use App\Http\Controllers\Api\V1\MasterData\FormulaController;
use App\Http\Controllers\Api\V1\MasterData\ReportingPeriodController;
use App\Http\Controllers\Api\V1\MasterData\UnitController;

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

// ==========================================================================
// Phase 6 — Master Data
// ==========================================================================
Route::prefix('master-data')->middleware('auth:sanctum')->group(function () {

    // --- Satuan (units_of_measure) — Master Data Operasional (TBD-1 CR-001) ---
    Route::middleware('permission:master-data-operasional.manage')->group(function () {
        Route::get('units-of-measure', [UnitOfMeasureController::class, 'index']);
        Route::post('units-of-measure', [UnitOfMeasureController::class, 'store']);
        Route::get('units-of-measure/{unitOfMeasure}', [UnitOfMeasureController::class, 'show']);
        Route::put('units-of-measure/{unitOfMeasure}', [UnitOfMeasureController::class, 'update']);
        Route::patch('units-of-measure/{unitOfMeasure}', [UnitOfMeasureController::class, 'update']);
        Route::delete('units-of-measure/{unitOfMeasure}', [UnitOfMeasureController::class, 'destroy']);
    });

    // --- Formula — Master Data Kritis ---
    Route::middleware('permission:master-data-kritis.view|master-data-kritis.manage')->group(function () {
        Route::get('formulas', [FormulaController::class, 'index']);
        Route::get('formulas/{formula}', [FormulaController::class, 'show']);
    });
    Route::middleware('permission:master-data-kritis.manage')->group(function () {
        Route::post('formulas', [FormulaController::class, 'store']);
        Route::put('formulas/{formula}', [FormulaController::class, 'update']);
        Route::patch('formulas/{formula}', [FormulaController::class, 'update']);
        Route::delete('formulas/{formula}', [FormulaController::class, 'destroy']);
    });

    // --- Periode Pelaporan — Master Data Operasional (TBD-1 CR-001) ---
    Route::middleware('permission:master-data-operasional.manage')->group(function () {
        Route::get('reporting-periods', [ReportingPeriodController::class, 'index']);
        Route::post('reporting-periods', [ReportingPeriodController::class, 'store']);
        Route::get('reporting-periods/{reportingPeriod}', [ReportingPeriodController::class, 'show']);
        Route::put('reporting-periods/{reportingPeriod}', [ReportingPeriodController::class, 'update']);
        Route::patch('reporting-periods/{reportingPeriod}', [ReportingPeriodController::class, 'update']);
        Route::delete('reporting-periods/{reportingPeriod}', [ReportingPeriodController::class, 'destroy']);
    });

    // --- Unit/Bidang — Master Data Operasional (TBD-1 CR-001) — TIDAK ADA physical delete ---
    Route::middleware('permission:master-data-operasional.manage')->group(function () {
        Route::get('units', [UnitController::class, 'index']);
        Route::post('units', [UnitController::class, 'store']);
        Route::get('units/{unit}', [UnitController::class, 'show']);
        Route::put('units/{unit}', [UnitController::class, 'update']);
        Route::patch('units/{unit}', [UnitController::class, 'update']);
        Route::patch('units/{unit}/deactivate', [UnitController::class, 'deactivate']);
        Route::patch('units/{unit}/activate', [UnitController::class, 'activate']);
    });
});