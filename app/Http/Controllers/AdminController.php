<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\CommodityPrice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class AdminController extends Controller
{
    /**
     * Display admin dashboard
     */
    public function index(Request $request)
    {
        return $this->processForecasting($request);
    }

    /**
     * Process forecasting/prediction
     */
    public function predict(Request $request)
    {
        return $this->processForecasting($request);
    }

    /**
     * Main processing logic for admin dashboard
     */
    private function processForecasting(Request $request)
    {
        // User Info
        $role = 'admin'; 
        $username = auth()->user()->name ?? 'Administrator BPS';
        $email = auth()->user()->email ?? 'admin_riau@bps.go.id';

        // Request Parameters
        $currentTab = $request->query('tab', $request->input('tab', 'insight')); 
        $selectedCommodity = $request->input('commodity', 'Beras Premium');
        $startDate = $request->input('start_date', Carbon::now()->subDays(30)->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));

        // Initialize Variables
        $users = [];
        $allData = [];
        $latestData = collect();
        $actualData = [];
        $chartLabels = [];
        $forecastData = [];
        $lowerBand = [];
        $upperBand = [];
        $dataIssues = collect();

        // Weekly/Monthly/Yearly aggregated data
        $weeklyLabels = [];
        $weeklyActual = [];
        $weeklyForecast = [];
        $weeklyLower = [];
        $weeklyUpper = [];
        
        $monthlyLabels = [];
        $monthlyActual = [];
        $monthlyForecast = [];
        $monthlyLower = [];
        $monthlyUpper = [];
        
        $yearlyLabels = [];
        $yearlyActual = [];
        $yearlyForecast = [];
        $yearlyLower = [];
        $yearlyUpper = [];

        try {
            // Tab Users: Load all users with pagination
            if ($currentTab === 'users') {
                $users = User::orderBy('created_at', 'desc')->paginate(10);
            }

            // Tab Manage: Load data management related info with pagination
            if ($currentTab === 'manage') {
                // Get all data for selected commodity with pagination (for table display)
                $latestData = CommodityPrice::where('commodity_name', $selectedCommodity)
                    ->orderBy('date', 'desc')
                    ->paginate(10);

                // Scan data quality issues with pagination
                $dataIssues = $this->scanDataQualityPaginated($selectedCommodity, $request);
            }

            // Main Query: Get historical data for chart
            $dbData = CommodityPrice::where('commodity_name', $selectedCommodity)
                ->whereBetween('date', [$startDate, $endDate])
                ->whereNotNull('price')
                ->where('price', '>', 0)
                ->orderBy('date', 'asc')
                ->get();

            if ($dbData->isNotEmpty()) {
                // Actual Data
                $actualData = $dbData->pluck('price')->toArray();
                $chartLabels = $dbData->map(function($d) {
                    return Carbon::parse($d->date)->format('d/m');
                })->toArray();
                
                // Generate Simple Forecast (7 days ahead)
                $lastVal = end($actualData);
                $lastDate = $dbData->last()->date;
                
                for ($i = 1; $i <= 7; $i++) {
                    $forecastVal = $lastVal + ($i * rand(-50, 100));
                    $forecastData[] = $forecastVal;
                    $lowerBand[] = $forecastVal * 0.95;
                    $upperBand[] = $forecastVal * 1.05;
                    $chartLabels[] = Carbon::parse($lastDate)->addDays($i)->format('d/m');
                }

                // Aggregate Weekly Data
                $this->aggregateWeeklyData(
                    $actualData, 
                    $forecastData, 
                    $weeklyLabels, 
                    $weeklyActual, 
                    $weeklyForecast,
                    $weeklyLower,
                    $weeklyUpper
                );

                // Aggregate Monthly Data
                $this->aggregateMonthlyData(
                    $actualData, 
                    $forecastData, 
                    $monthlyLabels, 
                    $monthlyActual, 
                    $monthlyForecast,
                    $monthlyLower,
                    $monthlyUpper
                );

                // Aggregate Yearly Data
                $this->aggregateYearlyData(
                    $actualData, 
                    $forecastData, 
                    $yearlyLabels, 
                    $yearlyActual, 
                    $yearlyForecast,
                    $yearlyLower,
                    $yearlyUpper
                );

            } else {
                throw new \Exception("Tidak ada data untuk periode yang dipilih");
            }

        } catch (\Exception $e) {
            Log::warning("Menggunakan data fallback: " . $e->getMessage());
            
            // Fallback Data
            $actualData = [14200, 14350, 14250, 14400, 14600, 14500, 14750];
            $chartLabels = ["27/12", "28/12", "29/12", "30/12", "31/12", "01/01", "02/01"];
            $forecastData = [14800, 14950, 15100, 15000, 15250, 15400, 15550];
            $lowerBand = array_map(fn($v) => $v * 0.95, $forecastData);
            $upperBand = array_map(fn($v) => $v * 1.05, $forecastData);
            
            if ($currentTab === 'manage' && (empty($allData) || $latestData->isEmpty())) {
                $fallbackData = collect([
                    (object)[
                        'id' => 1, 
                        'commodity_name' => $selectedCommodity, 
                        'date' => Carbon::now()->format('Y-m-d'), 
                        'price' => 14750
                    ],
                    (object)[
                        'id' => 2, 
                        'commodity_name' => $selectedCommodity, 
                        'date' => Carbon::now()->subDay()->format('Y-m-d'), 
                        'price' => 14500
                    ],
                ]);
                
                $latestData = new \Illuminate\Pagination\LengthAwarePaginator(
                    $fallbackData,
                    $fallbackData->count(),
                    10,
                    1,
                    ['path' => $request->url(), 'query' => $request->query()]
                );
                
                $allData = $fallbackData;
            }
        }

        // Calculate Statistics
        $countData = count($actualData);
        $avgPrice = $countData > 0 ? array_sum($actualData) / $countData : 0;
        $maxPrice = $countData > 0 ? max($actualData) : 0;
        $trendDir = (count($forecastData) > 0 && end($forecastData) >= (end($actualData) ?: 0)) ? 'Naik' : 'Turun';

        // Prophet Parameters
        $cpScale = $request->input('changepoint_prior_scale', 0.05);
        $seasonScale = $request->input('seasonality_prior_scale', 10);
        $seasonalityMode = $request->input('seasonality_mode', 'multiplicative');
        $weeklySeason = $request->input('weekly_seasonality') === 'true';
        $yearlySeason = $request->input('yearly_seasonality') === 'true';

        // Model Metrics (mock for now)
        $mape = rand(2, 5) + (rand(0, 99) / 100);
        $rSquared = 0.85 + (rand(0, 10) / 100);

        // Return View
        return view('admin_dashboard', compact(
            'role',
            'username',
            'email',
            'currentTab',
            'users',
            'allData',
            'latestData',
            'dataIssues',
            'selectedCommodity',
            'startDate',
            'endDate',
            'trendDir',
            'actualData',
            'chartLabels',
            'forecastData',
            'lowerBand',
            'upperBand',
            'avgPrice',
            'maxPrice',
            'cpScale',
            'seasonScale',
            'seasonalityMode',
            'weeklySeason',
            'yearlySeason',
            'mape',
            'rSquared',
            'weeklyLabels',
            'weeklyActual',
            'weeklyForecast',
            'weeklyLower',
            'weeklyUpper',
            'monthlyLabels',
            'monthlyActual',
            'monthlyForecast',
            'monthlyLower',
            'monthlyUpper',
            'yearlyLabels',
            'yearlyActual',
            'yearlyForecast',
            'yearlyLower',
            'yearlyUpper'
        ));
    }

    // ========================================
    // DATA QUALITY MANAGEMENT
    // ========================================

    /**
     * Scan data quality issues (Outliers & Missing Values) with pagination
     */
    private function scanDataQualityPaginated($commodity, $request)
    {
        $data = CommodityPrice::where('commodity_name', $commodity)
            ->orderBy('date', 'asc')
            ->get();

        if ($data->isEmpty()) {
            return new \Illuminate\Pagination\LengthAwarePaginator(
                collect([]),
                0,
                8,
                1,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        }

        // Get valid prices for statistical calculation
        $prices = $data->pluck('price')->filter()->toArray();
        
        if (count($prices) < 4) {
            return new \Illuminate\Pagination\LengthAwarePaginator(
                collect([]),
                0,
                8,
                1,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        }

        // Calculate IQR for Outlier Detection
        sort($prices);
        $q1 = $prices[floor(count($prices) * 0.25)];
        $q3 = $prices[floor(count($prices) * 0.75)];
        $iqr = $q3 - $q1;
        $lowerBound = $q1 - (1.5 * $iqr);
        $upperBound = $q3 + (1.5 * $iqr);

        $issues = [];
        foreach ($data as $item) {
            // Check Missing/Zero Values
            if (is_null($item->price) || $item->price <= 0) {
                $issues[] = (object)[
                    'date' => $item->date,
                    'issue' => 'Missing Value',
                    'value' => 0,
                    'status' => 'Perlu Diisi (Imputation)'
                ];
            } 
            // Check Outliers
            elseif ($item->price < $lowerBound || $item->price > $upperBound) {
                $status = $item->price > $upperBound ? 'Terlalu Tinggi' : 'Terlalu Rendah';
                $issues[] = (object)[
                    'date' => $item->date,
                    'issue' => 'Outlier',
                    'value' => $item->price,
                    'status' => $status
                ];
            }
        }

        // Convert to paginator
        $issuesCollection = collect($issues);
        $perPage = 8;
        $currentPage = $request->input('page', 1);
        $currentItems = $issuesCollection->slice(($currentPage - 1) * $perPage, $perPage)->values();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $currentItems,
            $issuesCollection->count(),
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => array_merge($request->query(), ['tab' => 'manage'])
            ]
        );
    }

    /**
     * Scan data quality issues (Non-paginated version for backward compatibility)
     */
    private function scanDataQuality($commodity)
    {
        $issues = [];
        
        $data = CommodityPrice::where('commodity_name', $commodity)
            ->orderBy('date', 'asc')
            ->get();

        if ($data->isEmpty()) {
            return [];
        }

        // Get valid prices for statistical calculation
        $prices = $data->pluck('price')->filter()->toArray();
        
        if (count($prices) < 4) {
            return [];
        }

        // Calculate IQR for Outlier Detection
        sort($prices);
        $q1 = $prices[floor(count($prices) * 0.25)];
        $q3 = $prices[floor(count($prices) * 0.75)];
        $iqr = $q3 - $q1;
        $lowerBound = $q1 - (1.5 * $iqr);
        $upperBound = $q3 + (1.5 * $iqr);

        foreach ($data as $item) {
            // Check Missing/Zero Values
            if (is_null($item->price) || $item->price <= 0) {
                $issues[] = (object)[
                    'date' => $item->date,
                    'issue' => 'Missing Value',
                    'value' => 0,
                    'status' => 'Perlu Diisi (Imputation)'
                ];
            } 
            // Check Outliers
            elseif ($item->price < $lowerBound || $item->price > $upperBound) {
                $status = $item->price > $upperBound ? 'Terlalu Tinggi' : 'Terlalu Rendah';
                $issues[] = (object)[
                    'date' => $item->date,
                    'issue' => 'Outlier',
                    'value' => $item->price,
                    'status' => $status
                ];
            }
        }

        return $issues;
    }

    /**
     * Clean data - Handle Outliers & Missing Values
     */
    public function cleanData(Request $request)
    {
        $request->validate([
            'action' => 'required|in:outlier,missing',
            'commodity' => 'required|string'
        ]);

        try {
            $action = $request->input('action');
            $method = $request->input($action === 'outlier' ? 'outlier_method' : 'missing_method');
            $commodity = $request->input('commodity');

            // Get all prices for statistical calculation
            $prices = CommodityPrice::where('commodity_name', $commodity)
                ->where('price', '>', 0)
                ->pluck('price')
                ->toArray();

            if (empty($prices)) {
                return redirect()->back()->with('error', 'Data tidak mencukupi untuk pemrosesan');
            }

            // Calculate Mean & Median
            sort($prices);
            $mean = array_sum($prices) / count($prices);
            $median = $prices[floor(count($prices) / 2)];
            $replacement = ($method === 'median') ? $median : $mean;

            $affectedCount = 0;

            if ($action === 'outlier') {
                // Handle Outliers
                $q1 = $prices[floor(count($prices) * 0.25)];
                $q3 = $prices[floor(count($prices) * 0.75)];
                $iqr = $q3 - $q1;
                $lowerBound = $q1 - (1.5 * $iqr);
                $upperBound = $q3 + (1.5 * $iqr);

                $outliers = CommodityPrice::where('commodity_name', $commodity)
                    ->where(function($q) use ($lowerBound, $upperBound) {
                        $q->where('price', '<', $lowerBound)
                          ->orWhere('price', '>', $upperBound);
                    });

                if ($method === 'remove') {
                    $affectedCount = $outliers->count();
                    $outliers->delete();
                } else {
                    $affectedCount = $outliers->count();
                    $outliers->update([
                        'price' => $replacement,
                        'updated_at' => now()
                    ]);
                }

            } else {
                // Handle Missing Values
                $missingValues = CommodityPrice::where('commodity_name', $commodity)
                    ->where(function($q) {
                        $q->whereNull('price')->orWhere('price', '<=', 0);
                    });

                if ($method === 'remove') {
                    $affectedCount = $missingValues->count();
                    $missingValues->delete();
                } else {
                    $affectedCount = $missingValues->count();
                    $missingValues->update([
                        'price' => $replacement,
                        'updated_at' => now()
                    ]);
                }
            }

            $actionText = $action === 'outlier' ? 'Outlier' : 'Missing Values';
            $methodText = $method === 'remove' ? 'dihapus' : 'diperbaiki';

            return redirect()
                ->route('admin.predict', ['tab' => 'manage', 'commodity' => $commodity])
                ->with('success', "{$actionText} berhasil {$methodText}! {$affectedCount} data diproses.");

        } catch (\Exception $e) {
            Log::error('Clean Data Error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Gagal membersihkan data: ' . $e->getMessage());
        }
    }
    
    // ========================================
    // DATA MANAGEMENT (CRUD)
    // ========================================

    /**
     * Store new commodity price data
     */
    public function storeData(Request $request)
    {
        try {
            // Handle CSV Upload
            if ($request->hasFile('dataset')) {
                $file = $request->file('dataset');
                $handle = fopen($file->getRealPath(), 'r');
                
                // Skip header
                fgetcsv($handle);
                
                $insertedCount = 0;
                while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                    if (count($data) >= 3) {
                        CommodityPrice::updateOrCreate(
                            [
                                'commodity_name' => $data[1] ?? $data[0],
                                'date' => $data[0] ?? $data[1]
                            ],
                            [
                                'price' => $data[2],
                                'source' => 'CSV Upload',
                                'updated_at' => now()
                            ]
                        );
                        $insertedCount++;
                    }
                }
                fclose($handle);

                return redirect()
                    ->route('admin.predict', ['tab' => 'manage'])
                    ->with('success', "Bulk upload berhasil! {$insertedCount} data diproses.");
            }

            // Handle Manual Single Input
            $request->validate([
                'commodity' => 'required|string',
                'date' => 'required|date',
                'price' => 'required|numeric|min:0'
            ]);

            CommodityPrice::create([
                'commodity_name' => $request->commodity,
                'date' => $request->date,
                'price' => $request->price,
                'source' => 'Manual Input'
            ]);

            return redirect()
                ->route('admin.predict', ['tab' => 'manage'])
                ->with('success', 'Data berhasil ditambahkan!');

        } catch (\Exception $e) {
            Log::error('Store Data Error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Gagal menyimpan data: ' . $e->getMessage());
        }
    }

    /**
     * Update commodity price data
     */
    public function updateData(Request $request, $id)
    {
        $request->validate([
            'commodity' => 'required|string',
            'date' => 'required|date',
            'price' => 'required|numeric|min:0'
        ]);

        try {
            $data = CommodityPrice::findOrFail($id);
            
            $data->update([
                'commodity_name' => $request->commodity,
                'date' => $request->date,
                'price' => $request->price,
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Data berhasil diperbarui!',
                'data' => [
                    'id' => $data->id,
                    'commodity' => $data->commodity_name,
                    'date' => $data->date,
                    'price' => $data->price
                ]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan.'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Update Data Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete commodity price data
     */
    public function deleteData($id)
    {
        try {
            $data = CommodityPrice::findOrFail($id);
            $data->delete();

            return redirect()
                ->route('admin.predict', ['tab' => 'manage'])
                ->with('success', 'Data berhasil dihapus!');

        } catch (\Exception $e) {
            Log::error('Delete Data Error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Gagal menghapus data.');
        }
    }

    /**
     * Download CSV Template
     */
    public function downloadTemplate()
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="template_data_komoditas.csv"',
        ];

        $columns = ['tanggal', 'komoditas', 'harga'];
        $sampleData = [
            ['2026-01-01', 'Beras Premium', '14500'],
            ['2026-01-02', 'Beras Premium', '14600'],
            ['2026-01-03', 'Cabai Merah', '85000'],
        ];

        $callback = function() use ($columns, $sampleData) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            foreach ($sampleData as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ========================================
    // USER MANAGEMENT
    // ========================================

    /**
     * Create new user
     */
    public function storeUser(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8'
        ]);

        try {
            User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'user'
            ]);

            return redirect()
                ->route('admin.predict', ['tab' => 'users'])
                ->with('success', 'Pengguna berhasil dibuat!');

        } catch (\Exception $e) {
            Log::error('Create User Error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Gagal membuat pengguna: ' . $e->getMessage());
        }
    }
    /**
 * Update user data
 */
