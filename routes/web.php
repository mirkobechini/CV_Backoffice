<?php

use App\Http\Controllers\Admin\IssueController;
use App\Http\Controllers\Admin\MaintenanceRecordController;
use App\Http\Controllers\Admin\ProviderController;
use App\Http\Controllers\Admin\VehicleController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('welcome');

Route::view('/dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified'])
    ->name("admin.")
    ->prefix("admin")
    ->group(function () {
        Route::resource("vehicles", VehicleController::class);
        Route::resource("providers", ProviderController::class);
        Route::resource("issues", IssueController::class);
        Route::resource("maintenancerecords", MaintenanceRecordController::class)
            ->parameters(['maintenancerecords' => 'maintenanceRecord']);
    });


require __DIR__ . '/auth.php';
