<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\PriceData;
use App\Models\MasterKomoditas;
use App\Models\UserPreference;
use App\Http\Traits\SavesUserPreferences;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class AdminController extends Controller
{
    use SavesUserPreferences;

    private string $flaskUrl;

    private const DEFAULT_CP              = 0.1;
    private const DEFAULT_SS              = 10.0;
    private const DEFAULT_MODE            = 'additive';
    private const DEFAULT_YEARLY          = true;
    private const DEFAULT_FORECAST_MONTHS = 12;

    public function __construct()
    {
        $this->flaskUrl = rtrim(env('FLASK_URL', 'http://localhost:5000'), '/');
    }

    public function index(Request $request)
    {
        
            return $this->predict($request);

    }

    public function beranda()
    {
        return redirect()->route('laporan.komoditas.index');
    }

    public function predict(Request $request)
{
    $currentTab = $request->query('tab', $request->input('tab', 'insight'));

    // Tab manage dan users TIDAK perlu Prophet sama sekali
    if ($currentTab === 'manage') {
        return $this->processManageTab($request);
    }

    if ($currentTab === 'users') {
        return $this->processUsersTab($request);
    }

    // Hanya tab insight yang panggil Flask Prophet
    set_time_limit(660);
    return $this->processForecasting($request);
}

    private function processManageTab(Request $request)
{
    $role     = 'admin';
    $username = auth()->user()->name  ?? 'Administrator BPS';
    $email    = auth()->user()->email ?? 'admin_riau@bps.go.id';
    $userId   = auth()->id();

    $currentTab = 'manage';

    try {
        $commodities = MasterKomoditas::orderBy('nama_komoditas')->get();
    } catch (\Exception $e) {
        $commodities = collect();
    }

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

    // Ambil data untuk tab manage
    try {
        $latestData = PriceData::with('komoditas')
            ->where('komoditas_id', $selectedKomoditasId)
            ->orderBy('tanggal', 'desc')
            ->paginate(10, ['*'], 'dataPage')
            ->withQueryString();
    } catch (\Exception $e) {
        $latestData = new \Illuminate\Pagination\LengthAwarePaginator(
            collect(), 0, 10, 1,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }

    try {
        $dataIssues = $this->scanDataQualityPaginated($selectedKomoditasId, $request);
    } catch (\Exception $e) {
        $dataIssues = collect();
    }

    try {
        $bobotList = DB::table('bobot_komoditas')
            ->join('master_komoditas', 'bobot_komoditas.komoditas_id', '=', 'master_komoditas.id')
            ->select(
                'bobot_komoditas.id',
                'bobot_komoditas.komoditas_id',
                'bobot_komoditas.tanggal',
                'bobot_komoditas.nilai_bobot',
                'bobot_komoditas.created_at',
                DB::raw("CONCAT(master_komoditas.nama_komoditas, IFNULL(CONCAT(' ', master_komoditas.nama_varian), '')) as nama_komoditas")
            )
            ->orderBy('bobot_komoditas.tanggal', 'desc')
            ->orderBy('master_komoditas.nama_komoditas', 'asc')
            ->paginate(10, ['*'], 'bobotPage')
            ->withQueryString();
    } catch (\Exception $e) {
        $bobotList = collect();
    }

    // Nilai default untuk variabel yang dibutuhkan view
    $startDate = Carbon::now()->subYear()->format('Y-m-d');
    $endDate   = Carbon::now()->format('Y-m-d');

    return view('admin_dashboard', compact(
        'role', 'username', 'email',
        'currentTab',
        'commodities', 'selectedCommodity', 'selectedKomoditasId',
        'latestData', 'dataIssues', 'bobotList',
        'startDate', 'endDate',
    ) + [
        // Nilai kosong untuk variabel chart yang dibutuhkan view
        'users'            => collect(),
        'allData'          => collect(),
        'trendDir'         => 'Stabil',
        'avgPrice'         => 0,
        'maxPrice'         => 0,
        'minPrice'         => 0,
        'countData'        => 0,
        'mape'             => 0,
        'rSquared'         => 0,
        'forecastMonths'   => 12,
        'cpScale'          => 0.1,
        'seasonScale'      => 10.0,
        'seasonMode'       => 'additive',
        'seasonalityMode'  => 'additive',
        'yearlySeason'     => true,
        'weeklySeason'     => false,
        'inSampleMape'     => 0,
        'intervalWidth'    => 0,
        'changepointCount' => 0,
        'seasonalityStrength' => 0,
        'trendFlexibility' => 0,
        'weeklyLabels'     => [], 'weeklyActual'   => [], 'weeklyForecast'  => [],
        'weeklyFitted'     => [], 'weeklyLower'    => [], 'weeklyUpper'     => [],
        'monthlyLabels'    => [], 'monthlyActual'  => [], 'monthlyForecast' => [],
        'monthlyFitted'    => [], 'monthlyLower'   => [], 'monthlyUpper'    => [],
        'yearlyLabels'     => [], 'yearlyActual'   => [], 'yearlyForecast'  => [],
        'yearlyFitted'     => [], 'yearlyLower'    => [], 'yearlyUpper'     => [],
        'actualData'       => [],
    ]);
}

private function processUsersTab(Request $request)
{
    $role     = 'admin';
    $username = auth()->user()->name  ?? 'Administrator BPS';
    $email    = auth()->user()->email ?? 'admin_riau@bps.go.id';

    $currentTab = 'users';

    try {
        $commodities = MasterKomoditas::orderBy('nama_komoditas')->get();
    } catch (\Exception $e) {
        $commodities = collect();
    }

    $selectedKomoditasId = optional($commodities->first())->id ?? 0;
    $selectedCommodity   = '-';

    $users = User::orderBy('created_at', 'desc')
        ->paginate(10)
        ->withQueryString();

    $startDate = Carbon::now()->subYear()->format('Y-m-d');
    $endDate   = Carbon::now()->format('Y-m-d');

    return view('admin_dashboard', compact(
        'role', 'username', 'email',
        'currentTab',
        'commodities', 'selectedCommodity', 'selectedKomoditasId',
        'users',
        'startDate', 'endDate',
    ) + [
        'allData'          => collect(),
        'latestData'       => collect(),
        'dataIssues'       => collect(),
        'bobotList'        => collect(),
        'trendDir'         => 'Stabil',
        'avgPrice'         => 0,
        'maxPrice'         => 0,
        'minPrice'         => 0,
        'countData'        => 0,
        'mape'             => 0,
        'rSquared'         => 0,
        'forecastMonths'   => 12,
        'cpScale'          => 0.1,
        'seasonScale'      => 10.0,
        'seasonMode'       => 'additive',
        'seasonalityMode'  => 'additive',
        'yearlySeason'     => true,
        'weeklySeason'     => false,
        'inSampleMape'     => 0,
        'intervalWidth'    => 0,
        'changepointCount' => 0,
        'seasonalityStrength' => 0,
        'trendFlexibility' => 0,
        'weeklyLabels'     => [], 'weeklyActual'   => [], 'weeklyForecast'  => [],
        'weeklyFitted'     => [], 'weeklyLower'    => [], 'weeklyUpper'     => [],
        'monthlyLabels'    => [], 'monthlyActual'  => [], 'monthlyForecast' => [],
        'monthlyFitted'    => [], 'monthlyLower'   => [], 'monthlyUpper'    => [],
        'yearlyLabels'     => [], 'yearlyActual'   => [], 'yearlyForecast'  => [],
        'yearlyFitted'     => [], 'yearlyLower'    => [], 'yearlyUpper'     => [],
        'actualData'       => [],
    ]);
}

    // =========================================================
    // MAIN FORECASTING PROCESSOR
    // =========================================================
    private function processForecasting(Request $request)
    {
        $role     = 'admin';
        $username = auth()->user()->name  ?? 'Administrator BPS';
        $email    = auth()->user()->email ?? 'admin_riau@bps.go.id';
        $userId   = auth()->id();

        $currentTab = $request->query('tab', $request->input('tab', 'insight'));

        try {
            $commodities = MasterKomoditas::orderBy('nama_komoditas')->get();
        } catch (\Exception $e) {
            Log::error('[ADMIN] Gagal ambil master_komoditas: ' . $e->getMessage());
            $commodities = collect();
        }

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

        try {
            $dateRange = PriceData::where('komoditas_id', $selectedKomoditasId)
                ->whereNotNull('harga')
                ->where('harga', '>', 0)
                ->selectRaw('MIN(tanggal) as min_date, MAX(tanggal) as max_date')
                ->first();

            $dbMinDate = $dateRange->min_date ?? '2020-01-01';
            $dbMaxDate = $dateRange->max_date ?? Carbon::now()->format('Y-m-d');
        } catch (\Exception $e) {
            Log::warning('[ADMIN] Gagal auto-detect date range: ' . $e->getMessage());
            $dbMinDate = '2020-01-01';
            $dbMaxDate = Carbon::now()->format('Y-m-d');
        }

        $prefs  = $this->loadUserPreferences($userId);
        $params = $this->resolveParameters($request, $prefs);

        if ($request->isMethod('POST') && $currentTab === 'insight') {
            $this->persistUserPreferences($userId, $request->all());
        }

        $forecastMonths = max(1, min(24, (int) (
            $request->input('forecast_months')
            ?? $params['forecastMonths']
            ?? self::DEFAULT_FORECAST_MONTHS
        )));
        $cpScale         = $params['cpScale'];
        $seasonScale     = $params['seasonScale'];
        $seasonMode      = $params['seasonMode'];
        $weeklySeason    = false;
        $yearlySeason    = $params['yearlySeason'];
        $seasonalityMode = $seasonMode;

        $forceRetrain = $this->parseBoolFromString(
            $request->input('force_retrain', 'false')
        );

        $isUserOverride = (
            (float) $cpScale     !== (float) self::DEFAULT_CP   ||
            (float) $seasonScale !== (float) self::DEFAULT_SS   ||
            $seasonMode          !== self::DEFAULT_MODE          ||
            (bool)  $yearlySeason !== self::DEFAULT_YEARLY
        );

        $startDate = ($params['startDate'] && $params['startDate'] >= $dbMinDate)
            ? $params['startDate']
            : $dbMinDate;

        $queryEndDate = ($params['endDate'] && $params['endDate'] <= $dbMaxDate)
            ? $params['endDate']
            : $dbMaxDate;

        $endDate = $queryEndDate;

        if ($startDate > $endDate) {
            $startDate    = $dbMinDate;
            $endDate      = $queryEndDate;
        }

        $users      = collect();
        $allData    = collect();
        $latestData = collect();
        $dataIssues = collect();
        $actualData = [];

        // ── Data bobot untuk tab manage ────────────────────────
        $bobotList = collect();

        $mape                = 0.0;
        $rSquared            = 0.0;
        $trendDir            = 'Stabil';
        $inSampleMape        = 0.0;
        $intervalWidth       = 0.0;
        $changepointCount    = 0;
        $seasonalityStrength = 0.0;
        $trendFlexibility    = 0.0;

        $avgPrice  = 0;
        $maxPrice  = 0;
        $minPrice  = 0;
        $countData = 0;

        $weeklyLabels  = []; $weeklyActual  = []; $weeklyForecast  = []; $weeklyFitted  = []; $weeklyLower  = []; $weeklyUpper  = [];
        $monthlyLabels = []; $monthlyActual = []; $monthlyForecast = []; $monthlyFitted = []; $monthlyLower = []; $monthlyUpper = [];
        $yearlyLabels  = []; $yearlyActual  = []; $yearlyForecast  = []; $yearlyFitted  = []; $yearlyLower  = []; $yearlyUpper  = [];

        if ($currentTab === 'users') {
            $users = User::orderBy('created_at', 'desc')
                ->paginate(10)
                ->withQueryString();
        }

        if ($currentTab === 'manage' && $selectedKomoditasId) {
            try {
                $latestData = PriceData::with('komoditas')
                    ->where('komoditas_id', $selectedKomoditasId)
                    ->orderBy('tanggal', 'desc')
                    ->paginate(10, ['*'], 'dataPage')
                    ->withQueryString();
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

            // ── Ambil data bobot semua komoditas (paginate) ────
            try {
                $bobotList = DB::table('bobot_komoditas')
                    ->join('master_komoditas', 'bobot_komoditas.komoditas_id', '=', 'master_komoditas.id')
                    ->select(
                        'bobot_komoditas.id',
                        'bobot_komoditas.komoditas_id',
                        'bobot_komoditas.tanggal',
                        'bobot_komoditas.nilai_bobot',
                        'bobot_komoditas.created_at',
                        DB::raw("CONCAT(master_komoditas.nama_komoditas, IFNULL(CONCAT(' ', master_komoditas.nama_varian), '')) as nama_komoditas")
                    )
                    ->orderBy('bobot_komoditas.tanggal', 'desc')
                    ->orderBy('master_komoditas.nama_komoditas', 'asc')
                    ->paginate(10, ['*'], 'bobotPage')
                    ->withQueryString();
            } catch (\Exception $e) {
                Log::error('[ADMIN] bobotList error: ' . $e->getMessage());
            }
        }

        $prices = [];
        $dates  = [];

        try {
            $dbData = PriceData::where('komoditas_id', $selectedKomoditasId)
                ->whereBetween('tanggal', [$startDate, $queryEndDate])
                ->whereNotNull('harga')
                ->where('harga', '>', 0)
                ->orderBy('tanggal', 'asc')
                ->get();

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

        if (count($prices) >= 2) {

            $actualData = $prices;
            $countData  = count($prices);
            $avgPrice   = array_sum($prices) / $countData;
            $maxPrice   = max($prices);
            $minPrice   = min($prices);

            $flaskResult = null;
            if ($countData >= 10) {
                $flaskResult = $this->callFlaskProphet(
                    $selectedKomoditasId,
                    $forecastMonths,
                    $cpScale,
                    $seasonScale,
                    $seasonMode,
                    $weeklySeason,
                    $yearlySeason,
                    $startDate,
                    $queryEndDate,
                    $forceRetrain,
                    $isUserOverride
                );
            }

            if ($flaskResult !== null) {
                $flaskMetrics = $flaskResult['metrics'];

                $mape     = $flaskResult['mape'];
                $rSquared = $flaskResult['r_squared'];
                $trendDir = match($flaskResult['trend_direction']) {
                    'increasing' => 'Naik',
                    'decreasing' => 'Turun',
                    default      => 'Stabil',
                };

                $inSampleMape        = round((float) ($flaskMetrics['in_sample_mape']        ?? 0), 2);
                $intervalWidth       = round((float) ($flaskMetrics['future_interval_width']
                                                   ?? $flaskMetrics['avg_interval_width']     ?? 0), 0);
                $changepointCount    = (int)           ($flaskMetrics['changepoint_count']     ?? 0);
                $seasonalityStrength = round((float) ($flaskMetrics['seasonality_strength']  ?? 0), 2);
                $trendFlexibility    = round((float) ($flaskMetrics['trend_flexibility']     ?? 0), 6);

                $this->buildChartFromProphet(
                    $dates, $prices,
                    $flaskResult['predictions'],
                    $flaskResult['fitted_values'],
                    $weeklyLabels,  $weeklyActual,  $weeklyForecast,  $weeklyFitted,  $weeklyLower,  $weeklyUpper,
                    $monthlyLabels, $monthlyActual, $monthlyForecast, $monthlyFitted, $monthlyLower, $monthlyUpper,
                    $yearlyLabels,  $yearlyActual,  $yearlyForecast,  $yearlyFitted,  $yearlyLower,  $yearlyUpper
                );

            } else {
                Log::warning("[ADMIN FALLBACK] Flask tidak tersedia, menggunakan kalkulasi PHP");

                $forecastDaysForFallback = $forecastMonths * 30;

                [$forecastDates, $forecastPrices, $forecastLowers, $forecastUppers] =
                    $this->simpleForecast($dates, $prices, $forecastDaysForFallback);

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
            $countData = count($prices);
            $avgPrice  = count($prices) > 0 ? $prices[0] : 0;
            $maxPrice  = $avgPrice;
            $minPrice  = $avgPrice;
        }

        $rSquared = round($rSquared, 3);

        return view('admin_dashboard', compact(
            'role', 'username', 'email',
            'currentTab',
            'commodities', 'selectedCommodity', 'selectedKomoditasId',
            'users',
            'allData', 'latestData', 'dataIssues',
            'bobotList',
            'startDate', 'endDate',
            'trendDir', 'avgPrice', 'maxPrice', 'minPrice',
            'cpScale', 'seasonScale', 'seasonalityMode', 'seasonMode',
            'weeklySeason', 'yearlySeason', 'forecastMonths',
            'mape', 'rSquared',
            'inSampleMape', 'intervalWidth', 'changepointCount',
            'seasonalityStrength', 'trendFlexibility',
            'weeklyLabels',  'weeklyActual',  'weeklyForecast',  'weeklyFitted',  'weeklyLower',  'weeklyUpper',
            'monthlyLabels', 'monthlyActual', 'monthlyForecast', 'monthlyFitted', 'monthlyLower', 'monthlyUpper',
            'yearlyLabels',  'yearlyActual',  'yearlyForecast',  'yearlyFitted',  'yearlyLower',  'yearlyUpper',
            'actualData', 'countData'
        ));
    }

    // =========================================================
    // BOBOT KOMODITAS — CRUD
    // =========================================================

    /**
     * Simpan bobot baru ke tabel bobot_komoditas
     */
    public function storeBobot(Request $request)
    {
        $request->validate([
            'komoditas_id' => 'required|exists:master_komoditas,id',
            'tanggal'      => 'required|date',
            'nilai_bobot'  => 'required|numeric|min:0',
        ]);

        try {
            // Cek apakah sudah ada bobot untuk komoditas + tanggal yang sama
            $exists = DB::table('bobot_komoditas')
                ->where('komoditas_id', $request->komoditas_id)
                ->where('tanggal', $request->tanggal)
                ->exists();

            if ($exists) {
                return redirect()
                    ->route('admin.predict', ['tab' => 'manage', 'komoditas_id' => $request->komoditas_id])
                    ->with('error', 'Bobot untuk komoditas dan tanggal ini sudah ada.');
            }

            DB::table('bobot_komoditas')->insert([
                'komoditas_id' => $request->komoditas_id,
                'tanggal'      => $request->tanggal,
                'nilai_bobot'  => $request->nilai_bobot,
                'created_at'   => now(),
            ]);

            return redirect()
                ->route('admin.predict', ['tab' => 'manage', 'komoditas_id' => $request->komoditas_id])
                ->with('success', 'Bobot komoditas berhasil disimpan!');

        } catch (\Exception $e) {
            Log::error('[ADMIN] Store Bobot Error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Gagal menyimpan bobot: ' . $e->getMessage());
        }
    }

    /**
     * Update bobot via AJAX (inline edit)
     */
    public function updateBobot(Request $request, $id)
    {
        $request->validate([
            'komoditas_id' => 'required|exists:master_komoditas,id',
            'tanggal'      => 'required|date',
            'nilai_bobot'  => 'required|numeric|min:0',
        ]);

        try {
            $affected = DB::table('bobot_komoditas')
                ->where('id', $id)
                ->update([
                    'komoditas_id' => $request->komoditas_id,
                    'tanggal'      => $request->tanggal,
                    'nilai_bobot'  => $request->nilai_bobot,
                ]);

            if ($affected === 0) {
                return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
            }

            return response()->json(['success' => true, 'message' => 'Bobot berhasil diperbarui!']);

        } catch (\Exception $e) {
            Log::error('[ADMIN] Update Bobot Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Hapus bobot
     */
    public function deleteBobot($id)
    {
        try {
            DB::table('bobot_komoditas')->where('id', $id)->delete();

            return redirect()
                ->route('admin.predict', ['tab' => 'manage'])
                ->with('success', 'Bobot berhasil dihapus!');

        } catch (\Exception $e) {
            Log::error('[ADMIN] Delete Bobot Error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Gagal menghapus bobot.');
        }
    }

    // =========================================================
    // PANGGIL FLASK PROPHET API
    // =========================================================
    private function callFlaskProphet(
        int    $komoditasId,
        int    $forecastMonths,
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
                'periods'                 => $forecastMonths,
                'frequency'               => 'MS',
                'changepoint_prior_scale' => $cpScale,
                'seasonality_prior_scale' => $seasonScale,
                'seasonality_mode'        => $seasonMode,
                'weekly_seasonality'      => false,
                'yearly_seasonality'      => $yearlySeason,
                'force_retrain'           => $forceRetrain,
                'user_override'           => $isUserOverride,
            ];

            if ($startDate) $payload['start_date'] = $startDate;
            if ($endDate)   $payload['end_date']   = $endDate;

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

            $response = Http::timeout($dynamicTimeout)
                ->connectTimeout(10)
                ->post("{$this->flaskUrl}/api/forecast/predict-advanced", $payload);

            if (!$response->successful()) {
                Log::warning("[ADMIN FLASK] HTTP {$response->status()}: " . $response->body());
                return null;
            }

            $data = $response->json();

            if (!($data['success'] ?? false)) {
                Log::warning("[ADMIN FLASK] Error dari Flask: " . ($data['message'] ?? 'unknown'));
                return null;
            }

            $modelMetrics = $data['data']['model_metrics'] ?? [];
            $predictions  = $data['data']['predictions']   ?? [];
            $fittedValues = $data['data']['fitted_values']  ?? [];

            if (empty($predictions)) {
                Log::warning("[ADMIN FLASK] Prediksi kosong dari Flask");
                return null;
            }

            $coverage = $modelMetrics['coverage']  ?? 0.95;
            $mape     = $modelMetrics['mape']       ?? $modelMetrics['in_sample_mape'] ?? 0.0;

            return [
                'predictions'     => $predictions,
                'fitted_values'   => $fittedValues,
                'mape'            => round((float) $mape, 2),
                'r_squared'       => round(min(1.0, max(0.0, (float) $coverage)), 4),
                'trend_direction' => $modelMetrics['trend_direction'] ?? 'stable',
                'metrics'         => $modelMetrics,
            ];

        } catch (\Illuminate\Http\Client\RequestException $e) {
            Log::warning("[ADMIN FLASK] Request timeout/error: " . $e->getMessage());
            return null;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning("[ADMIN FLASK] Tidak bisa dihubungi: " . $e->getMessage());
            return null;
        } catch (\Exception $e) {
            Log::error("[ADMIN FLASK] Error tidak terduga: " . $e->getMessage());
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
        &$weeklyLabels,  &$weeklyActual,  &$weeklyForecast,  &$weeklyFitted,
        &$weeklyLower,   &$weeklyUpper,
        &$monthlyLabels, &$monthlyActual, &$monthlyForecast, &$monthlyFitted,
        &$monthlyLower,  &$monthlyUpper,
        &$yearlyLabels,  &$yearlyActual,  &$yearlyForecast,  &$yearlyFitted,
        &$yearlyLower,   &$yearlyUpper
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

            if ($key !== null && isset($grouped[$key])) {
                $vals     = array_filter($grouped[$key], fn($v) => $v !== null);
                $result[] = count($vals) > 0
                    ? (int) round(array_sum($vals) / count($vals))
                    : null;
            } else {
                $result[] = null;
            }
        }

        return $result;
    }

    // =========================================================
    // AGGREGATION — Weekly
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

        $actualWeekKeys = [];
        foreach ($weekGroups as $key => $g) {
            if (!empty($g['actualPrices'])) {
                $actualWeekKeys[$key] = true;
            }
        }

        foreach ($forecastDates as $i => $date) {
            $d   = $date instanceof Carbon ? $date : Carbon::parse($date);
            $key = $d->year . '-W' . str_pad($d->weekOfYear, 2, '0', STR_PAD_LEFT);

            if (isset($actualWeekKeys[$key])) continue;

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

        $actualMonthKeys = [];
        foreach ($monthGroups as $key => $g) {
            if (!empty($g['actualPrices'])) {
                $actualMonthKeys[$key] = true;
            }
        }

        foreach ($forecastDates as $i => $date) {
            $d   = $date instanceof Carbon ? $date : Carbon::parse($date);
            $key = $d->format('Y-m');

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

        $actualYearKeys = [];
        foreach ($yearGroups as $key => $g) {
            if (!empty($g['actualPrices'])) {
                $actualYearKeys[$key] = true;
            }
        }

        foreach ($forecastDates as $i => $date) {
            $d   = $date instanceof Carbon ? $date : Carbon::parse($date);
            $key = (string) $d->year;

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
                $issues[] = (object)[
                    'id'     => $item->id,
                    'date'   => $item->tanggal,
                    'issue'  => 'Missing Value',
                    'value'  => 0,
                    'status' => 'Perlu Diisi',
                ];
            } elseif ($item->harga < ($q1 - 1.5 * $iqr) || $item->harga > ($q3 + 1.5 * $iqr)) {
                $issues[] = (object)[
                    'id'     => $item->id,
                    'date'   => $item->tanggal,
                    'issue'  => 'Outlier',
                    'value'  => $item->harga,
                    'status' => $item->harga > ($q3 + 1.5 * $iqr) ? 'Terlalu Tinggi' : 'Terlalu Rendah',
                ];
            }
        }

        $issuesCollection = collect($issues);
        $perPage          = 8;
        $currentPage      = (int) $request->input('issuePage', 1);
        $currentItems     = $issuesCollection->slice(($currentPage - 1) * $perPage, $perPage)->values();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $currentItems,
            $issuesCollection->count(),
            $perPage,
            $currentPage,
            [
                'path'     => $request->url(),
                'query'    => array_merge($request->query(), ['tab' => 'manage']),
                'pageName' => 'issuePage',
            ]
        );
    }

    // =========================================================
    // DATA MANAGEMENT (CRUD)
    // =========================================================
    public function storeData(Request $request)
    {
        try {
            if ($request->hasFile('dataset')) {
                return redirect()->route('admin.manajemen-data.upload-csv');
            }

            $request->validate([
                'komoditas_id' => 'required|exists:master_komoditas,id',
                'date'         => 'required|date|before_or_equal:today',
                'price'        => 'required|numeric|min:1',
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
            'date'         => 'required|date|before_or_equal:today',
            'price'        => 'required|numeric|min:1',
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
                ->where('harga', '>', 0)
                ->pluck('harga')
                ->map(fn($h) => (float) $h)
                ->toArray();

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
                    ->where(fn($q) => $q
                        ->where('harga', '<', $q1 - 1.5 * $iqr)
                        ->orWhere('harga', '>', $q3 + 1.5 * $iqr)
                    );
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
        $headers  = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="template_data_komoditas.csv"',
        ];
        $columns  = ['nama_komoditas', 'nama_varian', 'tanggal', 'harga'];
        $samples  = [
            ['Beras',  'Premium', '2023-01-06', '14500'],
            ['Beras',  'Medium',  '2023-01-06', '13000'],
            ['Cabai',  'Merah',   '2023-01-06', '35000'],
        ];
        $callback = function () use ($columns, $samples) {
            $f = fopen('php://output', 'w');
            fputcsv($f, $columns);
            foreach ($samples as $row) fputcsv($f, $row);
            fclose($f);
        };
        return response()->stream($callback, 200, $headers);
    }

    // =========================================================
    // USER MANAGEMENT
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
            return redirect()
                ->route('admin.predict', ['tab' => 'users'])
                ->with('success', 'Pengguna berhasil dibuat!');
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
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak dapat mengubah role Anda sendiri!',
                ], 403);
            }
            if (User::where('email', $request->email)->where('id', '!=', $id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email sudah digunakan!',
                ], 422);
            }
            User::findOrFail($id)->update([
                'name'  => $request->name,
                'email' => $request->email,
                'role'  => $request->role,
            ]);
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
            return redirect()
                ->route('admin.predict', ['tab' => 'users'])
                ->with('success', 'Pengguna berhasil dihapus!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal menghapus pengguna.');
        }
    }

    // =========================================================
    // FLASK CACHE MANAGEMENT
    // =========================================================
    public function clearModelCache(Request $request, $id)
    {
        try {
            $forceRetrain = $this->parseBoolFromString($request->input('force_retrain', 'false'));

            $response = Http::timeout(15)
                ->connectTimeout(5)
                ->delete("{$this->flaskUrl}/api/forecast/clear-cache/{$id}");

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => "Flask mengembalikan status {$response->status()}",
                ], 502);
            }

            $data = $response->json();

            if ($forceRetrain && ($data['success'] ?? false)) {
                $this->triggerForceRetrain((int) $id);
            }

            return response()->json([
                'success' => $data['success'] ?? true,
                'message' => $data['message'] ?? 'Cache berhasil dihapus.',
                'deleted' => $data['deleted'] ?? true,
            ]);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Flask API tidak dapat dijangkau.',
            ], 503);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function clearModelCacheAll()
    {
        try {
            $response = Http::timeout(30)
                ->connectTimeout(5)
                ->delete("{$this->flaskUrl}/api/forecast/clear-cache-all");

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => "Flask mengembalikan status {$response->status()}",
                ], 502);
            }

            $data  = $response->json();
            $count = $data['deleted_count'] ?? 0;

            return response()->json([
                'success'       => true,
                'message'       => "{$count} cache model berhasil dihapus.",
                'deleted_count' => $count,
            ]);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return response()->json(['success' => false, 'message' => 'Flask API tidak dapat dijangkau.'], 503);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    public function flaskModelStatus()
    {
        try {
            $response = Http::timeout(15)
                ->connectTimeout(5)
                ->get("{$this->flaskUrl}/api/forecast/model-status");

            if (!$response->successful()) {
                return response()->json(['success' => false, 'message' => 'Flask tidak merespons', 'models' => []], 502);
            }

            $data   = $response->json();
            $models = $data['data'] ?? [];

            try {
                $masterKomoditas = MasterKomoditas::all()->keyBy('id');
                $models = array_map(function ($m) use ($masterKomoditas) {
                    $id  = $m['commodity_id'] ?? null;
                    $kom = $id ? $masterKomoditas->get($id) : null;
                    if ($kom) {
                        $m['commodity_name'] = $kom->display_name
                            ?? trim($kom->nama_komoditas . ' ' . ($kom->nama_varian ?? ''));
                    }
                    return $m;
                }, $models);
            } catch (\Exception $e) {
                Log::warning("[ADMIN CACHE] Gagal enrichment nama: " . $e->getMessage());
            }

            return response()->json(['success' => true, 'models' => $models, 'total' => count($models)]);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return response()->json(['success' => false, 'message' => 'Flask tidak dapat dijangkau', 'models' => []], 503);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'models' => []], 500);
        }
    }

    private function triggerForceRetrain(int $komoditasId): void
    {
        try {
            $dateRange = PriceData::where('komoditas_id', $komoditasId)
                ->whereNotNull('harga')
                ->where('harga', '>', 0)
                ->selectRaw('MIN(tanggal) as min_date, MAX(tanggal) as max_date')
                ->first();

            $payload = [
                'commodity_id'            => $komoditasId,
                'periods'                 => 12,
                'frequency'               => 'MS',
                'changepoint_prior_scale' => self::DEFAULT_CP,
                'seasonality_prior_scale' => self::DEFAULT_SS,
                'seasonality_mode'        => self::DEFAULT_MODE,
                'weekly_seasonality'      => false,
                'yearly_seasonality'      => self::DEFAULT_YEARLY,
                'force_retrain'           => true,
                'user_override'           => false,
            ];

            if ($dateRange) {
                if ($dateRange->min_date) $payload['start_date'] = $dateRange->min_date;
                if ($dateRange->max_date) $payload['end_date']   = $dateRange->max_date;
            }

            Http::timeout(3)->connectTimeout(2)
                ->post("{$this->flaskUrl}/api/forecast/predict-advanced", $payload);

        } catch (\Exception $e) {
            Log::info("[ADMIN CACHE] Trigger retrain dikirim: " . $e->getMessage());
        }
    }
}