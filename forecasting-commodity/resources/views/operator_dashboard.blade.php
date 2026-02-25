@extends('layouts.app')

@section('content')
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div id="real-content">
<style>
    .dashboard-container {
        font-family: 'Inter', sans-serif;
    }

    .card-standard {
        background: white;
        border-radius: 0.5rem;
        border: 1px solid #e5e7eb;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
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
        border-radius: 0.375rem;
        font-size: 0.75rem;
        font-weight: 600;
        transition: all 0.2s;
        border: 1px solid #d1d5db;
        background: white;
        color: #4b5563;
    }

    .filter-btn.active {
        background: #2563eb;
        color: white;
        border-color: #2563eb;
    }

    .filter-btn:hover:not(.active) {
        background: #f8fafc;
        border-color: #cbd5e1;
    }

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

    .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: #f8fafc; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .animate-fade-in {
        animation: fadeIn 0.4s ease-out;
    }

    .tab-single { display: block; }
    .tab-bulk { display: none; }

    /* Alert styles */
    .alert-success {
        background: #dcfce7;
        border: 1px solid #bbf7d0;
        color: #166534;
        padding: 1rem;
        border-radius: 0.5rem;
        margin-bottom: 1rem;
    }

    .alert-error {
        background: #fee2e2;
        border: 1px solid #fecaca;
        color: #991b1b;
        padding: 1rem;
        border-radius: 0.5rem;
        margin-bottom: 1rem;
    }
</style>
</div>

<div id="skeleton-overlay" class="hidden fixed inset-0 bg-white/50 z-50 flex items-center justify-center opacity-0">
    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
</div>

<div class="dashboard-container space-y-6 animate-fade-in">

