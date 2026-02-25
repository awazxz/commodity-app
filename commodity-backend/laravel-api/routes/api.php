<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CommodityController;
use App\Http\Controllers\Api\ForecastController;
use App\Http\Controllers\Api\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::get('/test', function () {
    return response()->json([
        'message' => 'Backend Connected',
        'time' => now()
    ]);
});

Route::get('/ping', function () {
    return response()->json(['pong' => true]);
});

// Public routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Protected routes (requires authentication)
Route::middleware('auth:sanctum')->group(function () {
    
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    
    // Commodity routes
    Route::get('/commodities', [CommodityController::class, 'index']);
    Route::post('/commodities', [CommodityController::class, 'store']);
    Route::get('/commodities/{id}', [CommodityController::class, 'show']);
    Route::put('/commodities/{id}', [CommodityController::class, 'update']);
    Route::delete('/commodities/{id}', [CommodityController::class, 'destroy']);
    
    // Commodity prices
    Route::get('/commodities/{id}/prices', [CommodityController::class, 'getPrices']);
    Route::post('/commodities/{id}/prices', [CommodityController::class, 'addPrice']);
    
    // Forecasting routes
    Route::post('/forecast/predict', [ForecastController::class, 'predict']);
    Route::post('/forecast/evaluate', [ForecastController::class, 'evaluate']);
    Route::get('/forecast/results/{commodity_id}', [ForecastController::class, 'getResults']);
    Route::get('/forecast/history/{commodity_id}', [ForecastController::class, 'getHistory']);
});

// Health check
Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'API is running',
        'timestamp' => now()
    ]);
});