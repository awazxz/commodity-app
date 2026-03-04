<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/**
 * BRIDGE ROUTE: Laravel ke Flask
 */
Route::get('/flask-health', function () {
    try {
        // PERBAIKAN DI SINI: Sesuaikan dengan route di app.py Anda
        // Kita panggil http://127.0.0.1:5000/api/flask-health
        $response = Http::timeout(2)->get('http://127.0.0.1:5000/api/flask-health');
        
        if ($response->successful()) {
            // Kita langsung kembalikan respon dari Flask karena sudah mengandung {'status': 'online'}
            return response()->json($response->json(), 200);
        }
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'offline',
            'message' => 'Flask tidak merespon di port 5000',
            'error' => $e->getMessage()
        ], 503);
    }

    return response()->json(['status' => 'offline'], 503);
});

/**
 * ROUTE PREDIKSI:
 */
Route::post('/forecast/predict-advanced', function (Request $request) {
    try {
        // PERBAIKAN DI SINI: Sesuaikan dengan route di app.py Anda
        // Di app.py Anda menggunakan /api/forecast/predict-advanced
        $response = Http::post('http://127.0.0.1:5000/api/forecast/predict-advanced', $request->all());
        return $response->json();
    } catch (\Exception $e) {
        return response()->json(['error' => 'Gagal menghubungi server prediksi'], 500);
    }
});