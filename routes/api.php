<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\IssueController;
use App\Http\Controllers\Api\DeadlineController;
use App\Http\Controllers\Api\MaintenanceRecordController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Auth (pubblica) — rate limit per prevenire brute-force
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

// Rotta protette da token
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/vehicles', [VehicleController::class, 'index']);
    Route::get('/vehicles/{vehicle}', [VehicleController::class, 'show']);

    Route::get('/issues', [IssueController::class, 'index']);
    Route::get('/issues/suggestions', [IssueController::class, 'suggestions'])->name('api.issues.suggestions');
    Route::get('/issues/{issue}', [IssueController::class, 'show']);

    Route::get('/deadlines', [DeadlineController::class, 'index']);
    Route::get('/deadlines/{deadline}', [DeadlineController::class, 'show']);

    Route::get('/maintenance-records', [MaintenanceRecordController::class, 'index']);
    Route::get('/maintenance-records/{maintenanceRecord}', [MaintenanceRecordController::class, 'show']);
});
