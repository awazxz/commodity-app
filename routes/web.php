<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ForecastingController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\OperatorController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\LaporanKomoditasController;
use App\Http\Controllers\ManajemenDataController;
use App\Http\Controllers\Admin\DatasetController;
use App\Http\Controllers\LanguageController;

/*
|--------------------------------------------------------------------------
| Web Routes - Sistem Analisis Prediksi Harga Komoditas
|--------------------------------------------------------------------------
*/

// 1. PUBLIC ROUTES
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('laporan.komoditas.index');
    }
    return view('welcome');
})->name('home');

// Contact admin (public)
Route::get('/contact-admin', function () {
    return view('layouts.contact-admin');
})->name('contact.admin');

// 2. AUTHENTICATED ROUTES
Route::middleware('auth')->group(function () {

    // ── LANGUAGE SWITCHER ────────────────────────────────────────
    Route::post('/language/switch', [LanguageController::class, 'switch'])->name('language.switch');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/laporan-komoditas',       [LaporanKomoditasController::class, 'index'])->name('laporan.komoditas.index');
    Route::get('/laporan-komoditas/cetak', [LaporanKomoditasController::class, 'cetak'])->name('laporan.komoditas.cetak');

    // ── USER ROLE ────────────────────────────────────────────────
    Route::prefix('user')->name('user.')->group(function () {
        Route::get('/dashboard', [UserController::class, 'index'])->name('dashboard');

        // ✅ Analisis & Prediksi (UserController baru)
        Route::get('/analisis', [UserController::class, 'analisis'])->name('analisis');

        // Legacy routes (tetap dipertahankan agar tidak breaking)
        Route::get('/olah-data', [ForecastingController::class, 'index'])->name('olah-data');
        Route::get('/forecast/data/{komoditas}',   [ForecastingController::class, 'historical'])->name('forecast.data');
        Route::post('/forecast/run/{komoditas}',   [ForecastingController::class, 'run'])->name('forecast.run');
        Route::get('/forecast/result/{komoditas}', [ForecastingController::class, 'result'])->name('forecast.result');
    });

    // ── ADMIN ROLE ───────────────────────────────────────────────
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {

        Route::get('/dashboard', [AdminController::class, 'index'])->name('dashboard');
        Route::match(['GET', 'POST'], '/predict', [AdminController::class, 'predict'])->name('predict');

        Route::post('/store-data',         [AdminController::class, 'storeData'])->name('storeData');
        Route::put('/update-data/{id}',    [AdminController::class, 'updateData'])->name('updateData');
        Route::delete('/delete-data/{id}', [AdminController::class, 'deleteData'])->name('deleteData');
        Route::post('/clean-data',         [AdminController::class, 'cleanData'])->name('cleanData');

        Route::post('/store-user',         [AdminController::class, 'storeUser'])->name('storeUser');
        Route::put('/update-user/{id}',    [AdminController::class, 'updateUser'])->name('updateUser');
        Route::delete('/delete-user/{id}', [AdminController::class, 'deleteUser'])->name('deleteUser');

        Route::prefix('manajemen-data')->name('manajemen-data.')->group(function () {
            Route::get('/',                  [ManajemenDataController::class, 'index'])->name('index');
            Route::post('/store-manual',     [ManajemenDataController::class, 'storeManual'])->name('store-manual');
            Route::post('/upload-csv',       [ManajemenDataController::class, 'uploadCsv'])->name('upload-csv');
            Route::get('/download-template', [ManajemenDataController::class, 'downloadTemplate'])->name('download-template');
            Route::post('/detect-outliers',  [ManajemenDataController::class, 'detectOutliers'])->name('detect-outliers');
            Route::post('/delete-outliers',  [ManajemenDataController::class, 'deleteOutliers'])->name('delete-outliers');
            Route::post('/fill-missing',     [ManajemenDataController::class, 'fillMissingValues'])->name('fill-missing');
            Route::post('/mark-cleaned',     [ManajemenDataController::class, 'markAsCleaned'])->name('mark-cleaned');
            Route::delete('/delete/{id}',    [ManajemenDataController::class, 'deleteData'])->name('delete');
        });

        Route::get('/download-template-dataset', [DatasetController::class, 'downloadTemplate'])->name('downloadTemplate');
    });

    // ── OPERATOR ROLE ─────────────────────────────────────────────
    Route::middleware('role:operator')->prefix('operator')->name('operator.')->group(function () {
        Route::get('/dashboard', [OperatorController::class, 'index'])->name('dashboard');
        Route::match(['GET', 'POST'], '/predict', [OperatorController::class, 'predict'])->name('predict');
        Route::post('/store-data',         [OperatorController::class, 'storeData'])->name('storeData');
        Route::put('/update-data/{id}',    [OperatorController::class, 'updateData'])->name('updateData');
        Route::delete('/delete-data/{id}', [OperatorController::class, 'deleteData'])->name('deleteData');
        Route::post('/clean-data',         [OperatorController::class, 'cleanData'])->name('cleanData');
        Route::get('/download-template',   [DatasetController::class, 'downloadTemplate'])->name('downloadTemplate');

        // ── MANAJEMEN DATA (mirrored from admin) ─────────────────
        Route::prefix('manajemen-data')->name('manajemen-data.')->group(function () {
            Route::get('/',                  [ManajemenDataController::class, 'index'])->name('index');
            Route::post('/store-manual',     [ManajemenDataController::class, 'storeManual'])->name('store-manual');
            Route::post('/upload-csv',       [ManajemenDataController::class, 'uploadCsv'])->name('upload-csv');
            Route::get('/download-template', [ManajemenDataController::class, 'downloadTemplate'])->name('download-template');
            Route::post('/detect-outliers',  [ManajemenDataController::class, 'detectOutliers'])->name('detect-outliers');
            Route::post('/delete-outliers',  [ManajemenDataController::class, 'deleteOutliers'])->name('delete-outliers');
            Route::post('/fill-missing',     [ManajemenDataController::class, 'fillMissingValues'])->name('fill-missing');
            Route::post('/mark-cleaned',     [ManajemenDataController::class, 'markAsCleaned'])->name('mark-cleaned');
            Route::delete('/delete/{id}',    [ManajemenDataController::class, 'deleteData'])->name('delete');
        });
    });

    // ── PROFILE ───────────────────────────────────────────────────
    Route::controller(ProfileController::class)->group(function () {
        Route::get('/profile',    'edit')->name('profile.edit');
        Route::patch('/profile',  'update')->name('profile.update');
        Route::delete('/profile', 'destroy')->name('profile.destroy');
    });
});

require __DIR__.'/auth.php';