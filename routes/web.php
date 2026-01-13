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
| Struktur routing terorganisir berdasarkan role dan fungsi
*/

// ==============================
// PUBLIC ROUTES
// ==============================
Route::get('/', function () {
    return view('welcome');
})->name('home');

// ==============================
// AUTHENTICATED ROUTES
// ==============================
Route::middleware('auth')->group(function () {

    // Dashboard Redirect (berdasarkan role)
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    // ==============================
    // USER/PUBLIC DASHBOARD & FORECASTING
    // ==============================
    Route::prefix('user')->name('user.')->group(function () {
        // User Dashboard
        Route::get('/dashboard', [ForecastingController::class, 'index'])
            ->name('dashboard');
        
        // Forecasting Endpoints
        Route::post('/predict', [ForecastingController::class, 'predict'])
            ->name('predict');
            
        Route::get('/forecast/data/{komoditas}', [ForecastingController::class, 'historical'])
            ->name('forecast.data');
            
        Route::post('/forecast/run/{komoditas}', [ForecastingController::class, 'run'])
            ->name('forecast.run');
            
        Route::get('/forecast/result/{komoditas}', [ForecastingController::class, 'result'])
            ->name('forecast.result');
    });

    // Legacy route untuk backward compatibility
    Route::post('/predict', [ForecastingController::class, 'predict'])
        ->name('predict');

    // ==============================
    // ADMIN ROUTES
    // ==============================
    Route::middleware('role:admin')
        ->prefix('admin')
        ->name('admin.')
        ->group(function () {
            
            // Dashboard Admin
            Route::get('/dashboard', [AdminController::class, 'index'])
                ->name('dashboard');

            // Forecasting & Prediction
            Route::post('/predict', [AdminController::class, 'predict'])
                ->name('predict');
            
            // Data Management
            Route::controller(AdminController::class)->group(function () {
                // Store Data (Manual & CSV Upload)
                Route::post('/store-data', 'storeData')
                    ->name('storeData');
                
                // Clean Data (Outlier & Missing Values)
                Route::post('/clean-data', 'cleanData')
                    ->name('cleanData');
                
                // Delete Data
                Route::delete('/delete-data/{id}', 'deleteData')
                    ->name('deleteData');
            });

            // User Management
            Route::controller(AdminController::class)->group(function () {
                Route::post('/users', 'storeUser')
                    ->name('storeUser');
                
                Route::delete('/users/{id}', 'deleteUser')
                    ->name('deleteUser');
            });

            // Dataset Template Download
            Route::get('/download-template', [DatasetController::class, 'downloadTemplate'])
                ->name('downloadTemplate');
        });

    // ==============================
    // OPERATOR ROUTES
    // ==============================
    Route::middleware('role:operator')
        ->prefix('operator')
        ->name('operator.')
        ->group(function () {
            
            // Dashboard Operator
            Route::get('/dashboard', [OperatorController::class, 'index'])
                ->name('dashboard');

            // Forecasting & Prediction
            Route::post('/predict', [OperatorController::class, 'predict'])
                ->name('predict');

            // Data Management
            Route::controller(OperatorController::class)->group(function () {
                // Store Data
                Route::post('/store-data', 'storeData')
                    ->name('storeData');
                
                // Clean Data
                Route::post('/clean-data', 'cleanData')
                    ->name('cleanData');
                
                // Delete Data
                Route::delete('/delete-data/{id}', 'deleteData')
                    ->name('deleteData');
            });

            // Dataset Template Download
            Route::get('/download-template', [DatasetController::class, 'downloadTemplate'])
                ->name('downloadTemplate');
        });

    // ==============================
    // PROFILE MANAGEMENT
    // ==============================
    Route::controller(ProfileController::class)->group(function () {
        Route::get('/profile', 'edit')->name('profile.edit');
        Route::patch('/profile', 'update')->name('profile.update');
        Route::delete('/profile', 'destroy')->name('profile.destroy');
    });
});

// Auth routes
require __DIR__.'/auth.php';