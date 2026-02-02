<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
// Pastikan memanggil ForecastingController
use App\Http\Controllers\ForecastingController;

class UserController extends Controller
{
    /**
     * Menampilkan Landing Page dengan Grafik untuk User
     */
    public function beranda(Request $request)
    {
        // Kita gunakan logika yang sama dengan halaman prediksi 
        // agar variabel $chartLabels, $avgPrice, dll terisi otomatis.
        return app(ForecastingController::class)->index($request);
    }
}