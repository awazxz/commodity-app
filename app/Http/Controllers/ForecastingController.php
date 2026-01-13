<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PriceData;
use App\Models\MasterKomoditas;
use Illuminate\Support\Facades\Http;

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
     * Submit filter (commodity saja)
     */
    public function predict(Request $request)
    {
        $request->validate([
            'commodity' => 'required|string'
        ]);

        return $this->processForecasting($request);
    }

    /**
     * ==============================
     * CORE LOGIC DASHBOARD
     * ==============================
     */
    private function processForecasting(Request $request)
    {
        // ================= PARAMETER PROPHET (WAJIB SELALU ADA) =================
        $cpScale          = (float) $request->input('changepoint_prior_scale', 0.05);
        $seasonScale     = (float) $request->input('seasonality_prior_scale', 10);
        $seasonalityMode = $request->input('seasonality_mode', 'additive');
        $weeklyActive    = $request->input('weekly', 'off') === 'on';
        $yearlyActive    = $request->input('yearly', 'off') === 'on';

        $selectedCommodity = $request->input('commodity', null);

        // ================= MAPPING KOMODITAS =================
        $komoditas = MasterKomoditas::when($selectedCommodity, function ($q) use ($selectedCommodity) {
                $q->where('nama_komoditas', $selectedCommodity);
            })
            ->first();

        // ================= DATA REAL DARI DB =================
        if ($komoditas) {
            $records = PriceData::where('komoditas_id', $komoditas->id)
                ->orderBy('tanggal')
                ->get();

            if ($records->count() >= 2) {
                return $this->buildViewFromDatabase(
                    $records,
                    $komoditas->nama_komoditas,
                    $cpScale,
                    $seasonScale,
                    $seasonalityMode,
                    $weeklyActive,
                    $yearlyActive
                );
            }
        }

        // ================= FALLBACK MOCK =================
        return $this->buildViewFromMock(
            $selectedCommodity,
            $cpScale,
            $seasonScale,
            $seasonalityMode,
            $weeklyActive,
            $yearlyActive
        );
    }

    /**
     * ==============================
     * BUILD VIEW FROM DATABASE
     * ==============================
     */
    private function buildViewFromDatabase(
        $records,
        $commodityName,
        $cpScale,
        $seasonScale,
        $seasonalityMode,
        $weeklyActive,
        $yearlyActive
    ) {
        $labels = [];
        $actual = [];

        foreach ($records as $row) {
            $labels[] = $row->tanggal->format('M Y');
            $actual[] = $row->harga;
        }

        return view('dashboard.index', [
            'selectedCommodity' => $commodityName,

            // PROPHET PARAM (WAJIB)
            'cpScale'           => $cpScale,
            'seasonScale'       => $seasonScale,
            'seasonalityMode'   => $seasonalityMode,
            'weeklyActive'      => $weeklyActive,
            'yearlyActive'      => $yearlyActive,

            // METRIC
            'avgPrice'  => round(collect($actual)->avg()),
            'maxPrice'  => max($actual),
            'startDate' => $records->first()->tanggal->format('Y-m-d'),
            'endDate'   => $records->last()->tanggal->format('Y-m-d'),
            'trendDir'  => 'Belum diforecast',

            // CHART
            'chartLabels'  => $labels,
            'actualData'   => $actual,
            'forecastData' => array_fill(0, count($actual), null),

            // PLACEHOLDER
            'upperBand' => [],
            'lowerBand' => [],
            'weeklyLabels' => [],
            'weeklyForecast' => [],
            'weeklyUpper' => [],
            'weeklyLower' => [],
            'commoditySummary' => []
        ]);
    }

    /**
     * ==============================
     * BUILD VIEW FROM MOCK
     * ==============================
     */
    private function buildViewFromMock(
        $commodity,
        $cpScale,
        $seasonScale,
        $seasonalityMode,
        $weeklyActive,
        $yearlyActive
    ) {
        $data = $this->getMockData($commodity);

        return view('dashboard.index', [
            'selectedCommodity' => $commodity,

            // PROPHET PARAM (WAJIB)
            'cpScale'           => $cpScale,
            'seasonScale'       => $seasonScale,
            'seasonalityMode'   => $seasonalityMode,
            'weeklyActive'      => $weeklyActive,
            'yearlyActive'      => $yearlyActive,

            // METRIC
            'avgPrice'     => $data['avgPrice'],
            'maxPrice'     => $data['maxPrice'],
            'startDate'    => $data['startDate'],
            'endDate'      => $data['endDate'],
            'trendDir'     => ucfirst($data['trendDir']),

            // CHART
            'chartLabels'  => $data['chartLabels'],
            'actualData'   => $data['actualData'],
            'forecastData' => $data['forecastData'],

            // PLACEHOLDER
            'upperBand' => [],
            'lowerBand' => [],
            'weeklyLabels' => [],
            'weeklyForecast' => [],
            'weeklyUpper' => [],
            'weeklyLower' => [],
            'commoditySummary' => []
        ]);
    }

    /**
     * ==============================
     * MOCK DATA
     * ==============================
     */
    private function getMockData($commodity)
    {
        return [
            'avgPrice'     => 14500,
            'maxPrice'     => 15800,
            'startDate'    => '2024-01-01',
            'endDate'      => '2025-12-23',
            'trendDir'     => 'stabil',
            'chartLabels'  => ['Jan','Feb','Mar','Apr','Mei','Jun(F)'],
            'actualData'   => [14200,14500,14800,14600,14700,null],
            'forecastData' => [null,null,null,null,14700,14900]
        ];
    }
}
