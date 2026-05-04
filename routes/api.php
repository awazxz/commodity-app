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

/**
 * ═══════════════════════════════════════
 * BRIDGE ROUTES: IHK & INFLASI
 * ═══════════════════════════════════════
 */

// Kalkulasi IHK
Route::post('/ihk/calculate', function (Request $request) {
    try {
        $response = Http::timeout(60)->post('http://127.0.0.1:5000/api/ihk/calculate', $request->all());
        return response()->json($response->json(), $response->status());
    } catch (\Exception $e) {
        return response()->json(['error' => 'Gagal menghubungi server IHK'], 500);
    }
});

Route::post('/ihk/recalculate', function (Request $request) {
    try {
        $response = Http::timeout(60)->post('http://127.0.0.1:5000/api/ihk/recalculate', $request->all());
        return response()->json($response->json(), $response->status());
    } catch (\Exception $e) {
        return response()->json(['error' => 'Gagal menghubungi server IHK'], 500);
    }
});

// Data IHK Aktual
Route::get('/ihk/summary', function (Request $request) {
    try {
        $response = Http::timeout(30)->get('http://127.0.0.1:5000/api/ihk/summary');
        return response()->json($response->json(), $response->status());
    } catch (\Exception $e) {
        return response()->json(['error' => 'Gagal menghubungi server IHK'], 500);
    }
});

Route::get('/ihk/history', function (Request $request) {
    try {
        $response = Http::timeout(30)->get('http://127.0.0.1:5000/api/ihk/history', $request->query());
        return response()->json($response->json(), $response->status());
    } catch (\Exception $e) {
        return response()->json(['error' => 'Gagal menghubungi server IHK'], 500);
    }
});

Route::get('/ihk/detail', function (Request $request) {
    try {
        $response = Http::timeout(30)->get('http://127.0.0.1:5000/api/ihk/detail', $request->query());
        return response()->json($response->json(), $response->status());
    } catch (\Exception $e) {
        return response()->json(['error' => 'Gagal menghubungi server IHK'], 500);
    }
});

Route::get('/inflasi/comparison', function (Request $request) {
    try {
        $response = Http::timeout(30)->get('http://127.0.0.1:5000/api/inflasi/comparison', $request->query());
        return response()->json($response->json(), $response->status());
    } catch (\Exception $e) {
        return response()->json(['error' => 'Gagal menghubungi server IHK'], 500);
    }
});

// Forecast IHK
Route::post('/ihk/forecast', function (Request $request) {
    try {
        $response = Http::timeout(120)->post('http://127.0.0.1:5000/api/ihk/forecast', $request->all());
        return response()->json($response->json(), $response->status());
    } catch (\Exception $e) {
        return response()->json(['error' => 'Gagal menghubungi server IHK'], 500);
    }
});

Route::get('/ihk/forecast/result', function (Request $request) {
    try {
        $response = Http::timeout(30)->get('http://127.0.0.1:5000/api/ihk/forecast/result', $request->query());
        return response()->json($response->json(), $response->status());
    } catch (\Exception $e) {
        return response()->json(['error' => 'Gagal menghubungi server IHK'], 500);
    }
});

Route::get('/ihk/forecast/vs-aktual', function (Request $request) {
    try {
        $response = Http::timeout(30)->get('http://127.0.0.1:5000/api/ihk/forecast/vs-aktual');
        return response()->json($response->json(), $response->status());
    } catch (\Exception $e) {
        return response()->json(['error' => 'Gagal menghubungi server IHK'], 500);
    }
});

Route::get('/ihk/forecast/summary', function (Request $request) {
    try {
        $response = Http::timeout(30)->get('http://127.0.0.1:5000/api/ihk/forecast/summary');
        return response()->json($response->json(), $response->status());
    } catch (\Exception $e) {
        return response()->json(['error' => 'Gagal menghubungi server IHK'], 500);
    }
});