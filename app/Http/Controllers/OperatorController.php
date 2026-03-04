<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CommodityPrice;
use App\Models\MasterKomoditas;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class OperatorController extends Controller
{
    /** URL Flask dari .env — default localhost:5000 */
    private string $flaskUrl;

    public function __construct()
    {
        $this->flaskUrl = rtrim(env('FLASK_URL', 'http://localhost:5000'), '/');
    }

    public function index(Request $request)
    {
        return $this->processForecasting($request);
    }

    public function predict(Request $request)
    {
        return $this->processForecasting($request);
    }

    // =========================================================
    // MAIN FORECASTING PROCESSOR
    // =========================================================
    private function processForecasting(Request $request)
    {
        $role     = 'operator';
        $username = auth()->user()->name  ?? 'Operator BPS';
        $email    = auth()->user()->email ?? 'operator_riau@bps.go.id';

        $currentTab = $request->query('tab', $request->input('tab', 'insight'));
        $startDate  = $request->input('start_date', '2020-01-01');
        $endDate    = $request->input('end_date', Carbon::now()->format('Y-m-d'));

        // ── Hyperparameter ────────────────────────────────────
        $cpScale       = (float)  $request->input('changepoint_prior_scale', 0.05);
        $seasonScale   = (float)  $request->input('seasonality_prior_scale', 10);
        $seasonMode    = (string) $request->input('seasonality_mode', 'multiplicative');
        $weeklySeason  = $request->input('weekly_seasonality') === 'true';
        $yearlySeason  = $request->input('yearly_seasonality') === 'true';
        $forecastWeeks = max(1, min(52, (int) $request->input('forecast_weeks', 12)));

        // ── Daftar komoditas ──────────────────────────────────
        try {
            $commodities = MasterKomoditas::orderBy('nama_komoditas')->get();
        } catch (\Exception $e) {
            Log::error('[OPERATOR] Gagal ambil master_komoditas: ' . $e->getMessage());
            $commodities = collect();
        }

        // ✅ Baca komoditas_id dari GET atau POST
        $selectedKomoditasId = (int) (
            $request->query('komoditas_id')
            ?? $request->input('komoditas_id')
            ?? optional($commodities->first())->id
        );

        $selectedKomoditas = $commodities->first(fn($k) => (int) $k->id === $selectedKomoditasId);
        $selectedCommodity = $selectedKomoditas
            ? trim($selectedKomoditas->nama_komoditas . ' ' . ($selectedKomoditas->nama_varian ?? ''))
            : 'Komoditas';

        // ── Inisialisasi semua variabel output ─────────────────
        $allData    = [];
        $latestData = collect();
        $actualData = [];
        $dataIssues = collect();
        $mape       = 0.0;
        $rSquared   = 0.0;
        $trendDir   = 'Stabil';
        $avgPrice   = 0;
        $maxPrice   = 0;
        $countData  = 0;
        $usedFallback = false; // flag apakah pakai fallback PHP

        $weeklyLabels  = []; $weeklyActual  = []; $weeklyForecast  = []; $weeklyLower  = []; $weeklyUpper  = [];
        $monthlyLabels = []; $monthlyActual = []; $monthlyForecast = []; $monthlyLower = []; $monthlyUpper = [];
        $yearlyLabels  = []; $yearlyActual  = []; $yearlyForecast  = []; $yearlyLower  = []; $yearlyUpper  = [];

        // ── Tab manage ────────────────────────────────────────
        if ($currentTab === 'manage') {
            try {
                $latestData = CommodityPrice::where('komoditas_id', $selectedKomoditasId)
                    ->orderBy('tanggal', 'desc')
                    ->paginate(10);
            } catch (\Exception $e) {
                Log::error('[OPERATOR] latestData error: ' . $e->getMessage());
                $latestData = new \Illuminate\Pagination\LengthAwarePaginator(
                    collect(), 0, 10, 1,
                    ['path' => $request->url(), 'query' => $request->query()]
                );
            }
            try {
                $dataIssues = $this->scanDataQualityPaginated($selectedKomoditasId, $request);
            } catch (\Exception $e) {
                Log::error('[OPERATOR] scanDataQuality error: ' . $e->getMessage());
            }
        }

        // ============================================================
        // AMBIL DATA HISTORIS DARI DATABASE
        // ============================================================
        $prices = [];
        $dates  = [];

        try {
            $dbData = CommodityPrice::where('komoditas_id', $selectedKomoditasId)
                ->whereBetween('tanggal', [$startDate, $endDate])
                ->whereNotNull('harga')
                ->where('harga', '>', 0)
                ->orderBy('tanggal', 'asc')
                ->get();

            Log::info("[OPERATOR INSIGHT] komoditas_id={$selectedKomoditasId} | nama={$selectedCommodity} | count={$dbData->count()} | periode={$startDate} s/d {$endDate}");

            if ($dbData->isNotEmpty()) {
                $dates  = $dbData->pluck('tanggal')
                                 ->map(fn($d) => Carbon::parse($d))
                                 ->values()
                                 ->toArray();

                $prices = $dbData->pluck('harga')
                                 ->map(fn($h) => (float) $h)
                                 ->values()
                                 ->toArray();
            }
        } catch (\Exception $e) {
            Log::error('[OPERATOR INSIGHT] Gagal ambil price_data: ' . $e->getMessage());
        }

        // ============================================================
        // FORECASTING — prioritas ke Flask Prophet, fallback ke PHP
        // ============================================================
        if (count($prices) >= 2) {

            $actualData = $prices;
            $countData  = count($prices);
            $avgPrice   = array_sum($prices) / $countData;
            $maxPrice   = max($prices);

            // ── Coba panggil Flask Prophet ────────────────────
            // Prophet butuh minimal 2 baris, tapi idealnya >= 30
            // untuk data mingguan (± 30 minggu = 7 bulan)
            $flaskResult = null;
            if ($countData >= 10) {
                $flaskResult = $this->callFlaskProphet(
                    $selectedKomoditasId,
                    $forecastWeeks,
                    $cpScale,
                    $seasonScale,
                    $seasonMode,
                    $weeklySeason,
                    $yearlySeason
                );
            }

            if ($flaskResult !== null) {
                // ════════════════════════════════════════════
                // ✅ GUNAKAN HASIL PROPHET DARI FLASK
                // ════════════════════════════════════════════
                Log::info("[OPERATOR PROPHET] Berhasil dapat hasil dari Flask Prophet");

                $mape     = $flaskResult['mape'];
                $rSquared = $flaskResult['r_squared'];
                $trendDir = $flaskResult['trend_direction'] === 'increasing' ? 'Naik'
                          : ($flaskResult['trend_direction'] === 'decreasing' ? 'Turun' : 'Stabil');

                // Gabungkan data aktual + prediksi Prophet ke dalam chart
                $this->buildChartFromProphet(
                    $dates, $prices,
                    $flaskResult['predictions'],
                    $weeklyLabels,  $weeklyActual,  $weeklyForecast,  $weeklyLower,  $weeklyUpper,
                    $monthlyLabels, $monthlyActual, $monthlyForecast, $monthlyLower, $monthlyUpper,
                    $yearlyLabels,  $yearlyActual,  $yearlyForecast,  $yearlyLower,  $yearlyUpper
                );

            } else {
                // ════════════════════════════════════════════
                // ⚠️ FALLBACK: Flask tidak tersedia / data < 10
                // Gunakan kalkulasi PHP sederhana
                // ════════════════════════════════════════════
                $usedFallback = true;
                Log::warning("[OPERATOR FALLBACK] Menggunakan kalkulasi PHP (Flask tidak tersedia atau data < 10 baris)");

                $forecastDays = $forecastWeeks * 7;

                [$forecastDates, $forecastPrices, $forecastLowers, $forecastUppers] =
                    $this->simpleForecast($dates, $prices, $forecastDays);

                [$mape, $rSquared] = $this->calculateMetricsFallback($prices, $dates);

                $this->aggregateWeeklyData(
                    $dates, $prices, $forecastDates, $forecastPrices, $forecastLowers, $forecastUppers,
                    $weeklyLabels, $weeklyActual, $weeklyForecast, $weeklyLower, $weeklyUpper
                );
                $this->aggregateMonthlyData(
                    $dates, $prices, $forecastDates, $forecastPrices, $forecastLowers, $forecastUppers,
                    $monthlyLabels, $monthlyActual, $monthlyForecast, $monthlyLower, $monthlyUpper
                );
                $this->aggregateYearlyData(
                    $dates, $prices, $forecastDates, $forecastPrices, $forecastLowers, $forecastUppers,
                    $yearlyLabels, $yearlyActual, $yearlyForecast, $yearlyLower, $yearlyUpper
                );

                // Arah tren dari monthly
                $lastActual   = collect($monthlyActual)->filter()->last();
                $lastForecast = collect($monthlyForecast)->filter()->last();
                if ($lastForecast && $lastActual) {
                    if ($lastForecast > $lastActual * 1.01)     $trendDir = 'Naik';
                    elseif ($lastForecast < $lastActual * 0.99) $trendDir = 'Turun';
                }
            }

        } else {
            Log::warning("[OPERATOR INSIGHT] Data tidak cukup komoditas_id={$selectedKomoditasId} (count=" . count($prices) . ")");
            $countData = count($prices);
            $avgPrice  = count($prices) > 0 ? $prices[0] : 0;
            $maxPrice  = count($prices) > 0 ? $prices[0] : 0;
        }

        return view('operator_dashboard', compact(
            'role', 'username', 'email', 'currentTab', 'allData', 'latestData', 'dataIssues',
            'selectedCommodity', 'startDate', 'endDate', 'trendDir', 'actualData',
            'avgPrice', 'maxPrice', 'countData',
            'cpScale', 'seasonScale', 'seasonMode', 'weeklySeason', 'yearlySeason', 'forecastWeeks',
            'mape', 'rSquared',
            'commodities', 'selectedKomoditasId',
            'weeklyLabels',  'weeklyActual',  'weeklyForecast',  'weeklyLower',  'weeklyUpper',
            'monthlyLabels', 'monthlyActual', 'monthlyForecast', 'monthlyLower', 'monthlyUpper',
            'yearlyLabels',  'yearlyActual',  'yearlyForecast',  'yearlyLower',  'yearlyUpper'
        ));
    }

    // =========================================================
    // PANGGIL FLASK PROPHET API
    // Mengirim request ke /api/forecast/predict-advanced
    // dengan hyperparameter dari UI operator
    // =========================================================
    private function callFlaskProphet(
        int    $komoditasId,
        int    $forecastWeeks,
        float  $cpScale,
        float  $seasonScale,
        string $seasonMode,
        bool   $weeklySeason,
        bool   $yearlySeason
    ): ?array {
        try {
            $payload = [
                'commodity_id'            => $komoditasId,
                'periods'                 => $forecastWeeks * 7, // kirim dalam hari (frequency = D)
                'frequency'               => 'W',               // data mingguan
                'changepoint_prior_scale' => $cpScale,
                'seasonality_prior_scale' => $seasonScale,
                'seasonality_mode'        => $seasonMode,
                'weekly_seasonality'      => $weeklySeason,
                'yearly_seasonality'      => $yearlySeason,
            ];

            Log::info("[OPERATOR FLASK] Mengirim request ke Flask", $payload);

            $response = Http::timeout(60)           // Prophet bisa lambat, beri 60 detik
                ->connectTimeout(5)                  // koneksi max 5 detik
                ->post("{$this->flaskUrl}/api/forecast/predict-advanced", $payload);

            if (!$response->successful()) {
                Log::warning("[OPERATOR FLASK] Response tidak sukses: HTTP {$response->status()} — " . $response->body());
                return null;
            }

            $data = $response->json();

            if (!($data['success'] ?? false)) {
                Log::warning("[OPERATOR FLASK] Flask error: " . ($data['message'] ?? 'unknown'));
                return null;
            }

            $modelMetrics = $data['data']['model_metrics'] ?? [];
            $predictions  = $data['data']['predictions']   ?? [];

            if (empty($predictions)) {
                Log::warning("[OPERATOR FLASK] Prediksi kosong dari Flask");
                return null;
            }

            // ── Hitung R² dari coverage (proxy) ──────────────
            // Flask mengembalikan coverage (% aktual dalam CI)
            // Kita konversi ke R²-like score untuk ditampilkan
            $coverage  = $modelMetrics['coverage']  ?? 0.95;
            $rSquared  = min(1.0, max(0.0, $coverage));

            // Ambil MAPE — gunakan in_sample_mape jika CV tidak tersedia
            $mape = $modelMetrics['mape'] ?? $modelMetrics['in_sample_mape'] ?? 0.0;

            Log::info("[OPERATOR FLASK] Berhasil: " . count($predictions) . " prediksi | MAPE={$mape} | coverage={$coverage}");

            return [
                'predictions'     => $predictions,        // array [{date, predicted_price, lower_bound, upper_bound, trend}]
                'mape'            => round((float) $mape, 2),
                'r_squared'       => round((float) $rSquared, 4),
                'trend_direction' => $data['data']['model_metrics']['trend_direction'] ?? 'stable',
                'metrics'         => $modelMetrics,
            ];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning("[OPERATOR FLASK] Flask tidak bisa dihubungi: " . $e->getMessage());
            return null;
        } catch (\Exception $e) {
            Log::error("[OPERATOR FLASK] Error tak terduga: " . $e->getMessage());
            return null;
        }
    }

    // =========================================================
    // BUILD CHART DATA DARI HASIL PROPHET
    // Menggabungkan data aktual (dari DB) + prediksi (dari Flask)
    // ke dalam format yang dibutuhkan chart
    // =========================================================
    private function buildChartFromProphet(
        array $actualDates,
        array $actualPrices,
        array $predictions,   // dari Flask: [{date, predicted_price, lower_bound, upper_bound}]
        &$weeklyLabels,  &$weeklyActual,  &$weeklyForecast,  &$weeklyLower,  &$weeklyUpper,
        &$monthlyLabels, &$monthlyActual, &$monthlyForecast, &$monthlyLower, &$monthlyUpper,
        &$yearlyLabels,  &$yearlyActual,  &$yearlyForecast,  &$yearlyLower,  &$yearlyUpper
    ): void {
        // Konversi predictions Flask → arrays berindeks
        $forecastDates  = [];
        $forecastPrices = [];
        $forecastLowers = [];
        $forecastUppers = [];

        foreach ($predictions as $p) {
            $forecastDates[]  = Carbon::parse($p['date']);
            $forecastPrices[] = (int) round($p['predicted_price']);
            $forecastLowers[] = (int) round($p['lower_bound']);
            $forecastUppers[] = (int) round($p['upper_bound']);
        }

        // Gunakan aggregation yang sudah ada
        $this->aggregateWeeklyData(
            $actualDates, $actualPrices,
            $forecastDates, $forecastPrices, $forecastLowers, $forecastUppers,
            $weeklyLabels, $weeklyActual, $weeklyForecast, $weeklyLower, $weeklyUpper
        );
        $this->aggregateMonthlyData(
            $actualDates, $actualPrices,
            $forecastDates, $forecastPrices, $forecastLowers, $forecastUppers,
            $monthlyLabels, $monthlyActual, $monthlyForecast, $monthlyLower, $monthlyUpper
        );
        $this->aggregateYearlyData(
            $actualDates, $actualPrices,
            $forecastDates, $forecastPrices, $forecastLowers, $forecastUppers,
            $yearlyLabels, $yearlyActual, $yearlyForecast, $yearlyLower, $yearlyUpper
        );
    }

    // =========================================================
    // FALLBACK FORECAST (PHP sederhana)
    // Dipakai hanya jika Flask tidak tersedia
    // Menggunakan moving average + linear trend
    // =========================================================
    private function simpleForecast(
        array $dates,
        array $prices,
        int   $forecastDays
    ): array {
        $n         = count($prices);
        $lastDate  = end($dates);
        $lastDate  = $lastDate instanceof Carbon ? $lastDate : Carbon::parse($lastDate);
        $lastPrice = end($prices);

        // Moving average (window = min 4, max 12)
        $maWindow = min(12, max(4, (int) floor($n * 0.2)));
        $maSlice  = array_slice($prices, -$maWindow);
        $maAvg    = array_sum($maSlice) / count($maSlice);

        // Trend dari regresi linear sederhana pada 30% data terakhir
        $trendWindow = max(4, (int) floor($n * 0.3));
        $trendSlice  = array_slice($prices, -$trendWindow);
        $trendCount  = count($trendSlice);
        $sumX = 0; $sumY = 0; $sumXY = 0; $sumX2 = 0;
        for ($i = 0; $i < $trendCount; $i++) {
            $sumX  += $i;
            $sumY  += $trendSlice[$i];
            $sumXY += $i * $trendSlice[$i];
            $sumX2 += $i * $i;
        }
        $denom = ($trendCount * $sumX2 - $sumX * $sumX);
        $slope = $denom != 0 ? ($trendCount * $sumXY - $sumX * $sumY) / $denom : 0;

        // Batasi slope
        $maxSlope = $lastPrice * 0.01;
        $slope    = max(-$maxSlope, min($maxSlope, $slope));

        // Residual std untuk CI
        $residuals = [];
        for ($i = max(0, $n - $trendWindow); $i < $n; $i++) {
            $fitted      = $maAvg + $slope * ($i - ($n - $trendWindow));
            $residuals[] = $prices[$i] - $fitted;
        }
        $residualStd = $this->standardDeviation($residuals);

        $forecastDates  = [];
        $forecastPrices = [];
        $forecastLowers = [];
        $forecastUppers = [];

        for ($h = 1; $h <= $forecastDays; $h++) {
            $point    = max(0, $lastPrice + $slope * $h);
            $ciWidth  = 1.96 * $residualStd * sqrt($h);

            $forecastDates[]  = $lastDate->copy()->addDays($h);
            $forecastPrices[] = (int) round($point);
            $forecastLowers[] = (int) round(max(0, $point - $ciWidth));
            $forecastUppers[] = (int) round($point + $ciWidth);
        }

        return [$forecastDates, $forecastPrices, $forecastLowers, $forecastUppers];
    }

    // =========================================================
    // METRICS FALLBACK (hanya dipakai saat Flask tidak ada)
    // =========================================================
    private function calculateMetricsFallback(array $prices, array $dates): array
    {
        $n = count($prices);
        if ($n < 4) return [0.0, 0.0];

        $splitIdx    = max(2, (int) floor($n * 0.7));
        $trainPrices = array_slice($prices, 0, $splitIdx);
        $trainDates  = array_slice($dates, 0, $splitIdx);
        $testPrices  = array_values(array_slice($prices, $splitIdx));
        $testCount   = count($testPrices);

        if ($testCount === 0) return [0.0, 0.0];

        [, $forecastPrices, ,] = $this->simpleForecast($trainDates, $trainPrices, $testCount);

        $mapeSum = 0.0; $mapeCount = 0;
        for ($i = 0; $i < $testCount; $i++) {
            $actual    = $testPrices[$i];
            $predicted = $forecastPrices[$i] ?? 0;
            if ($actual != 0) {
                $mapeSum += abs(($actual - $predicted) / $actual);
                $mapeCount++;
            }
        }
        $mape = $mapeCount > 0 ? ($mapeSum / $mapeCount) * 100 : 0.0;

        $meanActual = array_sum($testPrices) / $testCount;
        $ssTot = 0.0; $ssRes = 0.0;
        for ($i = 0; $i < $testCount; $i++) {
            $predicted = $forecastPrices[$i] ?? $meanActual;
            $ssTot    += pow($testPrices[$i] - $meanActual, 2);
            $ssRes    += pow($testPrices[$i] - $predicted, 2);
        }

        $rSquared = $ssTot > 0 ? max(0.0, min(1.0, 1 - ($ssRes / $ssTot))) : 0.0;

        return [round($mape, 2), round($rSquared, 4)];
    }

    // =========================================================
    // HELPER: Standard Deviation (sample, n-1)
    // =========================================================
    private function standardDeviation(array $values): float
    {
        $n = count($values);
        if ($n < 2) return 0.0;

        $mean     = array_sum($values) / $n;
        $variance = array_sum(array_map(fn($v) => pow($v - $mean, 2), $values)) / ($n - 1);

        return sqrt(max(0, $variance));
    }

    // =========================================================
    // DATA QUALITY SCANNER
    // =========================================================
    private function scanDataQualityPaginated($komoditasId, $request)
    {
        $data = CommodityPrice::where('komoditas_id', $komoditasId)
            ->orderBy('tanggal', 'asc')
            ->get();

        if ($data->isEmpty()) {
            return new \Illuminate\Pagination\LengthAwarePaginator(
                collect([]), 0, 8, 1,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        }

        $prices = $data->pluck('harga')->filter()->values()->toArray();

        if (count($prices) < 4) {
            return new \Illuminate\Pagination\LengthAwarePaginator(
                collect([]), 0, 8, 1,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        }

        sort($prices);
        $q1  = $prices[(int) floor(count($prices) * 0.25)];
        $q3  = $prices[(int) floor(count($prices) * 0.75)];
        $iqr = $q3 - $q1;
        $lowerBound = $q1 - (1.5 * $iqr);
        $upperBound = $q3 + (1.5 * $iqr);

        $issues = [];
        foreach ($data as $item) {
            if (is_null($item->harga) || $item->harga <= 0) {
                $issues[] = (object)[
                    'date'   => $item->tanggal,
                    'issue'  => 'Missing Value',
                    'value'  => 0,
                    'status' => 'Perlu Diisi (Imputation)',
                ];
            } elseif ($item->harga < $lowerBound || $item->harga > $upperBound) {
                $issues[] = (object)[
                    'date'   => $item->tanggal,
                    'issue'  => 'Outlier',
                    'value'  => $item->harga,
                    'status' => $item->harga > $upperBound ? 'Terlalu Tinggi' : 'Terlalu Rendah',
                ];
            }
        }

        $issuesCollection = collect($issues);
        $perPage      = 8;
        $currentPage  = $request->input('page', 1);
        $currentItems = $issuesCollection->slice(($currentPage - 1) * $perPage, $perPage)->values();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $currentItems,
            $issuesCollection->count(),
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => array_merge($request->query(), ['tab' => 'manage'])]
        );
    }

    // =========================================================
    // CLEAN DATA
    // =========================================================
    public function cleanData(Request $request)
    {
        $request->validate([
            'action'       => 'required|in:outlier,missing',
            'komoditas_id' => 'required',
        ]);

        try {
            $action      = $request->input('action');
            $method      = $request->input($action === 'outlier' ? 'outlier_method' : 'missing_method');
            $komoditasId = $request->input('komoditas_id');

            $prices = CommodityPrice::where('komoditas_id', $komoditasId)
                ->where('harga', '>', 0)
                ->pluck('harga')
                ->toArray();

            if (empty($prices)) {
                return redirect()->back()->with('error', 'Data tidak mencukupi untuk diproses.');
            }

            sort($prices);
            $mean        = array_sum($prices) / count($prices);
            $median      = $prices[(int) floor(count($prices) / 2)];
            $replacement = ($method === 'median') ? $median : $mean;
            $affectedCount = 0;

            if ($action === 'outlier') {
                $q1  = $prices[(int) floor(count($prices) * 0.25)];
                $q3  = $prices[(int) floor(count($prices) * 0.75)];
                $iqr = $q3 - $q1;

                $outliers = CommodityPrice::where('komoditas_id', $komoditasId)
                    ->where(function ($q) use ($q1, $q3, $iqr) {
                        $q->where('harga', '<', $q1 - 1.5 * $iqr)
                          ->orWhere('harga', '>', $q3 + 1.5 * $iqr);
                    });

                $affectedCount = $outliers->count();
                $method === 'remove'
                    ? $outliers->delete()
                    : $outliers->update(['harga' => round($replacement, 2), 'updated_at' => now()]);
            } else {
                $missingValues = CommodityPrice::where('komoditas_id', $komoditasId)
                    ->where(function ($q) {
                        $q->whereNull('harga')->orWhere('harga', '<=', 0);
                    });

                $affectedCount = $missingValues->count();
                $method === 'remove'
                    ? $missingValues->delete()
                    : $missingValues->update(['harga' => round($replacement, 2), 'updated_at' => now()]);
            }

            $actionText = $action === 'outlier' ? 'Outlier' : 'Missing Values';
            $methodText = $method === 'remove'  ? 'dihapus' : 'diperbaiki';

            return redirect()
                ->route('operator.predict', ['tab' => 'manage', 'komoditas_id' => $komoditasId])
                ->with('success', "{$actionText} berhasil {$methodText}! {$affectedCount} data diproses.");

        } catch (\Exception $e) {
            Log::error('[OPERATOR] Clean Data Error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Gagal membersihkan data: ' . $e->getMessage());
        }
    }

    // =========================================================
    // STORE DATA (Manual + CSV)
    // =========================================================
    public function storeData(Request $request)
    {
        try {
            if ($request->hasFile('csv_file')) {
                $file   = $request->file('csv_file');
                $handle = fopen($file->getRealPath(), 'r');
                fgetcsv($handle); // skip header

                $insertedCount = 0;
                while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                    if (count($row) >= 3 && !empty(trim($row[0])) && !empty(trim($row[1]))) {
                        CommodityPrice::updateOrCreate(
                            [
                                'komoditas_id' => (int) trim($row[0]),
                                'tanggal'      => trim($row[1]),
                            ],
                            [
                                'harga'      => (float) str_replace(',', '', trim($row[2])),
                                'updated_at' => now(),
                            ]
                        );
                        $insertedCount++;
                    }
                }
                fclose($handle);

                return redirect()
                    ->route('operator.predict', ['tab' => 'manage'])
                    ->with('success', "Bulk upload berhasil! {$insertedCount} data diproses.");
            }

            $request->validate([
                'komoditas_id' => 'required|integer',
                'date'         => 'required|date',
                'price'        => 'required|numeric|min:1',
            ]);

            CommodityPrice::create([
                'komoditas_id' => $request->komoditas_id,
                'tanggal'      => $request->date,
                'harga'        => $request->price,
            ]);

            return redirect()
                ->route('operator.predict', ['tab' => 'manage'])
                ->with('success', 'Data berhasil ditambahkan!');

        } catch (\Exception $e) {
            Log::error('[OPERATOR] Store Data Error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Gagal menyimpan data: ' . $e->getMessage());
        }
    }

    // =========================================================
    // UPDATE DATA (Inline AJAX)
    // =========================================================
    public function updateData(Request $request, $id)
    {
        try {
            CommodityPrice::findOrFail($id)->update([
                'komoditas_id' => $request->komoditas_id,
                'tanggal'      => $request->date,
                'harga'        => $request->price,
                'updated_at'   => now(),
            ]);

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error('[OPERATOR] Update Data Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================
    // DELETE DATA
    // =========================================================
    public function deleteData($id)
    {
        try {
            CommodityPrice::findOrFail($id)->delete();

            return redirect()
                ->route('operator.predict', ['tab' => 'manage'])
                ->with('success', 'Data berhasil dihapus!');

        } catch (\Exception $e) {
            Log::error('[OPERATOR] Delete Data Error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Gagal menghapus data.');
        }
    }

    // =========================================================
    // DOWNLOAD CSV TEMPLATE
    // =========================================================
    public function downloadTemplate()
    {
        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="template_data_komoditas.csv"',
        ];

        $columns    = ['komoditas_id', 'tanggal', 'harga'];
        $sampleData = [
            ['9',  '2020-01-06', '6000'],
            ['10', '2020-02-03', '7000'],
            ['11', '2020-03-02', '25100'],
        ];

        $callback = function () use ($columns, $sampleData) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            foreach ($sampleData as $row) fputcsv($file, $row);
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // =========================================================
    // AGGREGATION — Weekly
    // =========================================================
    private function aggregateWeeklyData(
        $actualDates, $actualPrices,
        $forecastDates, $forecastPrices, $forecastLowers, $forecastUppers,
        &$labels, &$actualAgg, &$forecastAgg, &$lower, &$upper
    ) {
        $weekGroups = [];

        foreach ($actualDates as $i => $date) {
            $d   = $date instanceof Carbon ? $date : Carbon::parse($date);
            $key = $d->year . '-W' . str_pad($d->weekOfYear, 2, '0', STR_PAD_LEFT);
            if (!isset($weekGroups[$key])) {
                $weekGroups[$key] = [
                    'label'          => $d->copy()->startOfWeek()->format('d/m') . ' - ' . $d->copy()->endOfWeek()->format('d/m/Y'),
                    'actualPrices'   => [],
                    'forecastPrices' => [],
                    'lowerPrices'    => [],
                    'upperPrices'    => [],
                    'sortKey'        => $d->timestamp,
                ];
            }
            if (isset($actualPrices[$i])) {
                $weekGroups[$key]['actualPrices'][] = $actualPrices[$i];
            }
        }

        foreach ($forecastDates as $i => $date) {
            $d   = $date instanceof Carbon ? $date : Carbon::parse($date);
            $key = $d->year . '-W' . str_pad($d->weekOfYear, 2, '0', STR_PAD_LEFT);
            if (!isset($weekGroups[$key])) {
                $weekGroups[$key] = [
                    'label'          => $d->copy()->startOfWeek()->format('d/m') . ' - ' . $d->copy()->endOfWeek()->format('d/m/Y'),
                    'actualPrices'   => [],
                    'forecastPrices' => [],
                    'lowerPrices'    => [],
                    'upperPrices'    => [],
                    'sortKey'        => $d->timestamp,
                ];
            }
            if (isset($forecastPrices[$i])) {
                $weekGroups[$key]['forecastPrices'][] = $forecastPrices[$i];
                $weekGroups[$key]['lowerPrices'][]    = $forecastLowers[$i] ?? $forecastPrices[$i];
                $weekGroups[$key]['upperPrices'][]    = $forecastUppers[$i] ?? $forecastPrices[$i];
            }
        }

        ksort($weekGroups);

        foreach ($weekGroups as $week) {
            $labels[]    = $week['label'];
            $actualAgg[] = !empty($week['actualPrices'])
                ? round(array_sum($week['actualPrices']) / count($week['actualPrices']))
                : null;

            if (!empty($week['forecastPrices'])) {
                $forecastAgg[] = round(array_sum($week['forecastPrices']) / count($week['forecastPrices']));
                $lower[]       = round(array_sum($week['lowerPrices'])    / count($week['lowerPrices']));
                $upper[]       = round(array_sum($week['upperPrices'])    / count($week['upperPrices']));
            } else {
                $forecastAgg[] = null;
                $lower[]       = null;
                $upper[]       = null;
            }
        }
    }

    // =========================================================
    // AGGREGATION — Monthly
    // =========================================================
    private function aggregateMonthlyData(
        $actualDates, $actualPrices,
        $forecastDates, $forecastPrices, $forecastLowers, $forecastUppers,
        &$labels, &$actualAgg, &$forecastAgg, &$lower, &$upper
    ) {
        $monthGroups = [];

        foreach ($actualDates as $i => $date) {
            $d   = $date instanceof Carbon ? $date : Carbon::parse($date);
            $key = $d->format('Y-m');
            if (!isset($monthGroups[$key])) {
                $monthGroups[$key] = [
                    'label'          => $d->format('M Y'),
                    'actualPrices'   => [],
                    'forecastPrices' => [],
                    'lowerPrices'    => [],
                    'upperPrices'    => [],
                ];
            }
            if (isset($actualPrices[$i])) {
                $monthGroups[$key]['actualPrices'][] = $actualPrices[$i];
            }
        }

        foreach ($forecastDates as $i => $date) {
            $d   = $date instanceof Carbon ? $date : Carbon::parse($date);
            $key = $d->format('Y-m');
            if (!isset($monthGroups[$key])) {
                $monthGroups[$key] = [
                    'label'          => $d->format('M Y'),
                    'actualPrices'   => [],
                    'forecastPrices' => [],
                    'lowerPrices'    => [],
                    'upperPrices'    => [],
                ];
            }
            if (isset($forecastPrices[$i])) {
                $monthGroups[$key]['forecastPrices'][] = $forecastPrices[$i];
                $monthGroups[$key]['lowerPrices'][]    = $forecastLowers[$i] ?? $forecastPrices[$i];
                $monthGroups[$key]['upperPrices'][]    = $forecastUppers[$i] ?? $forecastPrices[$i];
            }
        }

        ksort($monthGroups);

        foreach ($monthGroups as $month) {
            $labels[]    = $month['label'];
            $actualAgg[] = !empty($month['actualPrices'])
                ? round(array_sum($month['actualPrices']) / count($month['actualPrices']))
                : null;

            if (!empty($month['forecastPrices'])) {
                $forecastAgg[] = round(array_sum($month['forecastPrices']) / count($month['forecastPrices']));
                $lower[]       = round(array_sum($month['lowerPrices'])    / count($month['lowerPrices']));
                $upper[]       = round(array_sum($month['upperPrices'])    / count($month['upperPrices']));
            } else {
                $forecastAgg[] = null;
                $lower[]       = null;
                $upper[]       = null;
            }
        }
    }

    // =========================================================
    // AGGREGATION — Yearly
    // =========================================================
    private function aggregateYearlyData(
        $actualDates, $actualPrices,
        $forecastDates, $forecastPrices, $forecastLowers, $forecastUppers,
        &$labels, &$actualAgg, &$forecastAgg, &$lower, &$upper
    ) {
        $yearGroups = [];

        foreach ($actualDates as $i => $date) {
            $d   = $date instanceof Carbon ? $date : Carbon::parse($date);
            $key = (string) $d->year;
            if (!isset($yearGroups[$key])) {
                $yearGroups[$key] = [
                    'label'          => 'Tahun ' . $d->year,
                    'actualPrices'   => [],
                    'forecastPrices' => [],
                    'lowerPrices'    => [],
                    'upperPrices'    => [],
                ];
            }
            if (isset($actualPrices[$i])) {
                $yearGroups[$key]['actualPrices'][] = $actualPrices[$i];
            }
        }

        foreach ($forecastDates as $i => $date) {
            $d   = $date instanceof Carbon ? $date : Carbon::parse($date);
            $key = (string) $d->year;
            if (!isset($yearGroups[$key])) {
                $yearGroups[$key] = [
                    'label'          => 'Tahun ' . $d->year,
                    'actualPrices'   => [],
                    'forecastPrices' => [],
                    'lowerPrices'    => [],
                    'upperPrices'    => [],
                ];
            }
            if (isset($forecastPrices[$i])) {
                $yearGroups[$key]['forecastPrices'][] = $forecastPrices[$i];
                $yearGroups[$key]['lowerPrices'][]    = $forecastLowers[$i] ?? $forecastPrices[$i];
                $yearGroups[$key]['upperPrices'][]    = $forecastUppers[$i] ?? $forecastPrices[$i];
            }
        }

        ksort($yearGroups);

        foreach ($yearGroups as $year) {
            $labels[]    = $year['label'];
            $actualAgg[] = !empty($year['actualPrices'])
                ? round(array_sum($year['actualPrices']) / count($year['actualPrices']))
                : null;

            if (!empty($year['forecastPrices'])) {
                $forecastAgg[] = round(array_sum($year['forecastPrices']) / count($year['forecastPrices']));
                $lower[]       = round(array_sum($year['lowerPrices'])    / count($year['lowerPrices']));
                $upper[]       = round(array_sum($year['upperPrices'])    / count($year['upperPrices']));
            } else {
                $forecastAgg[] = null;
                $lower[]       = null;
                $upper[]       = null;
            }
        }
    }
}