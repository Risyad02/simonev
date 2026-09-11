<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\TestPermissionController;
use App\Http\Controllers\Api\V1\MasterData\UnitOfMeasureController;
use App\Http\Controllers\Api\V1\MasterData\FormulaController;
use App\Http\Controllers\Api\V1\MasterData\ReportingPeriodController;
use App\Http\Controllers\Api\V1\MasterData\UnitController;
use App\Http\Controllers\Api\V1\Structure\PerformanceStructureController;
use App\Http\Controllers\Api\V1\Indicator\IndicatorController;
use App\Http\Controllers\Api\V1\Indicator\IndicatorVersionController;

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

// ==========================================================================
// Phase 7A — Performance Structure (Structure Core, tanpa revision workflow)
// ==========================================================================
Route::prefix('performance-structure')->middleware('auth:sanctum')->group(function () {
    Route::middleware('permission:structure.view')->group(function () {
        Route::get('/', [PerformanceStructureController::class, 'index']);
        Route::get('/{performanceStructure}', [PerformanceStructureController::class, 'show']);
    });
    Route::middleware('permission:structure.manage')->group(function () {
        Route::post('/', [PerformanceStructureController::class, 'store']);
        Route::put('/{performanceStructure}', [PerformanceStructureController::class, 'update']);
        Route::patch('/{performanceStructure}', [PerformanceStructureController::class, 'update']);
    });
});

// ==========================================================================
// Phase 8 — Indicator Management
// ==========================================================================
Route::prefix('indicators')->middleware('auth:sanctum')->group(function () {
    Route::middleware('permission:indicator.view')->group(function () {
        Route::get('/', [IndicatorController::class, 'index']);
        Route::get('/{indicator}', [IndicatorController::class, 'show']);
        Route::get('/{indicator}/versions', [IndicatorVersionController::class, 'index']);
        Route::get('/{indicator}/versions/active', [IndicatorVersionController::class, 'active']);
        Route::get('/{indicator}/versions/{indicatorVersion}', [IndicatorVersionController::class, 'show']);
    });

    Route::middleware('permission:indicator.manage')->group(function () {
        Route::post('/', [IndicatorController::class, 'store']);
        Route::put('/{indicator}', [IndicatorController::class, 'update']);
        Route::patch('/{indicator}', [IndicatorController::class, 'update']);
        Route::delete('/{indicator}', [IndicatorController::class, 'destroy']);

        Route::post('/{indicator}/versions', [IndicatorVersionController::class, 'store']);
    });
});