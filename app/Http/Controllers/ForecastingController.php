<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ForecastingController extends Controller
{
    /**
     * Dashboard default
     */
    public function index(Request $request)
    {
        return $this->processForecasting($request);
    }

    /**
     * Submit filter (commodity saja untuk public user)
     */
    public function predict(Request $request)
    {
        $request->validate([
            'commodity' => 'required|string'
        ]);

        return $this->processForecasting($request);
    }

    /**
     * Core logic (SINGLE SOURCE OF TRUTH)
     */
    private function processForecasting(Request $request)
    {
        // ================= INPUT DASAR =================
        $selectedCommodity = $request->input('commodity', 'Beras Premium');

        // ================= PARAMETER PROPHET (WAJIB ADA, MESKI UI DIHAPUS) =================
        // Dipakai oleh hidden input di Blade
        $cpScale = (float) $request->input('changepoint_prior_scale', 0.05);
        $seasonScale = (float) $request->input('seasonality_prior_scale', 10);
        $seasonalityMode = $request->input('seasonality_mode', 'additive');

        // checkbox → default off jika tidak dikirim
        $weeklyActive = $request->input('weekly', 'off') === 'on';
        $yearlyActive = $request->input('yearly', 'off') === 'on';

        // ================= DATA SOURCE (MOCK / NANTI PROPHET) =================
        $data = $this->getMockData($selectedCommodity);

        // ================= CONFIDENCE INTERVAL (DAILY) =================
        $upperBand = [];
        $lowerBand = [];

        foreach ($data['forecastData'] as $value) {
            if ($value === null) {
                $upperBand[] = null;
                $lowerBand[] = null;
            } else {
                // simulasi confidence interval ±5%
                $upperBand[] = round($value * 1.05);
                $lowerBand[] = round($value * 0.95);
            }
        }

        // ================= WEEKLY AGGREGATION =================
        $weeklyLabels   = [];
        $weeklyForecast = [];
        $weeklyUpper    = [];
        $weeklyLower    = [];

        $weekIndex = 1;
        foreach ($data['forecastData'] as $i => $value) {
            if ($value !== null) {
                $weeklyLabels[]   = 'Minggu ' . $weekIndex;
                $weeklyForecast[] = $value;
                $weeklyUpper[]    = $upperBand[$i];
                $weeklyLower[]    = $lowerBand[$i];
                $weekIndex++;
            }
        }

        // ================= SUMMARY SEMUA KOMODITAS (TABEL PUBLIK) =================
        $commoditySummary = [];

        foreach (['Beras Premium', 'Cabai Merah', 'Minyak Goreng'] as $commodity) {
            $mock = $this->getMockData($commodity);

            $lastActual   = collect($mock['actualData'])->filter()->last();
            $lastForecast = collect($mock['forecastData'])->filter()->last();

            // klasifikasi tren sederhana (bisa diganti statistik CI overlap)
            $trend = 'Stabil';
            if ($lastForecast > $lastActual * 1.02) {
                $trend = 'Naik';
            } elseif ($lastForecast < $lastActual * 0.98) {
                $trend = 'Turun';
            }

            $commoditySummary[] = [
                'commodity' => $commodity,
                'actual'    => $lastActual,
                'forecast'  => $lastForecast,
                'trend'     => $trend
            ];
        }

        // ================= RETURN VIEW =================
        return view('dashboard.index', [
            // header & filter
            'selectedCommodity' => $selectedCommodity,

            // PARAMETER PROPHET (UNTUK HIDDEN INPUT)
            'cpScale'           => $cpScale,
            'seasonScale'       => $seasonScale,
            'seasonalityMode'   => $seasonalityMode,
            'weeklyActive'      => $weeklyActive,
            'yearlyActive'      => $yearlyActive,

            // metric cards
            'avgPrice'  => $data['avgPrice'],
            'maxPrice'  => $data['maxPrice'],
            'startDate' => $data['startDate'],
            'endDate'   => $data['endDate'],
            'trendDir'  => ucfirst($data['trendDir']),

            // chart utama
            'chartLabels'  => $data['chartLabels'],
            'actualData'   => $data['actualData'],
            'forecastData' => $data['forecastData'],

            // interval harian
            'upperBand' => $upperBand,
            'lowerBand' => $lowerBand,

            // weekly chart
            'weeklyLabels'   => $weeklyLabels,
            'weeklyForecast' => $weeklyForecast,
            'weeklyUpper'    => $weeklyUpper,
            'weeklyLower'    => $weeklyLower,

            // tabel publik
            'commoditySummary' => $commoditySummary
        ]);
    }

    /**
     * MOCK DATA SOURCE
     * (GANTI DENGAN ENGINE PROPHET / DATABASE)
     */
    private function getMockData($commodity)
    {
        if ($commodity === 'Cabai Merah') {
            return [
                'avgPrice'     => 45000,
                'maxPrice'     => 62000,
                'startDate'    => '01 Jan 2024',
                'endDate'      => '23 Des 2025',
                'trendDir'     => 'fluktuatif',
                'chartLabels'  => ['M1','M2','M3','M4','F1','F2'],
                'actualData'   => [42000,58000,41000,45000,null,null],
                'forecastData' => [null,null,null,45000,49000,55000]
            ];
        }

        if ($commodity === 'Minyak Goreng') {
            return [
                'avgPrice'     => 18500,
                'maxPrice'     => 20000,
                'startDate'    => '01 Jan 2024',
                'endDate'      => '23 Des 2025',
                'trendDir'     => 'menaik',
                'chartLabels'  => ['Okt','Nov','Des','Jan(F)','Feb(F)'],
                'actualData'   => [17500,18000,19000,null,null],
                'forecastData' => [null,null,19000,19500,20200]
            ];
        }

        // Default: Beras Premium
        return [
            'avgPrice'     => 14500,
            'maxPrice'     => 15800,
            'startDate'    => '01 Jan 2024',
            'endDate'      => '23 Des 2025',
            'trendDir'     => 'stabil',
            'chartLabels'  => ['Jan','Feb','Mar','Apr','Mei','Jun(F)'],
            'actualData'   => [14200,14500,14800,14600,14700,null],
            'forecastData' => [null,null,null,null,14700,14900]
        ];
    }
}
