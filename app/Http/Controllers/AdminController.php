<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\PriceData;
use App\Models\MasterKomoditas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class AdminController extends Controller
{
    /** URL Flask dari .env */
    private string $flaskUrl;

    public function __construct()
    {
        $this->flaskUrl = rtrim(env('FLASK_URL', 'http://localhost:5000'), '/');
    }

    public function index(Request $request)
    {
        return $this->processForecasting($request);
    }

    public function beranda()
    {
        return redirect()->route('laporan.komoditas.index');
    }

    public function predict(Request $request)
    {
        return $this->processForecasting($request);
    }

    // =========================================================
    // MAIN FORECASTING PROCESSOR
    // ✅ Selaras penuh dengan OperatorController
    // =========================================================
    private function processForecasting(Request $request)
    {
        $role     = 'admin';
        $username = auth()->user()->name  ?? 'Administrator BPS';
        $email    = auth()->user()->email ?? 'admin_riau@bps.go.id';

        $currentTab = $request->query('tab', $request->input('tab', 'insight'));
        $startDate  = $request->input('start_date', '2020-01-01');
        $endDate    = $request->input('end_date', Carbon::now()->format('Y-m-d'));

        // ── Hyperparameter ────────────────────────────────────
        $cpScale       = $this->parseFloatSafe($request->input('changepoint_prior_scale'), 0.05);
        $seasonScale   = $this->parseFloatSafe($request->input('seasonality_prior_scale'), 10.0);
        $seasonMode    = $request->input('seasonality_mode', 'multiplicative');
        $weeklySeason  = $this->parseBoolFromString($request->input('weekly_seasonality', 'false'));
        $yearlySeason  = $this->parseBoolFromString($request->input('yearly_seasonality', 'false'));
        $forecastWeeks = max(1, min(52, (int) $request->input('forecast_weeks', 12)));

        // Validasi range
        $cpScale     = max(0.001, min(0.5,  $cpScale));
        $seasonScale = max(0.01,  min(50.0, $seasonScale));
        if (!in_array($seasonMode, ['additive', 'multiplicative'])) {
            $seasonMode = 'multiplicative';
        }

        // Alias untuk view
        $seasonalityMode = $seasonMode;

        Log::info('[ADMIN] Hyperparameters:', [
            'cp' => $cpScale, 'season' => $seasonScale, 'mode' => $seasonMode,
            'weekly' => $weeklySeason, 'yearly' => $yearlySeason, 'weeks' => $forecastWeeks,
        ]);

        // ── Daftar komoditas ──────────────────────────────────
        try {
            $commodities = MasterKomoditas::orderBy('nama_komoditas')->get();
        } catch (\Exception $e) {
            Log::error('[ADMIN] Gagal ambil master_komoditas: ' . $e->getMessage());
            $commodities = collect();
        }

        // ✅ Selaras Operator: baca GET query DULU, baru POST input
        $selectedKomoditasId = (int) (
            $request->query('komoditas_id')
            ?? $request->input('komoditas_id')
            ?? optional($commodities->first())->id
        );

        $selectedKomoditas = $commodities->first(fn($k) => (int) $k->id === $selectedKomoditasId);
        $selectedCommodity = $selectedKomoditas
            ? ($selectedKomoditas->display_name
               ?? trim($selectedKomoditas->nama_komoditas . ' ' . ($selectedKomoditas->nama_varian ?? '')))
            : 'Tidak Ada Data';

        // ── Inisialisasi semua variabel output ─────────────────
        $users      = collect();
        $allData    = collect();
        $latestData = collect();
        $dataIssues = collect();
        $actualData = [];

        $mape                = 0.0;
        $rSquared            = 0.0;
        $trendDir            = 'Stabil';
        $inSampleMape        = 0.0;   // khusus admin — sensitif terhadap hyperparameter
        $intervalWidth       = 0.0;   // khusus admin — lebar confidence interval
        $changepointCount    = 0;     // khusus admin — jumlah changepoint Prophet
        $seasonalityStrength = 0.0;   // khusus admin — kekuatan pola musiman
        $trendFlexibility    = 0.0;   // khusus admin — fleksibilitas tren

        $avgPrice  = 0;
        $maxPrice  = 0;
        $minPrice  = 0;
        $countData = 0;

        $weeklyLabels  = []; $weeklyActual  = []; $weeklyForecast  = []; $weeklyLower  = []; $weeklyUpper  = [];
        $monthlyLabels = []; $monthlyActual = []; $monthlyForecast = []; $monthlyLower = []; $monthlyUpper = [];
        $yearlyLabels  = []; $yearlyActual  = []; $yearlyForecast  = []; $yearlyLower  = []; $yearlyUpper  = [];

        // ── Tab users (khusus admin) ──────────────────────────
        if ($currentTab === 'users') {
            $users = User::orderBy('created_at', 'desc')->paginate(10);
        }

        // ── Tab manage ────────────────────────────────────────
        if ($currentTab === 'manage' && $selectedKomoditasId) {
            try {
                $latestData = PriceData::with('komoditas')
                    ->where('komoditas_id', $selectedKomoditasId)
                    ->orderBy('tanggal', 'desc')
                    ->paginate(10);
            } catch (\Exception $e) {
                Log::error('[ADMIN] latestData error: ' . $e->getMessage());
                $latestData = new \Illuminate\Pagination\LengthAwarePaginator(
                    collect(), 0, 10, 1,
                    ['path' => $request->url(), 'query' => $request->query()]
                );
            }
            try {
                $dataIssues = $this->scanDataQualityPaginated($selectedKomoditasId, $request);
            } catch (\Exception $e) {
                Log::error('[ADMIN] scanDataQuality error: ' . $e->getMessage());
            }
        }

        // ============================================================
        // AMBIL DATA HISTORIS DARI DATABASE
        // ✅ Selaras Operator: filter harga > 0 dan NOT NULL
        // ============================================================
        $prices = [];
        $dates  = [];

        try {
            $dbData = PriceData::where('komoditas_id', $selectedKomoditasId)
                ->whereBetween('tanggal', [$startDate, $endDate])
                ->whereNotNull('harga')
                ->where('harga', '>', 0)
                ->orderBy('tanggal', 'asc')
                ->get();

            Log::info("[ADMIN INSIGHT] komoditas_id={$selectedKomoditasId} | nama={$selectedCommodity} | count={$dbData->count()} | periode={$startDate} s/d {$endDate}");

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
            Log::error('[ADMIN INSIGHT] Gagal ambil price_data: ' . $e->getMessage());
        }

        // ============================================================
        // FORECASTING — prioritas Flask Prophet, fallback PHP
        // ✅ Selaras penuh dengan OperatorController
        // ============================================================
        if (count($prices) >= 2) {

            $actualData = $prices;
            $countData  = count($prices);
            $avgPrice   = array_sum($prices) / $countData;
            $maxPrice   = max($prices);
            $minPrice   = min($prices);

            // ── Coba panggil Flask Prophet ────────────────────
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
                Log::info("[ADMIN PROPHET] Berhasil: " . count($flaskResult['predictions']) . " prediksi | MAPE=" . $flaskResult['mape']);

                $flaskMetrics = $flaskResult['metrics'];

                $mape     = $flaskResult['mape'];
                $rSquared = $flaskResult['r_squared'];
                $trendDir = match($flaskResult['trend_direction']) {
                    'increasing' => 'Naik',
                    'decreasing' => 'Turun',
                    default      => 'Stabil',
                };

                // Metrics tambahan khusus admin
                $inSampleMape        = round((float) ($flaskMetrics['in_sample_mape']                          ?? 0), 2);
                $intervalWidth       = round((float) ($flaskMetrics['future_interval_width']
                                                   ?? $flaskMetrics['avg_interval_width']                      ?? 0), 0);
                $changepointCount    = (int)           ($flaskMetrics['changepoint_count']                      ?? 0);
                $seasonalityStrength = round((float) ($flaskMetrics['seasonality_strength']                    ?? 0), 2);
                $trendFlexibility    = round((float) ($flaskMetrics['trend_flexibility']                       ?? 0), 6);

                // Build chart dari hasil Prophet
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
                // ✅ Selaras Operator: tidak ada dummy data
                // ════════════════════════════════════════════
                Log::warning("[ADMIN FALLBACK] Flask tidak tersedia, menggunakan kalkulasi PHP");

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
            Log::warning("[ADMIN INSIGHT] Data tidak cukup komoditas_id={$selectedKomoditasId} (count=" . count($prices) . ")");
            $countData = count($prices);
            $avgPrice  = count($prices) > 0 ? $prices[0] : 0;
            $maxPrice  = $avgPrice;
            $minPrice  = $avgPrice;
        }

        $rSquared = round($rSquared, 3);

        Log::info('[ADMIN] Final → mape=' . $mape . ' rSquared=' . $rSquared . ' trendDir=' . $trendDir);

        return view('admin_dashboard', compact(
            'role', 'username', 'email',
            'currentTab',
            'commodities', 'selectedCommodity', 'selectedKomoditasId',
            'users',
            'allData', 'latestData', 'dataIssues',
            'startDate', 'endDate',
            'trendDir', 'avgPrice', 'maxPrice', 'minPrice',
            'cpScale', 'seasonScale', 'seasonalityMode', 'seasonMode',
            'weeklySeason', 'yearlySeason', 'forecastWeeks',
            'mape', 'rSquared',
            'inSampleMape', 'intervalWidth', 'changepointCount',
            'seasonalityStrength', 'trendFlexibility',
            'weeklyLabels',  'weeklyActual',  'weeklyForecast',  'weeklyLower',  'weeklyUpper',
            'monthlyLabels', 'monthlyActual', 'monthlyForecast', 'monthlyLower', 'monthlyUpper',
            'yearlyLabels',  'yearlyActual',  'yearlyForecast',  'yearlyLower',  'yearlyUpper',
            'actualData', 'countData'
        ));
    }

    // =========================================================
    // PANGGIL FLASK PROPHET API
    // ✅ Selaras Operator: Http facade, forecastWeeks, frequency='W'
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
                'periods'                 => $forecastWeeks * 7,  // hari, Flask yang konversi ke minggu
                'frequency'               => 'W',                 // data mingguan
                'changepoint_prior_scale' => $cpScale,
                'seasonality_prior_scale' => $seasonScale,
                'seasonality_mode'        => $seasonMode,
                'weekly_seasonality'      => $weeklySeason,
                'yearly_seasonality'      => $yearlySeason,
            ];

            Log::info("[ADMIN FLASK] Mengirim request", $payload);

            $response = Http::timeout(60)
                ->connectTimeout(5)
                ->post("{$this->flaskUrl}/api/forecast/predict-advanced", $payload);

            if (!$response->successful()) {
                Log::warning("[ADMIN FLASK] HTTP {$response->status()}: " . $response->body());
                return null;
            }

            $data = $response->json();

            if (!($data['success'] ?? false)) {
                Log::warning("[ADMIN FLASK] Error: " . ($data['message'] ?? 'unknown'));
                return null;
            }

            $modelMetrics = $data['data']['model_metrics'] ?? [];
            $predictions  = $data['data']['predictions']   ?? [];

            if (empty($predictions)) {
                Log::warning("[ADMIN FLASK] Prediksi kosong");
                return null;
            }

            $coverage = $modelMetrics['coverage']  ?? 0.95;
            $mape     = $modelMetrics['mape']       ?? $modelMetrics['in_sample_mape'] ?? 0.0;

            Log::info("[ADMIN FLASK] Berhasil: " . count($predictions) . " prediksi | MAPE={$mape} | coverage={$coverage}");

            return [
                'predictions'     => $predictions,
                'mape'            => round((float) $mape, 2),
                'r_squared'       => round(min(1.0, max(0.0, (float) $coverage)), 4),
                'trend_direction' => $modelMetrics['trend_direction'] ?? 'stable',
                'metrics'         => $modelMetrics,
            ];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning("[ADMIN FLASK] Tidak bisa dihubungi: " . $e->getMessage());
            return null;
        } catch (\Exception $e) {
            Log::error("[ADMIN FLASK] Error: " . $e->getMessage());
            return null;
        }
    }

    // =========================================================
    // BUILD CHART DARI HASIL PROPHET
    // ✅ Selaras Operator: satu method terpusat, hapus duplikat
    //    aggregateWeeklyFromFlask / aggregateMonthlyFromFlask /
    //    aggregateYearlyFromFlask
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
    // ✅ Selaras Operator: signature identik
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
    // ✅ Selaras Operator: signature identik
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
    // ✅ Selaras Operator: signature identik
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
    // ✅ Selaras Operator: identik
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

    private function calculateRSquared(array $actual, array $predicted): float
    {
        $n = min(count($actual), count($predicted));
        if ($n < 2) return 0.0;
        $actualSlice = array_slice($actual, -$n);
        $predSlice   = array_slice($predicted, 0, $n);
        $meanActual  = array_sum($actualSlice) / $n;
        $ssTot = 0.0; $ssRes = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $ssTot += pow($actualSlice[$i] - $meanActual, 2);
            $ssRes += pow($actualSlice[$i] - $predSlice[$i], 2);
        }
        if ($ssTot == 0) return 1.0;
        return round(max(-1.0, min(1.0, 1.0 - ($ssRes / $ssTot))), 3);
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
    // ✅ Dipertahankan dari admin lama (lebih aman dari Operator)
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

    // =========================================================
    // DATA QUALITY SCANNER
    // =========================================================
    private function scanDataQualityPaginated($komoditasId, $request)
    {
        $data = PriceData::where('komoditas_id', $komoditasId)
            ->orderBy('tanggal', 'asc')
            ->get();

        if ($data->isEmpty()) {
            return new \Illuminate\Pagination\LengthAwarePaginator(
                collect([]), 0, 8, 1,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        }

        $prices = $data->where('is_outlier', false)->pluck('harga')->filter()->values()->toArray();

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

        $issues = [];
        foreach ($data as $item) {
            if (is_null($item->harga) || $item->harga <= 0) {
                $issues[] = (object)['date' => $item->tanggal, 'issue' => 'Missing Value', 'value' => 0,          'status' => 'Perlu Diisi'];
            } elseif ($item->harga < ($q1 - 1.5 * $iqr) || $item->harga > ($q3 + 1.5 * $iqr)) {
                $issues[] = (object)['date' => $item->tanggal, 'issue' => 'Outlier',       'value' => $item->harga, 'status' => $item->harga > ($q3 + 1.5 * $iqr) ? 'Terlalu Tinggi' : 'Terlalu Rendah'];
            }
        }

        $issuesCollection = collect($issues);
        $perPage      = 8;
        $currentPage  = $request->input('page', 1);
        $currentItems = $issuesCollection->slice(($currentPage - 1) * $perPage, $perPage)->values();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $currentItems, $issuesCollection->count(), $perPage, $currentPage,
            ['path' => $request->url(), 'query' => array_merge($request->query(), ['tab' => 'manage'])]
        );
    }

    // =========================================================
    // DATA MANAGEMENT (CRUD)
    // ✅ Dipertahankan dari admin lama
    // =========================================================
    public function storeData(Request $request)
    {
        try {
            if ($request->hasFile('dataset')) {
                return redirect()->route('admin.manajemen-data.upload-csv');
            }

            $request->validate([
                'komoditas_id' => 'required|exists:master_komoditas,id',
                'date'         => 'required|date',
                'price'        => 'required|numeric|min:0',
            ]);

            $exists = PriceData::where('komoditas_id', $request->komoditas_id)
                ->where('tanggal', $request->date)
                ->exists();

            if ($exists) {
                return redirect()->back()->with('error', 'Data untuk komoditas dan tanggal ini sudah ada.');
            }

            PriceData::create([
                'komoditas_id' => $request->komoditas_id,
                'tanggal'      => $request->date,
                'harga'        => $request->price,
                'status'       => 'cleaned',
                'is_outlier'   => false,
            ]);

            return redirect()
                ->route('admin.predict', ['tab' => 'manage', 'komoditas_id' => $request->komoditas_id])
                ->with('success', 'Data berhasil ditambahkan!');

        } catch (\Exception $e) {
            Log::error('[ADMIN] Store Data Error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Gagal menyimpan data: ' . $e->getMessage());
        }
    }

    public function updateData(Request $request, $id)
    {
        $request->validate([
            'komoditas_id' => 'required|exists:master_komoditas,id',
            'date'         => 'required|date',
            'price'        => 'required|numeric|min:0',
        ]);

        try {
            PriceData::findOrFail($id)->update([
                'komoditas_id' => $request->komoditas_id,
                'tanggal'      => $request->date,
                'harga'        => $request->price,
            ]);
            return response()->json(['success' => true, 'message' => 'Data berhasil diperbarui!']);
        } catch (\Exception $e) {
            Log::error('[ADMIN] Update Data Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteData($id)
    {
        try {
            PriceData::findOrFail($id)->delete();
            return redirect()
                ->route('admin.predict', ['tab' => 'manage'])
                ->with('success', 'Data berhasil dihapus!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal menghapus data.');
        }
    }

    public function cleanData(Request $request)
    {
        $request->validate([
            'action'       => 'required|in:outlier,missing',
            'komoditas_id' => 'required|exists:master_komoditas,id',
        ]);

        try {
            $action      = $request->input('action');
            $method      = $request->input($action === 'outlier' ? 'outlier_method' : 'missing_method');
            $komoditasId = $request->input('komoditas_id');

            $prices = PriceData::where('komoditas_id', $komoditasId)
                ->where('harga', '>', 0)->pluck('harga')
                ->map(fn($h) => (float) $h)->toArray();

            if (empty($prices)) {
                return redirect()->back()->with('error', 'Data tidak mencukupi untuk pemrosesan.');
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
                $outliers = PriceData::where('komoditas_id', $komoditasId)
                    ->where(fn($q) => $q->where('harga', '<', $q1 - 1.5 * $iqr)->orWhere('harga', '>', $q3 + 1.5 * $iqr));
                $affectedCount = $outliers->count();
                $method === 'remove'
                    ? $outliers->delete()
                    : $outliers->update(['harga' => round($replacement, 2), 'is_outlier' => false, 'status' => 'cleaned']);
            } else {
                $missing = PriceData::where('komoditas_id', $komoditasId)
                    ->where(fn($q) => $q->whereNull('harga')->orWhere('harga', '<=', 0));
                $affectedCount = $missing->count();
                $method === 'remove'
                    ? $missing->delete()
                    : $missing->update(['harga' => round($replacement, 2), 'status' => 'cleaned']);
            }

            return redirect()
                ->route('admin.predict', ['tab' => 'manage', 'komoditas_id' => $komoditasId])
                ->with('success', "{$affectedCount} data berhasil diproses.");

        } catch (\Exception $e) {
            Log::error('[ADMIN] Clean Data Error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Gagal membersihkan data: ' . $e->getMessage());
        }
    }

    public function downloadTemplate()
    {
        $headers  = ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="template_data_komoditas.csv"'];
        $columns  = ['nama_komoditas', 'nama_varian', 'tanggal', 'harga'];
        $samples  = [['Beras', 'Premium', '2026-01-06', '14500'], ['Beras', 'Medium', '2026-01-06', '13000'], ['Cabai', 'Merah', '2026-01-06', '35000']];
        $callback = function () use ($columns, $samples) {
            $f = fopen('php://output', 'w');
            fputcsv($f, $columns);
            foreach ($samples as $row) fputcsv($f, $row);
            fclose($f);
        };
        return response()->stream($callback, 200, $headers);
    }

    // =========================================================
    // USER MANAGEMENT (khusus admin)
    // ✅ Dipertahankan dari admin lama
    // =========================================================
    public function storeUser(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role'     => 'required|in:user,operator,admin',
        ]);

        try {
            User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'password' => Hash::make($request->password),
                'role'     => $request->role,
            ]);
            return redirect()->route('admin.predict', ['tab' => 'users'])->with('success', 'Pengguna berhasil dibuat!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal membuat pengguna: ' . $e->getMessage());
        }
    }

    public function updateUser(Request $request, $id)
    {
        $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'role'  => 'required|in:user,operator,admin',
        ]);

        try {
            if (auth()->id() == $id && $request->role !== auth()->user()->role) {
                return response()->json(['success' => false, 'message' => 'Tidak dapat mengubah role Anda sendiri!'], 403);
            }
            if (User::where('email', $request->email)->where('id', '!=', $id)->exists()) {
                return response()->json(['success' => false, 'message' => 'Email sudah digunakan!'], 422);
            }
            User::findOrFail($id)->update(['name' => $request->name, 'email' => $request->email, 'role' => $request->role]);
            return response()->json(['success' => true, 'message' => 'Data pengguna berhasil diperbarui!']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteUser($id)
    {
        try {
            if (auth()->id() == $id) {
                return redirect()->back()->with('error', 'Tidak dapat menghapus akun Anda sendiri!');
            }
            User::findOrFail($id)->delete();
            return redirect()->route('admin.predict', ['tab' => 'users'])->with('success', 'Pengguna berhasil dihapus!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal menghapus pengguna.');
        }
    }
}