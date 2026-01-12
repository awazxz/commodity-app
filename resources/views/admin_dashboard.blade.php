@extends('layouts.app')

@section('content')
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    body, div, h2, h3, h4, p, span, table, button, select, input {
        font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, sans-serif !important;
        font-style: normal !important;
    }

    .font-clean { font-weight: 400 !important; }
    .font-medium-clean { font-weight: 600 !important; }
    .tracking-widest { letter-spacing: 0.12em !important; }

    .card-custom {
        background: white;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
    }

    .hover-card {
        transition: all 0.3s ease;
    }

    .hover-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }

    .filter-btn {
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        transition: all 0.2s;
        border: 1px solid #e2e8f0;
        background: white;
        color: #64748b;
    }

    .filter-btn.active {
        background: #043277;
        color: white;
        border-color: #043277;
        box-shadow: 0 2px 8px rgba(4, 50, 119, 0.25);
    }

    .filter-btn:hover:not(.active) {
        background: #f8fafc;
        border-color: #cbd5e1;
    }

    .insight-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 9px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .insight-naik {
        background: #fee2e2;
        color: #991b1b;
    }

    .insight-turun {
        background: #d1fae5;
        color: #065f46;
    }

    .insight-stabil {
        background: #e0e7ff;
        color: #3730a3;
    }

    input[type="range"]::-webkit-slider-thumb {
        height: 16px;
        width: 16px;
        border-radius: 50%;
        background: #043277;
        cursor: pointer;
        border: 2px solid #fff;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        appearance: none;
        -webkit-appearance: none;
    }

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

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .animate-fade-in {
        animation: fadeIn 0.4s ease-out;
    }

    [x-cloak] { display: none !important; }
</style>

<div id="skeleton-overlay" class="hidden space-y-4">
    <div class="h-16 bg-slate-200 animate-pulse rounded-xl"></div>
    <div class="h-[400px] bg-slate-200 animate-pulse rounded-2xl"></div>
</div>

<div id="real-content" class="space-y-5 transition-all duration-500 font-clean">

{{-- ==========================================
     TAB 1: TAMPILAN ANALISIS
     ========================================== --}}
