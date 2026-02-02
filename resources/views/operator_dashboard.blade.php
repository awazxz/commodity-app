@extends('layouts.app')

@section('content')
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div id="real-content">
<style>
    /* Reset & Typography Standar - SAMA dengan admin_dashboard.blade.php */
    .dashboard-container {
        font-family: 'Inter', sans-serif;
    }

    /* Style Kartu identik dengan admin_dashboard.blade.php */
    .card-standard {
        background: white;
        border-radius: 0.5rem; /* rounded-lg */
        border: 1px solid #e5e7eb; /* border-gray-200 */
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
    }

    /* Hover effect konsisten */
    .hover-card {
        transition: all 0.3s ease;
    }

    .hover-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }

    /* Filter Button Standard - SAMA dengan admin */
    .filter-btn {
        padding: 0.5rem 1rem;
        border-radius: 0.375rem;
        font-size: 0.75rem;
        font-weight: 600;
        transition: all 0.2s;
        border: 1px solid #d1d5db;
        background: white;
        color: #4b5563;
    }

    .filter-btn.active {
        background: #2563eb; /* Blue-600 - SAMA dengan admin */
        color: white;
        border-color: #2563eb;
    }

    .filter-btn:hover:not(.active) {
        background: #f8fafc;
        border-color: #cbd5e1;
    }

    /* Badge Insight identik dengan admin_dashboard.blade.php */
    .insight-badge {
        padding: 0.25rem 0.625rem;
        border-radius: 9999px;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
    }

    .insight-naik { background: #fee2e2; color: #991b1b; }
    .insight-turun { background: #dcfce7; color: #166534; }
    .insight-stabil { background: #f3f4f6; color: #1f2937; }

    /* Range Input Styling */
    input[type="range"]::-webkit-slider-thumb {
        height: 16px;
        width: 16px;
        border-radius: 50%;
        background: #2563eb;
        cursor: pointer;
        border: 2px solid #fff;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        appearance: none;
        -webkit-appearance: none;
    }

    /* Scrollbar styling */
    .custom-scrollbar::-webkit-scrollbar { 
        width: 6px; 
        height: 6px; 
    }
    
    .custom-scrollbar::-webkit-scrollbar-track { 
        background: #f8fafc; 
    }
    
    .custom-scrollbar::-webkit-scrollbar-thumb { 
        background: #cbd5e1; 
        border-radius: 10px; 
    }

    /* Animation */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .animate-fade-in {
        animation: fadeIn 0.4s ease-out;
    }

    [x-cloak] { display: none !important; }
</style>
</div>

<div id="skeleton-overlay" class="hidden fixed inset-0 bg-white/50 z-50 flex items-center justify-center opacity-0">
    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
</div>

<div class="dashboard-container space-y-6 animate-fade-in">

{{-- ==========================================
     TAB 1: TAMPILAN ANALISIS
     ========================================== --}}
@if(($currentTab ?? 'insight') == 'insight')

    {{-- HEADER & FILTER KOMODITAS --}}
    <div class="card-standard p-6">
        <div class="flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="flex items-center gap-4">
                <div class="bg-blue-600 p-3 rounded-lg text-white shadow-md">
                    <i class="fas fa-chart-line text-xl"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-gray-900 leading-none">
                        Sistem Analisis Prediksi Harga Komoditas
                    </h2>
                    <p class="text-xs text-emerald-500 font-medium uppercase tracking-wider mt-1.5">
                        Panel Operator • Kontrol Data
                    </p>
                </div>
            </div>
        </div>

        {{-- FILTER FORM --}}
        <form action="{{ route('operator.predict') }}" method="POST" id="mainForm" class="mt-6 pt-6 border-t border-gray-100">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                <div class="md:col-span-4">
                    <label class="text-xs font-semibold text-gray-700 uppercase mb-2 block tracking-tight">
                        Komoditas Terpilih
                    </label>
                    <select name="commodity" onchange="handleCommodityChange()" 
                            class="w-full bg-gray-50 border border-gray-300 rounded-md py-2 px-3 text-sm font-medium text-gray-900 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all">
                        <option value="Beras Premium" {{ $selectedCommodity == 'Beras Premium' ? 'selected' : '' }}>Beras Premium</option>
                        <option value="Cabai Merah" {{ $selectedCommodity == 'Cabai Merah' ? 'selected' : '' }}>Cabai Merah</option>
                        <option value="Minyak Goreng" {{ $selectedCommodity == 'Minyak Goreng' ? 'selected' : '' }}>Minyak Goreng</option>
                    </select>
                </div>

                <div class="md:col-span-8">
                    <label class="text-xs font-semibold text-gray-700 uppercase mb-2 block tracking-tight">
                        Rentang Waktu Analisis Historis
                    </label>
                    <div class="flex items-center gap-3 bg-gray-50 p-1.5 rounded-md border border-gray-300">
                        <input type="date" name="start_date" value="{{ $startDate }}" onchange="triggerSubmit()" 
                               class="bg-transparent text-sm p-1 outline-none flex-1 font-medium">
                        <span class="text-gray-400 font-bold">→</span>
                        <input type="date" name="end_date" value="{{ $endDate }}" onchange="triggerSubmit()" 
                               class="bg-transparent text-sm p-1 outline-none flex-1 font-medium">
                    </div>
                </div>
            </div>

            {{-- Hidden Inputs --}}
            <input type="hidden" name="changepoint_prior_scale" id="hidden_cp" value="{{ $cpScale }}">
            <input type="hidden" name="seasonality_prior_scale" id="hidden_season" value="{{ $seasonScale ?? 10 }}">
            <input type="hidden" name="seasonality_mode" id="hidden_mode" value="{{ $seasonMode ?? 'multiplicative' }}">
            <input type="hidden" name="weekly_seasonality" id="hidden_weekly" value="{{ ($weeklySeason ?? false) ? 'true' : 'false' }}">
            <input type="hidden" name="yearly_seasonality" id="hidden_yearly" value="{{ ($yearlySeason ?? false) ? 'true' : 'false' }}">
            <input type="hidden" name="tab" value="insight">
        </form>
    </div>

    {{-- METRIC CARDS --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
        <div class="card-standard hover-card p-5">
            <p class="text-[10px] uppercase text-gray-500 font-bold tracking-wider mb-2">Rata-rata Harga</p>
            <p class="text-xl font-bold text-gray-900">Rp {{ number_format($avgPrice ?? 0,0,',','.') }}</p>
        </div>

        <div class="card-standard hover-card p-5">
            <p class="text-[10px] uppercase text-gray-500 font-bold tracking-wider mb-2">Harga Tertinggi</p>
            <p class="text-xl font-bold text-red-600">Rp {{ number_format($maxPrice ?? 0,0,',','.') }}</p>
        </div>

        <div class="card-standard hover-card p-5">
            <p class="text-[10px] uppercase text-gray-500 font-bold tracking-wider mb-2">Status Data</p>
            <div class="flex items-center gap-2">
                <span class="h-2 w-2 bg-green-500 rounded-full animate-pulse"></span>
                <p class="text-sm font-semibold text-gray-700">Aktif & Terverifikasi</p>
            </div>
        </div>

        <div class="bg-blue-600 rounded-lg p-5 text-white shadow-lg hover-card">
            <p class="text-[10px] uppercase text-blue-100 font-bold tracking-wider mb-2">Arah Tren</p>
            <p class="text-sm font-bold uppercase flex items-center gap-2">
                <i class="fas {{ str_contains(strtolower($trendDir ?? 'Naik'), 'naik') ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' }}"></i>
                {{ $trendDir ?? 'Naik' }}
            </p>
        </div>
    </div>

    {{-- LAYOUT GRAFIK & HYPERPARAMETER --}}
    <!-- <div class="grid grid-cols-12 gap-5"> -->
        
        {{-- HYPERPARAMETER SETTINGS --}}
        <!-- <div class="col-span-12 lg:col-span-4 space-y-4">
            <div class="card-standard p-5">
                <h4 class="text-sm font-bold text-gray-900 uppercase tracking-tight mb-6 pb-3 border-b border-gray-100">
                    Pengaturan Hyperparameter
                </h4>
                <div class="space-y-6">
                    {{-- Changepoint Prior Scale --}}
                    <div>
                        <div class="flex justify-between mb-2">
                            <span class="text-xs text-gray-500 font-semibold uppercase">Changepoint Prior</span>
                            <span class="text-xs font-mono font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded" id="cp_display">{{ $cpScale }}</span>
                        </div>
                        <input type="range" min="0.001" max="0.5" step="0.001" value="{{ $cpScale }}" class="w-full h-1 bg-gray-100 rounded-lg appearance-none cursor-pointer" oninput="updateVal('hidden_cp', 'cp_display', this.value)">
                        <p class="text-[9px] text-gray-400 mt-1">Fleksibilitas perubahan tren</p>
                    </div>

                    {{-- Seasonality Prior Scale --}}
                    <div>
                        <div class="flex justify-between mb-2">
                            <span class="text-xs text-gray-500 font-semibold uppercase">Seasonality Prior</span>
                            <span class="text-xs font-mono font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded" id="season_display">{{ $seasonScale ?? 10 }}</span>
                        </div>
                        <input type="range" min="0.01" max="10" step="0.01" value="{{ $seasonScale ?? 10 }}" class="w-full h-1 bg-gray-100 rounded-lg appearance-none cursor-pointer" oninput="updateVal('hidden_season', 'season_display', this.value)">
                        <p class="text-[9px] text-gray-400 mt-1">Kekuatan pola musiman</p>
                    </div>

                    {{-- Seasonality Mode --}}
                    <div>
                        <label class="text-xs text-gray-500 font-semibold uppercase mb-2 block">Mode Musiman</label>
                        <select onchange="document.getElementById('hidden_mode').value = this.value" class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-xs text-gray-600 font-medium outline-none">
                            <option value="multiplicative" {{ ($seasonMode ?? '') == 'multiplicative' ? 'selected' : '' }}>Multiplikatif</option>
                            <option value="additive" {{ ($seasonMode ?? '') == 'additive' ? 'selected' : '' }}>Aditif</option>
                        </select>
                        <p class="text-[9px] text-gray-400 mt-1">Metode penerapan musiman</p>
                    </div>

                    {{-- Komponen Musiman --}}
                    <div class="space-y-2 pt-2 border-t border-gray-100">
                        <label class="text-xs text-gray-500 font-semibold uppercase block mb-2">Komponen Musiman</label>
                        <div class="flex items-center justify-between p-2.5 bg-gray-50 rounded-lg border border-gray-100">
                            <span class="text-xs text-gray-500 font-medium uppercase">Mingguan</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" {{ ($weeklySeason ?? false) ? 'checked' : '' }} onchange="document.getElementById('hidden_weekly').value = this.checked" class="sr-only peer">
                                <div class="w-7 h-4 bg-gray-200 rounded-full peer peer-checked:bg-blue-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:after:translate-x-3"></div>
                            </label>
                        </div>
                        <div class="flex items-center justify-between p-2.5 bg-gray-50 rounded-lg border border-gray-100">
                            <span class="text-xs text-gray-500 font-medium uppercase">Tahunan</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" {{ ($yearlySeason ?? false) ? 'checked' : '' }} onchange="document.getElementById('hidden_yearly').value = this.checked" class="sr-only peer">
                                <div class="w-7 h-4 bg-gray-200 rounded-full peer peer-checked:bg-blue-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:after:translate-x-3"></div>
                            </label>
                        </div>
                    </div>

                    <button type="button" onclick="triggerSubmit()" class="w-full bg-blue-600 text-white py-3 rounded-lg text-xs font-bold uppercase tracking-wider hover:bg-blue-700 transition-all shadow-sm">
                        Perbarui Prediksi
                    </button>
                </div>
            </div>

            {{-- STATISTIK --}}
            <div class="bg-gradient-to-br from-blue-600 to-blue-700 rounded-lg shadow-lg p-5 text-white">
                <h4 class="text-xs font-bold uppercase tracking-wider mb-4 opacity-90">
                    Ringkasan Statistik
                </h4>
                <div class="space-y-4">
                    <div class="flex justify-between items-end border-b border-white/10 pb-2">
                        <span class="text-[10px] opacity-70 font-semibold uppercase">MAPE (Akurasi)</span>
                        <span class="text-sm font-bold">{{ number_format($mape ?? 0, 2) }}%</span>
                    </div>
                    <div class="flex justify-between items-end border-b border-white/10 pb-2">
                        <span class="text-[10px] opacity-70 font-semibold uppercase">Skor R-Squared</span>
                        <span class="text-sm font-bold">{{ number_format($rSquared ?? 0, 3) }}</span>
                    </div>
                </div>
            </div>
        </div> -->

        {{-- GRAFIK UTAMA DENGAN FILTER --}}
        <!-- <div class="col-span-12 lg:col-span-8 card-standard overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex flex-col lg:flex-row justify-between items-center gap-4">
                <div>
                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-tight">Visualisasi Tren & Proyeksi</h3>
                    <p class="text-xs text-gray-500">Menampilkan data historis vs hasil algoritma Prophet</p>
                </div>

                <div class="flex bg-white border border-gray-300 p-1 rounded-md shadow-sm">
                    <button onclick="changeChartPeriod('daily')" class="filter-btn active" id="btn-daily">Harian</button>
                    <button onclick="changeChartPeriod('weekly')" class="filter-btn border-none" id="btn-weekly">Mingguan</button>
                    <button onclick="changeChartPeriod('monthly')" class="filter-btn border-none" id="btn-monthly">Bulanan</button>
                    <button onclick="changeChartPeriod('yearly')" class="filter-btn border-none" id="btn-yearly">Tahunan</button>
                </div>
            </div>
            
            <div class="p-6 h-[450px]">
                <canvas id="mainChart"></canvas>
            </div>
        </div>
    </div> -->
    {{-- LAYOUT GRAFIK & HYPERPARAMETER - IMPROVED VERSION --}}
<div class="grid grid-cols-12 gap-5">
    
    {{-- HYPERPARAMETER SETTINGS --}}
    <div class="col-span-12 lg:col-span-4 space-y-4">
        <div class="card-standard p-5">
            <h4 class="text-sm font-bold text-gray-900 uppercase tracking-tight mb-5 pb-3 border-b border-gray-100">
                Pengaturan Hyperparameter
            </h4>
            <div class="space-y-5">
                {{-- Changepoint Prior Scale --}}
                <div>
                    <div class="flex justify-between mb-2">
                        <span class="text-xs text-gray-500 font-semibold uppercase">Changepoint Prior</span>
                        <span class="text-xs font-mono font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded" id="cp_display">{{ $cpScale }}</span>
                    </div>
                    <input type="range" min="0.001" max="0.5" step="0.001" value="{{ $cpScale }}" class="w-full h-1 bg-gray-100 rounded-lg appearance-none cursor-pointer" oninput="updateVal('hidden_cp', 'cp_display', this.value)">
                    <p class="text-[9px] text-gray-400 mt-1">Fleksibilitas perubahan tren</p>
                </div>

                {{-- Seasonality Prior Scale --}}
                <div>
                    <div class="flex justify-between mb-2">
                        <span class="text-xs text-gray-500 font-semibold uppercase">Seasonality Prior</span>
                        <span class="text-xs font-mono font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded" id="season_display">{{ $seasonScale ?? 10 }}</span>
                    </div>
                    <input type="range" min="0.01" max="10" step="0.01" value="{{ $seasonScale ?? 10 }}" class="w-full h-1 bg-gray-100 rounded-lg appearance-none cursor-pointer" oninput="updateVal('hidden_season', 'season_display', this.value)">
                    <p class="text-[9px] text-gray-400 mt-1">Kekuatan pola musiman</p>
                </div>

                {{-- Seasonality Mode --}}
                <div>
                    <label class="text-xs text-gray-500 font-semibold uppercase mb-2 block">Mode Musiman</label>
                    <select onchange="document.getElementById('hidden_mode').value = this.value" class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-xs text-gray-600 font-medium outline-none">
                        <option value="multiplicative" {{ ($seasonMode ?? '') == 'multiplicative' ? 'selected' : '' }}>Multiplikatif</option>
                        <option value="additive" {{ ($seasonMode ?? '') == 'additive' ? 'selected' : '' }}>Aditif</option>
                    </select>
                    <p class="text-[9px] text-gray-400 mt-1">Metode penerapan musiman</p>
                </div>

                {{-- Komponen Musiman --}}
                <div class="space-y-2 pt-2 border-t border-gray-100">
                    <label class="text-xs text-gray-500 font-semibold uppercase block mb-2">Komponen Musiman</label>
                    <div class="flex items-center justify-between p-2.5 bg-gray-50 rounded-lg border border-gray-100">
                        <span class="text-xs text-gray-500 font-medium uppercase">Mingguan</span>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" {{ ($weeklySeason ?? false) ? 'checked' : '' }} onchange="document.getElementById('hidden_weekly').value = this.checked" class="sr-only peer">
                            <div class="w-7 h-4 bg-gray-200 rounded-full peer peer-checked:bg-blue-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:after:translate-x-3"></div>
                        </label>
                    </div>
                    <div class="flex items-center justify-between p-2.5 bg-gray-50 rounded-lg border border-gray-100">
                        <span class="text-xs text-gray-500 font-medium uppercase">Tahunan</span>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" {{ ($yearlySeason ?? false) ? 'checked' : '' }} onchange="document.getElementById('hidden_yearly').value = this.checked" class="sr-only peer">
                            <div class="w-7 h-4 bg-gray-200 rounded-full peer peer-checked:bg-blue-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:after:translate-x-3"></div>
                        </label>
                    </div>
                </div>

                <button type="button" onclick="triggerSubmit()" class="w-full bg-blue-600 text-white py-3 rounded-lg text-xs font-bold uppercase tracking-wider hover:bg-blue-700 transition-all shadow-sm">
                    Perbarui Prediksi
                </button>
            </div>
        </div>

        {{-- STATISTIK --}}
        <div class="bg-gradient-to-br from-blue-600 to-blue-700 rounded-lg shadow-lg p-5 text-white">
            <h4 class="text-xs font-bold uppercase tracking-wider mb-3 opacity-90">
                Ringkasan Statistik
            </h4>
            <div class="space-y-3">
                <div class="flex justify-between items-end border-b border-white/10 pb-2">
                    <span class="text-[10px] opacity-70 font-semibold uppercase">MAPE (Akurasi)</span>
                    <span class="text-sm font-bold">{{ number_format($mape ?? 0, 2) }}%</span>
                </div>
                <div class="flex justify-between items-end border-b border-white/10 pb-2">
                    <span class="text-[10px] opacity-70 font-semibold uppercase">Skor R-Squared</span>
                    <span class="text-sm font-bold">{{ number_format($rSquared ?? 0, 3) }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- GRAFIK UTAMA DENGAN FILTER - IMPROVED HEIGHT --}}
    <div class="col-span-12 lg:col-span-8 card-standard overflow-hidden flex flex-col">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex flex-col lg:flex-row justify-between items-center gap-4 flex-shrink-0">
            <div>
                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-tight">Visualisasi Tren & Proyeksi</h3>
                <p class="text-xs text-gray-500">Menampilkan data historis vs hasil algoritma Prophet</p>
            </div>

            <div class="flex bg-white border border-gray-300 p-1 rounded-md shadow-sm">
                <button onclick="changeChartPeriod('daily')" class="filter-btn active" id="btn-daily">Harian</button>
                <button onclick="changeChartPeriod('weekly')" class="filter-btn border-none" id="btn-weekly">Mingguan</button>
                <button onclick="changeChartPeriod('monthly')" class="filter-btn border-none" id="btn-monthly">Bulanan</button>
                <button onclick="changeChartPeriod('yearly')" class="filter-btn border-none" id="btn-yearly">Tahunan</button>
            </div>
        </div>
        
        {{-- CHART CONTAINER WITH OPTIMIZED HEIGHT --}}
        <div class="flex-1 p-6" style="min-height: 500px;">
            <canvas id="mainChart"></canvas>
        </div>
    </div>
</div>

    {{-- TABEL INSIGHT PROYEKSI --}}
    <div class="card-standard overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-tight">Ringkasan Analisis <span id="selectedPeriodText" class="text-blue-600">Harian</span></h3>
        </div>

        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-[11px] font-bold text-gray-500 uppercase bg-gray-50 border-b border-gray-200">
                        <th class="px-6 py-4">Periode</th>
                        <th class="px-6 py-4 text-right">Harga Aktual</th>
                        <th class="px-6 py-4 text-right">Harga Prediksi</th>
                        <th class="px-6 py-4 text-right">Selisih</th>
                        <th class="px-6 py-4 text-center">Indikator</th>
                    </tr>
                </thead>
                <tbody class="text-sm text-gray-700 divide-y divide-gray-100" id="insightTableBody">
                    <!-- Data akan diisi oleh JavaScript -->
                </tbody>
            </table>
        </div>
    </div>

    {{-- KESIMPULAN ANALISIS --}}
    <div class="card-standard p-6 border-l-4 border-l-blue-600">
        <div class="flex items-center gap-3 mb-3">
            <h4 class="text-sm font-bold text-gray-900 uppercase">Interpretasi Model Prophet</h4>
        </div>
        <p class="text-sm text-gray-600 leading-relaxed">
            Berdasarkan analisis data historis untuk komoditas <strong>{{ $selectedCommodity }}</strong>, 
            model mendeteksi pola musiman dan tren jangka panjang. Hasil proyeksi ini dimaksudkan sebagai alat bantu dalam pengambilan kebijakan stabilisasi pasokan dan harga di wilayah Provinsi Riau.
        </p>
    </div>

@endif

{{-- ==========================================
     TAB 2: KELOLA DATA
     ========================================== --}}
@if($currentTab == 'manage')
    <div class="space-y-6 animate-fade-in">
        
        {{-- BARIS 1: Form Tambah Data dan Tabel Riwayat Database --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Form Input (Kiri) --}}
            <div class="lg:col-span-1">
                <div class="card-standard p-6" x-data="{ inputMode: 'single' }">
                    <h3 class="text-sm font-bold text-gray-900 mb-6 uppercase tracking-tight">Tambah Data Baru</h3>
                    <div class="flex gap-4 mb-6 border-b pb-2">
                        <button @click="inputMode = 'single'" :class="inputMode === 'single' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-400'" class="text-xs uppercase tracking-wider pb-1 font-semibold">Manual</button>
                        <button @click="inputMode = 'bulk'" :class="inputMode === 'bulk' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-400'" class="text-xs uppercase tracking-wider pb-1 font-semibold">Unggah CSV</button>
                    </div>

                    {{-- Input Manual --}}
                    <form x-show="inputMode === 'single'" action="{{ route('admin.storeData') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="text-xs font-semibold text-gray-700 uppercase mb-1.5 block tracking-tight">Komoditas</label>
                            <select name="commodity" class="w-full bg-gray-50 border border-gray-200 rounded-lg p-3 text-xs text-gray-900 font-medium outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="Beras Premium">Beras Premium</option>
                                <option value="Cabai Merah">Cabai Merah</option>
                                <option value="Minyak Goreng">Minyak Goreng</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-700 uppercase mb-1.5 block tracking-tight">Tanggal</label>
                            <input type="date" name="date" required class="w-full bg-gray-50 border border-gray-200 rounded-lg p-3 text-xs text-gray-600 font-medium focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-700 uppercase mb-1.5 block tracking-tight">Harga (Rp)</label>
                            <input type="number" name="price" placeholder="Masukkan harga" required class="w-full bg-gray-50 border border-gray-200 rounded-lg p-3 text-xs text-gray-600 font-medium focus:ring-2 focus:ring-blue-500">
                        </div>
                        <button type="submit" class="w-full bg-emerald-500 text-white py-3 rounded-lg text-xs font-bold uppercase tracking-wider hover:bg-emerald-600 transition-all">
                            Simpan Data
                        </button>
                    </form>

                    {{-- Input CSV --}}
                    <form x-show="inputMode === 'bulk'" action="{{ route('admin.storeData') }}" method="POST" enctype="multipart/form-data" class="space-y-4" x-cloak>
                        @csrf
                        <div>
                            <label class="text-xs font-semibold text-gray-700 uppercase mb-1.5 block tracking-tight">Unggah File CSV</label>
                            <div class="p-8 border-2 border-dashed border-gray-200 rounded-xl bg-gray-50/50 text-center relative hover:border-blue-300 transition-colors">
                                <input type="file" name="dataset" accept=".csv" class="absolute inset-0 opacity-0 cursor-pointer">
                                <i class="fas fa-cloud-upload-alt text-gray-300 text-2xl mb-2"></i>
                                <p class="text-xs text-gray-400 font-medium">Pilih atau seret file CSV ke sini</p>
                                <p class="text-[9px] text-gray-300 mt-1">Format: tanggal, komoditas, harga</p>
                            </div>
                        </div>

                        {{-- Template Download --}}
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <div class="flex items-start gap-3">
                                <i class="fas fa-info-circle text-blue-500 text-sm mt-0.5"></i>
                                <div class="flex-1">
                                    <p class="text-xs text-blue-700 font-semibold uppercase tracking-tight mb-2">Template CSV</p>
                                    <p class="text-[10px] text-blue-600 mb-3 leading-relaxed">
                                        Gunakan template standar untuk memastikan format data yang benar
                                    </p>
                                    <a href="{{ route('admin.downloadTemplate') }}" class="inline-flex items-center gap-2 bg-blue-600 text-white px-3 py-2 rounded-lg text-[10px] font-bold uppercase tracking-wider hover:bg-blue-700 transition-colors">
                                        <i class="fas fa-download"></i>
                                        Unduh Template CSV
                                    </a>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="w-full bg-blue-600 text-white py-3 rounded-lg text-xs font-bold uppercase tracking-wider hover:bg-blue-700 transition-all">
                            Unggah Dataset
                        </button>
                    </form>
                </div>
            </div>

            {{-- Tabel Riwayat Database (Kanan) --}}
            <div class="lg:col-span-2">
                <div class="card-standard overflow-hidden flex flex-col" style="height: fit-content;">
                    <div class="p-5 border-b bg-gray-50/50">
                        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-tight">Riwayat Database</h3>
                    </div>

                    <div class="overflow-x-auto" style="max-height: 450px;">
                        <div class="custom-scrollbar">
                            <table class="w-full text-left">
                                <thead class="sticky top-0 bg-white border-b border-gray-100 z-10">
                                    <tr class="text-xs text-gray-400 uppercase font-bold">
                                        <th class="px-6 py-4">Komoditas</th>
                                        <th class="px-6 py-4">Tanggal</th>
                                        <th class="px-6 py-4">Harga</th>
                                        <th class="px-6 py-4 text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 text-xs">
                                    @forelse($latestData ?? $allData ?? [] as $data)
                                        <tr class="hover:bg-gray-50 transition-colors" id="row-{{ $data->id }}">
                                            {{-- Komoditas --}}
                                            <td class="px-6 py-4 uppercase font-bold text-blue-600">
                                                <span class="commodity-view">{{ $data->commodity_name ?? $data->commodity }}</span>
                                                <select class="commodity-edit hidden w-full bg-gray-50 border border-gray-300 rounded-lg p-2 text-xs font-medium focus:ring-2 focus:ring-blue-500" data-id="{{ $data->id }}" onchange="autoSaveData({{ $data->id }})">
                                                    <option value="Beras Premium" {{ ($data->commodity_name ?? $data->commodity) == 'Beras Premium' ? 'selected' : '' }}>Beras Premium</option>
                                                    <option value="Cabai Merah" {{ ($data->commodity_name ?? $data->commodity) == 'Cabai Merah' ? 'selected' : '' }}>Cabai Merah</option>
                                                    <option value="Minyak Goreng" {{ ($data->commodity_name ?? $data->commodity) == 'Minyak Goreng' ? 'selected' : '' }}>Minyak Goreng</option>
                                                </select>
                                            </td>
                                            
                                            {{-- Tanggal --}}
                                            <td class="px-6 py-4 text-gray-500">
                                                <span class="date-view">{{ $data->date }}</span>
                                                <input type="date" class="date-edit hidden w-full bg-gray-50 border border-gray-300 rounded-lg p-2 text-xs focus:ring-2 focus:ring-blue-500" value="{{ $data->date }}" data-id="{{ $data->id }}" onchange="autoSaveData({{ $data->id }})">
                                            </td>
                                            
                                            {{-- Harga --}}
                                            <td class="px-6 py-4 font-bold text-emerald-600">
                                                <span class="price-view">Rp {{ number_format($data->price, 0, ',', '.') }}</span>
                                                <input type="number" class="price-edit hidden w-full bg-gray-50 border border-gray-300 rounded-lg p-2 text-xs focus:ring-2 focus:ring-blue-500" value="{{ $data->price }}" data-id="{{ $data->id }}" onchange="autoSaveData({{ $data->id }})">
                                            </td>
                                            
                                            {{-- Aksi --}}
                                            <td class="px-6 py-4">
                                                <div class="flex items-center justify-center gap-3">
                                                    {{-- Button Edit --}}
                                                    <button type="button" onclick="toggleEditMode({{ $data->id }})" class="edit-btn text-blue-500 hover:text-blue-700 transition-colors text-sm font-medium">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                    
                                                    {{-- Button Selesai (Hidden by default) --}}
                                                    <button type="button" onclick="toggleEditMode({{ $data->id }})" class="done-btn hidden text-green-500 hover:text-green-700 transition-colors text-sm font-medium">
                                                        <i class="fas fa-check"></i> Selesai
                                                    </button>
                                                    
                                                    {{-- Button Hapus --}}
                                                    <form action="{{ route('operator.deleteData', $data->id) }}" method="POST" onsubmit="return confirm('Hapus data ini?')" class="inline delete-form">
                                                        @csrf 
                                                        @method('DELETE')
                                                        <button type="submit" class="text-red-400 hover:text-red-600 transition-colors text-sm font-medium">
                                                            <i class="fas fa-trash"></i> Hapus
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="p-12 text-center text-gray-400">Data tidak ditemukan</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Pagination --}}
                    @if(isset($latestData) && $latestData->hasPages())
                        <div class="px-6 py-4 border-t bg-gray-50/30">
                            <div class="flex items-center justify-between">
                                <div class="text-xs text-gray-500">
                                    Menampilkan {{ $latestData->firstItem() ?? 0 }} - {{ $latestData->lastItem() ?? 0 }} dari {{ $latestData->total() }} data
                                </div>
                                <div class="flex gap-1">
                                    {{-- Previous Button --}}
                                    @if ($latestData->onFirstPage())
                                        <span class="px-3 py-1.5 text-xs text-gray-400 bg-gray-100 rounded cursor-not-allowed">
                                            <i class="fas fa-chevron-left"></i>
                                        </span>
                                    @else
                                        <a href="{{ $latestData->previousPageUrl() }}" class="px-3 py-1.5 text-xs text-gray-700 bg-white border border-gray-200 rounded hover:bg-gray-50 transition-colors">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    @endif

                                    {{-- Page Numbers --}}
                                    @foreach ($latestData->getUrlRange(1, $latestData->lastPage()) as $page => $url)
                                        @if ($page == $latestData->currentPage())
                                            <span class="px-3 py-1.5 text-xs font-bold text-white bg-blue-600 rounded">{{ $page }}</span>
                                        @else
                                            <a href="{{ $url }}" class="px-3 py-1.5 text-xs text-gray-700 bg-white border border-gray-200 rounded hover:bg-gray-50 transition-colors">{{ $page }}</a>
                                        @endif
                                    @endforeach

                                    {{-- Next Button --}}
                                    @if ($latestData->hasMorePages())
                                        <a href="{{ $latestData->nextPageUrl() }}" class="px-3 py-1.5 text-xs text-gray-700 bg-white border border-gray-200 rounded hover:bg-gray-50 transition-colors">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    @else
                                        <span class="px-3 py-1.5 text-xs text-gray-400 bg-gray-100 rounded cursor-not-allowed">
                                            <i class="fas fa-chevron-right"></i>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- BARIS 2: Form Pembersihan Data + Tabel Hasil Pemindaian --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Pembersihan Data (Kiri) --}}
            <div class="lg:col-span-1">
                <div class="card-standard p-6" style="height: fit-content;">
                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-tight mb-6">Pembersihan Data</h3>

                    <form action="{{ route('admin.predict') }}" method="POST" class="mb-6 pb-6 border-b border-gray-100">
                        @csrf
                        <input type="hidden" name="tab" value="manage">
                        <label class="text-xs text-gray-700 font-semibold block mb-2 uppercase tracking-tight">Pindai Data Untuk</label>
                        <div class="flex gap-2">
                            <select name="commodity" class="flex-1 bg-gray-50 border border-gray-200 rounded-lg p-2 text-xs font-medium outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="Beras Premium" {{ $selectedCommodity == 'Beras Premium' ? 'selected' : '' }}>Beras Premium</option>
                                <option value="Cabai Merah" {{ $selectedCommodity == 'Cabai Merah' ? 'selected' : '' }}>Cabai Merah</option>
                                <option value="Minyak Goreng" {{ $selectedCommodity == 'Minyak Goreng' ? 'selected' : '' }}>Minyak Goreng</option>
                            </select>
                            <button type="submit" class="bg-blue-600 text-white px-4 rounded-lg text-xs font-bold uppercase hover:bg-blue-700">Pindai</button>
                        </div>
                    </form>
                    
                    <form action="{{ route('admin.cleanData') }}" method="POST" class="space-y-6">
                        @csrf
                        <input type="hidden" name="commodity" value="{{ $selectedCommodity }}">
                        <div class="space-y-4">
                            <div>
                                <label class="text-xs text-gray-700 font-semibold block mb-2 uppercase tracking-tight">Deteksi Outlier</label>
                                <div class="flex items-center gap-2">
                                    <select name="outlier_method" class="flex-1 bg-gray-50 border border-gray-200 rounded-lg p-2.5 text-xs font-medium outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="remove">Hapus Outlier</option>
                                        <option value="mean">Ganti dengan Rata-rata</option>
                                        <option value="median">Ganti dengan Median</option>
                                    </select>
                                    <button type="submit" name="action" value="outlier" class="bg-orange-500 text-white px-4 py-2.5 rounded-lg text-xs font-bold hover:bg-orange-600 whitespace-nowrap">
                                        Terapkan
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label class="text-xs text-gray-700 font-semibold block mb-2 uppercase tracking-tight">Nilai Hilang</label>
                                <div class="flex items-center gap-2">
                                    <select name="missing_method" class="flex-1 bg-gray-50 border border-gray-200 rounded-lg p-2.5 text-xs font-medium outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="mean">Isi dengan Rata-rata</option>
                                        <option value="median">Isi dengan Median</option>
                                        <option value="remove">Hapus Data Kosong</option>
                                    </select>
                                    <button type="submit" name="action" value="missing" class="bg-blue-600 text-white px-4 py-2.5 rounded-lg text-xs font-bold hover:bg-blue-700 whitespace-nowrap">
                                        Terapkan
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Tabel Hasil Pemindaian (Kanan) --}}
            <div class="lg:col-span-2">
                <div class="card-standard border-orange-200 overflow-hidden flex flex-col" style="height: fit-content;">
                    <div class="p-4 bg-orange-50/50 border-b border-orange-100 flex justify-between items-center">
                        <h3 class="text-xs text-orange-700 font-bold uppercase tracking-tight">Hasil Pemindaian: {{ $selectedCommodity }}</h3>
                        <span class="bg-orange-100 text-orange-600 px-2 py-0.5 rounded text-[10px] font-bold">{{ ($dataIssues instanceof \Illuminate\Pagination\LengthAwarePaginator ? $dataIssues->total() : count($dataIssues ?? [])) }} Temuan</span>
                    </div>
                    <div class="overflow-x-auto" style="max-height: 350px;">
                        <div class="custom-scrollbar">
                            <table class="w-full text-left">
                                <thead class="bg-gray-50 sticky top-0 text-xs text-gray-400 uppercase font-bold z-10">
                                    <tr>
                                        <th class="px-6 py-3">Tanggal</th>
                                        <th class="px-6 py-3">Jenis Masalah</th>
                                        <th class="px-6 py-3">Nilai</th>
                                        <th class="px-6 py-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 text-xs">
                                    @forelse($dataIssues ?? [] as $issue)
                                        <tr class="bg-orange-50/20 hover:bg-orange-50/40 transition-colors">
                                            <td class="px-6 py-3 font-medium">{{ $issue->date }}</td>
                                            <td class="px-6 py-3">
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $issue->issue == 'Outlier' ? 'bg-red-100 text-red-600' : 'bg-yellow-100 text-yellow-600' }}">
                                                    {{ $issue->issue }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-3 font-medium">Rp {{ number_format($issue->value, 0) }}</td>
                                            <td class="px-6 py-3 text-gray-500">{{ $issue->status }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="p-8 text-center text-gray-400">Tidak ada masalah yang terdeteksi</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Pagination untuk Hasil Pemindaian --}}
                    @if(isset($dataIssues) && $dataIssues instanceof \Illuminate\Pagination\LengthAwarePaginator && $dataIssues->hasPages())
                        <div class="px-6 py-4 border-t bg-orange-50/20">
                            <div class="flex items-center justify-between">
                                <div class="text-xs text-gray-500">
                                    Menampilkan {{ $dataIssues->firstItem() ?? 0 }} - {{ $dataIssues->lastItem() ?? 0 }} dari {{ $dataIssues->total() }} temuan
                                </div>
                                <div class="flex gap-1">
                                    {{-- Previous Button --}}
                                    @if ($dataIssues->onFirstPage())
                                        <span class="px-3 py-1.5 text-xs text-gray-400 bg-gray-100 rounded cursor-not-allowed">
                                            <i class="fas fa-chevron-left"></i>
                                        </span>
                                    @else
                                        <a href="{{ $dataIssues->previousPageUrl() }}" class="px-3 py-1.5 text-xs text-gray-700 bg-white border border-gray-200 rounded hover:bg-gray-50 transition-colors">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    @endif

                                    {{-- Page Numbers --}}
                                    @foreach ($dataIssues->getUrlRange(1, $dataIssues->lastPage()) as $page => $url)
                                        @if ($page == $dataIssues->currentPage())
                                            <span class="px-3 py-1.5 text-xs font-bold text-white bg-orange-600 rounded">{{ $page }}</span>
                                        @else
                                            <a href="{{ $url }}" class="px-3 py-1.5 text-xs text-gray-700 bg-white border border-gray-200 rounded hover:bg-gray-50 transition-colors">{{ $page }}</a>
                                        @endif
                                    @endforeach

                                    {{-- Next Button --}}
                                    @if ($dataIssues->hasMorePages())
                                        <a href="{{ $dataIssues->nextPageUrl() }}" class="px-3 py-1.5 text-xs text-gray-700 bg-white border border-gray-200 rounded hover:bg-gray-50 transition-colors">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    @else
                                        <span class="px-3 py-1.5 text-xs text-gray-400 bg-gray-100 rounded cursor-not-allowed">
                                            <i class="fas fa-chevron-right"></i>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

    </div>
