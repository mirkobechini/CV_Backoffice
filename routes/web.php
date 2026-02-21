<?php

use App\Http\Controllers\Admin\IssueController;
use App\Http\Controllers\Admin\ProviderController;
use App\Http\Controllers\Admin\VehicleController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('welcome');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::resource("vehicles", VehicleController::class)
    ->middleware(["auth", "verified"]);

Route::resource("providers", ProviderController::class)
    ->middleware(["auth", "verified"]);

Route::resource("issues", IssueController::class)
    ->middleware(["auth", "verified"]);


require __DIR__.'/auth.php';
