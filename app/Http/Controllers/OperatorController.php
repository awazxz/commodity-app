<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class OperatorController extends Controller
{
    /**
     * Menampilkan halaman utama operator
     */
    public function index(Request $request) 
    {
        return $this->processForecasting($request);
    }

    /**
     * Menangani navigasi tab dan prediksi
     */
    public function predict(Request $request) 
    {
        return $this->processForecasting($request);
    }

    /**
     * Logika utama untuk menyiapkan data dashboard
     */
    private function processForecasting(Request $request) 
    {
        // Tanpa Middleware, kita set data statis untuk profil
        $username = "Operator BPS";
        $email = "operator@bps.go.id";
        
        // Mengambil tab dari query string atau input form
        $currentTab = $request->input('tab', $request->query('tab', 'insight'));
        
        // 1. DATA SIMULASI UNTUK TAB "MANAJEMEN DATA"
        $allData = [
            (object)['id' => 1, 'date' => '2026-01-01', 'commodity' => 'Beras Premium', 'price' => 14500],
            (object)['id' => 2, 'date' => '2026-01-02', 'commodity' => 'Beras Premium', 'price' => 14700],
            (object)['id' => 3, 'date' => '2026-01-03', 'commodity' => 'Beras Premium', 'price' => 14600],
            (object)['id' => 4, 'date' => '2026-01-04', 'commodity' => 'Beras Premium', 'price' => 14800],
        ];

        // 2. DATA SIMULASI DETEKSI MASALAH (DATA ISSUES)
        $selectedCommodity = $request->input('commodity', 'Beras Premium');
        $dataIssues = [];
        if($currentTab == 'manage') {
            $dataIssues = [
                (object)['date' => '2025-12-15', 'issue' => 'Outlier', 'value' => 'Rp 25.000', 'status' => 'Extreme High'],
                (object)['date' => '2025-12-20', 'issue' => 'Missing', 'value' => 'N/A', 'status' => 'Empty Record'],
            ];
        }

        // 3. PARAMETER PREDIKSI (Sesuai dengan nama variabel di View)
        $startDate = $request->input('start_date', '2025-12-01');
        $endDate = $request->input('end_date', '2026-01-05');
        $cpScale = $request->input('changepoint_prior_scale', 0.05);
        $seasonScale = $request->input('seasonality_prior_scale', 10);
        $seasonMode = $request->input('seasonality_mode', 'multiplicative');
        $weeklySeason = $request->input('weekly_seasonality') === 'true';
        $yearlySeason = $request->input('yearly_seasonality') === 'true';

        // 4. DATA SIMULASI UNTUK GRAFIK
        $actualData = [14200, 14350, 14300, 14450, 14600, 14550, 14700];
        $chartLabels = ['29/12', '30/12', '31/12', '01/01', '02/01', '03/01', '04/01'];
        
        // Data proyeksi (dimulai dari titik terakhir data aktual agar garis menyambung)
        $forecastData = [null, null, null, null, null, null, 14700, 14800, 14900, 14850, 15000, 15100, 15200];
        $forecastLabels = ['05/01', '06/01', '07/01', '08/01', '09/01', '10/01', '11/01'];

        // Menyelaraskan array aktual agar panjangnya sama dengan label total
        $actualDataExtended = array_merge($actualData, array_fill(0, count($forecastLabels), null));
        $allLabels = array_merge($chartLabels, $forecastLabels);

        return view('operator_dashboard', [
            'username' => $username,
            'email' => $email,
            'role' => 'operator',
            'currentTab' => $currentTab,
            'allData' => $allData,
            'dataIssues' => $dataIssues, 
            'selectedCommodity' => $selectedCommodity,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'actualData' => $actualDataExtended,
            'forecastData' => $forecastData,
            'chartLabels' => $allLabels,
            'avgPrice' => 14585,
            'maxPrice' => 14800,
            'trendDir' => 'Naik',
            'percentageChange' => 3.42,
            'mape' => 2.15,
            'rSquared' => 0.945,
            'cpScale' => $cpScale,
            'seasonScale' => $seasonScale,
            'seasonMode' => $seasonMode,
            'weeklySeason' => $weeklySeason,
            'yearlySeason' => $yearlySeason
        ]);
    }

    /**
     * Simulasi penyimpanan data baru
     */
    public function storeData(Request $request) 
    {
        $request->validate([
            'date' => 'required|date',
            'price' => 'required|numeric',
            'commodity' => 'required|string',
        ]);

        return redirect()->route('operator.predict', ['tab' => 'manage', 'commodity' => $request->commodity])
                         ->with('success', 'Data komoditas berhasil ditambahkan ke antrian simulasi!');
    }

    /**
     * Simulasi penghapusan data
     */
    public function deleteData($id) 
    {
        return redirect()->route('operator.predict', ['tab' => 'manage'])
                         ->with('success', 'Data dengan ID ' . $id . ' berhasil dihapus (Simulasi)');
    }

    /**
     * Handling Outlier & Missing Values
     */
    public function cleanData(Request $request)
    {
        $action = $request->input('action'); 
        
        if ($action === 'outlier') {
            $method = $request->input('outlier_method'); 
            $msg = "Deteksi Outlier selesai. Data ditangani dengan metode: " . strtoupper($method);
        } else {
            $method = $request->input('missing_method'); 
            $msg = "Missing Values berhasil diisi menggunakan metode: " . strtoupper($method);
        }

        return redirect()->route('operator.predict', ['tab' => 'manage'])
                         ->with('success', $msg);
    }
}