@endif

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
/* =========================================================
   DATA INITIALIZATION
   ========================================================= */
const chartData = {
    daily: {
        labels: {!! json_encode($chartLabels ?? []) !!},
        actual: {!! json_encode($actualData ?? []) !!},
        forecast: {!! json_encode($forecastData ?? []) !!},
        lower: {!! json_encode($lowerBand ?? []) !!},
        upper: {!! json_encode($upperBand ?? []) !!}
    },
    weekly: {
        labels: {!! json_encode($weeklyLabels ?? []) !!},
        actual: {!! json_encode($weeklyActual ?? []) !!},
        forecast: {!! json_encode($weeklyForecast ?? []) !!},
        lower: {!! json_encode($weeklyLower ?? []) !!},
        upper: {!! json_encode($weeklyUpper ?? []) !!}
    },
    monthly: {
        labels: {!! json_encode($monthlyLabels ?? []) !!},
        actual: {!! json_encode($monthlyActual ?? []) !!},
        forecast: {!! json_encode($monthlyForecast ?? []) !!},
        lower: {!! json_encode($monthlyLower ?? []) !!},
        upper: {!! json_encode($monthlyUpper ?? []) !!}
    },
    yearly: {
        labels: {!! json_encode($yearlyLabels ?? []) !!},
        actual: {!! json_encode($yearlyActual ?? []) !!},
        forecast: {!! json_encode($yearlyForecast ?? []) !!},
        lower: {!! json_encode($yearlyLower ?? []) !!},
        upper: {!! json_encode($yearlyUpper ?? []) !!}
    }
};

