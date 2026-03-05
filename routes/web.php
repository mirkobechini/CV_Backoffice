<?php

use App\Http\Controllers\Admin\DeadlineController;
use App\Http\Controllers\Admin\EquipmentController;
use App\Http\Controllers\Admin\IssueController;
use App\Http\Controllers\Admin\MaintenanceRecordController;
use App\Http\Controllers\Admin\ProviderController;
use App\Http\Controllers\Admin\VehicleController;
use App\Http\Controllers\Admin\VehicleTypeController;
use App\Http\Controllers\Admin\MileageLogController;
use App\Http\Controllers\Admin\EquipmentTypeController;
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
        Route::resource("deadlines", DeadlineController::class);
        Route::resource("equipments", EquipmentController::class);
        Route::resource("mileagelogs", MileageLogController::class)
            ->parameters(['mileagelogs' => 'mileageLog']);
        Route::resource("maintenancerecords", MaintenanceRecordController::class)
            ->parameters(['maintenancerecords' => 'maintenanceRecord']);
        Route::resource("vehicletypes", VehicleTypeController::class)
            ->parameters(['vehicletypes' => 'vehicleType']);
        Route::resource("equipmenttypes", EquipmentTypeController::class)
            ->parameters(['equipmenttypes' => 'equipmentType']);
        Route::patch('maintenancerecords/{maintenanceRecord}/complete', [MaintenanceRecordController::class, 'complete'])
            ->name('maintenancerecords.complete');
    });


require __DIR__ . '/auth.php';
