<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ForecastingController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\OperatorController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\DatasetController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('welcome');
});

// ==============================
// AUTHENTICATED ROUTES
// ==============================
Route::middleware('auth')->group(function () {

    // Redirect setelah login (berdasarkan role)
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    // ==============================
    // USER (GENERAL)
    // ==============================
    Route::get('/user/dashboard', [ForecastingController::class, 'index'])
        ->name('user.dashboard');

    Route::post('/predict', [ForecastingController::class, 'predict'])
        ->name('predict');

    
// ==============================
// ADMIN ROUTES
// ==============================
Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        // Dashboard
        Route::get('/dashboard', [AdminController::class, 'index'])
            ->name('dashboard');

        // Forecast / Predict
        Route::post('/predict', [AdminController::class, 'predict'])
            ->name('predict');

        // Dataset Management
        Route::post('/store-data', [AdminController::class, 'storeData'])
            ->name('storeData');

        Route::post('/clean-data', [AdminController::class, 'cleanData'])
            ->name('cleanData');

        Route::delete('/delete-data/{id}', [AdminController::class, 'deleteData'])
            ->name('deleteData');

        // User Management
        Route::post('/users', [AdminController::class, 'storeUser'])
            ->name('storeUser');

        Route::delete('/users/{id}', [AdminController::class, 'deleteUser'])
            ->name('deleteUser');

        // Dataset Template
        Route::get('/download-template', [DatasetController::class, 'downloadTemplate'])
            ->name('downloadTemplate');
    });

   Route::middleware(['auth', 'role:operator'])
    ->prefix('operator')
    ->name('operator.')
    ->group(function () {

        Route::get('/dashboard', [OperatorController::class, 'index'])
            ->name('dashboard');

        Route::post('/predict', [OperatorController::class, 'predict'])
            ->name('predict');

        Route::post('/store-data', [OperatorController::class, 'storeData'])
            ->name('storeData');

        Route::post('/clean-data', [OperatorController::class, 'cleanData'])
            ->name('cleanData');

        Route::delete('/delete-data/{id}', [OperatorController::class, 'deleteData'])
            ->name('deleteData');

        // ==============================
        // DATASET TEMPLATE (CSV)
        // ==============================
        Route::get('/download-template', [DatasetController::class, 'downloadTemplate'])
            ->name('downloadTemplate');
    });

    // ==============================
    // PROFILE
    // ==============================
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
