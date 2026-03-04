<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PriceData;
use App\Models\MasterKomoditas;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class UserController extends Controller
{
    /** URL Flask dari .env */
    private string $flaskUrl;

    public function __construct()
    {
        $this->flaskUrl = rtrim(env('FLASK_URL', 'http://localhost:5000'), '/');
    }

    // =========================================================
    // ENTRY POINTS
    // =========================================================

    public function index(Request $request)
    {
        return $this->processForecasting($request);
    }

    public function analisis(Request $request)
    {
        return $this->processForecasting($request);
    }

    // =========================================================
    // MAIN FORECASTING PROCESSOR
    // ✅ Selaras penuh dengan AdminController
    //    Perbedaan: tidak ada tab, tidak ada user/manage/users,
    //    hyperparameter read-only (tidak bisa diubah user),
    //    view mengarah ke user/analisis
    // =========================================================
    private function processForecasting(Request $request)
    {
        $startDate = $request->input('start_date', '2020-01-01');
        $endDate   = $request->input('end_date', Carbon::now()->format('Y-m-d'));

        // ── Hyperparameter: user hanya bisa kirim commodity & tanggal
        //    Hyperparameter menggunakan nilai default yang tetap
        $cpScale       = $this->parseFloatSafe($request->input('changepoint_prior_scale'), 0.05);
        $seasonScale   = $this->parseFloatSafe($request->input('seasonality_prior_scale'), 10.0);
        $seasonMode    = $request->input('seasonality_mode', 'multiplicative');
        $weeklySeason  = $this->parseBoolFromString($request->input('weekly_seasonality', 'false'));
        $yearlySeason  = $this->parseBoolFromString($request->input('yearly_seasonality', 'false'));
        $forecastWeeks = max(1, min(52, (int) $request->input('forecast_weeks', 12)));

        // Validasi range (sama persis dengan AdminController)
        $cpScale     = max(0.001, min(0.5,  $cpScale));
        $seasonScale = max(0.01,  min(50.0, $seasonScale));
        if (!in_array($seasonMode, ['additive', 'multiplicative'])) {
            $seasonMode = 'multiplicative';
        }

        // Alias untuk view (blade pakai $seasonalityMode & $weeklyActive & $yearlyActive)
        $seasonalityMode = $seasonMode;
        $weeklyActive    = $weeklySeason;
        $yearlyActive    = $yearlySeason;

        // ── Daftar komoditas untuk dropdown ──────────────────
        try {
            $allCommodities = MasterKomoditas::orderBy('nama_komoditas')->get();
        } catch (\Exception $e) {
            Log::error('[USER] Gagal ambil master_komoditas: ' . $e->getMessage());
            $allCommodities = collect();
        }

        // ── Komoditas yang dipilih (GET query) ────────────────
        $commodityInput = $request->input('commodity')
            ?? $request->query('commodity');

        if ($commodityInput) {
            $selectedKomoditas = MasterKomoditas::find($commodityInput);
        } else {
            $selectedKomoditas = $allCommodities->first();
        }

        $selectedCommodityId = $selectedKomoditas ? (int) $selectedKomoditas->id : null;

        $selectedCommodity = $selectedKomoditas
            ? ($selectedKomoditas->display_name
               ?? trim($selectedKomoditas->nama_komoditas . ' ' . ($selectedKomoditas->nama_varian ?? '')))
            : 'Tidak Ada Data';

        // ── Inisialisasi variabel output ──────────────────────
        $mape      = 0.0;
        $rSquared  = 0.0;
        $trendDir  = 'Stabil';
        $avgPrice  = 0;
        $maxPrice  = 0;
        $minPrice  = 0;
        $countData = 0;

        $weeklyLabels  = []; $weeklyActual  = []; $weeklyForecast  = []; $weeklyLower  = []; $weeklyUpper  = [];
        $monthlyLabels = []; $monthlyActual = []; $monthlyForecast = []; $monthlyLower = []; $monthlyUpper = [];
        $yearlyLabels  = []; $yearlyActual  = []; $yearlyForecast  = []; $yearlyLower  = []; $yearlyUpper  = [];

        // ── Ambil data historis dari database ─────────────────
        $prices = [];
        $dates  = [];

        try {
            $dbData = PriceData::where('komoditas_id', $selectedCommodityId)
                ->whereBetween('tanggal', [$startDate, $endDate])
                ->whereNotNull('harga')
                ->where('harga', '>', 0)
                ->orderBy('tanggal', 'asc')
                ->get();

            Log::info("[USER INSIGHT] komoditas_id={$selectedCommodityId} | nama={$selectedCommodity} | count={$dbData->count()} | periode={$startDate} s/d {$endDate}");

            if ($dbData->isNotEmpty()) {
                $dates = $dbData->pluck('tanggal')
                                ->map(fn($d) => Carbon::parse($d))
                                ->values()
                                ->toArray();

                $prices = $dbData->pluck('harga')
                                 ->map(fn($h) => (float) $h)
                                 ->values()
                                 ->toArray();
            }
        } catch (\Exception $e) {
            Log::error('[USER INSIGHT] Gagal ambil price_data: ' . $e->getMessage());
        }

        // ============================================================
        // FORECASTING — prioritas Flask Prophet, fallback PHP
        // ✅ Selaras penuh dengan AdminController
        // ============================================================
        if (count($prices) >= 2) {

            $countData = count($prices);
            $avgPrice  = array_sum($prices) / $countData;
            $maxPrice  = max($prices);
            $minPrice  = min($prices);

            // ── Coba panggil Flask Prophet ────────────────────
            $flaskResult = null;
            if ($countData >= 10) {
                $flaskResult = $this->callFlaskProphet(
                    $selectedCommodityId,
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
                Log::info("[USER PROPHET] Berhasil: " . count($flaskResult['predictions']) . " prediksi | MAPE=" . $flaskResult['mape']);

                $mape     = $flaskResult['mape'];
                $rSquared = $flaskResult['r_squared'];
                $trendDir = match($flaskResult['trend_direction']) {
                    'increasing' => 'Naik',
                    'decreasing' => 'Turun',
                    default      => 'Stabil',
                };

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
                // ✅ Selaras AdminController
                // ════════════════════════════════════════════
                Log::warning("[USER FALLBACK] Flask tidak tersedia, menggunakan kalkulasi PHP");

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
            Log::warning("[USER INSIGHT] Data tidak cukup komoditas_id={$selectedCommodityId} (count=" . count($prices) . ")");
            $countData = count($prices);
            $avgPrice  = count($prices) > 0 ? $prices[0] : 0;
            $maxPrice  = $avgPrice;
            $minPrice  = $avgPrice;
        }

        $rSquared = round($rSquared, 3);

        Log::info('[USER] Final → mape=' . $mape . ' rSquared=' . $rSquared . ' trendDir=' . $trendDir);

        return view('dashboard.users.index', compact(
            // Komoditas
            'allCommodities', 'selectedCommodity', 'selectedCommodityId',
            // Rentang tanggal
            'startDate', 'endDate',
            // Metrik
            'trendDir', 'avgPrice', 'maxPrice', 'minPrice', 'countData',
            'mape', 'rSquared',
            // Hyperparameter (read-only, diteruskan via hidden input di blade)
            'cpScale', 'seasonScale', 'seasonalityMode',
            'weeklyActive', 'yearlyActive', 'forecastWeeks',
            // Chart data
            'weeklyLabels',  'weeklyActual',  'weeklyForecast',  'weeklyLower',  'weeklyUpper',
            'monthlyLabels', 'monthlyActual', 'monthlyForecast', 'monthlyLower', 'monthlyUpper',
            'yearlyLabels',  'yearlyActual',  'yearlyForecast',  'yearlyLower',  'yearlyUpper'
        ));
    }

    // =========================================================
    // PANGGIL FLASK PROPHET API
    // ✅ Identik dengan AdminController
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
                'periods'                 => $forecastWeeks * 7,
                'frequency'               => 'W',
                'changepoint_prior_scale' => $cpScale,
                'seasonality_prior_scale' => $seasonScale,
                'seasonality_mode'        => $seasonMode,
                'weekly_seasonality'      => $weeklySeason,
                'yearly_seasonality'      => $yearlySeason,
            ];

            Log::info("[USER FLASK] Mengirim request", $payload);

            $response = Http::timeout(60)
                ->connectTimeout(5)
                ->post("{$this->flaskUrl}/api/forecast/predict-advanced", $payload);

            if (!$response->successful()) {
                Log::warning("[USER FLASK] HTTP {$response->status()}: " . $response->body());
                return null;
            }

            $data = $response->json();

            if (!($data['success'] ?? false)) {
                Log::warning("[USER FLASK] Error: " . ($data['message'] ?? 'unknown'));
                return null;
            }

            $modelMetrics = $data['data']['model_metrics'] ?? [];
            $predictions  = $data['data']['predictions']   ?? [];

            if (empty($predictions)) {
                Log::warning("[USER FLASK] Prediksi kosong");
                return null;
            }

            $coverage = $modelMetrics['coverage']  ?? 0.95;
            $mape     = $modelMetrics['mape']       ?? $modelMetrics['in_sample_mape'] ?? 0.0;

            Log::info("[USER FLASK] Berhasil: " . count($predictions) . " prediksi | MAPE={$mape}");

            return [
                'predictions'     => $predictions,
                'mape'            => round((float) $mape, 2),
                'r_squared'       => round(min(1.0, max(0.0, (float) $coverage)), 4),
                'trend_direction' => $modelMetrics['trend_direction'] ?? 'stable',
                'metrics'         => $modelMetrics,
            ];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning("[USER FLASK] Tidak bisa dihubungi: " . $e->getMessage());
            return null;
        } catch (\Exception $e) {
            Log::error("[USER FLASK] Error: " . $e->getMessage());
            return null;
        }
    }

    // =========================================================
    // BUILD CHART DARI HASIL PROPHET
    // ✅ Identik dengan AdminController
    // =========================================================
    private function buildChartFromProphet(
        array $actualDates,
        array $actualPrices,
        array $predictions,
        &$weeklyLabels,  &$weeklyActual,  &$weeklyForecast,  &$weeklyLower,  &$weeklyUpper,
        &$monthlyLabels, &$monthlyActual, &$monthlyForecast, &$monthlyLower, &$monthlyUpper,
        &$yearlyLabels,  &$yearlyActual,  &$yearlyForecast,  &$yearlyLower,  &$yearlyUpper
    ): void {
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
    // AGGREGATION — Weekly
    // ✅ Identik dengan AdminController
    // =========================================================
    private function aggregateWeeklyData(
        $actualDates, $actualPrices,
        $forecastDates, $forecastPrices, $forecastLowers, $forecastUppers,
        &$labels, &$actualAgg, &$forecastAgg, &$lower, &$upper
    ): void {
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
    // ✅ Identik dengan AdminController
    // =========================================================
    private function aggregateMonthlyData(
        $actualDates, $actualPrices,
        $forecastDates, $forecastPrices, $forecastLowers, $forecastUppers,
        &$labels, &$actualAgg, &$forecastAgg, &$lower, &$upper
    ): void {
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
    // ✅ Identik dengan AdminController
    // =========================================================
    private function aggregateYearlyData(
        $actualDates, $actualPrices,
        $forecastDates, $forecastPrices, $forecastLowers, $forecastUppers,
        &$labels, &$actualAgg, &$forecastAgg, &$lower, &$upper
    ): void {
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

    // =========================================================
    // FALLBACK FORECAST (PHP)
    // ✅ Identik dengan AdminController
    // =========================================================
    private function simpleForecast(array $dates, array $prices, int $forecastDays): array
    {
        $n         = count($prices);
        $lastDate  = end($dates);
        $lastDate  = $lastDate instanceof Carbon ? $lastDate : Carbon::parse($lastDate);
        $lastPrice = end($prices);

        $maWindow = min(12, max(4, (int) floor($n * 0.2)));
        $maSlice  = array_slice($prices, -$maWindow);
        $maAvg    = array_sum($maSlice) / count($maSlice);

        $trendWindow = max(4, (int) floor($n * 0.3));
        $trendSlice  = array_slice($prices, -$trendWindow);
        $trendCount  = count($trendSlice);
        $sumX = 0; $sumY = 0; $sumXY = 0; $sumX2 = 0;
        for ($i = 0; $i < $trendCount; $i++) {
            $sumX  += $i; $sumY  += $trendSlice[$i];
            $sumXY += $i * $trendSlice[$i]; $sumX2 += $i * $i;
        }
        $denom    = ($trendCount * $sumX2 - $sumX * $sumX);
        $slope    = $denom != 0 ? ($trendCount * $sumXY - $sumX * $sumY) / $denom : 0;
        $maxSlope = $lastPrice * 0.01;
        $slope    = max(-$maxSlope, min($maxSlope, $slope));

        $residuals = [];
        for ($i = max(0, $n - $trendWindow); $i < $n; $i++) {
            $residuals[] = $prices[$i] - ($maAvg + $slope * ($i - ($n - $trendWindow)));
        }
        $residualStd = $this->standardDeviation($residuals);

        $forecastDates = []; $forecastPrices = []; $forecastLowers = []; $forecastUppers = [];
        for ($h = 1; $h <= $forecastDays; $h++) {
            $point            = max(0, $lastPrice + $slope * $h);
            $ciWidth          = 1.96 * $residualStd * sqrt($h);
            $forecastDates[]  = $lastDate->copy()->addDays($h);
            $forecastPrices[] = (int) round($point);
            $forecastLowers[] = (int) round(max(0, $point - $ciWidth));
            $forecastUppers[] = (int) round($point + $ciWidth);
        }

        return [$forecastDates, $forecastPrices, $forecastLowers, $forecastUppers];
    }

    private function calculateMetricsFallback(array $prices, array $dates): array
    {
        $n = count($prices);
        if ($n < 4) return [0.0, 0.0];

        $splitIdx    = max(2, (int) floor($n * 0.7));
        $trainPrices = array_slice($prices, 0, $splitIdx);
        $trainDates  = array_slice($dates,  0, $splitIdx);
        $testPrices  = array_values(array_slice($prices, $splitIdx));
        $testCount   = count($testPrices);

        if ($testCount === 0) return [0.0, 0.0];

        [, $forecastPrices, ,] = $this->simpleForecast($trainDates, $trainPrices, $testCount);

        $mapeSum = 0.0; $mapeCount = 0;
        for ($i = 0; $i < $testCount; $i++) {
            $actual = $testPrices[$i]; $predicted = $forecastPrices[$i] ?? 0;
            if ($actual != 0) { $mapeSum += abs(($actual - $predicted) / $actual); $mapeCount++; }
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

    private function standardDeviation(array $values): float
    {
        $n = count($values);
        if ($n < 2) return 0.0;
        $mean     = array_sum($values) / $n;
        $variance = array_sum(array_map(fn($v) => pow($v - $mean, 2), $values)) / ($n - 1);
        return sqrt(max(0, $variance));
    }

    // =========================================================
    // PARSE HELPERS
    // ✅ Identik dengan AdminController
    // =========================================================
    private function parseFloatSafe($value, float $default): float
    {
        if ($value === null || $value === '' || $value === false) return $default;
        $parsed = (float) $value;
        if ($parsed == 0 && trim((string) $value) !== '0') return $default;
        return $parsed;
    }

    private function parseBoolFromString($value): bool
    {
        if ($value === null || $value === '') return false;
        if (is_bool($value)) return $value;
        return in_array(strtolower(trim((string) $value)), ['true', '1', 'yes', 'on'], true);
    }
}