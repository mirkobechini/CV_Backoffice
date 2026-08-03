<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\DeadlineController;
use App\Http\Controllers\Admin\EquipmentController;
use App\Http\Controllers\Admin\IssueController;
use App\Http\Controllers\PdfExportController;
use App\Http\Controllers\Admin\MaintenanceRecordController;
use App\Http\Controllers\Admin\ProviderController;
use App\Http\Controllers\Admin\VehicleController;
use App\Http\Controllers\Admin\VehicleTypeController;
use App\Http\Controllers\Admin\MileageLogController;
use App\Http\Controllers\Admin\EquipmentTypeController;
use App\Http\Controllers\Admin\NotificationSettingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CsvExportController;
use App\Http\Controllers\CsvImportController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('welcome');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified', 'throttle:admin-mutations'])
    ->name("admin.")
    ->prefix("admin")
    ->group(function () {
        Route::resource("vehicles", VehicleController::class);
        Route::resource("providers", ProviderController::class);
        Route::resource("issues", IssueController::class);
        Route::resource("deadlines", DeadlineController::class);
        Route::resource("equipments", EquipmentController::class);
        Route::resource("mileage-logs", MileageLogController::class)
            ->parameters(['mileage-logs' => 'mileageLog']);
        Route::get('mileage-logs/bulk/create', [MileageLogController::class, 'bulkCreate'])
            ->name('mileage-logs.bulk');
        Route::post('mileage-logs/bulk/store', [MileageLogController::class, 'bulkStore'])
            ->name('mileage-logs.bulk-store');
        Route::resource("maintenance-records", MaintenanceRecordController::class)
            ->parameters(['maintenance-records' => 'maintenanceRecord']);
        Route::resource("vehicle-types", VehicleTypeController::class)
            ->parameters(['vehicle-types' => 'vehicleType']);
        Route::resource("equipment-types", EquipmentTypeController::class)
            ->parameters(['equipment-types' => 'equipmentType']);
        Route::patch('maintenance-records/{maintenanceRecord}/complete', [MaintenanceRecordController::class, 'complete'])
            ->name('maintenance-records.complete');

        Route::get('maintenance-records/calendar', [MaintenanceRecordController::class, 'calendar'])
            ->name('maintenance-records.calendar');
        Route::get('maintenance-records/events', [MaintenanceRecordController::class, 'events'])
            ->name('maintenance-records.events');

        Route::get('/notifications', [NotificationSettingController::class, 'edit'])
            ->name('notifications.edit');
        Route::patch('/notifications', [NotificationSettingController::class, 'update'])
            ->name('notifications.update');

        Route::get('vehicles/{vehicle}/pdf', [PdfExportController::class, 'vehiclePdf'])
            ->name('vehicles.pdf');

        Route::get('activity-log', [ActivityLogController::class, 'index'])
            ->name('activity-log.index');

        // Export CSV
        Route::get('csv/{entity}', [CsvExportController::class, 'export'])
            ->name('csv.export')
            ->whereIn('entity', ['vehicles', 'issues', 'deadlines', 'equipments', 'maintenance-records', 'mileage-logs', 'providers']);

        // Import CSV
        Route::get('csv-import', [CsvImportController::class, 'index'])
            ->name('csv-import.index');
        Route::post('csv-import/preview', [CsvImportController::class, 'preview'])
            ->name('csv-import.preview');
        Route::post('csv-import/confirm', [CsvImportController::class, 'confirm'])
            ->name('csv-import.confirm');
    });

require __DIR__ . '/auth.php';

// ⚠️ Dev only: login rapido per sviluppo (funziona solo in ambiente local)
if (app()->environment('local')) {
    Route::get('/dev-login', function () {
        $user = User::first();
        if ($user) {
            Auth::login($user);
            return redirect('/dashboard');
        }
        return redirect('/login');
    });
}
