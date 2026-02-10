<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ForecastingController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\OperatorController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\LaporanKomoditasController;
use App\Http\Controllers\Admin\DatasetController;

/*
|--------------------------------------------------------------------------
| Web Routes - Sistem Analisis Prediksi Harga Komoditas
|--------------------------------------------------------------------------
*/

// 1. PUBLIC ROUTES
Route::get('/', function () {
    // Jika user sudah login, langsung ke beranda
    if (auth()->check()) {
        return redirect()->route('laporan.komoditas.index');
    }
    // Jika belum login, tampilkan halaman welcome
    return view('welcome');
})->name('home');

// 2. AUTHENTICATED ROUTES (Wajib Login)
Route::middleware('auth')->group(function () {

    /**
     * Dashboard Redirector - Analisis (role-based)
     */
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    /**
     * Global Forecasting Prediction
     */
    Route::post('/predict', [ForecastingController::class, 'predict'])
        ->name('predict');

    /**
     * Fitur Laporan Komoditas - BERANDA
     */
    Route::get('/laporan-komoditas', [LaporanKomoditasController::class, 'index'])
        ->name('laporan.komoditas.index');
        
    Route::get('/laporan-komoditas/cetak', [LaporanKomoditasController::class, 'cetak'])
        ->name('laporan.komoditas.cetak');


    // 3. USER ROLE ROUTES
    Route::prefix('user')->name('user.')->group(function () {
        
        // Halaman Dashboard User (untuk Analisis)
        Route::get('/dashboard', [UserController::class, 'index'])
            ->name('dashboard');
        
        /**
         * Fitur Olah Data (Grafik & Forecast)
         */
        Route::get('/olah-data', [ForecastingController::class, 'index'])
            ->name('olah-data');

        // Endpoints API Internal untuk Forecasting
        Route::get('/forecast/data/{komoditas}', [ForecastingController::class, 'historical'])
            ->name('forecast.data');

        Route::post('/forecast/run/{komoditas}', [ForecastingController::class, 'run'])
            ->name('forecast.run');

        Route::get('/forecast/result/{komoditas}', [ForecastingController::class, 'result'])
            ->name('forecast.result');
    }); // Penutup Group User

    // 4. ADMIN ROLE ROUTES
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        
        // Dashboard & Predict (untuk Analisis)
        Route::get('/dashboard', [AdminController::class, 'index'])->name('dashboard');
        Route::post('/predict', [AdminController::class, 'predict'])->name('predict');
        
        // Data Management (CRUD)
        Route::controller(AdminController::class)->group(function () {
            Route::post('/store-data', 'storeData')->name('storeData');
            Route::put('/update-data/{id}', 'updateData')->name('updateData');
            Route::delete('/delete-data/{id}', 'deleteData')->name('deleteData');
            Route::post('/clean-data', 'cleanData')->name('cleanData');
            
            // User Management
            Route::post('/users', 'storeUser')->name('storeUser');
            Route::put('/update-user/{id}', 'updateUser')->name('updateUser');
            Route::delete('/users/{id}', 'deleteUser')->name('deleteUser');
        });

        // Download Template
        Route::get('/download-template', [DatasetController::class, 'downloadTemplate'])->name('downloadTemplate');
    });

    // 5. OPERATOR ROLE ROUTES
    Route::middleware('role:operator')->prefix('operator')->name('operator.')->group(function () {
        
        // Dashboard & Predict (untuk Analisis)
        Route::get('/dashboard', [OperatorController::class, 'index'])->name('dashboard');
        Route::post('/predict', [OperatorController::class, 'predict'])->name('predict');

        // Data Management (CRUD)
        Route::controller(OperatorController::class)->group(function () {
            Route::post('/store-data', 'storeData')->name('storeData');
            Route::put('/update-data/{id}', 'updateData')->name('updateData');
            Route::delete('/delete-data/{id}', 'deleteData')->name('deleteData');
            Route::post('/clean-data', 'cleanData')->name('cleanData');
        });

        // Download Template
        Route::get('/download-template', [DatasetController::class, 'downloadTemplate'])->name('downloadTemplate');
    });

    // 6. PROFILE ROUTES
    Route::controller(ProfileController::class)->group(function () {
        Route::get('/profile', 'edit')->name('profile.edit');
        Route::patch('/profile', 'update')->name('profile.update');
        Route::delete('/profile', 'destroy')->name('profile.destroy');
    });

}); // Penutup middleware auth

// 7. AUTHENTICATION
require __DIR__.'/auth.php';