public function updateUser(Request $request, $id)
{
    $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|string|email|max:255',
        'role' => 'required|in:user,operator,admin'
    ]);

    try {
        // Prevent changing own role
        if (auth()->id() == $id && $request->role !== auth()->user()->role) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat mengubah role Anda sendiri!'
            ], 403);
        }

        $user = User::findOrFail($id);
        
        // Check if email is unique (excluding current user)
        $emailExists = User::where('email', $request->email)
            ->where('id', '!=', $id)
            ->exists();
            
        if ($emailExists) {
            return response()->json([
                'success' => false,
                'message' => 'Email sudah digunakan oleh pengguna lain!'
            ], 422);
        }
        
        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
            'updated_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Data pengguna berhasil diperbarui!',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role
            ]
        ]);

    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Pengguna tidak ditemukan.'
        ], 404);

    } catch (\Exception $e) {
        Log::error('Update User Error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Gagal memperbarui data pengguna: ' . $e->getMessage()
        ], 500);
    }
}

    /**
     * Delete user
     */
    public function deleteUser($id)
    {
        try {
            // Prevent self-deletion
            if (auth()->id() == $id) {
                return redirect()->back()->with('error', 'Tidak dapat menghapus akun Anda sendiri!');
            }

            $user = User::findOrFail($id);
            $user->delete();

            return redirect()
                ->route('admin.predict', ['tab' => 'users'])
                ->with('success', 'Pengguna berhasil dihapus!');

        } catch (\Exception $e) {
            Log::error('Delete User Error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Gagal menghapus pengguna.');
        }
    }

    // ========================================
    // DATA AGGREGATION HELPERS
    // ========================================

    /**
     * Aggregate data by week
     */
    private function aggregateWeeklyData($actual, $forecast, &$labels, &$actualAgg, &$forecastAgg, &$lower, &$upper)
    {
        $step = 7;
        for ($i = 0; $i < count($actual); $i += $step) {
            $weekActual = array_slice($actual, $i, $step);
            $weekForecast = array_slice($forecast, $i, min($step, count($forecast) - $i));
            
            if (!empty($weekActual)) {
                $labels[] = 'Minggu ' . (floor($i / $step) + 1);
                $avgActual = array_sum($weekActual) / count($weekActual);
                $actualAgg[] = round($avgActual);
                
                if (!empty($weekForecast)) {
                    $avgForecast = array_sum($weekForecast) / count($weekForecast);
                    $forecastAgg[] = round($avgForecast);
                    $lower[] = round($avgForecast * 0.95);
                    $upper[] = round($avgForecast * 1.05);
                }
            }
        }
    }

    /**
     * Aggregate data by month
     */
    private function aggregateMonthlyData($actual, $forecast, &$labels, &$actualAgg, &$forecastAgg, &$lower, &$upper)
    {
        $step = 30;
        for ($i = 0; $i < count($actual); $i += $step) {
            $monthActual = array_slice($actual, $i, $step);
            $monthForecast = array_slice($forecast, $i, min($step, count($forecast) - $i));
            
            if (!empty($monthActual)) {
                $labels[] = 'Bulan ' . (floor($i / $step) + 1);
                $avgActual = array_sum($monthActual) / count($monthActual);
                $actualAgg[] = round($avgActual);
                
                if (!empty($monthForecast)) {
                    $avgForecast = array_sum($monthForecast) / count($monthForecast);
                    $forecastAgg[] = round($avgForecast);
                    $lower[] = round($avgForecast * 0.97);
                    $upper[] = round($avgForecast * 1.03);
                }
            }
        }
    }

    /**
     * Aggregate data by year
     */
    private function aggregateYearlyData($actual, $forecast, &$labels, &$actualAgg, &$forecastAgg, &$lower, &$upper)
    {
        $step = 365;
        for ($i = 0; $i < count($actual); $i += $step) {
            $yearActual = array_slice($actual, $i, $step);
            $yearForecast = array_slice($forecast, $i, min($step, count($forecast) - $i));
            
            if (!empty($yearActual)) {
                $labels[] = 'Tahun ' . (floor($i / $step) + 1);
                $avgActual = array_sum($yearActual) / count($yearActual);
                $actualAgg[] = round($avgActual);
                
                if (!empty($yearForecast)) {
                    $avgForecast = array_sum($yearForecast) / count($yearForecast);
                    $forecastAgg[] = round($avgForecast);
                    $lower[] = round($avgForecast * 0.98);
                    $upper[] = round($avgForecast * 1.02);
                }
            }
        }
    }
}