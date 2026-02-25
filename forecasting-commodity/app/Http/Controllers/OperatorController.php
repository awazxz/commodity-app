<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PriceData;
use App\Models\MasterKomoditas;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class OperatorController extends Controller
{
    public function index(Request $request)
    {
        return $this->processForecasting($request);
    }

    public function predict(Request $request)
    {
        return $this->processForecasting($request);
    }

    private function processForecasting(Request $request)
    {
        // User Info
        $role     = 'operator';
        $username = auth()->user()->name  ?? 'Operator BPS';
        $email    = auth()->user()->email ?? 'operator_riau@bps.go.id';

        // Ambil semua komoditas dari DB
        $daftarKomoditas = MasterKomoditas::orderBy('nama_komoditas')->get();

        // Default: komoditas pertama di DB
        $defaultKomoditasId = $daftarKomoditas->isNotEmpty()
            ? $daftarKomoditas->first()->id
            : null;

        // Request parameters
        $currentTab = $request->query('tab', $request->input('tab', 'insight'));
        $startDate  = $request->input('start_date', Carbon::now()->subMonths(3)->format('Y-m-d'));
        $endDate    = $request->input('end_date', Carbon::now()->format('Y-m-d'));

        // Resolve commodity input → ID
        $commodityInput = $request->input('commodity');
        $selectedId     = $this->resolveCommodityId($commodityInput, $daftarKomoditas, $defaultKomoditasId);

        // Nama komoditas terpilih
        $selectedKomoditas = $daftarKomoditas->firstWhere('id', $selectedId);
        $selectedCommodity = $selectedKomoditas ? $selectedKomoditas->display_name : 'Komoditas';

        // Initialize variables
        $allData    = [];
        $latestData = collect();
        $actualData = [];
        $dataIssues = collect();

        $weeklyLabels  = []; $weeklyActual  = []; $weeklyForecast  = []; $weeklyLower  = []; $weeklyUpper  = [];
        $monthlyLabels = []; $monthlyActual = []; $monthlyForecast = []; $monthlyLower = []; $monthlyUpper = [];
        $yearlyLabels  = []; $yearlyActual  = []; $yearlyForecast  = []; $yearlyLower  = []; $yearlyUpper  = [];

        try {
            // Tab manage: data tabel + scan kualitas
            if ($currentTab === 'manage') {
                $latestData = PriceData::with('komoditas')
                    ->where('komoditas_id', $selectedId)
                    ->orderBy('tanggal', 'desc')
                    ->paginate(10);

                $dataIssues = $this->scanDataQualityPaginated($selectedId, $request);
            }

            // Query utama: data historis untuk chart
            $dbData = PriceData::where('komoditas_id', $selectedId)
                ->whereBetween('tanggal', [$startDate, $endDate])
                ->whereNotNull('harga')
                ->where('harga', '>', 0)
                ->where('status', 'cleaned')
                ->where('is_outlier', false)
                ->orderBy('tanggal', 'asc')
                ->get();

            if ($dbData->isNotEmpty()) {
                $dates  = $dbData->map(fn($r) => Carbon::parse($r->tanggal))->toArray();
                $prices = $dbData->pluck('harga')->map(fn($h) => (float) $h)->toArray();

                $lastDate  = end($dates);
                $lastPrice = end($prices);

                // Forecast sederhana 28 hari ke depan
                $forecastDates  = [];
                $forecastPrices = [];
                for ($i = 1; $i <= 28; $i++) {
                    $forecastDates[]  = Carbon::parse($lastDate)->addDays($i);
                    $forecastPrices[] = $lastPrice + ($i * rand(50, 150));
                }

                $allDates = array_merge($dates, $forecastDates);

                $this->aggregateWeeklyData($allDates,  $prices, $forecastPrices, $weeklyLabels,  $weeklyActual,  $weeklyForecast,  $weeklyLower,  $weeklyUpper);
                $this->aggregateMonthlyData($allDates, $prices, $forecastPrices, $monthlyLabels, $monthlyActual, $monthlyForecast, $monthlyLower, $monthlyUpper);
                $this->aggregateYearlyData($allDates,  $prices, $forecastPrices, $yearlyLabels,  $yearlyActual,  $yearlyForecast,  $yearlyLower,  $yearlyUpper);

                $actualData = $prices;

            } else {
                throw new \Exception("Tidak ada data cleaned untuk komoditas_id={$selectedId} periode {$startDate} s/d {$endDate}");
            }

        } catch (\Exception $e) {
            Log::warning("Operator fallback: " . $e->getMessage());

            $actualData     = [14200, 14350, 14250, 14400, 14600, 14500, 14750, 14800, 14900, 15000, 15100, 15200];
            $weeklyLabels   = ['Minggu 1','Minggu 2','Minggu 3','Minggu 4','Minggu 5','Minggu 6'];
            $weeklyActual   = [14250, 14500, 14750, 14900, 15100, 15200];
            $weeklyForecast = [15300, 15500, 15700, 15900, 16100, 16300];
            $weeklyLower    = array_map(fn($v) => round($v * 0.95), $weeklyForecast);
            $weeklyUpper    = array_map(fn($v) => round($v * 1.05), $weeklyForecast);
            $monthlyLabels   = ['Bulan 1','Bulan 2','Bulan 3'];
            $monthlyActual   = [14400, 14800, 15150];
            $monthlyForecast = [15600, 16000, 16400];
            $monthlyLower    = array_map(fn($v) => round($v * 0.97), $monthlyForecast);
            $monthlyUpper    = array_map(fn($v) => round($v * 1.03), $monthlyForecast);
            $yearlyLabels    = ['Tahun 1'];
            $yearlyActual    = [14800];
            $yearlyForecast  = [16000];
            $yearlyLower     = [15680];
            $yearlyUpper     = [16320];

            // Jangan buat fallback stdClass — biarkan $latestData kosong
            // agar blade menampilkan "Data tidak ditemukan"
        }

        // Statistics
        $countData = count($actualData);
        $avgPrice  = $countData > 0 ? array_sum($actualData) / $countData : 0;
        $maxPrice  = $countData > 0 ? max($actualData) : 0;

        $trendDir = 'Stabil';
        if (!empty($weeklyForecast) && !empty($weeklyActual)) {
            $lastActual   = end($weeklyActual);
            $lastForecast = end($weeklyForecast);
            if ($lastForecast > $lastActual * 1.02)     $trendDir = 'Naik';
            elseif ($lastForecast < $lastActual * 0.98) $trendDir = 'Turun';
        }

        // Prophet parameters
        $cpScale      = $request->input('changepoint_prior_scale', 0.05);
        $seasonScale  = $request->input('seasonality_prior_scale', 10);
        $seasonMode   = $request->input('seasonality_mode', 'multiplicative');
        $weeklySeason = $request->input('weekly_seasonality') === 'true';
        $yearlySeason = $request->input('yearly_seasonality') === 'true';

        // Model metrics (placeholder)
        $mape     = rand(2, 5) + (rand(0, 99) / 100);
        $rSquared = 0.85 + (rand(0, 10) / 100);

        return view('operator_dashboard', compact(
            'role', 'username', 'email', 'currentTab', 'allData', 'latestData', 'dataIssues',
            'selectedCommodity', 'selectedId', 'startDate', 'endDate', 'trendDir', 'actualData',
            'avgPrice', 'maxPrice', 'cpScale',
            'seasonScale', 'seasonMode', 'weeklySeason', 'yearlySeason', 'mape', 'rSquared',
            'weeklyLabels',  'weeklyActual',  'weeklyForecast',  'weeklyLower',  'weeklyUpper',
            'monthlyLabels', 'monthlyActual', 'monthlyForecast', 'monthlyLower', 'monthlyUpper',
            'yearlyLabels',  'yearlyActual',  'yearlyForecast',  'yearlyLower',  'yearlyUpper',
            'daftarKomoditas'
        ));
    }

    // ── Helper: Resolve commodity input → integer ID ──────────────────────────
    private function resolveCommodityId($input, $daftarKomoditas, $defaultId): ?int
    {
        if (empty($input)) {
            return $defaultId;
        }

        if (is_numeric($input)) {
            return (int) $input;
        }

        // Input adalah display_name string — cari ID yang cocok
        $matched = $daftarKomoditas->first(function ($k) use ($input) {
            return $k->display_name === trim($input);
        });

        return $matched ? $matched->id : $defaultId;
    }

    // ── Data Quality Scanner ──────────────────────────────────────────────────
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

        $prices = $data->pluck('harga')->filter()->map(fn($v) => (float) $v)->toArray();

        if (count($prices) < 4) {
            return new \Illuminate\Pagination\LengthAwarePaginator(
                collect([]), 0, 8, 1,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        }

        sort($prices);
        $q1         = $prices[floor(count($prices) * 0.25)];
        $q3         = $prices[floor(count($prices) * 0.75)];
        $iqr        = $q3 - $q1;
        $lowerBound = $q1 - (1.5 * $iqr);
        $upperBound = $q3 + (1.5 * $iqr);

        $issues = [];
        foreach ($data as $item) {
            $price = (float) $item->harga;
            if ($price <= 0) {
                $issues[] = (object)[
                    'date'   => Carbon::parse($item->tanggal)->format('Y-m-d'),
                    'issue'  => 'Missing Value',
                    'value'  => 0,
                    'status' => 'Perlu Diisi (Imputation)',
                ];
            } elseif ($price < $lowerBound || $price > $upperBound) {
                $issues[] = (object)[
                    'date'   => Carbon::parse($item->tanggal)->format('Y-m-d'),
                    'issue'  => 'Outlier',
                    'value'  => $price,
                    'status' => $price > $upperBound ? 'Terlalu Tinggi' : 'Terlalu Rendah',
                ];
            }
        }

        $col         = collect($issues);
        $perPage     = 8;
        $currentPage = $request->input('page', 1);

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $col->slice(($currentPage - 1) * $perPage, $perPage)->values(),
            $col->count(), $perPage, $currentPage,
            ['path' => $request->url(), 'query' => array_merge($request->query(), ['tab' => 'manage'])]
        );
    }

    // ── Clean Data ────────────────────────────────────────────────────────────
    public function cleanData(Request $request)
    {
        $request->validate([
            'action'    => 'required|in:outlier,missing',
            'commodity' => 'required',
        ]);

        try {
            $action = $request->input('action');
            $method = $request->input($action === 'outlier' ? 'outlier_method' : 'missing_method');

            // Resolve commodity (bisa ID integer atau display_name string)
            $daftarKomoditas = MasterKomoditas::all();
            $komoditasId     = $this->resolveCommodityId(
                $request->input('commodity'),
                $daftarKomoditas,
                null
            );

            if (!$komoditasId) {
                return redirect()->back()->with('error', 'Komoditas tidak ditemukan.');
            }

            $prices = PriceData::where('komoditas_id', $komoditasId)
                ->where('harga', '>', 0)
                ->pluck('harga')
                ->map(fn($v) => (float) $v)
                ->toArray();

            if (empty($prices)) return redirect()->back()->with('error', 'Data tidak mencukupi');

            sort($prices);
            $mean        = array_sum($prices) / count($prices);
            $median      = $prices[floor(count($prices) / 2)];
            $replacement = ($method === 'median') ? $median : $mean;
            $affected    = 0;

            if ($action === 'outlier') {
                $q1         = $prices[floor(count($prices) * 0.25)];
                $q3         = $prices[floor(count($prices) * 0.75)];
                $iqr        = $q3 - $q1;
                $lowerBound = $q1 - (1.5 * $iqr);
                $upperBound = $q3 + (1.5 * $iqr);

                $q = PriceData::where('komoditas_id', $komoditasId)
                    ->where(fn($q) => $q->where('harga', '<', $lowerBound)
                                        ->orWhere('harga', '>', $upperBound));
                $affected = $q->count();
                $method === 'remove' ? $q->delete() : $q->update(['harga' => $replacement, 'updated_at' => now()]);

            } else {
                $q = PriceData::where('komoditas_id', $komoditasId)
                    ->where(fn($q) => $q->whereNull('harga')->orWhere('harga', '<=', 0));
                $affected = $q->count();
                $method === 'remove' ? $q->delete() : $q->update(['harga' => $replacement, 'updated_at' => now()]);
            }

            $actionText = $action === 'outlier' ? 'Outlier' : 'Missing Values';
            $methodText = $method === 'remove'  ? 'dihapus' : 'diperbaiki';

            return redirect()->route('operator.predict', ['tab' => 'manage', 'commodity' => $komoditasId])
                ->with('success', "{$actionText} berhasil {$methodText}! {$affected} data diproses.");

        } catch (\Exception $e) {
            Log::error('Clean Data Error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Gagal membersihkan data: ' . $e->getMessage());
        }
    }

    // ── Store Data ────────────────────────────────────────────────────────────
    public function storeData(Request $request)
    {
        try {
            // ── Bulk Upload CSV ──
            if ($request->hasFile('dataset')) {
                $handle = fopen($request->file('dataset')->getRealPath(), 'r');
                fgetcsv($handle); // skip header
                $count = 0;
                while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                    if (count($row) >= 3) {
                        PriceData::updateOrCreate(
                            ['komoditas_id' => (int) $row[0], 'tanggal' => $row[1]],
                            ['harga' => $row[2], 'status' => 'cleaned', 'is_outlier' => false, 'updated_at' => now()]
                        );
                        $count++;
                    }
                }
                fclose($handle);
                return redirect()->route('operator.predict', ['tab' => 'manage'])
                    ->with('success', "Bulk upload berhasil! {$count} data diproses.");
            }

            // ── Input Manual ──
            $request->validate([
                'commodity' => 'required',
                'date'      => 'required|date',
                'price'     => 'required|numeric|min:1',
            ]);

            // Resolve commodity → integer ID
            $daftarKomoditas = MasterKomoditas::all();
            $komoditasId     = $this->resolveCommodityId(
                $request->input('commodity'),
                $daftarKomoditas,
                null
            );

            if (!$komoditasId) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Komoditas tidak ditemukan. Silakan pilih dari daftar.');
            }

            PriceData::create([
                'komoditas_id' => $komoditasId,
                'tanggal'      => $request->date,
                'harga'        => $request->price,
                'status'       => 'cleaned',
                'is_outlier'   => false,
            ]);

            return redirect()->route('operator.predict', ['tab' => 'manage', 'commodity' => $komoditasId])
                ->with('success', 'Data berhasil ditambahkan!');

        } catch (\Exception $e) {
            Log::error('Store Data Error: ' . $e->getMessage());
            return redirect()->back()->withInput()->with('error', 'Gagal menyimpan data: ' . $e->getMessage());
        }
    }

    // ── Update Data (AJAX) ────────────────────────────────────────────────────
    public function updateData(Request $request, $id)
    {
        try {
            $request->validate([
                'date'  => 'required|date',
                'price' => 'required|numeric|min:0',
            ]);

            PriceData::where('id', $id)->update([
                'tanggal'    => $request->date,
                'harga'      => $request->price,
                'updated_at' => now(),
            ]);

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error('Update Data Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ── Delete Data ───────────────────────────────────────────────────────────
    public function deleteData($id)
    {
        try {
            PriceData::findOrFail($id)->delete();
            return redirect()->route('operator.predict', ['tab' => 'manage'])
                ->with('success', 'Data berhasil dihapus!');
        } catch (\Exception $e) {
            Log::error('Delete Data Error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Gagal menghapus data.');
        }
    }

    // ── Aggregation Helpers ───────────────────────────────────────────────────
    private function aggregateWeeklyData($dates, $actualPrices, $forecastPrices, &$labels, &$actualAgg, &$forecastAgg, &$lower, &$upper)
    {
        $groups      = [];
        $actualCount = count($actualPrices);

        foreach ($dates as $index => $date) {
            $key = "{$date->year}-W{$date->weekOfYear}";
            if (!isset($groups[$key])) {
                $groups[$key] = ['label' => 'Minggu ' . $date->weekOfYear, 'actual' => [], 'forecast' => []];
            }
            if ($index < $actualCount) {
                $groups[$key]['actual'][] = $actualPrices[$index];
            }
            $fi = $index - $actualCount;
            if ($fi >= 0 && $fi < count($forecastPrices)) {
                $groups[$key]['forecast'][] = $forecastPrices[$fi];
            }
        }

        foreach ($groups as $g) {
            $labels[]    = $g['label'];
            $actualAgg[] = !empty($g['actual']) ? round(array_sum($g['actual']) / count($g['actual'])) : null;
            if (!empty($g['forecast'])) {
                $avg = array_sum($g['forecast']) / count($g['forecast']);
                $forecastAgg[] = round($avg); $lower[] = round($avg * 0.95); $upper[] = round($avg * 1.05);
            } else {
                $forecastAgg[] = null; $lower[] = null; $upper[] = null;
            }
        }
    }

    private function aggregateMonthlyData($dates, $actualPrices, $forecastPrices, &$labels, &$actualAgg, &$forecastAgg, &$lower, &$upper)
    {
        $groups      = [];
        $actualCount = count($actualPrices);

        foreach ($dates as $index => $date) {
            $key = $date->format('Y-m');
            if (!isset($groups[$key])) {
                $groups[$key] = ['label' => $date->translatedFormat('M Y'), 'actual' => [], 'forecast' => []];
            }
            if ($index < $actualCount) {
                $groups[$key]['actual'][] = $actualPrices[$index];
            }
            $fi = $index - $actualCount;
            if ($fi >= 0 && $fi < count($forecastPrices)) {
                $groups[$key]['forecast'][] = $forecastPrices[$fi];
            }
        }

        foreach ($groups as $g) {
            $labels[]    = $g['label'];
            $actualAgg[] = !empty($g['actual']) ? round(array_sum($g['actual']) / count($g['actual'])) : null;
            if (!empty($g['forecast'])) {
                $avg = array_sum($g['forecast']) / count($g['forecast']);
                $forecastAgg[] = round($avg); $lower[] = round($avg * 0.97); $upper[] = round($avg * 1.03);
            } else {
                $forecastAgg[] = null; $lower[] = null; $upper[] = null;
            }
        }
    }

    private function aggregateYearlyData($dates, $actualPrices, $forecastPrices, &$labels, &$actualAgg, &$forecastAgg, &$lower, &$upper)
    {
        $groups      = [];
        $actualCount = count($actualPrices);

        foreach ($dates as $index => $date) {
            $year = $date->year;
            if (!isset($groups[$year])) {
                $groups[$year] = ['label' => "Tahun {$year}", 'actual' => [], 'forecast' => []];
            }
            if ($index < $actualCount) {
                $groups[$year]['actual'][] = $actualPrices[$index];
            }
            $fi = $index - $actualCount;
            if ($fi >= 0 && $fi < count($forecastPrices)) {
                $groups[$year]['forecast'][] = $forecastPrices[$fi];
            }
        }

        foreach ($groups as $g) {
            $labels[]    = $g['label'];
            $actualAgg[] = !empty($g['actual']) ? round(array_sum($g['actual']) / count($g['actual'])) : null;
            if (!empty($g['forecast'])) {
                $avg = array_sum($g['forecast']) / count($g['forecast']);
                $forecastAgg[] = round($avg); $lower[] = round($avg * 0.98); $upper[] = round($avg * 1.02);
            } else {
                $forecastAgg[] = null; $lower[] = null; $upper[] = null;
            }
        }
    }
}