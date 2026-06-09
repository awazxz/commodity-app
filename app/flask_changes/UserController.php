<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\PriceData;
use App\Models\MasterKomoditas;
use App\Models\UserPreference;
use App\Http\Traits\SavesUserPreferences;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class UserController extends Controller
{
    use SavesUserPreferences;

    private string $flaskUrl;
    private string $flaskApiKey;

    // ── Sinkron dengan AdminController dan UserPreference::DEFAULTS ──
    private const DEFAULT_CP     = 0.05;
    private const DEFAULT_SS     = 1.0;
    private const DEFAULT_MODE   = 'multiplicative';
    private const DEFAULT_WEEKLY = false;
    private const DEFAULT_YEARLY = true;

    public function __construct()
    {
        $this->flaskUrl    = rtrim(env('FLASK_URL', 'http://localhost:5000'), '/');
        $this->flaskApiKey = env('FLASK_API_KEY', '');
    }

    private function flaskHttp(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withHeaders([
            'X-Flask-API-Key' => $this->flaskApiKey,
        ]);
    }

    // =========================================================
    // ENTRY POINTS
    // =========================================================

    public function index(Request $request)
    {
        set_time_limit(660);
        return $this->processForecasting($request);
    }

    public function analisis(Request $request)
    {
        set_time_limit(660);
        return $this->processForecasting($request);
    }

    // =========================================================
    // AMBIL ADMIN USER ID — cached per request lifecycle
    // =========================================================

    private function getAdminUserId(): ?int
    {
        static $adminId = null;

        if ($adminId === null) {
            $admin   = User::where('role', 'admin')->orderBy('id')->first();
            $adminId = $admin?->id;

            if ($adminId === null) {
                Log::warning('[USER] Tidak ada user dengan role=admin, fallback ke default parameter.');
            }
        }

        return $adminId;
    }

    // =========================================================
    // RESOLVE PARAMETER DARI ADMIN
    // Tidak membaca $request sama sekali — user tidak bisa
    // override parameter apapun via URL atau form.
    // =========================================================

    private function resolveAdminParams(int $komoditasId = 0): array
    {
        $adminId = $this->getAdminUserId();

        if ($adminId === null) {
            Log::warning('[USER] Tidak ada admin, menggunakan parameter default.');
            return UserPreference::defaultParamArray();
        }

        // Coba ambil preferensi admin per komoditas
        $pref = \App\Models\UserPreference::where('user_id', $adminId)
            ->where('komoditas_id', $komoditasId)
            ->first();
        if (!$pref) {
            Log::info('[USER] Preferensi admin belum ada untuk komoditas_id=' . $komoditasId . ', pakai default.');
            return UserPreference::defaultParamArray();
        }
        $params = $pref->toParamArray();
        Log::info('[USER] Preferensi admin dimuat untuk admin_id=' . $adminId . ' komoditas_id=' . $komoditasId, $params);
        return $params;
    }

    // =========================================================
    // MAIN FORECASTING PROCESSOR
    // =========================================================

    private function processForecasting(Request $request)
    {
        // ── STEP 1: Ambil daftar komoditas ────────────────────
        try {
            $commodities = MasterKomoditas::orderBy('nama_komoditas')->get();
        } catch (\Exception $e) {
            Log::error('[USER] Gagal ambil master_komoditas: ' . $e->getMessage());
            $commodities = collect();
        }

        $allCommodities = $commodities;

        // ── STEP 2: Komoditas yang dipilih ────────────────────
        $selectedKomoditasId = (int) (
            $request->query('komoditas_id')
            ?? $request->query('commodity')
            ?? optional($commodities->first())->id
        );

        $selectedKomoditas = $commodities->first(
            fn($k) => (int) $k->id === $selectedKomoditasId
        );

        $selectedCommodity = $selectedKomoditas
            ? ($selectedKomoditas->display_name
               ?? trim($selectedKomoditas->nama_komoditas . ' ' . ($selectedKomoditas->nama_varian ?? '')))
            : 'Tidak Ada Data';

        $selectedCommodityId = $selectedKomoditasId;

        // ── STEP 3: Auto-detect date range dari DB ────────────
        try {
            $dateRange = PriceData::where('komoditas_id', $selectedKomoditasId)
                ->whereNotNull('harga')
                ->where('harga', '>', 0)
                ->selectRaw('MIN(tanggal) as min_date, MAX(tanggal) as max_date')
                ->first();

            $dbMinDate = $dateRange->min_date ?? '2020-01-01';
            $dbMaxDate = $dateRange->max_date ?? Carbon::now()->format('Y-m-d');
        } catch (\Exception $e) {
            Log::warning('[USER] Gagal auto-detect date range: ' . $e->getMessage());
            $dbMinDate = '2020-01-01';
            $dbMaxDate = Carbon::now()->format('Y-m-d');
        }

        // ── STEP 4: Ambil parameter dari prefs admin ──────────
        $params = $this->resolveAdminParams($selectedKomoditasId);

        // ── STEP 5: Destructure parameter ─────────────────────
        $forecastWeeks   = $params['forecastWeeks'];
        $cpScale         = $params['cpScale'];
        $seasonScale     = $params['seasonScale'];
        $seasonMode      = $params['seasonMode'];
        $weeklySeason    = $params['weeklySeason'];
        $yearlySeason    = $params['yearlySeason'];
        $seasonalityMode = $seasonMode;
        $weeklyActive    = $weeklySeason;
        $yearlyActive    = $yearlySeason;

        // ── STEP 6: Resolve tanggal dari prefs admin ──────────
        $startDate = ($params['startDate'] && $params['startDate'] >= $dbMinDate)
            ? $params['startDate']
            : $dbMinDate;

        $queryEndDate = ($params['endDate'] && $params['endDate'] <= $dbMaxDate)
            ? $params['endDate']
            : $dbMaxDate;

        $endDate = $queryEndDate;

        if ($startDate > $endDate) {
            Log::warning('[USER] startDate > endDate, reset ke full range.');
            $startDate = $dbMinDate;
            $endDate   = $queryEndDate;
        }

        Log::info('[USER] Date range final → start=' . $startDate . ' | end=' . $queryEndDate);

        // ── STEP 7: Hitung user_override dari prefs admin ─────
        $isUserOverride = (
            (float) $cpScale      !== (float) self::DEFAULT_CP     ||
            (float) $seasonScale  !== (float) self::DEFAULT_SS     ||
            $seasonMode           !== self::DEFAULT_MODE            ||
            (bool)  $weeklySeason !== (bool)  self::DEFAULT_WEEKLY ||
            (bool)  $yearlySeason !== (bool)  self::DEFAULT_YEARLY
        );

        Log::info('[USER] Hyperparameters dari prefs admin:', [
            'cpScale'        => $cpScale,
            'seasonScale'    => $seasonScale,
            'seasonMode'     => $seasonMode,
            'weeklySeason'   => $weeklySeason,
            'yearlySeason'   => $yearlySeason,
            'forecastWeeks'  => $forecastWeeks,
            'isUserOverride' => $isUserOverride,
        ]);

        // ── Inisialisasi variabel output ──────────────────────
        $mape      = 0.0;
        $rSquared  = 0.0;
        $trendDir  = 'Stabil';
        $avgPrice  = 0;
        $maxPrice  = 0;
        $minPrice  = 0;
        $countData = 0;

        $weeklyLabels  = []; $weeklyActual  = []; $weeklyForecast  = []; $weeklyLower  = []; $weeklyUpper  = []; $weeklyFitted  = [];
        $monthlyLabels = []; $monthlyActual = []; $monthlyForecast = []; $monthlyLower = []; $monthlyUpper = []; $monthlyFitted = [];
        $yearlyLabels  = []; $yearlyActual  = []; $yearlyForecast  = []; $yearlyLower  = []; $yearlyUpper  = []; $yearlyFitted  = [];

        // ── STEP 8: Ambil data historis ───────────────────────
        $prices = [];
        $dates  = [];

        try {
            $dbData = PriceData::where('komoditas_id', $selectedKomoditasId)
                ->whereBetween('tanggal', [$startDate, $queryEndDate])
                ->whereNotNull('harga')
                ->where('harga', '>', 0)
                ->orderBy('tanggal', 'asc')
                ->get();

            Log::info('[USER] Data historis: komoditas_id=' . $selectedKomoditasId
                . ' | nama=' . $selectedCommodity
                . ' | count=' . $dbData->count()
                . ' | periode=' . $startDate . ' s/d ' . $queryEndDate);

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
            Log::error('[USER] Gagal ambil price_data: ' . $e->getMessage());
        }

        // ── STEP 9: Forecasting ───────────────────────────────
        if (count($prices) >= 2) {

            $countData = count($prices);
            $avgPrice  = array_sum($prices) / $countData;
            $maxPrice  = max($prices);
            $minPrice  = min($prices);

            $flaskResult = null;

            if ($countData >= 10) {
                $flaskResult = $this->callFlaskProphet(
                    $selectedKomoditasId,
                    $forecastWeeks,
                    $cpScale,
                    $seasonScale,
                    $seasonMode,
                    $weeklySeason,
                    $yearlySeason,
                    $startDate,
                    $queryEndDate,
                    false,
                    $isUserOverride
                );
            }

            if ($flaskResult !== null) {
                Log::info('[USER PROPHET] Berhasil: '
                    . count($flaskResult['predictions']) . ' prediksi'
                    . ' | MAPE=' . $flaskResult['mape']
                    . ' | trend=' . $flaskResult['trend_direction']);

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
                    $flaskResult['fitted_values'] ?? [],
                    $weeklyLabels,  $weeklyActual,  $weeklyForecast,  $weeklyLower,  $weeklyUpper,  $weeklyFitted,
                    $monthlyLabels, $monthlyActual, $monthlyForecast, $monthlyLower, $monthlyUpper, $monthlyFitted,
                    $yearlyLabels,  $yearlyActual,  $yearlyForecast,  $yearlyLower,  $yearlyUpper,  $yearlyFitted
                );

            } else {
                Log::warning('[USER FALLBACK] Flask tidak tersedia, menggunakan kalkulasi PHP.');

                $forecastDays = $forecastWeeks * 30;

                [$forecastDates, $forecastPrices, $forecastLowers, $forecastUppers] =
                    $this->simpleForecast($dates, $prices, $forecastDays);

                [$mape, $rSquared] = $this->calculateMetricsFallback($prices, $dates);

                $this->aggregateWeeklyData(
                    $dates, $prices,
                    $forecastDates, $forecastPrices, $forecastLowers, $forecastUppers,
                    $weeklyLabels, $weeklyActual, $weeklyForecast, $weeklyLower, $weeklyUpper
                );
                $this->aggregateMonthlyData(
                    $dates, $prices,
                    $forecastDates, $forecastPrices, $forecastLowers, $forecastUppers,
                    $monthlyLabels, $monthlyActual, $monthlyForecast, $monthlyLower, $monthlyUpper
                );
                $this->aggregateYearlyData(
                    $dates, $prices,
                    $forecastDates, $forecastPrices, $forecastLowers, $forecastUppers,
                    $yearlyLabels, $yearlyActual, $yearlyForecast, $yearlyLower, $yearlyUpper
                );

                // Fallback: fitted = actual (tidak ada Prophet fitted values)
                $weeklyFitted  = $weeklyActual;
                $monthlyFitted = $monthlyActual;
                $yearlyFitted  = $yearlyActual;

                $lastActual   = collect($monthlyActual)->filter()->last();
                $lastForecast = collect($monthlyForecast)->filter()->last();
                if ($lastForecast && $lastActual) {
                    if ($lastForecast > $lastActual * 1.01)     $trendDir = 'Naik';
                    elseif ($lastForecast < $lastActual * 0.99) $trendDir = 'Turun';
                }
            }

        } else {
            Log::warning('[USER] Data tidak cukup untuk komoditas_id=' . $selectedKomoditasId
                . ' (count=' . count($prices) . ')');

            $countData = count($prices);
            $avgPrice  = count($prices) > 0 ? $prices[0] : 0;
            $maxPrice  = $avgPrice;
            $minPrice  = $avgPrice;
        }

        $rSquared = round($rSquared, 3);

        Log::info('[USER] Final → mape=' . $mape
            . ' | rSquared=' . $rSquared
            . ' | trendDir=' . $trendDir);

        return view('dashboard.users.index', compact(
            'allCommodities', 'selectedCommodity', 'selectedCommodityId',
            'startDate', 'endDate',
            'trendDir', 'avgPrice', 'maxPrice', 'minPrice', 'countData',
            'mape', 'rSquared',
            'cpScale', 'seasonScale', 'seasonalityMode',
            'weeklyActive', 'yearlyActive', 'forecastWeeks',
            'weeklyLabels',  'weeklyActual',  'weeklyForecast',  'weeklyLower',  'weeklyUpper',  'weeklyFitted',
            'monthlyLabels', 'monthlyActual', 'monthlyForecast', 'monthlyLower', 'monthlyUpper', 'monthlyFitted',
            'yearlyLabels',  'yearlyActual',  'yearlyForecast',  'yearlyLower',  'yearlyUpper',  'yearlyFitted'
        ));
    }

    // =========================================================
    // PANGGIL FLASK PROPHET API
    // FIX: frequency 'MS' (bulanan) dan periods langsung forecastWeeks
    //      — identik dengan AdminController
    // =========================================================

    private function callFlaskProphet(
        int    $komoditasId,
        int    $forecastWeeks,
        float  $cpScale,
        float  $seasonScale,
        string $seasonMode,
        bool   $weeklySeason,
        bool   $yearlySeason,
        string $startDate      = '',
        string $endDate        = '',
        bool   $forceRetrain   = false,
        bool   $isUserOverride = false
    ): ?array {
        try {
            $payload = [
                'commodity_id'            => $komoditasId,
                'periods'                 => $forecastWeeks,   // langsung bulan, bukan * 7
                'frequency'               => 'MS',             // bulanan, sama dengan admin
                'changepoint_prior_scale' => $cpScale,
                'seasonality_prior_scale' => $seasonScale,
                'seasonality_mode'        => $seasonMode,
                'weekly_seasonality'      => false,            // selalu false, sama dengan admin
                'yearly_seasonality'      => $yearlySeason,
                'force_retrain'           => $forceRetrain,
                'user_override'           => $isUserOverride,
            ];

            if ($startDate) $payload['start_date'] = $startDate;
            if ($endDate)   $payload['end_date']   = $endDate;

            Log::info('[USER FLASK] Payload ke Flask:', $payload);

            $dataCount = PriceData::where('komoditas_id', $komoditasId)
                ->whereBetween('tanggal', [
                    $startDate ?: '2000-01-01',
                    $endDate   ?: now()->format('Y-m-d'),
                ])
                ->where('harga', '>', 0)
                ->count();

            $needsLongTimeout = $forceRetrain || $isUserOverride;
            $dynamicTimeout   = $needsLongTimeout
                ? max(300, min(600, (int) ceil($dataCount / 50) * 20 + 60))
                : max(60,  min(180, (int) ceil($dataCount / 50) * 5  + 30));

            Log::info('[USER FLASK] data_count=' . $dataCount
                . ' | needs_long_timeout=' . ($needsLongTimeout ? 'true' : 'false')
                . ' | timeout=' . $dynamicTimeout . 's');

            $response = $this->flaskHttp()->timeout($dynamicTimeout)
                ->connectTimeout(10)
                ->post("{$this->flaskUrl}/api/forecast/predict-advanced", $payload);

            if (!$response->successful()) {
                Log::warning('[USER FLASK] HTTP ' . $response->status() . ': ' . $response->body());
                return null;
            }

            $data = $response->json();

            if (!($data['success'] ?? false)) {
                Log::warning('[USER FLASK] Error dari Flask: ' . ($data['message'] ?? 'unknown'));
                return null;
            }

            $modelMetrics = $data['data']['model_metrics'] ?? [];
            $predictions  = $data['data']['predictions']   ?? [];

            if (empty($predictions)) {
                Log::warning('[USER FLASK] Prediksi kosong dari Flask.');
                return null;
            }

            $coverage = $modelMetrics['coverage']  ?? 0.95;
            $mape     = $modelMetrics['mape']       ?? $modelMetrics['in_sample_mape'] ?? 0.0;

            Log::info('[USER FLASK] Berhasil: ' . count($predictions) . ' prediksi'
                . ' | MAPE=' . $mape
                . ' | coverage=' . $coverage
                . ' | user_override=' . ($isUserOverride ? 'true' : 'false'));

            return [
                'predictions'     => $predictions,
                'mape'            => round((float) $mape, 2),
                'r_squared'       => round(min(1.0, max(0.0, (float) ($modelMetrics['r_squared'] ?? $coverage))), 4),
                'trend_direction' => $modelMetrics['trend_direction'] ?? 'stable',
                'fitted_values'   => $data['data']['fitted_values'] ?? [],
                'metrics'         => $modelMetrics,
            ];

        } catch (\Illuminate\Http\Client\RequestException $e) {
            Log::warning('[USER FLASK] Timeout/error: ' . $e->getMessage());
            return null;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('[USER FLASK] Tidak bisa dihubungi: ' . $e->getMessage());
            return null;
        } catch (\Exception $e) {
            Log::error('[USER FLASK] Error tidak terduga: ' . $e->getMessage());
            return null;
        }
    }

    // =========================================================
    // BUILD CHART DARI HASIL PROPHET
    // =========================================================

    private function buildChartFromProphet(
        array $actualDates,
        array $actualPrices,
        array $predictions,
        array $fittedValues,
        &$weeklyLabels,  &$weeklyActual,  &$weeklyForecast,  &$weeklyLower,  &$weeklyUpper,  &$weeklyFitted,
        &$monthlyLabels, &$monthlyActual, &$monthlyForecast, &$monthlyLower, &$monthlyUpper, &$monthlyFitted,
        &$yearlyLabels,  &$yearlyActual,  &$yearlyForecast,  &$yearlyLower,  &$yearlyUpper,  &$yearlyFitted
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

        $fittedDates  = [];
        $fittedPrices = [];
        foreach ($fittedValues as $f) {
            $fittedDates[]  = Carbon::parse($f['date']);
            $fittedPrices[] = (int) round($f['fitted_price']);
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

        $weeklyFitted  = $this->aggregateFittedToLabels($fittedDates, $fittedPrices, $weeklyLabels,  $weeklyActual,  'week');
        $monthlyFitted = $this->aggregateFittedToLabels($fittedDates, $fittedPrices, $monthlyLabels, $monthlyActual, 'month');
        $yearlyFitted  = $this->aggregateFittedToLabels($fittedDates, $fittedPrices, $yearlyLabels,  $yearlyActual,  'year');
    }

    // =========================================================
    // AGGREGATE FITTED VALUES KE LABEL YANG SUDAH ADA
    // =========================================================

    private function aggregateFittedToLabels(
        array $fittedDates,
        array $fittedPrices,
        array $labels,
        array $actualAgg,
        string $granularity
    ): array {
        if (empty($fittedDates) || empty($labels)) {
            return array_fill(0, count($labels), null);
        }
        $grouped = [];
        foreach ($fittedDates as $i => $date) {
            $d = $date instanceof Carbon ? $date : Carbon::parse($date);
            switch ($granularity) {
                case 'week':
                    $key = $d->year . '-W' . str_pad($d->weekOfYear, 2, '0', STR_PAD_LEFT);
                    break;
                case 'month':
                    $key = $d->format('Y-m');
                    break;
                case 'year':
                    $key = (string) $d->year;
                    break;
                default:
                    $key = $d->format('Y-m');
            }
            $grouped[$key][] = $fittedPrices[$i] ?? null;
        }
        $result = [];
        foreach ($labels as $li => $label) {
            $actualVal = $actualAgg[$li] ?? null;
            if ($actualVal === null) {
                $result[] = null;
                continue;
            }
            $key = null;
            switch ($granularity) {
                case 'week':
                    if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})$/', $label, $m)) {
                        $d   = Carbon::createFromDate($m[3], $m[2], $m[1]);
                        $key = $d->year . '-W' . str_pad($d->weekOfYear, 2, '0', STR_PAD_LEFT);
                    }
                    break;
                case 'month':
                    try {
                        $d   = Carbon::createFromFormat('M Y', $label);
                        $key = $d->format('Y-m');
                    } catch (\Exception $e) {
                        $key = null;
                    }
                    break;
                case 'year':
                    if (preg_match('/(\d{4})/', $label, $m)) {
                        $key = $m[1];
                    }
                    break;
            }
            if ($key && isset($grouped[$key])) {
                $vals     = array_filter($grouped[$key], fn($v) => $v !== null);
                $result[] = count($vals) ? (int) round(array_sum($vals) / count($vals)) : null;
            } else {
                $result[] = null;
            }
        }
        return $result;
    }

    // =========================================================
    // AGGREGATION — Weekly
    // FIX: tambah $actualWeekKeys + continue agar forecast hanya
    //      mengisi periode yang TIDAK punya data aktual
    //      (identik dengan AdminController)
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
                    'label'          => $d->copy()->startOfWeek()->format('d/m')
                                      . ' - '
                                      . $d->copy()->endOfWeek()->format('d/m/Y'),
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

        // Kumpulkan key minggu yang punya data aktual
        $actualWeekKeys = [];
        foreach ($weekGroups as $key => $g) {
            if (!empty($g['actualPrices'])) {
                $actualWeekKeys[$key] = true;
            }
        }

        foreach ($forecastDates as $i => $date) {
            $d   = $date instanceof Carbon ? $date : Carbon::parse($date);
            $key = $d->year . '-W' . str_pad($d->weekOfYear, 2, '0', STR_PAD_LEFT);

            // Skip minggu yang sudah punya data aktual — forecast hanya untuk periode baru
            if (isset($actualWeekKeys[$key])) continue;

            if (!isset($weekGroups[$key])) {
                $weekGroups[$key] = [
                    'label'          => $d->copy()->startOfWeek()->format('d/m')
                                      . ' - '
                                      . $d->copy()->endOfWeek()->format('d/m/Y'),
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
    // FIX: tambah $actualMonthKeys + continue agar forecast hanya
    //      mengisi periode yang TIDAK punya data aktual
    //      (identik dengan AdminController)
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

        // Kumpulkan key bulan yang punya data aktual
        $actualMonthKeys = [];
        foreach ($monthGroups as $key => $g) {
            if (!empty($g['actualPrices'])) {
                $actualMonthKeys[$key] = true;
            }
        }

        foreach ($forecastDates as $i => $date) {
            $d   = $date instanceof Carbon ? $date : Carbon::parse($date);
            $key = $d->format('Y-m');

            // Skip bulan yang sudah punya data aktual — forecast hanya untuk periode baru
            if (isset($actualMonthKeys[$key])) continue;

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
    // FIX: tambah $actualYearKeys + continue agar forecast hanya
    //      mengisi periode yang TIDAK punya data aktual
    //      (identik dengan AdminController)
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

        // Kumpulkan key tahun yang punya data aktual
        $actualYearKeys = [];
        foreach ($yearGroups as $key => $g) {
            if (!empty($g['actualPrices'])) {
                $actualYearKeys[$key] = true;
            }
        }

        foreach ($forecastDates as $i => $date) {
            $d   = $date instanceof Carbon ? $date : Carbon::parse($date);
            $key = (string) $d->year;

            // Skip tahun yang sudah punya data aktual — forecast hanya untuk periode baru
            if (isset($actualYearKeys[$key])) continue;

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
            $sumX  += $i;
            $sumY  += $trendSlice[$i];
            $sumXY += $i * $trendSlice[$i];
            $sumX2 += $i * $i;
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

        $forecastDates  = [];
        $forecastPrices = [];
        $forecastLowers = [];
        $forecastUppers = [];

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
            $ssRes    += pow($testPrices[$i] - $predicted,  2);
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
}