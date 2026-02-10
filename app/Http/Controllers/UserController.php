<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\ForecastingController;

class UserController extends Controller
{
    /**
     * Halaman Dashboard/Analisis untuk User
     * Menampilkan grafik dan forecasting
     */
    public function index(Request $request)
    {
        // Gunakan logika yang sama dengan forecasting
        return app(ForecastingController::class)->index($request);
    }

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