@if(($currentTab ?? 'insight') == 'insight')

    {{-- HEADER & FILTER KOMODITAS --}}
    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="flex items-center gap-4">
                <div class="bg-[#043277] p-2.5 rounded-lg text-white shadow-lg shadow-blue-900/20">
                    <i class="fas fa-chart-line text-xl"></i>
                </div>
                <div>
                    <h2 class="text-lg font-medium-clean text-[#043277] tracking-tighter uppercase leading-none mt-1">
                        Sistem Analisis Prediksi Harga Komoditas
                    </h2>
                    <p class="text-[9px] text-orange-500 font-medium-clean uppercase tracking-widest mt-1">
                        Panel Administrator • Kontrol Penuh
                    </p>
                </div>
            </div>
            <button onclick="window.print()" class="bg-[#58a832] hover:bg-green-600 text-white px-4 py-2 rounded-lg font-medium-clean text-[9px] uppercase tracking-widest shadow-sm flex items-center gap-2">
                <i class="fas fa-print"></i> Cetak Laporan
            </button>
        </div>

        <form action="{{ route('admin.predict') }}" method="POST" id="mainForm" class="mt-6 pt-5 border-t border-slate-100">
            @csrf
            <div class="grid grid-cols-12 gap-4">
                <div class="col-span-12 md:col-span-4">
                    <label class="text-[9px] font-medium-clean text-slate-400 uppercase mb-1.5 block tracking-widest">
                        Pilih Komoditas
                    </label>
                    <select name="commodity" onchange="handleCommodityChange()" class="w-full bg-slate-50 border border-slate-200 rounded-lg py-2 px-3 text-xs font-medium-clean text-[#043277]">
                        <option value="Beras Premium" {{ $selectedCommodity == 'Beras Premium' ? 'selected' : '' }}>Beras Premium</option>
                        <option value="Cabai Merah" {{ $selectedCommodity == 'Cabai Merah' ? 'selected' : '' }}>Cabai Merah</option>
                        <option value="Minyak Goreng" {{ $selectedCommodity == 'Minyak Goreng' ? 'selected' : '' }}>Minyak Goreng</option>
                    </select>
                </div>

                <div class="col-span-12 md:col-span-8">
                    <label class="text-[9px] font-medium-clean text-slate-400 uppercase mb-1.5 block tracking-widest">
                        Rentang Waktu Historis
                    </label>
                    <div class="flex items-center gap-2 bg-slate-50 p-1 rounded-lg border">
                        <input type="date" name="start_date" value="{{ $startDate }}" onchange="triggerSubmit()" class="bg-transparent text-[10px] p-1.5 outline-none flex-1">
                        <span class="text-slate-300 text-xs">—</span>
                        <input type="date" name="end_date" value="{{ $endDate }}" onchange="triggerSubmit()" class="bg-transparent text-[10px] p-1.5 outline-none flex-1">
                    </div>
                </div>
            </div>

            <input type="hidden" name="changepoint_prior_scale" id="hidden_cp" value="{{ $cpScale }}">
            <input type="hidden" name="seasonality_prior_scale" id="hidden_season" value="{{ $seasonScale ?? 10 }}">
            <input type="hidden" name="seasonality_mode" id="hidden_mode" value="{{ $seasonMode ?? 'multiplicative' }}">
            <input type="hidden" name="weekly_seasonality" id="hidden_weekly" value="{{ ($weeklySeason ?? false) ? 'true' : 'false' }}">
            <input type="hidden" name="yearly_seasonality" id="hidden_yearly" value="{{ ($yearlySeason ?? false) ? 'true' : 'false' }}">
            <input type="hidden" name="tab" value="insight">
        </form>
    </div>

    {{-- METRIC CARDS --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="card-custom hover-card p-4">
            <p class="text-[8px] uppercase text-slate-400 tracking-widest">Rata-rata Harga</p>
            <p class="text-lg font-medium-clean text-[#043277]">
                Rp {{ number_format($avgPrice ?? 0,0,',','.') }}
            </p>
        </div>

        <div class="card-custom hover-card p-4">
            <p class="text-[8px] uppercase text-red-400 tracking-widest">Harga Tertinggi</p>
            <p class="text-lg font-medium-clean text-red-600">
                Rp {{ number_format($maxPrice ?? 0,0,',','.') }}
            </p>
        </div>

        <div class="card-custom hover-card p-4">
            <p class="text-[8px] uppercase text-slate-400 tracking-widest">Cakupan Data</p>
            <p class="text-xs text-slate-600">
                {{ $startDate }}<br>{{ $endDate }}
            </p>
        </div>

        <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl p-4 text-white">
            <p class="text-[8px] uppercase text-white/80 tracking-widest">Proyeksi Tren</p>
            <p class="text-xs font-bold uppercase">
                {{ $trendDir ?? 'Naik' }}
            </p>
        </div>
    </div>

    {{-- LAYOUT GRAFIK & HYPERPARAMETER --}}
    <div class="grid grid-cols-12 gap-5">
        
        {{-- HYPERPARAMETER SETTINGS --}}
        <div class="col-span-12 lg:col-span-4 space-y-4">
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                <h4 class="text-[10px] text-[#043277] font-medium-clean uppercase tracking-widest mb-6 pb-3 border-b border-slate-100">
                    Pengaturan Hyperparameter
                </h4>
                <div class="space-y-6">
                    {{-- Changepoint Prior Scale --}}
                    <div>
                        <div class="flex justify-between mb-2">
                            <span class="text-[9px] text-slate-500 font-medium-clean uppercase">Changepoint Prior</span>
                            <span class="text-[10px] font-mono font-medium-clean text-blue-600 bg-blue-50 px-2 py-0.5 rounded" id="cp_display">{{ $cpScale }}</span>
                        </div>
                        <input type="range" min="0.001" max="0.5" step="0.001" value="{{ $cpScale }}" class="w-full h-1 bg-slate-100 rounded-lg appearance-none cursor-pointer" oninput="updateVal('hidden_cp', 'cp_display', this.value)">
                        <p class="text-[8px] text-slate-400 mt-1">Fleksibilitas perubahan tren</p>
                    </div>

                    {{-- Seasonality Prior Scale --}}
                    <div>
                        <div class="flex justify-between mb-2">
                            <span class="text-[9px] text-slate-500 font-medium-clean uppercase">Seasonality Prior</span>
                            <span class="text-[10px] font-mono font-medium-clean text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded" id="season_display">{{ $seasonScale ?? 10 }}</span>
                        </div>
                        <input type="range" min="0.01" max="10" step="0.01" value="{{ $seasonScale ?? 10 }}" class="w-full h-1 bg-slate-100 rounded-lg appearance-none cursor-pointer" oninput="updateVal('hidden_season', 'season_display', this.value)">
                        <p class="text-[8px] text-slate-400 mt-1">Kekuatan pola musiman</p>
                    </div>

                    {{-- Seasonality Mode --}}
                    <div>
                        <label class="text-[9px] text-slate-500 font-medium-clean uppercase mb-2 block">Mode Musiman</label>
                        <select onchange="document.getElementById('hidden_mode').value = this.value" class="w-full bg-slate-50 border border-slate-200 rounded-lg py-2 px-3 text-[10px] text-slate-600 font-medium-clean outline-none">
                            <option value="multiplicative" {{ ($seasonMode ?? '') == 'multiplicative' ? 'selected' : '' }}>Multiplikatif</option>
                            <option value="additive" {{ ($seasonMode ?? '') == 'additive' ? 'selected' : '' }}>Aditif</option>
                        </select>
                        <p class="text-[8px] text-slate-400 mt-1">Metode penerapan musiman</p>
                    </div>

                    {{-- Komponen Musiman --}}
                    <div class="space-y-2 pt-2 border-t border-slate-100">
                        <label class="text-[9px] text-slate-500 font-medium-clean uppercase block mb-2">Komponen Musiman</label>
                        <div class="flex items-center justify-between p-2.5 bg-slate-50 rounded-lg border border-slate-100">
                            <span class="text-[9px] text-slate-500 font-medium-clean uppercase">Mingguan</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" {{ ($weeklySeason ?? false) ? 'checked' : '' }} onchange="document.getElementById('hidden_weekly').value = this.checked" class="sr-only peer">
                                <div class="w-7 h-4 bg-slate-200 rounded-full peer peer-checked:bg-blue-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:after:translate-x-3"></div>
                            </label>
                        </div>
                        <div class="flex items-center justify-between p-2.5 bg-slate-50 rounded-lg border border-slate-100">
                            <span class="text-[9px] text-slate-500 font-medium-clean uppercase">Tahunan</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" {{ ($yearlySeason ?? false) ? 'checked' : '' }} onchange="document.getElementById('hidden_yearly').value = this.checked" class="sr-only peer">
                                <div class="w-7 h-4 bg-slate-200 rounded-full peer peer-checked:bg-blue-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:after:translate-x-3"></div>
                            </label>
                        </div>
                    </div>

                    <button type="button" onclick="triggerSubmit()" class="w-full bg-[#043277] text-white py-3 rounded-lg text-[9px] font-medium-clean uppercase tracking-widest hover:bg-blue-800 transition-all shadow-lg">
                        Perbarui Prediksi
                    </button>
                </div>
            </div>

            {{-- STATISTIK --}}
            <div class="bg-gradient-to-br from-[#043277] to-[#0a469b] rounded-xl shadow-xl p-5 text-white">
                <h4 class="text-[10px] font-medium-clean uppercase tracking-widest mb-4 opacity-90">
                    Ringkasan Statistik
                </h4>
                <div class="space-y-4">
                    <div class="flex justify-between items-end border-b border-white/10 pb-2">
                        <span class="text-[9px] opacity-70 font-medium-clean uppercase">MAPE (Akurasi)</span>
                        <span class="text-xs font-medium-clean">{{ number_format($mape ?? 0, 2) }}%</span>
                    </div>
                    <div class="flex justify-between items-end border-b border-white/10 pb-2">
                        <span class="text-[9px] opacity-70 font-medium-clean uppercase">Skor R-Squared</span>
                        <span class="text-xs font-medium-clean">{{ number_format($rSquared ?? 0, 3) }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- GRAFIK UTAMA DENGAN FILTER --}}
        <div class="col-span-12 lg:col-span-8 card-custom overflow-hidden">
            <div class="px-5 py-4 border-b bg-slate-50/50 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h3 class="text-[10px] font-medium-clean uppercase tracking-widest text-[#043277]">
                        Grafik Tren Harga & Proyeksi
                    </h3>
                    <p class="text-[9px] text-slate-400 mt-1">
                        Komoditas: <span class="font-medium-clean text-blue-600">{{ $selectedCommodity }}</span> • Visualisasi data historis dan prediksi
                    </p>
                </div>

                <div class="flex gap-2">
                    <button onclick="changeChartPeriod('daily')" class="filter-btn active" id="btn-daily">
                        Harian
                    </button>
                    <button onclick="changeChartPeriod('weekly')" class="filter-btn" id="btn-weekly">
                        Mingguan
                    </button>
                    <button onclick="changeChartPeriod('monthly')" class="filter-btn" id="btn-monthly">
                        Bulanan
                    </button>
                    <button onclick="changeChartPeriod('yearly')" class="filter-btn" id="btn-yearly">
                        Tahunan
                    </button>
                </div>
            </div>
            
            <div class="p-6 h-[450px]">
                <canvas id="mainChart"></canvas>
            </div>
        </div>
    </div>

    {{-- TABEL INSIGHT PROYEKSI --}}
    <div class="card-custom overflow-hidden">
        <div class="px-5 py-4 border-b bg-slate-50/50">
            <h3 class="text-[10px] font-medium-clean text-[#043277] uppercase tracking-widest">
                Ringkasan Proyeksi & Analisis Tren
            </h3>
            <p class="text-[9px] text-slate-400 mt-1">
                Data proyeksi berdasarkan periode waktu terpilih: <strong id="selectedPeriodText">Harian</strong>
            </p>
        </div>

        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left font-clean" id="insightTable">
                <thead>
                    <tr class="text-[9px] font-medium-clean text-slate-400 uppercase bg-white border-b border-slate-100">
                        <th class="px-6 py-4">Tanggal/Periode</th>
                        <th class="px-6 py-4 text-right">Harga Aktual</th>
                        <th class="px-6 py-4 text-right">Harga Prediksi</th>
                        <th class="px-6 py-4 text-right">Selisih</th>
                        <th class="px-6 py-4 text-center">Insight Tren</th>
                    </tr>
                </thead>
                <tbody class="text-[11px] font-medium-clean text-slate-600" id="insightTableBody">
                    <!-- Data akan diisi oleh JavaScript -->
                </tbody>
            </table>
        </div>
    </div>

    {{-- KESIMPULAN ANALISIS --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-1 h-6 bg-blue-600 rounded-full"></div>
            <h4 class="text-[11px] text-[#043277] font-bold uppercase tracking-widest">
                Kesimpulan Analisis
            </h4>
        </div>

        <div class="bg-blue-50/50 border-l-4 border-blue-500 p-6 rounded-r-xl">
            <p class="text-xs text-slate-700 leading-relaxed">
                Berdasarkan hasil pemodelan statistik menggunakan algoritma
                <strong>Prophet</strong>, pergerakan harga komoditas <strong>{{ $selectedCommodity }}</strong> 
                menunjukkan kecenderungan tren yang relatif konsisten pada berbagai horizon waktu.
                Sistem ini menganalisis pola historis dan proyeksi masa depan untuk membantu dalam 
                pengambilan keputusan terkait stabilitas harga komoditas di Provinsi Riau.
            </p>
        </div>
    </div>

@endif

{{-- ==========================================
     TAB 2: KELOLA DATA
     ========================================== --}}
@if($currentTab == 'manage')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 animate-fade-in">
        <div class="lg:col-span-1 space-y-6">
            {{-- Form Input --}}
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm" x-data="{ inputMode: 'single' }">
                <h3 class="text-[11px] text-[#043277] font-medium-clean mb-6 uppercase tracking-widest">Tambah Data Baru</h3>
                <div class="flex gap-4 mb-6 border-b pb-2">
                    <button @click="inputMode = 'single'" :class="inputMode === 'single' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-slate-400'" class="text-[9px] uppercase tracking-widest pb-1 font-medium-clean">Manual</button>
                    <button @click="inputMode = 'bulk'" :class="inputMode === 'bulk' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-slate-400'" class="text-[9px] uppercase tracking-widest pb-1 font-medium-clean">Unggah CSV</button>
                </div>

                {{-- Input Manual --}}
                <form x-show="inputMode === 'single'" action="{{ route('admin.storeData') }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="text-[9px] font-medium-clean text-slate-400 uppercase mb-1.5 block tracking-widest">Komoditas</label>
                        <select name="commodity" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-3 text-xs text-[#043277] font-medium-clean outline-none">
                            <option value="Beras Premium">Beras Premium</option>
                            <option value="Cabai Merah">Cabai Merah</option>
                            <option value="Minyak Goreng">Minyak Goreng</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-[9px] font-medium-clean text-slate-400 uppercase mb-1.5 block tracking-widest">Tanggal</label>
                        <input type="date" name="date" required class="w-full bg-slate-50 border border-slate-200 rounded-lg p-3 text-xs text-slate-600 font-medium-clean">
                    </div>
                    <div>
                        <label class="text-[9px] font-medium-clean text-slate-400 uppercase mb-1.5 block tracking-widest">Harga (Rp)</label>
                        <input type="number" name="price" placeholder="Masukkan harga" required class="w-full bg-slate-50 border border-slate-200 rounded-lg p-3 text-xs text-slate-600 font-medium-clean">
                    </div>
                    <button type="submit" class="w-full bg-emerald-500 text-white py-3 rounded-lg text-[9px] font-medium-clean uppercase tracking-widest">
                        Simpan Data
                    </button>
                </form>

                {{-- Input CSV --}}
                <form x-show="inputMode === 'bulk'" action="{{ route('admin.storeData') }}" method="POST" enctype="multipart/form-data" class="space-y-4" x-cloak>
                    @csrf
                    <div>
                        <label class="text-[9px] font-medium-clean text-slate-400 uppercase mb-1.5 block tracking-widest">Unggah File CSV</label>
                        <div class="p-8 border-2 border-dashed border-slate-200 rounded-xl bg-slate-50/50 text-center relative hover:border-blue-300 transition-colors">
                            <input type="file" name="dataset" accept=".csv" class="absolute inset-0 opacity-0 cursor-pointer">
                            <i class="fas fa-cloud-upload-alt text-slate-300 text-2xl mb-2"></i>
                            <p class="text-[10px] text-slate-400 font-medium-clean">Pilih atau seret file CSV ke sini</p>
                            <p class="text-[8px] text-slate-300 mt-1">Format: tanggal, komoditas, harga</p>
                        </div>
                    </div>

                    {{-- Template Download --}}
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex items-start gap-3">
                            <i class="fas fa-info-circle text-blue-500 text-sm mt-0.5"></i>
                            <div class="flex-1">
                                <p class="text-[9px] text-blue-700 font-medium-clean uppercase tracking-widest mb-2">Template CSV</p>
                                <p class="text-[8px] text-blue-600 mb-3 leading-relaxed">
                                    Gunakan template standar untuk memastikan format data yang benar
                                </p>
                                <a href="{{ route('admin.downloadTemplate') }}" class="inline-flex items-center gap-2 bg-blue-600 text-white px-3 py-2 rounded-lg text-[8px] font-medium-clean uppercase tracking-widest hover:bg-blue-700 transition-colors">
                                    <i class="fas fa-download"></i>
                                    Unduh Template CSV
                                </a>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-[#043277] text-white py-3 rounded-lg text-[9px] font-medium-clean uppercase tracking-widest">
                        Unggah Dataset
                    </button>
                </form>
            </div>

            {{-- Pembersihan Data --}}
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                <h3 class="text-[11px] text-[#043277] font-medium-clean uppercase tracking-widest mb-6">Pembersihan Data</h3>

                <form action="{{ route('admin.predict') }}" method="POST" class="mb-6 pb-6 border-b border-slate-100">
                    @csrf
                    <input type="hidden" name="tab" value="manage">
                    <label class="text-[9px] text-slate-400 font-medium-clean block mb-2 uppercase tracking-widest">Pindai Data Untuk</label>
                    <div class="flex gap-2">
                        <select name="commodity" class="flex-1 bg-slate-50 border border-slate-200 rounded-lg p-2 text-[10px] font-medium-clean outline-none">
                            <option value="Beras Premium" {{ $selectedCommodity == 'Beras Premium' ? 'selected' : '' }}>Beras Premium</option>
                            <option value="Cabai Merah" {{ $selectedCommodity == 'Cabai Merah' ? 'selected' : '' }}>Cabai Merah</option>
                            <option value="Minyak Goreng" {{ $selectedCommodity == 'Minyak Goreng' ? 'selected' : '' }}>Minyak Goreng</option>
                        </select>
                        <button type="submit" class="bg-blue-600 text-white px-4 rounded-lg text-[9px] font-medium-clean uppercase">Pindai</button>
                    </div>
                </form>
                
                <form action="{{ route('admin.cleanData') }}" method="POST" class="space-y-6">
                    @csrf
                    <input type="hidden" name="commodity" value="{{ $selectedCommodity }}">
                    <div class="space-y-4">
                        <div>
                            <label class="text-[9px] text-slate-400 font-medium-clean block mb-2 uppercase tracking-widest">Deteksi Outlier</label>
                            <div class="flex items-center gap-2">
                                <select name="outlier_method" class="flex-1 bg-slate-50 border border-slate-200 rounded-lg p-2.5 text-[10px] font-medium-clean outline-none">
                                    <option value="remove">Hapus Outlier</option>
                                    <option value="mean">Ganti dengan Rata-rata</option>
                                    <option value="median">Ganti dengan Median</option>
                                </select>
                                <button type="submit" name="action" value="outlier" class="bg-orange-500 text-white px-4 py-2.5 rounded-lg text-[9px] font-medium-clean">
                                    Terapkan
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="text-[9px] text-slate-400 font-medium-clean block mb-2 uppercase tracking-widest">Nilai Hilang</label>
                            <div class="flex items-center gap-2">
                                <select name="missing_method" class="flex-1 bg-slate-50 border border-slate-200 rounded-lg p-2.5 text-[10px] font-medium-clean outline-none">
                                    <option value="mean">Isi dengan Rata-rata</option>
                                    <option value="median">Isi dengan Median</option>
                                    <option value="remove">Hapus Data Kosong</option>
                                </select>
                                <button type="submit" name="action" value="missing" class="bg-[#043277] text-white px-4 py-2.5 rounded-lg text-[9px] font-medium-clean">
                                    Terapkan
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="lg:col-span-2 space-y-6">
            {{-- Hasil Pemindaian --}}
            <div class="bg-white rounded-2xl border border-orange-200 shadow-sm overflow-hidden">
                <div class="p-4 bg-orange-50/50 border-b border-orange-100 flex justify-between items-center">
                    <h3 class="text-[10px] text-orange-700 font-medium-clean uppercase tracking-widest">Hasil Pemindaian: {{ $selectedCommodity }}</h3>
                    <span class="bg-orange-100 text-orange-600 px-2 py-0.5 rounded text-[8px] font-medium-clean">{{ count($dataIssues ?? []) }} Temuan</span>
                </div>
                <div class="overflow-x-auto max-h-[200px] custom-scrollbar">
                    <table class="w-full text-left">
                        <thead class="bg-slate-50 sticky top-0 text-[9px] text-slate-400 uppercase font-medium-clean">
                            <tr>
                                <th class="px-6 py-3">Tanggal</th>
                                <th class="px-6 py-3">Jenis Masalah</th>
                                <th class="px-6 py-3">Nilai</th>
                                <th class="px-6 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-[10px]">
                            @forelse($dataIssues ?? [] as $issue)
                                <tr class="bg-orange-50/20">
                                    <td class="px-6 py-3 font-medium-clean">{{ $issue->date }}</td>
                                    <td class="px-6 py-3">
                                        <span class="px-2 py-0.5 rounded-full text-[8px] font-medium-clean {{ $issue->issue == 'Outlier' ? 'bg-red-100 text-red-600' : 'bg-yellow-100 text-yellow-600' }}">
                                            {{ $issue->issue }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3 font-medium-clean">Rp {{ number_format($issue->value, 0) }}</td>
                                    <td class="px-6 py-3 text-slate-500">{{ $issue->status }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="p-8 text-center text-slate-400">Tidak ada masalah yang terdeteksi</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Database Riwayat --}}
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b bg-slate-50/50">
                    <h3 class="text-[10px] text-[#043277] font-medium-clean uppercase tracking-widest">Riwayat Database</h3>
                </div>

                <div class="overflow-x-auto h-[350px] custom-scrollbar">
                    <table class="w-full text-left">
                        <thead class="sticky top-0 bg-white border-b border-slate-100 z-10">
                            <tr class="text-[9px] text-slate-400 uppercase font-medium-clean">
                                <th class="px-6 py-4">Komoditas</th>
                                <th class="px-6 py-4">Tanggal</th>
                                <th class="px-6 py-4">Harga</th>
                                <th class="px-6 py-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-[10px]">
                            @forelse($latestData ?? $allData ?? [] as $data)
                                <tr class="hover:bg-slate-50 transition-colors font-clean">
                                    <td class="px-6 py-4 uppercase font-medium-clean text-[#043277]">{{ $data->commodity_name ?? $data->commodity }}</td>
                                    <td class="px-6 py-4 text-slate-500">{{ $data->date }}</td>
                                    <td class="px-6 py-4 font-medium-clean text-emerald-600">Rp {{ number_format($data->price, 0, ',', '.') }}</td>
                                    <td class="px-6 py-4 text-center">
                                        <form action="{{ route('operator.deleteData', $data->id) }}" method="POST" onsubmit="return confirm('Hapus data ini?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-red-400 hover:text-red-600 transition-colors text-sm">
                                                Hapus
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="p-20 text-center text-slate-400">Data tidak ditemukan</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endif

{{-- ==========================================
     TAB 3: KONTROL PENGGUNA
     ========================================== --}}
@if($currentTab == 'users')
    <div class="grid grid-cols-12 gap-6 animate-fade-in">
        <div class="col-span-12 lg:col-span-4">
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                <h3 class="text-[10px] text-[#043277] font-medium-clean uppercase tracking-widest mb-6">Ringkasan Pengguna</h3>
                
                <div class="flex items-center justify-between p-4 bg-slate-50 rounded-xl border border-slate-100 mb-6">
                    <div>
                        <p class="text-[8px] text-slate-400 font-medium-clean uppercase tracking-widest">Total Pengguna</p>
                        <p class="text-sm text-[#043277] font-medium-clean">{{ count($users ?? []) }} Aktif</p>
                    </div>
                    <div class="bg-blue-100 p-2.5 rounded-lg">
                        <i class="fas fa-users text-blue-600"></i>
                    </div>
                </div>
                
                {{-- Form Buat Pengguna --}}
                <div class="p-5 border border-slate-200 rounded-xl">
                    <h4 class="text-[9px] text-orange-500 font-medium-clean uppercase mb-4 tracking-widest">Buat Pengguna Baru</h4>
                    <form action="{{ route('admin.storeUser') }}" method="POST" class="space-y-3">
                        @csrf
                        <div>
                            <label class="text-[9px] font-medium-clean text-slate-400 uppercase mb-1.5 block tracking-widest">Nama Lengkap</label>
                            <input type="text" name="name" required class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5 text-[10px] text-[#043277] font-medium-clean outline-none">
                        </div>
                        <div>
                            <label class="text-[9px] font-medium-clean text-slate-400 uppercase mb-1.5 block tracking-widest">Alamat Email</label>
                            <input type="email" name="email" required class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5 text-[10px] text-[#043277] font-medium-clean outline-none">
                        </div>
                        <div>
                            <label class="text-[9px] font-medium-clean text-slate-400 uppercase mb-1.5 block tracking-widest">Kata Sandi</label>
                            <input type="password" name="password" required class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5 text-[10px] text-[#043277] font-medium-clean outline-none">
                        </div>
                        <button type="submit" class="w-full bg-orange-500 text-white py-2.5 rounded-lg text-[9px] font-medium-clean uppercase tracking-widest">
                            Buat Pengguna
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-span-12 lg:col-span-8 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-5 border-b bg-slate-50/50">
                <h3 class="text-[10px] text-[#043277] font-medium-clean uppercase tracking-widest">Kelola Akses Pengguna</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-[9px] text-slate-400 font-medium-clean uppercase tracking-widest bg-slate-50/30">
                            <th class="px-6 py-4">Informasi Pengguna</th>
                            <th class="px-6 py-4">Peran</th>
                            <th class="px-6 py-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($users ?? [] as $user)
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex flex-col">
                                    <span class="text-xs text-[#043277] font-medium-clean">{{ $user->name }}</span>
                                    <span class="text-[9px] text-slate-400 font-medium-clean">{{ $user->email }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-3 py-1 rounded-full text-[8px] font-medium-clean uppercase tracking-widest {{ $user->is_admin ? 'bg-blue-100 text-blue-600' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $user->is_admin ? 'Administrator' : 'Pengguna' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                @if(auth()->id() !== $user->id)
                                <form action="{{ route('admin.deleteUser', $user->id) }}" method="POST" onsubmit="return confirm('Hapus pengguna ini?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-400 hover:text-red-600 transition-colors text-sm">
                                        Hapus
                                    </button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
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
            <tr class="border-b border-slate-50 hover:bg-slate-50 animate-fade-in">
                <td class="px-6 py-4 text-slate-500 font-medium-clean">
                    ${data.labels[i]}
                </td>
                <td class="px-6 py-4 text-right">
                    ${actual ? 'Rp ' + actual.toLocaleString('id-ID') : '—'}
                </td>
                <td class="px-6 py-4 text-right text-[#043277] font-medium-clean">
                    Rp ${forecast.toLocaleString('id-ID')}
                </td>
                <td class="px-6 py-4 text-right ${diff > 0 ? 'text-red-600' : diff < 0 ? 'text-emerald-600' : 'text-slate-500'}">
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
</script>

@endsection