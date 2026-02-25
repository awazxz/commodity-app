<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PriceData;
use App\Models\MasterKomoditas;
use App\Models\PriceForecast; 
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ForecastingController extends Controller
{
    private $pythonApiUrl = 'http://localhost:5000';

    /**
     * Menampilkan halaman utama forecasting
     */
    public function index(Request $request)
    {
        return $this->processForecasting($request);
    }

    /**
     * Menangani request prediksi (alias dari index untuk form submission)
     */
    public function predict(Request $request)
    {
        return $this->processForecasting($request);
    }

    /**
     * CORE LOGIC - Menangani pengambilan data, pemanggilan API, dan pengiriman ke View
     */
    private function processForecasting(Request $request)
    {
        // 1. Ambil semua data komoditas untuk dropdown filter
        $allCommodities = MasterKomoditas::all();

        // 2. Ambil Parameter dari Request dengan nilai default
        $cpScale         = (float) $request->input('changepoint_prior_scale', 0.05);
        $seasonScale     = (float) $request->input('seasonality_prior_scale', 10);
        $seasonalityMode = $request->input('seasonality_mode', 'additive');
        $weeklyActive    = $request->input('weekly', 'off') === 'on';
        $yearlyActive    = $request->input('yearly', 'off') === 'on';
        $forecastPeriods = (int) $request->input('periods', 30);
        $commodityInput  = $request->input('commodity');

        // 3. Identifikasi Komoditas yang dipilih
        if ($commodityInput) {
            $komoditas = MasterKomoditas::where('id', $commodityInput)
                ->orWhere('nama_komoditas', $commodityInput)
                ->first();
        } else {
            // Default: Ambil komoditas pertama yang memiliki data transaksi
            $komoditas = MasterKomoditas::whereHas('priceData')->first() ?? MasterKomoditas::first();
        }

        $selectedCommodityId = $komoditas ? $komoditas->id : null;

        // 4. Proses Data Historis Jika Komoditas Ditemukan
        if ($komoditas) {
            $records = PriceData::where('komoditas_id', $komoditas->id)
                ->orderBy('tanggal', 'asc')
                ->get();

            if ($records->count() >= 2) {
                $displayName = $komoditas->nama_varian 
                    ? $komoditas->nama_komoditas . ' (' . $komoditas->nama_varian . ')' 
                    : $komoditas->nama_komoditas;

                return $this->buildViewFromDatabase(
                    $records, $komoditas->id, $displayName, $forecastPeriods,
                    $cpScale, $seasonScale, $seasonalityMode, $weeklyActive, $yearlyActive,
                    $allCommodities, $selectedCommodityId
                );
            }
        }

        // 5. Fallback: Jika data tidak ditemukan atau komoditas kosong
        return $this->buildViewFromMock(
            $commodityInput ?? 'Data Kosong', $cpScale, $seasonScale, $seasonalityMode, $weeklyActive, $yearlyActive,
            $allCommodities, $selectedCommodityId
        );
    }

    /**
     * Berkomunikasi dengan Python API (Prophet)
     */
    private function buildViewFromDatabase($records, $komoditasId, $commodityName, $periods, $cp, $ss, $sm, $wa, $ya, $allCommodities, $selectedId)
    {
        $prophetData = $records->map(fn($row) => [
            'ds' => Carbon::parse($row->tanggal)->format('Y-m-d'),
            'y'  => (float) $row->harga
        ])->toArray();

        try {
            $response = Http::timeout(120)->post($this->pythonApiUrl . '/forecast-json', [
                'data' => $prophetData,
                'periods' => $periods,
                'changepoint_prior_scale' => $cp,
                'seasonality_prior_scale' => $ss,
                'seasonality_mode' => $sm,
                'weekly_seasonality' => $wa,
                'yearly_seasonality' => $ya
            ]);

            if ($response->successful()) {
                $result = $response->json();
                $forecastBody = $result['data'] ?? $result;

                if (isset($forecastBody['forecast_data'])) {
                    // Simpan hasil ramalan ke DB agar bisa diakses fitur lain
                    $this->storeForecastToDatabase($komoditasId, $forecastBody['forecast_data']);
                    
                    return $this->buildViewWithForecast(
                        $forecastBody, $commodityName, $records, $cp, $ss, $sm, $wa, $ya, 
                        $allCommodities, $selectedId
                    );
                }
            }
        } catch (\Exception $e) {
            Log::error('Gagal memanggil Python API: ' . $e->getMessage());
        }

        return $this->buildViewWithoutForecast($records, $commodityName, $allCommodities, $selectedId);
    }

    /**
     * Menyimpan hasil prediksi ke tabel price_forecasts
     */
    private function storeForecastToDatabase($komoditasId, $forecastData)
    {
        foreach ($forecastData as $item) {
            PriceForecast::updateOrCreate(
                [
                    'komoditas_id' => $komoditasId,
                    'tanggal'      => Carbon::parse($item['ds'])->format('Y-m-d'),
                ],
                [
                    'yhat'       => $item['yhat'],
                    'yhat_lower' => $item['yhat_lower'] ?? null,
                    'yhat_upper' => $item['yhat_upper'] ?? null,
                ]
            );
        }
    }

    /**
     * Memproses data untuk Chart.js (AGREGASI MINGGUAN sebagai basis utama)
     * Data harian dihilangkan, menggunakan agregasi mingguan sebagai default
     */
    private function buildViewWithForecast($forecastResult, $commodityName, $records, $cp, $ss, $sm, $wa, $ya, $allCommodities, $selectedId)
    {
        $historical = $forecastResult['historical_data'];
        $forecast   = $forecastResult['forecast_data'];

        // Gabungkan semua data (historis + prediksi) dengan penanda tipe
        $allCollection = collect($historical)->map(fn($i) => [
            'ds' => $i['ds'],
            'y'  => $i['y'],
            'u'  => $i['y'],      // upper bound untuk data aktual = nilai aktual
            'l'  => $i['y'],      // lower bound untuk data aktual = nilai aktual
            't'  => 'a'           // tipe: actual
        ])->concat(collect($forecast)->map(fn($i) => [
            'ds' => $i['ds'],
            'y'  => $i['yhat'],
            'u'  => $i['yhat_upper'],
            'l'  => $i['yhat_lower'],
            't'  => 'f'           // tipe: forecast
        ]));

        // Generate agregasi untuk Weekly, Monthly, Yearly
        $aggs = $this->generateAggregations($allCollection);

        $prices = collect($historical)->pluck('y');
        
        $startDate = Carbon::parse($records->first()->tanggal)->format('Y-m-d');
        $endDate = Carbon::parse($records->last()->tanggal)->format('Y-m-d');

        // Hitung trend berdasarkan data forecast
        $forecastValues = collect($forecast)->pluck('yhat');
        $trendDir = $forecastValues->isNotEmpty() && $forecastValues->last() > $prices->last() ? 'Naik' : 'Turun';

        return view('dashboard.index', array_merge([
            'allCommodities'      => $allCommodities,
            'selectedCommodityId' => $selectedId,
            'selectedCommodity'   => $commodityName,
            'cpScale'             => $cp, 
            'seasonScale'         => $ss, 
            'seasonalityMode'     => $sm,
            'weeklyActive'        => $wa, 
            'yearlyActive'        => $ya,
            'avgPrice'            => round($prices->avg()), 
            'maxPrice'            => round($prices->max()), 
            'minPrice'            => round($prices->min()),
            'startDate'           => $startDate,
            'endDate'             => $endDate,
            'trendDir'            => $trendDir,
            'forecastSuccess'     => true,
        ], $aggs));
    }

    /**
     * Fallback View jika API sedang Offline
     * Menampilkan data agregasi mingguan dari data aktual yang ada
     */
    private function buildViewWithoutForecast($records, $commodityName, $allCommodities, $selectedId)
    {
        // Buat collection dengan format yang sama untuk agregasi
        $allCollection = $records->map(fn($row) => [
            'ds' => Carbon::parse($row->tanggal)->format('Y-m-d'),
            'y'  => (float) $row->harga,
            'u'  => (float) $row->harga,
            'l'  => (float) $row->harga,
            't'  => 'a'
        ]);

        $aggs = $this->generateAggregations($allCollection);
        $actual = $records->pluck('harga')->map(fn($h) => (float)$h);

        return view('dashboard.index', array_merge([
            'allCommodities'      => $allCommodities,
            'selectedCommodityId' => $selectedId,
            'selectedCommodity'   => $commodityName,
            'cpScale'             => 0.05, 
            'seasonScale'         => 10, 
            'seasonalityMode'     => 'additive',
            'weeklyActive'        => false, 
            'yearlyActive'        => false,
            'avgPrice'            => round($actual->avg()), 
            'maxPrice'            => round($actual->max()), 
            'minPrice'            => round($actual->min()),
            'startDate'           => Carbon::parse($records->first()->tanggal)->format('Y-m-d'),
            'endDate'             => Carbon::parse($records->last()->tanggal)->format('Y-m-d'),
            'trendDir'            => 'API Offline',
            'forecastSuccess'     => false
        ], $aggs));
    }

    /**
     * Menghasilkan data untuk filter grafik periode (Weekly, Monthly, Yearly)
     * Data harian dihilangkan dari agregasi
     */
    private function generateAggregations($collection)
    {
        $periods = [
            'weekly'  => 'o-W',  // ISO Week format (contoh: 2024-W01)
            'monthly' => 'Y-m',  // Year-Month format (contoh: 2024-01)
            'yearly'  => 'Y'     // Year format (contoh: 2024)
        ];
        
        $results = [];

        foreach ($periods as $key => $format) {
            $grouped = $collection->groupBy(fn($i) => Carbon::parse($i['ds'])->format($format));
            
            // Label periode
            $results["{$key}Labels"] = $grouped->keys()->toArray();
            
            // Data Aktual (rata-rata dari data aktual dalam periode)
            $results["{$key}Actual"] = $grouped->map(function($items) {
                $actualItems = $items->where('t', 'a');
                return $actualItems->isNotEmpty() ? round($actualItems->avg('y')) : null;
            })->values()->toArray();
            
            // Data Forecast (rata-rata dari data prediksi dalam periode)
            $results["{$key}Forecast"] = $grouped->map(function($items) {
                $forecastItems = $items->where('t', 'f');
                return $forecastItems->isNotEmpty() ? round($forecastItems->avg('y')) : null;
            })->values()->toArray();

            // Batas Atas (Upper Bound)
            $results["{$key}Upper"] = $grouped->map(function($items) {
                $forecastItems = $items->where('t', 'f');
                return $forecastItems->isNotEmpty() ? round($forecastItems->avg('u')) : null;
            })->values()->toArray();

            // Batas Bawah (Lower Bound)
            $results["{$key}Lower"] = $grouped->map(function($items) {
                $forecastItems = $items->where('t', 'f');
                return $forecastItems->isNotEmpty() ? round($forecastItems->avg('l')) : null;
            })->values()->toArray();
        }

        return $results;
    }

    /**
     * Placeholder data kosong untuk inisialisasi awal
     * Hanya menyediakan weekly, monthly, yearly (tanpa daily)
     */
    private function emptyAggregations() {
        return [
            'weeklyLabels'   => [], 
            'weeklyActual'   => [], 
            'weeklyForecast' => [], 
            'weeklyUpper'    => [], 
            'weeklyLower'    => [],
            
            'monthlyLabels'   => [], 
            'monthlyActual'   => [], 
            'monthlyForecast' => [], 
            'monthlyUpper'    => [], 
            'monthlyLower'    => [],
            
            'yearlyLabels'   => [], 
            'yearlyActual'   => [], 
            'yearlyForecast' => [], 
            'yearlyUpper'    => [], 
            'yearlyLower'    => []
        ];
    }

    /**
     * Menampilkan view dengan data kosong (Mock)
     */
    private function buildViewFromMock($commodity, $cp, $ss, $sm, $wa, $ya, $allCommodities, $selectedId) {
        return view('dashboard.index', array_merge([
            'allCommodities'      => $allCommodities,
            'selectedCommodityId' => $selectedId,
            'selectedCommodity'   => $commodity, 
            'cpScale'             => $cp, 
            'seasonScale'         => $ss, 
            'seasonalityMode'     => $sm,
            'weeklyActive'        => $wa, 
            'yearlyActive'        => $ya, 
            'avgPrice'            => 0, 
            'maxPrice'            => 0, 
            'minPrice'            => 0,
            'startDate'           => now()->format('Y-m-d'), 
            'endDate'             => now()->format('Y-m-d'), 
            'trendDir'            => 'N/A',
            'forecastSuccess'     => false
        ], $this->emptyAggregations()));
    }
}