let currentPeriod = 'daily';
let mainChart = null;

/* =========================================================
   UTILITY FUNCTIONS
   ========================================================= */
function updateVal(hiddenId, displayId, val) {
    document.getElementById(hiddenId).value = val;
    document.getElementById(displayId).innerText = val;
}

function triggerSubmit() {
    document.getElementById('real-content').classList.add('opacity-30', 'blur-[2px]');
    document.getElementById('skeleton-overlay').classList.remove('hidden');
    setTimeout(() => document.getElementById('mainForm').submit(), 100);
}

function handleCommodityChange() {
    triggerSubmit();
}

/* =========================================================
   CHART FUNCTIONS
   ========================================================= */
@if(($currentTab ?? 'insight') == 'insight')

function initializeChart() {
    const canvas = document.getElementById('mainChart');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    const data = chartData[currentPeriod];

    const gradientActual = ctx.createLinearGradient(0, 0, 0, 400);
    gradientActual.addColorStop(0, 'rgba(4, 50, 119, 0.15)');
    gradientActual.addColorStop(1, 'rgba(4, 50, 119, 0)');

    const gradientForecast = ctx.createLinearGradient(0, 0, 0, 400);
    gradientForecast.addColorStop(0, 'rgba(249, 115, 22, 0.15)');
    gradientForecast.addColorStop(1, 'rgba(249, 115, 22, 0)');

    if (mainChart) {
        mainChart.destroy();
    }

    mainChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [
                {
                    label: 'Rentang Bawah',
                    data: data.lower,
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    borderColor: 'transparent',
                    fill: '+1',
                    pointRadius: 0,
                    tension: 0.4
                },
                {
                    label: 'Rentang Atas',
                    data: data.upper,
                    borderColor: 'transparent',
                    fill: false,
                    pointRadius: 0,
                    tension: 0.4
                },
                {
                    label: 'Harga Aktual',
                    data: data.actual,
                    borderColor: '#043277',
                    backgroundColor: gradientActual,
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 0,
                    pointHoverRadius: 6,
                    pointHoverBackgroundColor: '#043277',
                    pointHoverBorderColor: '#fff',
                    pointHoverBorderWidth: 2
                },
                {
                    label: 'Harga Prediksi',
                    data: data.forecast,
                    borderColor: '#f97316',
                    backgroundColor: gradientForecast,
                    borderDash: [8, 4],
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 0,
                    pointHoverRadius: 6,
                    pointHoverBackgroundColor: '#f97316',
                    pointHoverBorderColor: '#fff',
                    pointHoverBorderWidth: 2
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { 
                intersect: false, 
                mode: 'index' 
            },
            plugins: {
                title: {
                    display: true,
                    text: '{{ $selectedCommodity }}',
                    color: '#043277',
                    font: {
                        size: 14,
                        weight: '600',
                        family: 'Inter'
                    },
                    padding: {
                        top: 10,
                        bottom: 15
                    }
                },
                legend: {
                    display: true,
                    position: 'top',
                    align: 'end',
                    labels: {
                        boxWidth: 12,
                        boxHeight: 12,
                        padding: 15,
                        font: { size: 11, weight: '600' },
                        color: '#64748b',
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                },
                tooltip: {
                    backgroundColor: '#ffffff',
                    titleColor: '#1e293b',
                    bodyColor: '#475569',
                    borderColor: '#e2e8f0',
                    borderWidth: 1,
                    padding: 12,
                    boxPadding: 6,
                    usePointStyle: true,
                    titleFont: { size: 11, weight: '600' },
                    bodyFont: { size: 11 },
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) label += ': ';
                            if (context.parsed.y !== null) {
                                label += new Intl.NumberFormat('id-ID', {
                                    style: 'currency',
                                    currency: 'IDR',
                                    maximumFractionDigits: 0
                                }).format(context.parsed.y);
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: false,
                    grid: { color: '#f1f5f9', drawBorder: false },
                    ticks: {
                        color: '#94a3b8',
                        font: { size: 10, weight: '500' },
                        padding: 8,
                        callback: value => 'Rp ' + value.toLocaleString('id-ID')
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: {
                        color: '#94a3b8',
                        font: { size: 9, weight: '500' },
                        maxRotation: 45,
                        minRotation: 0,
                        autoSkip: true,
                        maxTicksLimit: 12
                    }
                }
            }
        }
    });
}

function changeChartPeriod(period) {
    currentPeriod = period;
    
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.getElementById(`btn-${period}`).classList.add('active');
    
    const periodText = {
        'daily': 'Harian',
        'weekly': 'Mingguan',
        'monthly': 'Bulanan',
        'yearly': 'Tahunan'
    };
    document.getElementById('selectedPeriodText').textContent = periodText[period];
    
    initializeChart();
    updateInsightTable();
}

function updateInsightTable() {
    const data = chartData[currentPeriod];
    const tbody = document.getElementById('insightTableBody');
    
    if (!tbody || !data.labels.length) return;
    
    tbody.innerHTML = '';
    
    const start = Math.max(0, data.labels.length - 10);
    
    for (let i = start; i < data.labels.length; i++) {
        const actual = data.actual[i];
        const forecast = data.forecast[i];
        const diff = actual && forecast ? forecast - actual : null;
        
        let insight = 'Stabil';
        let insightClass = 'insight-stabil';
        
        if (diff !== null) {
            if (diff > 500) {
                insight = 'Naik';
                insightClass = 'insight-naik';
            } else if (diff < -500) {
                insight = 'Turun';
                insightClass = 'insight-turun';
            }
        }
        
        const row = `
            <tr class="border-b border-gray-50 hover:bg-gray-50 animate-fade-in">
                <td class="px-6 py-4 text-gray-500 font-medium">
                    ${data.labels[i]}
                </td>
                <td class="px-6 py-4 text-right">
                    ${actual ? 'Rp ' + actual.toLocaleString('id-ID') : '—'}
                </td>
                <td class="px-6 py-4 text-right text-blue-600 font-bold">
                    Rp ${forecast.toLocaleString('id-ID')}
                </td>
                <td class="px-6 py-4 text-right ${diff > 0 ? 'text-red-600' : diff < 0 ? 'text-emerald-600' : 'text-gray-500'}">
                    ${diff !== null ? (diff > 0 ? '+' : '') + diff.toLocaleString('id-ID') : '—'}
                </td>
                <td class="px-6 py-4 text-center">
                    <span class="insight-badge ${insightClass}">
                        ${insight}
                    </span>
                </td>
            </tr>
        `;
        
        tbody.innerHTML += row;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    initializeChart();
    updateInsightTable();
});

@endif
// Toggle mode edit/view
function toggleEditMode(id) {
    const row = document.getElementById(`row-${id}`);
    const isEditing = row.querySelector('.commodity-edit').classList.contains('hidden');
    
    if (isEditing) {
        // Masuk mode edit
        row.querySelector('.commodity-view').classList.add('hidden');
        row.querySelector('.commodity-edit').classList.remove('hidden');
        row.querySelector('.date-view').classList.add('hidden');
        row.querySelector('.date-edit').classList.remove('hidden');
        row.querySelector('.price-view').classList.add('hidden');
        row.querySelector('.price-edit').classList.remove('hidden');
        
        // Toggle buttons
        row.querySelector('.edit-btn').classList.add('hidden');
        row.querySelector('.done-btn').classList.remove('hidden');
        row.querySelector('.delete-form').style.opacity = '0.3';
        row.querySelector('.delete-form').style.pointerEvents = 'none';
        
        // Highlight row
        row.classList.add('bg-blue-50', 'border-l-4', 'border-l-blue-500');
    } else {
        // Keluar mode edit
        row.querySelector('.commodity-view').classList.remove('hidden');
        row.querySelector('.commodity-edit').classList.add('hidden');
        row.querySelector('.date-view').classList.remove('hidden');
        row.querySelector('.date-edit').classList.add('hidden');
        row.querySelector('.price-view').classList.remove('hidden');
        row.querySelector('.price-edit').classList.add('hidden');
        
        // Toggle buttons
        row.querySelector('.edit-btn').classList.remove('hidden');
        row.querySelector('.done-btn').classList.add('hidden');
        row.querySelector('.delete-form').style.opacity = '1';
        row.querySelector('.delete-form').style.pointerEvents = 'auto';
        
        // Remove highlight
        row.classList.remove('bg-blue-50', 'border-l-4', 'border-l-blue-500');
    }
}

// Auto-save saat ada perubahan
function autoSaveData(id) {
    const row = document.getElementById(`row-${id}`);
    const commodity = row.querySelector('.commodity-edit').value;
    const date = row.querySelector('.date-edit').value;
    const price = row.querySelector('.price-edit').value;
    
    // Validasi
    if (!commodity || !date || !price) {
        showNotification('Semua field harus diisi!', 'error');
        return;
    }
    
    if (price < 0) {
        showNotification('Harga tidak boleh negatif!', 'error');
        return;
    }
    
    // Tampilkan loading indicator
    const originalBg = row.style.backgroundColor;
    row.style.backgroundColor = '#fef3c7';
    
    // Kirim data via AJAX
    fetch(`{{ url('/admin/update-data') }}/${id}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            commodity: commodity,
            date: date,
            price: price
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update tampilan
            row.querySelector('.commodity-view').textContent = commodity;
            row.querySelector('.date-view').textContent = date;
            row.querySelector('.price-view').textContent = 'Rp ' + parseInt(price).toLocaleString('id-ID');
            
            // Flash sukses
            row.style.backgroundColor = '#d1fae5';
            setTimeout(() => {
                row.style.backgroundColor = originalBg;
            }, 500);
            
            showNotification('Data tersimpan otomatis!', 'success');
        } else {
            showNotification('Gagal menyimpan: ' + (data.message || 'Terjadi kesalahan'), 'error');
            row.style.backgroundColor = originalBg;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Terjadi kesalahan saat menyimpan data', 'error');
        row.style.backgroundColor = originalBg;
    });
}

// Fungsi untuk menampilkan notifikasi
function showNotification(message, type = 'success') {
    // Hapus notifikasi sebelumnya jika ada
    const existing = document.querySelector('.toast-notification');
    if (existing) existing.remove();
    
    const notification = document.createElement('div');
    notification.className = `toast-notification fixed top-4 right-4 z-50 px-6 py-4 rounded-lg shadow-lg text-white text-sm font-medium animate-fade-in ${
        type === 'success' ? 'bg-green-500' : 'bg-red-500'
    }`;
    notification.innerHTML = `
        <div class="flex items-center gap-3">
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
            <span>${message}</span>
        </div>
    `;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transform = 'translateX(100%)';
        notification.style.transition = 'all 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

</script>

@endsection