{{-- ✅ TAB NAVIGATION --}}
<div class="card-standard p-1.5 flex gap-1">
    <a href="{{ route('operator.predict', ['tab' => 'insight']) }}"
       class="flex-1 flex items-center justify-center gap-2 py-2.5 px-4 rounded-md text-xs font-bold uppercase tracking-wider transition-all
              {{ ($currentTab ?? 'insight') === 'insight' ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-500 hover:bg-gray-100' }}">
        <i class="fas fa-chart-line"></i>
        <span>Insight & Prediksi</span>
    </a>
    <a href="{{ route('operator.predict', ['tab' => 'manage']) }}"
       class="flex-1 flex items-center justify-center gap-2 py-2.5 px-4 rounded-md text-xs font-bold uppercase tracking-wider transition-all
              {{ ($currentTab ?? '') === 'manage' ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-500 hover:bg-gray-100' }}">
        <i class="fas fa-database"></i>
        <span>Manajemen Data</span>
    </a>
</div>

{{-- ✅ Flash Messages --}}
@if(session('success'))
    <div class="alert-success flex items-center gap-3">
        <i class="fas fa-check-circle"></i>
        <span>{{ session('success') }}</span>
    </div>
@endif

@if(session('error'))
    <div class="alert-error flex items-center gap-3">
        <i class="fas fa-exclamation-circle"></i>
        <span>{{ session('error') }}</span>
    </div>
@endif

@if(($currentTab ?? 'insight') == 'insight')

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
                        Panel Operator — BPS Provinsi Riau
                    </p>
                </div>
            </div>
        </div>

        <form action="{{ route('operator.predict') }}" method="POST" id="mainForm" class="mt-6 pt-6 border-t border-gray-100">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                <div class="md:col-span-4">
                    <label class="text-xs font-semibold text-gray-700 uppercase mb-2 block tracking-tight">
                        Komoditas Terpilih
                    </label>
                    <select name="commodity" onchange="handleCommodityChange()"
                            class="w-full bg-gray-50 border border-gray-300 rounded-md py-2 px-3 text-sm font-medium text-gray-900 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all">
                        @forelse($daftarKomoditas ?? [] as $komoditas)
                            <option value="{{ $komoditas->id }}"
                                {{ ($selectedId ?? null) == $komoditas->id ? 'selected' : '' }}>
                                {{ $komoditas->display_name }}
                            </option>
                        @empty
                            <option value="">-- Tidak ada komoditas --</option>
                        @endforelse
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

            <input type="hidden" name="changepoint_prior_scale" id="hidden_cp" value="{{ $cpScale ?? 0.05 }}">
            <input type="hidden" name="seasonality_prior_scale" id="hidden_season" value="{{ $seasonScale ?? 10 }}">
            <input type="hidden" name="seasonality_mode" id="hidden_mode" value="{{ $seasonMode ?? 'multiplicative' }}">
            <input type="hidden" name="weekly_seasonality" id="hidden_weekly" value="{{ ($weeklySeason ?? false) ? 'true' : 'false' }}">
            <input type="hidden" name="yearly_seasonality" id="hidden_yearly" value="{{ ($yearlySeason ?? false) ? 'true' : 'false' }}">
            <input type="hidden" name="tab" value="insight">
        </form>
    </div>

    {{-- ✅ Stats Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
        <div class="card-standard hover-card p-5">
            <p class="text-[10px] uppercase text-gray-500 font-bold tracking-wider mb-2">Rata-rata Harga</p>
            <p class="text-xl font-bold text-gray-900">Rp {{ number_format($avgPrice ?? 0, 0, ',', '.') }}</p>
            <p class="text-[10px] text-gray-400 mt-1">{{ count($actualData ?? []) }} data poin</p>
        </div>

        <div class="card-standard hover-card p-5">
            <p class="text-[10px] uppercase text-gray-500 font-bold tracking-wider mb-2">Harga Tertinggi</p>
            <p class="text-xl font-bold text-red-600">Rp {{ number_format($maxPrice ?? 0, 0, ',', '.') }}</p>
        </div>

        <div class="card-standard hover-card p-5">
            <p class="text-[10px] uppercase text-gray-500 font-bold tracking-wider mb-2">Periode Data</p>
            <div class="flex items-center gap-2">
                <p class="text-sm font-semibold text-gray-900">
                    {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }}
                    <span class="text-gray-400 mx-1">→</span>
                    {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}
                </p>
            </div>
        </div>

        <div class="bg-blue-600 rounded-lg p-5 text-white shadow-lg hover-card">
            <p class="text-[10px] uppercase text-blue-100 font-bold tracking-wider mb-2">Arah Tren</p>
            <p class="text-sm font-bold uppercase flex items-center gap-2">
                @php
                    $trendIcon = match(strtolower($trendDir ?? 'stabil')) {
                        'naik'  => 'fa-arrow-trend-up',
                        'turun' => 'fa-arrow-trend-down',
                        default => 'fa-minus'
                    };
                @endphp
                <i class="fas {{ $trendIcon }}"></i>
                {{ $trendDir ?? 'Stabil' }}
            </p>
        </div>
    </div>

    <div class="grid grid-cols-12 gap-5">

        <div class="col-span-12 lg:col-span-4 space-y-4">
            <div class="card-standard p-5">
                <h4 class="text-sm font-bold text-gray-900 uppercase tracking-tight mb-5 pb-3 border-b border-gray-100">
                    Pengaturan Hyperparameter
                </h4>
                <div class="space-y-5">
                    <div>
                        <div class="flex justify-between mb-2">
                            <span class="text-xs text-gray-500 font-semibold uppercase">Changepoint Prior</span>
                            <span class="text-xs font-mono font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded" id="cp_display">{{ $cpScale ?? 0.05 }}</span>
                        </div>
                        <input type="range" min="0.001" max="0.5" step="0.001" value="{{ $cpScale ?? 0.05 }}"
                               class="w-full h-1 bg-gray-100 rounded-lg appearance-none cursor-pointer"
                               oninput="updateVal('hidden_cp', 'cp_display', this.value)">
                        <p class="text-[9px] text-gray-400 mt-1">Fleksibilitas perubahan tren</p>
                    </div>

                    <div>
                        <div class="flex justify-between mb-2">
                            <span class="text-xs text-gray-500 font-semibold uppercase">Seasonality Prior</span>
                            <span class="text-xs font-mono font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded" id="season_display">{{ $seasonScale ?? 10 }}</span>
                        </div>
                        <input type="range" min="0.01" max="10" step="0.01" value="{{ $seasonScale ?? 10 }}"
                               class="w-full h-1 bg-gray-100 rounded-lg appearance-none cursor-pointer"
                               oninput="updateVal('hidden_season', 'season_display', this.value)">
                        <p class="text-[9px] text-gray-400 mt-1">Kekuatan pola musiman</p>
                    </div>

                    <div>
                        <label class="text-xs text-gray-500 font-semibold uppercase mb-2 block">Mode Musiman</label>
                        <select onchange="document.getElementById('hidden_mode').value = this.value"
                                class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-xs text-gray-600 font-medium outline-none">
                            <option value="multiplicative" {{ ($seasonMode ?? '') == 'multiplicative' ? 'selected' : '' }}>Multiplikatif</option>
                            <option value="additive" {{ ($seasonMode ?? '') == 'additive' ? 'selected' : '' }}>Aditif</option>
                        </select>
                        <p class="text-[9px] text-gray-400 mt-1">Metode penerapan musiman</p>
                    </div>

                    <div class="space-y-2 pt-2 border-t border-gray-100">
                        <label class="text-xs text-gray-500 font-semibold uppercase block mb-2">Komponen Musiman</label>
                        <div class="flex items-center justify-between p-2.5 bg-gray-50 rounded-lg border border-gray-100">
                            <span class="text-xs text-gray-500 font-medium uppercase">Mingguan</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" {{ ($weeklySeason ?? false) ? 'checked' : '' }}
                                       onchange="document.getElementById('hidden_weekly').value = this.checked" class="sr-only peer">
                                <div class="w-7 h-4 bg-gray-200 rounded-full peer peer-checked:bg-blue-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:after:translate-x-3"></div>
                            </label>
                        </div>
                        <div class="flex items-center justify-between p-2.5 bg-gray-50 rounded-lg border border-gray-100">
                            <span class="text-xs text-gray-500 font-medium uppercase">Tahunan</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" {{ ($yearlySeason ?? false) ? 'checked' : '' }}
                                       onchange="document.getElementById('hidden_yearly').value = this.checked" class="sr-only peer">
                                <div class="w-7 h-4 bg-gray-200 rounded-full peer peer-checked:bg-blue-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:after:translate-x-3"></div>
                            </label>
                        </div>
                    </div>

                    <button type="button" onclick="triggerSubmit()"
                            class="w-full bg-blue-600 text-white py-3 rounded-lg text-xs font-bold uppercase tracking-wider hover:bg-blue-700 transition-all shadow-sm">
                        Perbarui Prediksi
                    </button>
                </div>
            </div>

            <div class="bg-gradient-to-br from-blue-600 to-blue-700 rounded-lg shadow-lg p-5 text-white">
                <h4 class="text-xs font-bold uppercase tracking-wider mb-3 opacity-90">Ringkasan Statistik</h4>
                <div class="space-y-3">
                    <div class="flex justify-between items-end border-b border-white/10 pb-2">
                        <span class="text-[10px] opacity-70 font-semibold uppercase">MAPE (Akurasi)</span>
                        <span class="text-sm font-bold">{{ number_format($mape ?? 0, 2) }}%</span>
                    </div>
                    <div class="flex justify-between items-end border-b border-white/10 pb-2">
                        <span class="text-[10px] opacity-70 font-semibold uppercase">Skor R-Squared</span>
                        <span class="text-sm font-bold">{{ number_format($rSquared ?? 0, 3) }}</span>
                    </div>
                    <div class="flex justify-between items-end">
                        <span class="text-[10px] opacity-70 font-semibold uppercase">Total Data Poin</span>
                        <span class="text-sm font-bold">{{ count($actualData ?? []) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-span-12 lg:col-span-8 card-standard overflow-hidden flex flex-col">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex flex-col lg:flex-row justify-between items-center gap-4 flex-shrink-0">
                <div>
                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-tight">Visualisasi Tren & Proyeksi</h3>
                    <p class="text-xs text-gray-500">{{ $selectedCommodity }} — Data Historis vs Proyeksi</p>
                </div>

                <div class="flex bg-white border border-gray-300 p-1 rounded-md shadow-sm">
                    <button onclick="changeChartPeriod('weekly')" class="filter-btn active" id="btn-weekly">Mingguan</button>
                    <button onclick="changeChartPeriod('monthly')" class="filter-btn border-none" id="btn-monthly">Bulanan</button>
                    <button onclick="changeChartPeriod('yearly')" class="filter-btn border-none" id="btn-yearly">Tahunan</button>
                </div>
            </div>

            <div class="flex-1 p-6" style="min-height: 500px;">
                <canvas id="mainChart"></canvas>
            </div>
        </div>
    </div>

    {{-- Insight Table --}}
    <div class="card-standard overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-tight">
                Ringkasan Analisis <span id="selectedPeriodText" class="text-blue-600">Mingguan</span>
            </h3>
            <span class="text-xs text-gray-400">{{ $selectedCommodity }}</span>
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
                </tbody>
            </table>
        </div>
    </div>

    <div class="card-standard p-6 border-l-4 border-l-blue-600">
        <div class="flex items-center gap-3 mb-3">
            <h4 class="text-sm font-bold text-gray-900 uppercase">Interpretasi Analisis Tren</h4>
        </div>
        <p id="dynamic-analysis" class="text-sm text-gray-600 leading-relaxed">
            Berdasarkan analisis data historis untuk komoditas <strong>{{ $selectedCommodity }}</strong>,
            model mendeteksi tren harga <strong>{{ strtolower($trendDir ?? 'stabil') }}</strong>
            dengan rata-rata harga <strong>Rp {{ number_format($avgPrice ?? 0, 0, ',', '.') }}</strong>
            dan total <strong>{{ count($actualData ?? []) }} data poin</strong> pada periode
            {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }} s/d {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}.
        </p>
    </div>

@endif

@if($currentTab == 'manage')
    <div class="space-y-6 animate-fade-in">

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1">
                <div class="card-standard p-6">
                    <h3 class="text-sm font-bold text-gray-900 mb-6 uppercase tracking-tight">Tambah Data Baru</h3>
                    <div class="flex gap-4 mb-6 border-b pb-2">
                        <button onclick="switchInputMode('single')" id="btn-tab-single"
                                class="text-blue-600 border-b-2 border-blue-600 text-xs uppercase tracking-wider pb-1 font-semibold">Manual</button>
                        <button onclick="switchInputMode('bulk')" id="btn-tab-bulk"
                                class="text-gray-400 text-xs uppercase tracking-wider pb-1 font-semibold">Unggah CSV</button>
                    </div>

                    <form id="form-single" action="{{ route('operator.storeData') }}" method="POST" class="space-y-4 tab-single">
                        @csrf
                        <div>
                            <label class="text-xs font-semibold text-gray-700 uppercase mb-1.5 block tracking-tight">Komoditas</label>
                            <select name="commodity" required
                                    class="w-full bg-gray-50 border border-gray-200 rounded-lg p-3 text-xs text-gray-900 font-medium outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">-- Pilih Komoditas --</option>
                                @forelse($daftarKomoditas ?? [] as $komoditas)
                                    <option value="{{ $komoditas->id }}"
                                        {{ ($selectedId ?? null) == $komoditas->id ? 'selected' : '' }}>
                                        {{ $komoditas->display_name }}
                                    </option>
                                @empty
                                    <option value="">-- Tidak ada komoditas --</option>
                                @endforelse
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-700 uppercase mb-1.5 block tracking-tight">Tanggal</label>
                            <input type="date" name="date" required
                                   class="w-full bg-gray-50 border border-gray-200 rounded-lg p-3 text-xs text-gray-600 font-medium focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-700 uppercase mb-1.5 block tracking-tight">Harga (Rp)</label>
                            <input type="number" name="price" placeholder="Masukkan harga" required min="1"
                                   class="w-full bg-gray-50 border border-gray-200 rounded-lg p-3 text-xs text-gray-600 font-medium focus:ring-2 focus:ring-blue-500">
                        </div>
                        <button type="submit"
                                class="w-full bg-emerald-500 text-white py-3 rounded-lg text-xs font-bold uppercase tracking-wider hover:bg-emerald-600 transition-all">
                            Simpan Data
                        </button>
                    </form>

                    <form id="form-bulk" action="{{ route('operator.storeData') }}" method="POST" enctype="multipart/form-data" class="space-y-4 tab-bulk">
                        @csrf
                        <div>
                            <label class="text-xs font-semibold text-gray-700 uppercase mb-1.5 block tracking-tight">Unggah File CSV</label>
                            <div class="p-8 border-2 border-dashed border-gray-200 rounded-xl bg-gray-50/50 text-center relative hover:border-blue-300 transition-colors" id="dropzone">
                                <input type="file" name="dataset" accept=".csv" class="absolute inset-0 opacity-0 cursor-pointer" onchange="showFileName(this)">
                                <i class="fas fa-cloud-upload-alt text-gray-300 text-2xl mb-2"></i>
                                <p class="text-xs text-gray-400 font-medium" id="file-name-display">Pilih atau seret file CSV ke sini</p>
                                <p class="text-[9px] text-gray-300 mt-1">Format: komoditas_id, tanggal, harga</p>
                            </div>
                        </div>

                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <div class="flex items-start gap-3">
                                <i class="fas fa-info-circle text-blue-500 text-sm mt-0.5"></i>
                                <div class="flex-1">
                                    <p class="text-xs text-blue-700 font-semibold uppercase tracking-tight mb-2">Template CSV</p>
                                    <p class="text-[10px] text-blue-600 mb-3 leading-relaxed">
                                        Gunakan template standar untuk memastikan format data yang benar
                                    </p>
                                    <a href="{{ route('operator.downloadTemplate') }}"
                                       class="inline-flex items-center gap-2 bg-blue-600 text-white px-3 py-2 rounded-lg text-[10px] font-bold uppercase tracking-wider hover:bg-blue-700 transition-colors">
                                        <i class="fas fa-download"></i>
                                        Unduh Template CSV
                                    </a>
                                </div>
                            </div>
                        </div>

                        <button type="submit"
                                class="w-full bg-blue-600 text-white py-3 rounded-lg text-xs font-bold uppercase tracking-wider hover:bg-blue-700 transition-all">
                            Unggah Dataset
                        </button>
                    </form>
                </div>
            </div>

            <div class="lg:col-span-2">
                <div class="card-standard overflow-hidden flex flex-col" style="height: fit-content;">
                    <div class="p-5 border-b bg-gray-50/50 flex justify-between items-center">
                        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-tight">Riwayat Database</h3>
                        <span class="text-xs text-gray-400">{{ $selectedCommodity }}</span>
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
                                    @forelse($latestData ?? [] as $data)
                                        <tr class="hover:bg-gray-50 transition-colors" id="row-{{ $data->id }}">
                                            <td class="px-6 py-4 uppercase font-bold text-blue-600">
                                                <span class="commodity-view">
                                                    {{ $daftarKomoditas->firstWhere('id', $data->komoditas_id)?->display_name ?? '—' }}
                                                </span>
                                            </td>

                                            <td class="px-6 py-4 text-gray-500">
                                                <span class="date-view">{{ \Carbon\Carbon::parse($data->tanggal)->format('d/m/Y') }}</span>
                                                <input type="date" class="date-edit hidden w-full bg-gray-50 border border-gray-300 rounded-lg p-2 text-xs focus:ring-2 focus:ring-blue-500"
                                                       value="{{ $data->tanggal }}" data-id="{{ $data->id }}">
                                            </td>

                                            <td class="px-6 py-4 font-bold text-emerald-600">
                                                <span class="price-view">Rp {{ number_format($data->harga, 0, ',', '.') }}</span>
                                                <input type="number" class="price-edit hidden w-full bg-gray-50 border border-gray-300 rounded-lg p-2 text-xs focus:ring-2 focus:ring-blue-500"
                                                       value="{{ $data->harga }}" data-id="{{ $data->id }}">
                                            </td>

                                            <td class="px-6 py-4">
                                                <div class="flex items-center justify-center gap-3">
                                                    <button type="button" onclick="toggleEditMode({{ $data->id }})"
                                                            class="edit-btn text-blue-500 hover:text-blue-700 transition-colors text-sm font-medium">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>

                                                    <button type="button" onclick="saveRow({{ $data->id }})"
                                                            class="done-btn hidden text-green-500 hover:text-green-700 transition-colors text-sm font-medium">
                                                        <i class="fas fa-check"></i> Selesai
                                                    </button>

                                                    <form action="{{ route('operator.deleteData', $data->id) }}" method="POST"
                                                          onsubmit="return confirm('Hapus data ini?')" class="inline delete-form">
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
                                        <tr>
                                            <td colspan="4" class="p-12 text-center">
                                                <div class="flex flex-col items-center gap-2 text-gray-400">
                                                    <i class="fas fa-database text-3xl opacity-30"></i>
                                                    <p class="text-sm font-medium">Data tidak ditemukan</p>
                                                    <p class="text-xs">Pilih komoditas atau tambah data baru</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    @if(isset($latestData) && method_exists($latestData, 'hasPages') && $latestData->hasPages())
                        <div class="px-6 py-4 border-t bg-gray-50/30">
                            <div class="flex items-center justify-between">
                                <div class="text-xs text-gray-500">
                                    Menampilkan {{ $latestData->firstItem() ?? 0 }} - {{ $latestData->lastItem() ?? 0 }} dari {{ $latestData->total() }} data
                                </div>
                                <div class="flex gap-1">
                                    @if ($latestData->onFirstPage())
                                        <span class="px-3 py-1.5 text-xs text-gray-400 bg-gray-100 rounded cursor-not-allowed"><i class="fas fa-chevron-left"></i></span>
                                    @else
                                        <a href="{{ $latestData->previousPageUrl() }}" class="px-3 py-1.5 text-xs text-gray-700 bg-white border border-gray-200 rounded hover:bg-gray-50 transition-colors"><i class="fas fa-chevron-left"></i></a>
                                    @endif

                                    @foreach ($latestData->getUrlRange(1, $latestData->lastPage()) as $page => $url)
                                        @if ($page == $latestData->currentPage())
                                            <span class="px-3 py-1.5 text-xs font-bold text-white bg-blue-600 rounded">{{ $page }}</span>
                                        @else
                                            <a href="{{ $url }}" class="px-3 py-1.5 text-xs text-gray-700 bg-white border border-gray-200 rounded hover:bg-gray-50 transition-colors">{{ $page }}</a>
                                        @endif
                                    @endforeach

                                    @if ($latestData->hasMorePages())
                                        <a href="{{ $latestData->nextPageUrl() }}" class="px-3 py-1.5 text-xs text-gray-700 bg-white border border-gray-200 rounded hover:bg-gray-50 transition-colors"><i class="fas fa-chevron-right"></i></a>
                                    @else
                                        <span class="px-3 py-1.5 text-xs text-gray-400 bg-gray-100 rounded cursor-not-allowed"><i class="fas fa-chevron-right"></i></span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Data Cleaning Section --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1">
                <div class="card-standard p-6" style="height: fit-content;">
                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-tight mb-6">Pembersihan Data</h3>

                    <form action="{{ route('operator.predict') }}" method="POST" class="mb-6 pb-6 border-b border-gray-100">
                        @csrf
                        <input type="hidden" name="tab" value="manage">
                        <label class="text-xs text-gray-700 font-semibold block mb-2 uppercase tracking-tight">Pindai Data Untuk</label>
                        <div class="flex gap-2">
                            <select name="commodity" class="flex-1 bg-gray-50 border border-gray-200 rounded-lg p-2 text-xs font-medium outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">-- Pilih Komoditas --</option>
                                @forelse($daftarKomoditas ?? [] as $komoditas)
                                    <option value="{{ $komoditas->id }}"
                                        {{ ($selectedId ?? null) == $komoditas->id ? 'selected' : '' }}>
                                        {{ $komoditas->display_name }}
                                    </option>
                                @empty
                                    <option value="">-- Tidak ada komoditas --</option>
                                @endforelse
                            </select>
                            <button type="submit" class="bg-blue-600 text-white px-4 rounded-lg text-xs font-bold uppercase hover:bg-blue-700">Pindai</button>
                        </div>
                    </form>

                    <form action="{{ route('operator.cleanData') }}" method="POST" class="space-y-6">
                        @csrf
                        <input type="hidden" name="commodity" value="{{ $selectedId }}">
                        <div class="space-y-4">
                            <div>
                                <label class="text-xs text-gray-700 font-semibold block mb-2 uppercase tracking-tight">Deteksi Outlier</label>
                                <div class="flex items-center gap-2">
                                    <select name="outlier_method" class="flex-1 bg-gray-50 border border-gray-200 rounded-lg p-2.5 text-xs font-medium outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="remove">Hapus Outlier</option>
                                        <option value="mean">Ganti dengan Rata-rata</option>
                                        <option value="median">Ganti dengan Median</option>
                                    </select>
                                    <button type="submit" name="action" value="outlier"
                                            class="bg-orange-500 text-white px-4 py-2.5 rounded-lg text-xs font-bold hover:bg-orange-600 whitespace-nowrap">
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
                                    <button type="submit" name="action" value="missing"
                                            class="bg-blue-600 text-white px-4 py-2.5 rounded-lg text-xs font-bold hover:bg-blue-700 whitespace-nowrap">
                                        Terapkan
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="lg:col-span-2">
                <div class="card-standard border-orange-200 overflow-hidden flex flex-col" style="height: fit-content;">
                    <div class="p-4 bg-orange-50/50 border-b border-orange-100 flex justify-between items-center">
                        <h3 class="text-xs text-orange-700 font-bold uppercase tracking-tight">Hasil Pemindaian: {{ $selectedCommodity }}</h3>
                        <span class="bg-orange-100 text-orange-600 px-2 py-0.5 rounded text-[10px] font-bold">
                            {{ ($dataIssues instanceof \Illuminate\Pagination\LengthAwarePaginator ? $dataIssues->total() : count($dataIssues ?? [])) }} Temuan
                        </span>
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
                                            <td class="px-6 py-3 font-medium">{{ \Carbon\Carbon::parse($issue->date)->format('d/m/Y') }}</td>
                                            <td class="px-6 py-3">
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $issue->issue == 'Outlier' ? 'bg-red-100 text-red-600' : 'bg-yellow-100 text-yellow-600' }}">
                                                    {{ $issue->issue }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-3 font-medium">Rp {{ number_format($issue->value, 0, ',', '.') }}</td>
                                            <td class="px-6 py-3 text-gray-500">{{ $issue->status }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="p-8 text-center">
                                                <div class="flex flex-col items-center gap-2 text-gray-400">
                                                    <i class="fas fa-check-circle text-2xl text-green-400 opacity-60"></i>
                                                    <p class="text-sm font-medium">Tidak ada masalah yang terdeteksi</p>
                                                    <p class="text-xs">Data sudah bersih</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    @if(isset($dataIssues) && $dataIssues instanceof \Illuminate\Pagination\LengthAwarePaginator && $dataIssues->hasPages())
                        <div class="px-6 py-4 border-t bg-orange-50/20">
                            <div class="flex items-center justify-between">
                                <div class="text-xs text-gray-500">
                                    Menampilkan {{ $dataIssues->firstItem() ?? 0 }} - {{ $dataIssues->lastItem() ?? 0 }} dari {{ $dataIssues->total() }} temuan
                                </div>
                                <div class="flex gap-1">
                                    @if ($dataIssues->onFirstPage())
                                        <span class="px-3 py-1.5 text-xs text-gray-400 bg-gray-100 rounded cursor-not-allowed"><i class="fas fa-chevron-left"></i></span>
                                    @else
                                        <a href="{{ $dataIssues->previousPageUrl() }}" class="px-3 py-1.5 text-xs text-gray-700 bg-white border border-gray-200 rounded hover:bg-gray-50 transition-colors"><i class="fas fa-chevron-left"></i></a>
                                    @endif

                                    @foreach ($dataIssues->getUrlRange(1, $dataIssues->lastPage()) as $page => $url)
                                        @if ($page == $dataIssues->currentPage())
                                            <span class="px-3 py-1.5 text-xs font-bold text-white bg-orange-600 rounded">{{ $page }}</span>
                                        @else
                                            <a href="{{ $url }}" class="px-3 py-1.5 text-xs text-gray-700 bg-white border border-gray-200 rounded hover:bg-gray-50 transition-colors">{{ $page }}</a>
                                        @endif
                                    @endforeach

                                    @if ($dataIssues->hasMorePages())
                                        <a href="{{ $dataIssues->nextPageUrl() }}" class="px-3 py-1.5 text-xs text-gray-700 bg-white border border-gray-200 rounded hover:bg-gray-50 transition-colors"><i class="fas fa-chevron-right"></i></a>
                                    @else
                                        <span class="px-3 py-1.5 text-xs text-gray-400 bg-gray-100 rounded cursor-not-allowed"><i class="fas fa-chevron-right"></i></span>
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
// ✅ Data dari controller
const chartData = {
    weekly: {
        labels:   {!! json_encode($weeklyLabels ?? []) !!},
        actual:   {!! json_encode($weeklyActual ?? []) !!},
        forecast: {!! json_encode($weeklyForecast ?? []) !!},
        lower:    {!! json_encode($weeklyLower ?? []) !!},
        upper:    {!! json_encode($weeklyUpper ?? []) !!}
    },
    monthly: {
        labels:   {!! json_encode($monthlyLabels ?? []) !!},
        actual:   {!! json_encode($monthlyActual ?? []) !!},
        forecast: {!! json_encode($monthlyForecast ?? []) !!},
        lower:    {!! json_encode($monthlyLower ?? []) !!},
        upper:    {!! json_encode($monthlyUpper ?? []) !!}
    },
    yearly: {
        labels:   {!! json_encode($yearlyLabels ?? []) !!},
        actual:   {!! json_encode($yearlyActual ?? []) !!},
        forecast: {!! json_encode($yearlyForecast ?? []) !!},
        lower:    {!! json_encode($yearlyLower ?? []) !!},
        upper:    {!! json_encode($yearlyUpper ?? []) !!}
    }
};

let currentPeriod = 'weekly';
let mainChart = null;

function updateVal(hiddenId, displayId, val) {
    document.getElementById(hiddenId).value = val;
    document.getElementById(displayId).innerText = parseFloat(val).toFixed(3);
}

function triggerSubmit() {
    document.getElementById('real-content').classList.add('opacity-30');
    document.getElementById('skeleton-overlay').classList.remove('hidden');
    document.getElementById('skeleton-overlay').style.opacity = '1';
    setTimeout(() => document.getElementById('mainForm').submit(), 100);
}

function handleCommodityChange() {
    triggerSubmit();
}

function showFileName(input) {
    const display = document.getElementById('file-name-display');
    if (input.files && input.files[0]) {
        display.textContent = input.files[0].name;
        display.classList.add('text-blue-600');
    }
}

function switchInputMode(mode) {
    const formSingle = document.getElementById('form-single');
    const formBulk   = document.getElementById('form-bulk');
    const btnSingle  = document.getElementById('btn-tab-single');
    const btnBulk    = document.getElementById('btn-tab-bulk');

    if (mode === 'single') {
        formSingle.style.display = 'block';
        formBulk.style.display   = 'none';
        btnSingle.className = 'text-blue-600 border-b-2 border-blue-600 text-xs uppercase tracking-wider pb-1 font-semibold';
        btnBulk.className   = 'text-gray-400 text-xs uppercase tracking-wider pb-1 font-semibold';
    } else {
        formSingle.style.display = 'none';
        formBulk.style.display   = 'block';
        btnSingle.className = 'text-gray-400 text-xs uppercase tracking-wider pb-1 font-semibold';
        btnBulk.className   = 'text-blue-600 border-b-2 border-blue-600 text-xs uppercase tracking-wider pb-1 font-semibold';
    }
}

@if(($currentTab ?? 'insight') == 'insight')

function initializeChart() {
    const canvas = document.getElementById('mainChart');
    if (!canvas) return;

    const ctx  = canvas.getContext('2d');
    const data = chartData[currentPeriod];

    if (!data.labels || data.labels.length === 0) {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = '#9ca3af';
        ctx.font      = '14px Inter, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('Tidak ada data untuk periode ini', canvas.width / 2, canvas.height / 2);
        return;
    }

    const gradientActual = ctx.createLinearGradient(0, 0, 0, 400);
    gradientActual.addColorStop(0, 'rgba(4, 50, 119, 0.15)');
    gradientActual.addColorStop(1, 'rgba(4, 50, 119, 0)');

    const gradientForecast = ctx.createLinearGradient(0, 0, 0, 400);
    gradientForecast.addColorStop(0, 'rgba(249, 115, 22, 0.15)');
    gradientForecast.addColorStop(1, 'rgba(249, 115, 22, 0)');

    if (mainChart) mainChart.destroy();

    mainChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [
                {
                    label: 'Rentang Bawah',
                    data: data.lower,
                    backgroundColor: 'rgba(34, 197, 94, 0.08)',
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
                    pointHoverBorderWidth: 2,
                    spanGaps: false
                },
                {
                    label: 'Harga Proyeksi',
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
                    pointHoverBorderWidth: 2,
                    spanGaps: false
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            plugins: {
                title: {
                    display: true,
                    text: '{{ $selectedCommodity }}',
                    color: '#043277',
                    font: { size: 14, weight: '600', family: 'Inter' },
                    padding: { top: 10, bottom: 15 }
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
                        pointStyle: 'circle',
                        filter: (item) => !item.text.includes('Rentang')
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
                            if (label.includes('Rentang')) return null;
                            if (label) label += ': ';
                            if (context.parsed.y !== null) {
                                label += new Intl.NumberFormat('id-ID', {
                                    style: 'currency', currency: 'IDR', maximumFractionDigits: 0
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
                        maxTicksLimit: 15
                    }
                }
            }
        }
    });
}

function changeChartPeriod(period) {
    currentPeriod = period;

    document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
    document.getElementById(`btn-${period}`).classList.add('active');

    const periodText = { 'weekly': 'Mingguan', 'monthly': 'Bulanan', 'yearly': 'Tahunan' };
    document.getElementById('selectedPeriodText').textContent = periodText[period];

    initializeChart();
    updateInsightTable();
}

function updateInsightTable() {
    const data  = chartData[currentPeriod];
    const tbody = document.getElementById('insightTableBody');
    if (!tbody) return;

    tbody.innerHTML = '';

    const allLabels   = data.labels;
    const allActual   = data.actual;
    const allForecast = data.forecast;

    if (!allLabels || allLabels.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" class="p-8 text-center text-gray-400">Tidak ada data</td></tr>`;
        return;
    }

    const rows = [];
    for (let i = 0; i < allLabels.length; i++) {
        if (allActual[i] !== null || allForecast[i] !== null) {
            rows.push({ label: allLabels[i], actual: allActual[i], forecast: allForecast[i] });
        }
    }

    const display = rows.slice(-10);

    display.forEach(row => {
        const { label, actual, forecast } = row;
        const diff = (actual !== null && forecast !== null) ? (forecast - actual) : null;

        let insight = 'Stabil';
        let insightClass = 'insight-stabil';

        if (diff !== null) {
            const threshold = (actual || 0) * 0.01;
            if (diff > threshold) { insight = 'Naik'; insightClass = 'insight-naik'; }
            else if (diff < -threshold) { insight = 'Turun'; insightClass = 'insight-turun'; }
        } else if (forecast !== null) {
            insight = 'Proyeksi'; insightClass = 'insight-stabil';
        }

        tbody.innerHTML += `
            <tr class="border-b border-gray-50 hover:bg-gray-50 animate-fade-in">
                <td class="px-6 py-4 text-gray-500 font-medium text-xs">${label}</td>
                <td class="px-6 py-4 text-right text-xs">${actual !== null ? 'Rp ' + actual.toLocaleString('id-ID') : '<span class="text-gray-300">—</span>'}</td>
                <td class="px-6 py-4 text-right text-blue-600 font-bold text-xs">${forecast !== null ? 'Rp ' + forecast.toLocaleString('id-ID') : '<span class="text-gray-300">—</span>'}</td>
                <td class="px-6 py-4 text-right text-xs ${diff !== null ? (diff > 0 ? 'text-red-600' : diff < 0 ? 'text-emerald-600' : 'text-gray-500') : 'text-gray-300'}">
                    ${diff !== null ? (diff > 0 ? '+' : '') + diff.toLocaleString('id-ID') : '—'}
                </td>
                <td class="px-6 py-4 text-center">
                    <span class="insight-badge ${insightClass}">${insight}</span>
                </td>
            </tr>
        `;
    });

    if (display.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" class="p-8 text-center text-gray-400 text-xs">Tidak ada data untuk periode ini</td></tr>`;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    initializeChart();
    updateInsightTable();
});

@endif

// ============================
// EDIT MODE — DATA TABLE
// ============================

function toggleEditMode(id) {
    const row = document.getElementById(`row-${id}`);
    if (!row) return;

    const isEditing = row.querySelector('.date-edit').classList.contains('hidden');

    row.querySelector('.date-view').classList.toggle('hidden', isEditing);
    row.querySelector('.date-edit').classList.toggle('hidden', !isEditing);
    row.querySelector('.price-view').classList.toggle('hidden', isEditing);
    row.querySelector('.price-edit').classList.toggle('hidden', !isEditing);
    row.querySelector('.edit-btn').classList.toggle('hidden', isEditing);
    row.querySelector('.done-btn').classList.toggle('hidden', !isEditing);

    const deleteForm = row.querySelector('.delete-form');
    if (deleteForm) {
        deleteForm.style.opacity       = isEditing ? '0.3' : '1';
        deleteForm.style.pointerEvents = isEditing ? 'none' : 'auto';
    }

    if (isEditing) {
        row.classList.add('bg-blue-50', 'border-l-4', 'border-l-blue-500');
    } else {
        row.classList.remove('bg-blue-50', 'border-l-4', 'border-l-blue-500');
    }
}

function saveRow(id) {
    const row   = document.getElementById(`row-${id}`);
    const date  = row.querySelector('.date-edit').value;
    const price = row.querySelector('.price-edit').value;

    if (!date || !price) {
        showNotification('Tanggal dan harga wajib diisi!', 'error');
        return;
    }

    if (parseFloat(price) <= 0) {
        showNotification('Harga harus lebih dari 0!', 'error');
        return;
    }

    row.style.backgroundColor = '#fef3c7';

    fetch(`/operator/update-data/${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ date, price })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const [y, m, d] = date.split('-');
            row.querySelector('.date-view').textContent  = `${d}/${m}/${y}`;
            row.querySelector('.price-view').textContent = 'Rp ' + parseInt(price).toLocaleString('id-ID');
            row.style.backgroundColor = '#d1fae5';
            setTimeout(() => { row.style.backgroundColor = ''; toggleEditMode(id); }, 600);
            showNotification('Data tersimpan!', 'success');
        } else {
            showNotification('Gagal menyimpan: ' + (data.message || 'Terjadi kesalahan'), 'error');
            row.style.backgroundColor = '';
        }
    })
    .catch(() => {
        showNotification('Terjadi kesalahan jaringan', 'error');
        row.style.backgroundColor = '';
    });
}

// ============================
// TOAST NOTIFICATION
// ============================

function showNotification(message, type = 'success') {
    const existing = document.querySelector('.toast-notification');
    if (existing) existing.remove();

    const notification = document.createElement('div');
    notification.className = `toast-notification fixed top-4 right-4 z-50 px-6 py-4 rounded-lg shadow-lg text-white text-sm font-medium animate-fade-in ${type === 'success' ? 'bg-green-500' : 'bg-red-500'}`;
    notification.innerHTML = `
        <div class="flex items-center gap-3">
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
            <span>${message}</span>
        </div>`;
    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.opacity   = '0';
        notification.style.transform = 'translateX(100%)';
        notification.style.transition = 'all 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}
</script>

@endsection