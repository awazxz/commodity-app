@extends('layouts.app')

@section('content')
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div id="real-content">
<style>
    .dashboard-container { font-family: 'Inter', sans-serif; }

    .card-standard {
        background: white;
        border-radius: 0.5rem;
        border: 1px solid #e5e7eb;
        box-shadow: 0 1px 3px 0 rgba(0,0,0,0.1), 0 1px 2px 0 rgba(0,0,0,0.06);
    }
    html.dark .card-standard {
        background: #1e2433;
        border-color: #2d3748;
    }

    .hover-card { transition: all 0.3s ease; }
    .hover-card:hover { transform: translateY(-3px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }

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
    html.dark .filter-btn {
        background: #2d3748;
        border-color: #4a5568;
        color: #a0aec0;
    }
    .filter-btn.active { background: #2563eb; color: white; border-color: #2563eb; }
    .filter-btn:hover:not(.active) { background: #f8fafc; }
    html.dark .filter-btn:hover:not(.active) { background: #374151; }

    .insight-badge {
        padding: 0.25rem 0.625rem;
        border-radius: 9999px;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
    }
    .insight-naik   { background: #fee2e2; color: #991b1b; }
    .insight-turun  { background: #dcfce7; color: #166534; }
    .insight-stabil { background: #f3f4f6; color: #1f2937; }

    html.dark .insight-naik  { background: #7f1d1d; color: #fca5a5; }
    html.dark .insight-turun { background: #14532d; color: #86efac; }
    html.dark .insight-stabil { background: #374151; color: #d1d5db; }

    input[type="range"]::-webkit-slider-thumb {
        height: 16px; width: 16px; border-radius: 50%;
        background: #2563eb; cursor: pointer;
        border: 2px solid #fff;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        appearance: none; -webkit-appearance: none;
    }

    .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: #f8fafc; }
    html.dark .custom-scrollbar::-webkit-scrollbar-track { background: #1a202c; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    html.dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #4a5568; }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in { animation: fadeIn 0.4s ease-out; }

    .tab-single { display: block; }
    .tab-bulk   { display: none; }

    html.dark input, html.dark select, html.dark textarea {
        background-color: #2d3748 !important;
        border-color: #4a5568 !important;
        color: #e2e8f0 !important;
    }
    html.dark input[type="range"] { background-color: transparent !important; }
    html.dark input[type="checkbox"] { background-color: transparent !important; }
    html.dark tbody tr:hover { background-color: rgba(255,255,255,0.03) !important; }

    .param-changed-indicator {
        display: none;
        position: fixed;
        bottom: 1.5rem;
        right: 1.5rem;
        z-index: 999;
        background: #1e40af;
        color: white;
        padding: 0.75rem 1.25rem;
        border-radius: 0.5rem;
        box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        font-size: 0.75rem;
        font-weight: 600;
        animation: fadeIn 0.3s ease-out;
        cursor: pointer;
    }
    .param-changed-indicator.visible { display: flex; align-items: center; gap: 0.5rem; }

    .param-dirty {
        border: 1px solid #3b82f6 !important;
        background: #eff6ff !important;
    }
</style>
</div>

{{-- Skeleton overlay --}}
<div id="skeleton-overlay" class="hidden fixed inset-0 bg-white/50 dark:bg-black/50 z-50 flex items-center justify-center opacity-0">
    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
</div>

{{-- Floating indicator ketika parameter berubah --}}
<div class="param-changed-indicator" id="param-changed-indicator" onclick="triggerSubmit()">
    <i class="fas fa-sync-alt fa-spin"></i>
    <span>Parameter berubah — Klik untuk Perbarui Prediksi</span>
</div>

<div class="dashboard-container space-y-6 animate-fade-in">

{{-- TAB NAVIGATION --}}
<div class="card-standard p-1.5 flex gap-1">
    <a href="{{ route('admin.predict', ['tab' => 'insight']) }}"
       class="flex-1 flex items-center justify-center gap-2 py-2.5 px-4 rounded-md text-xs font-bold uppercase tracking-wider transition-all
              {{ ($currentTab ?? 'insight') === 'insight' ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
        <i class="fas fa-chart-line"></i>
        <span>Insight & Prediksi</span>
    </a>
    <a href="{{ route('admin.predict', ['tab' => 'manage']) }}"
       class="flex-1 flex items-center justify-center gap-2 py-2.5 px-4 rounded-md text-xs font-bold uppercase tracking-wider transition-all
              {{ ($currentTab ?? '') === 'manage' ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
        <i class="fas fa-database"></i>
        <span>Manajemen Data</span>
    </a>
    <a href="{{ route('admin.predict', ['tab' => 'users']) }}"
       class="flex-1 flex items-center justify-center gap-2 py-2.5 px-4 rounded-md text-xs font-bold uppercase tracking-wider transition-all
              {{ ($currentTab ?? '') === 'users' ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
        <i class="fas fa-users"></i>
        <span>Kelola Pengguna</span>
    </a>
</div>

{{-- Flash Messages --}}
@if(session('success'))
    <div class="p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-300 rounded-lg flex items-center gap-3">
        <i class="fas fa-check-circle"></i><span>{{ session('success') }}</span>
    </div>
@endif
@if(session('error'))
    <div class="p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 rounded-lg flex items-center gap-3">
        <i class="fas fa-exclamation-circle"></i><span>{{ session('error') }}</span>
    </div>
@endif

@if(($currentTab ?? 'insight') == 'insight')

    {{-- Header & Form --}}
    <div class="card-standard p-6">
        <div class="flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="flex items-center gap-4">
                <div class="bg-blue-600 p-3 rounded-lg text-white shadow-md">
                    <i class="fas fa-chart-line text-xl"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 leading-none">
                        Sistem Analisis Prediksi Harga Komoditas
                    </h2>
                    <p class="text-xs text-orange-500 font-medium uppercase tracking-wider mt-1.5">
                        Panel Administrator — BPS Provinsi Riau
                    </p>
                </div>
            </div>

              {{-- Status Flask API --}}
            <div class="flex items-center gap-2">
                  <span class="flex items-center gap-1.5 px-2 py-1 rounded-full text-[10px] font-medium transition-all duration-500" id="flask-status-badge">
                    <span class="w-2 h-2 rounded-full" id="flask-status-dot"></span>
                    <span id="flask-status-text">Memeriksa...</span>
                </span>
                <span class="text-[9px] text-gray-400">Flask API</span>
            </div>
        </div>


        {{-- FORM UTAMA --}}
        <form action="{{ route('admin.predict') }}" method="POST" id="mainForm" class="mt-6 pt-6 border-t border-gray-100 dark:border-gray-700">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                <div class="md:col-span-4">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase mb-2 block tracking-tight">
                        Komoditas Terpilih
                    </label>
                    <select name="komoditas_id" id="select_komoditas"
                            onchange="handleCommodityChange()"
                            class="w-full bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md py-2 px-3 text-sm font-medium text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all">
                        @foreach($commodities ?? [] as $kom)
                            <option value="{{ $kom->id }}" {{ $selectedKomoditasId == $kom->id ? 'selected' : '' }}>
                                {{ $kom->display_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="md:col-span-8">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase mb-2 block tracking-tight">
                        Rentang Waktu Analisis Historis
                    </label>
                    <div class="flex items-center gap-3 bg-gray-50 dark:bg-gray-700 p-1.5 rounded-md border border-gray-300 dark:border-gray-600">
                        <input type="date" name="start_date" id="input_start_date"
                               value="{{ $startDate ?? '2020-01-01' }}"
                               onchange="triggerSubmit()"
                               class="bg-transparent text-sm p-1 outline-none flex-1 font-medium text-gray-900 dark:text-gray-100">
                        <span class="text-gray-400 font-bold">→</span>
                        <input type="date" name="end_date" id="input_end_date"
                               value="{{ $endDate ?? date('Y-m-d') }}"
                               onchange="triggerSubmit()"
                               class="bg-transparent text-sm p-1 outline-none flex-1 font-medium text-gray-900 dark:text-gray-100">
                    </div>
                </div>
            </div>

            {{-- Hidden inputs untuk semua hyperparameter --}}
            <input type="hidden" name="changepoint_prior_scale" id="hidden_cp" value="{{ $cpScale ?? 0.05 }}">
            <input type="hidden" name="seasonality_prior_scale" id="hidden_season" value="{{ $seasonScale ?? 10 }}">
            <input type="hidden" name="seasonality_mode" id="hidden_mode" value="{{ $seasonMode ?? 'multiplicative' }}">
            <input type="hidden" name="weekly_seasonality" id="hidden_weekly" value="{{ ($weeklySeason ?? false) ? 'true' : 'false' }}">
            <input type="hidden" name="yearly_seasonality" id="hidden_yearly" value="{{ ($yearlySeason ?? false) ? 'true' : 'false' }}">
            <input type="hidden" name="forecast_weeks" id="hidden_forecast_weeks" value="{{ $forecastWeeks ?? 12 }}">
            <input type="hidden" name="tab" value="insight">
        </form>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
        <div class="card-standard hover-card p-5">
            <p class="text-[10px] uppercase text-gray-500 dark:text-gray-400 font-bold tracking-wider mb-2">Rata-rata Harga</p>
            <p class="text-xl font-bold text-gray-900 dark:text-gray-100">Rp {{ number_format($avgPrice ?? 0, 0, ',', '.') }}</p>
            <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1">{{ $countData ?? 0 }} data poin</p>
        </div>

        <div class="card-standard hover-card p-5">
            <p class="text-[10px] uppercase text-gray-500 dark:text-gray-400 font-bold tracking-wider mb-2">Harga Tertinggi</p>
            <p class="text-xl font-bold text-red-600 dark:text-red-400">Rp {{ number_format($maxPrice ?? 0, 0, ',', '.') }}</p>
        </div>

        <div class="card-standard hover-card p-5">
            <p class="text-[10px] uppercase text-gray-500 dark:text-gray-400 font-bold tracking-wider mb-2">Periode Data</p>
            <div class="flex items-center gap-2">
                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                    {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }}
                    <span class="text-gray-400 mx-1">→</span>
                    {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}
                </p>
            </div>
        </div>

        <div class="bg-blue-600 dark:bg-blue-700 rounded-lg p-5 text-white shadow-lg hover-card">
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

        {{-- Hyperparameter Panel --}}
        <div class="col-span-12 lg:col-span-4 space-y-4">
            <div class="card-standard p-5">
                <div class="flex items-center justify-between mb-5 pb-3 border-b border-gray-100 dark:border-gray-700">
                    <h4 class="text-sm font-bold text-gray-900 dark:text-gray-100 uppercase tracking-tight">
                        Pengaturan Hyperparameter
                    </h4>
                    <span class="text-[9px] bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 font-bold px-2 py-0.5 rounded-full uppercase">
                        Prophet Model
                    </span>
                </div>

                <div class="space-y-5">

                    {{-- Changepoint Prior Scale --}}
                    <div>
                        <div class="flex justify-between mb-2">
                            <span class="text-xs text-gray-500 dark:text-gray-400 font-semibold uppercase">Changepoint Prior</span>
                            <span class="text-xs font-mono font-bold text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 px-2 py-0.5 rounded" id="cp_display">{{ number_format($cpScale ?? 0.05, 3) }}</span>
                        </div>
                        <input type="range" min="0.001" max="0.5" step="0.001"
                               value="{{ $cpScale ?? 0.05 }}"
                               class="w-full h-1 bg-gray-100 dark:bg-gray-600 rounded-lg appearance-none cursor-pointer"
                               id="range_cp"
                               oninput="updateVal('hidden_cp', 'cp_display', this.value, 3); markParamDirty();">
                        <p class="text-[9px] text-gray-400 dark:text-gray-500 mt-1">Fleksibilitas perubahan tren (0.001 – 0.5)</p>
                    </div>

                    {{-- Seasonality Prior Scale --}}
                    <div>
                        <div class="flex justify-between mb-2">
                            <span class="text-xs text-gray-500 dark:text-gray-400 font-semibold uppercase">Seasonality Prior</span>
                            <span class="text-xs font-mono font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30 px-2 py-0.5 rounded" id="season_display">{{ number_format($seasonScale ?? 10, 2) }}</span>
                        </div>
                        <input type="range" min="0.01" max="50" step="0.01"
                               value="{{ $seasonScale ?? 10 }}"
                               class="w-full h-1 bg-gray-100 dark:bg-gray-600 rounded-lg appearance-none cursor-pointer"
                               id="range_season"
                               oninput="updateVal('hidden_season', 'season_display', this.value, 2); markParamDirty();">
                        <p class="text-[9px] text-gray-400 dark:text-gray-500 mt-1">Kekuatan pola musiman (0.01 – 50)</p>
                    </div>

                    {{-- Seasonality Mode --}}
                    <div>
                        <label class="text-xs text-gray-500 dark:text-gray-400 font-semibold uppercase mb-2 block">Mode Musiman</label>
                        <select id="select_mode"
                                onchange="updateMode(this.value); markParamDirty();"
                                class="w-full bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg py-2 px-3 text-xs text-gray-600 dark:text-gray-300 font-medium outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="multiplicative" {{ ($seasonMode ?? 'multiplicative') === 'multiplicative' ? 'selected' : '' }}>
                                Multiplikatif
                            </option>
                            <option value="additive" {{ ($seasonMode ?? '') === 'additive' ? 'selected' : '' }}>
                                Aditif
                            </option>
                        </select>
                        <p class="text-[9px] text-gray-400 dark:text-gray-500 mt-1">Metode penerapan musiman</p>
                    </div>

                    {{-- Horizon Prediksi slider --}}
                    <div class="pt-2 border-t border-gray-100 dark:border-gray-700">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-xs text-gray-500 dark:text-gray-400 font-semibold uppercase">Horizon Prediksi</span>
                            <span class="inline-flex items-center gap-1 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400 text-[9px] font-bold px-2 py-0.5 rounded-full uppercase" id="fw_display">
                                <i class="fas fa-calendar-alt" style="font-size:8px;"></i>
                                <span id="fw_display_text">{{ $forecastWeeks ?? 12 }} minggu</span>
                            </span>
                        </div>
                        <input type="range" min="1" max="52" step="1"
                               value="{{ $forecastWeeks ?? 12 }}"
                               class="w-full h-1 bg-gray-100 dark:bg-gray-600 rounded-lg appearance-none cursor-pointer"
                               id="range_fw"
                               oninput="updateForecastWeeks(this.value); markParamDirty();"
                               style="-webkit-appearance: none; appearance: none;">
                        <div class="flex justify-between text-[8px] text-gray-300 dark:text-gray-600 mt-1">
                            <span>1 minggu</span>
                            <span>26 minggu</span>
                            <span>52 minggu</span>
                        </div>
                        <p class="text-[9px] text-gray-400 dark:text-gray-500 mt-1">Jumlah minggu prediksi ke depan (1 – 52)</p>
                    </div>

                    {{-- Toggle Seasonality --}}
                    <div class="space-y-2 pt-2 border-t border-gray-100 dark:border-gray-700">
                        <label class="text-xs text-gray-500 dark:text-gray-400 font-semibold uppercase block mb-2">Komponen Musiman</label>

                        <div class="flex items-center justify-between p-2.5 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-100 dark:border-gray-700">
                            <div>
                                <span class="text-xs text-gray-500 dark:text-gray-400 font-medium uppercase">Mingguan</span>
                                <p class="text-[9px] text-gray-400 dark:text-gray-500">Deteksi pola per minggu</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox"
                                       id="checkbox_weekly"
                                       {{ ($weeklySeason ?? false) ? 'checked' : '' }}
                                       onchange="updateToggle('hidden_weekly', this.checked); markParamDirty();"
                                       class="sr-only peer">
                                <div class="w-9 h-5 bg-gray-200 dark:bg-gray-600 rounded-full peer peer-checked:bg-blue-600
                                            after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                                            after:bg-white after:rounded-full after:h-4 after:w-4
                                            after:transition-all peer-checked:after:translate-x-4"></div>
                            </label>
                        </div>

                        <div class="flex items-center justify-between p-2.5 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-100 dark:border-gray-700">
                            <div>
                                <span class="text-xs text-gray-500 dark:text-gray-400 font-medium uppercase">Tahunan</span>
                                <p class="text-[9px] text-gray-400 dark:text-gray-500">Deteksi pola per tahun</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox"
                                       id="checkbox_yearly"
                                       {{ ($yearlySeason ?? false) ? 'checked' : '' }}
                                       onchange="updateToggle('hidden_yearly', this.checked); markParamDirty();"
                                       class="sr-only peer">
                                <div class="w-9 h-5 bg-gray-200 dark:bg-gray-600 rounded-full peer peer-checked:bg-blue-600
                                            after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                                            after:bg-white after:rounded-full after:h-4 after:w-4
                                            after:transition-all peer-checked:after:translate-x-4"></div>
                            </label>
                        </div>
                    </div>

                    {{-- Preview parameter aktif --}}
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 border border-gray-100 dark:border-gray-700 space-y-1" id="param-preview-box">
                        <p class="text-[9px] text-gray-400 dark:text-gray-500 font-bold uppercase mb-2">Parameter Aktif (dikirim ke Flask)</p>
                        <div class="flex justify-between text-[10px]">
                            <span class="text-gray-500 dark:text-gray-400">cp_scale</span>
                            <span class="font-mono text-blue-600 dark:text-blue-400" id="preview_cp">{{ $cpScale ?? 0.05 }}</span>
                        </div>
                        <div class="flex justify-between text-[10px]">
                            <span class="text-gray-500 dark:text-gray-400">season_scale</span>
                            <span class="font-mono text-emerald-600 dark:text-emerald-400" id="preview_season">{{ $seasonScale ?? 10 }}</span>
                        </div>
                        <div class="flex justify-between text-[10px]">
                            <span class="text-gray-500 dark:text-gray-400">mode</span>
                            <span class="font-mono text-purple-600 dark:text-purple-400" id="preview_mode">{{ $seasonMode ?? 'multiplicative' }}</span>
                        </div>
                        <div class="flex justify-between text-[10px]">
                            <span class="text-gray-500 dark:text-gray-400">weekly</span>
                            <span class="font-mono text-gray-600 dark:text-gray-400" id="preview_weekly">{{ ($weeklySeason ?? false) ? 'true' : 'false' }}</span>
                        </div>
                        <div class="flex justify-between text-[10px]">
                            <span class="text-gray-500 dark:text-gray-400">yearly</span>
                            <span class="font-mono text-gray-600 dark:text-gray-400" id="preview_yearly">{{ ($yearlySeason ?? false) ? 'true' : 'false' }}</span>
                        </div>
                        <div class="flex justify-between text-[10px]">
                            <span class="text-gray-500 dark:text-gray-400">forecast_weeks</span>
                            <span class="font-mono text-indigo-600 dark:text-indigo-400" id="preview_fw">{{ $forecastWeeks ?? 12 }}</span>
                        </div>

                        <div id="param-dirty-notice" class="hidden mt-2 pt-2 border-t border-orange-200 dark:border-orange-800 text-[9px] text-orange-600 dark:text-orange-400 font-bold flex items-center gap-1">
                            <i class="fas fa-exclamation-triangle"></i>
                            Parameter belum diterapkan — klik "Perbarui Prediksi"
                        </div>
                    </div>

                    {{-- Tombol submit --}}
                    <button type="button" onclick="triggerSubmit()"
                            id="btn-update"
                            class="w-full bg-blue-600 text-white py-3 rounded-lg text-xs font-bold uppercase tracking-wider hover:bg-blue-700 transition-all shadow-sm flex items-center justify-center gap-2">
                        <i class="fas fa-sync-alt" id="btn-refresh-icon"></i>
                        Perbarui Prediksi
                    </button>

                    <div class="text-[9px] text-gray-400 dark:text-gray-500 text-center">
                        Prediksi terakhir menggunakan parameter di atas.<br>
                        Ubah parameter → klik "Perbarui Prediksi" untuk mendapatkan insight baru.
                    </div>
                </div>
            </div>

            {{-- Statistik Model --}}
            <div class="bg-gradient-to-br from-blue-600 to-blue-700 dark:from-blue-800 dark:to-blue-900 rounded-lg shadow-lg p-5 text-white">
                <h4 class="text-xs font-bold uppercase tracking-wider mb-3 opacity-90">Ringkasan Statistik</h4>
                <div class="space-y-3">
                    <div class="flex justify-between items-end border-b border-white/10 pb-2">
                        <div>
                            <span class="text-[10px] opacity-70 font-semibold uppercase">MAPE (CV 80/20)</span>
                            <p class="text-[8px] opacity-50 mt-0.5">Berubah saat hyperparameter berubah</p>
                        </div>
                        <span class="text-sm font-bold">{{ number_format($mape ?? 0, 2) }}%</span>
                    </div>
                    <div class="flex justify-between items-end border-b border-white/10 pb-2">
                        <span class="text-[10px] opacity-70 font-semibold uppercase">Skor R-Squared</span>
                        <span class="text-sm font-bold">{{ number_format($rSquared ?? 0, 3) }}</span>
                    </div>
                    <div class="flex justify-between items-end border-b border-white/10 pb-2">
                        <span class="text-[10px] opacity-70 font-semibold uppercase">Total Data Poin</span>
                        <span class="text-sm font-bold">{{ $countData ?? 0 }}</span>
                    </div>
                    <div class="flex justify-between items-end">
                        <span class="text-[10px] opacity-70 font-semibold uppercase">Horizon Prediksi</span>
                        <span class="text-sm font-bold">{{ $forecastWeeks ?? 12 }} minggu</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Chart --}}
        <div class="col-span-12 lg:col-span-8 card-standard overflow-hidden flex flex-col">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50 flex flex-col lg:flex-row justify-between items-center gap-4 flex-shrink-0">
                <div>
                    <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 uppercase tracking-tight">Visualisasi Tren & Proyeksi</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $selectedCommodity }} — Data Historis vs Proyeksi
                        <span class="ml-2 inline-flex items-center gap-1 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400 text-[9px] font-bold px-2 py-0.5 rounded-full uppercase">
                            <i class="fas fa-calendar-alt" style="font-size:8px;"></i>
                            {{ $forecastWeeks ?? 12 }} minggu ke depan
                        </span>
                    </p>
                </div>
                <div class="flex bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 p-1 rounded-md shadow-sm">
                    <button onclick="changeChartPeriod('weekly')"  class="filter-btn active" id="btn-weekly">Mingguan</button>
                    <button onclick="changeChartPeriod('monthly')" class="filter-btn border-none" id="btn-monthly">Bulanan</button>
                    <button onclick="changeChartPeriod('yearly')"  class="filter-btn border-none" id="btn-yearly">Tahunan</button>
                </div>
            </div>
            <div class="flex-1 p-6" style="min-height: 500px;">
                <canvas id="mainChart"></canvas>
            </div>
        </div>
    </div>

    {{-- Insight Table --}}
    <div class="card-standard overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50 flex justify-between items-center">
            <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 uppercase tracking-tight">
                Ringkasan Analisis
                <span id="selectedPeriodText" class="text-blue-600 dark:text-blue-400">Mingguan</span>
            </h3>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-400 dark:text-gray-500">{{ $selectedCommodity }}</span>
                <span class="text-[9px] bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 font-bold px-2 py-0.5 rounded-full">
                    MAPE: {{ number_format($mape ?? 0, 2) }}%
                </span>
                <span class="inline-flex items-center gap-1 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400 text-[9px] font-bold px-2 py-0.5 rounded-full uppercase">
                    {{ $forecastWeeks ?? 12 }} minggu
                </span>
            </div>
        </div>
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-[11px] font-bold text-gray-500 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                        <th class="px-6 py-4">Periode</th>
                        <th class="px-6 py-4 text-right">Harga Aktual</th>
                        <th class="px-6 py-4 text-right">Harga Prediksi</th>
                        <th class="px-6 py-4 text-right">Interval Bawah</th>
                        <th class="px-6 py-4 text-right">Interval Atas</th>
                        <th class="px-6 py-4 text-right">Selisih</th>
                        <th class="px-6 py-4 text-center">Indikator</th>
                    </tr>
                </thead>
                <tbody class="text-sm text-gray-700 dark:text-gray-300 divide-y divide-gray-100 dark:divide-gray-700" id="insightTableBody">
                </tbody>
            </table>
        </div>
    </div>

    {{-- Interpretasi --}}
    <div class="card-standard p-6 border-l-4 border-l-blue-600">
        <div class="flex items-center gap-3 mb-3">
            <h4 class="text-sm font-bold text-gray-900 dark:text-gray-100 uppercase">Interpretasi Analisis Tren</h4>
        </div>
        <p id="dynamic-analysis" class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
            Berdasarkan analisis data historis untuk komoditas <strong>{{ $selectedCommodity }}</strong>,
            model mendeteksi tren harga <strong>{{ strtolower($trendDir ?? 'stabil') }}</strong>
            dengan rata-rata harga <strong>Rp {{ number_format($avgPrice ?? 0, 0, ',', '.') }}</strong>
            dan total <strong>{{ $countData ?? 0 }} data poin</strong> pada periode
            {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }}
            s/d {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}.
            Model Prophet dilatih dengan <strong>changepoint_prior_scale={{ $cpScale ?? 0.05 }}</strong>,
            <strong>seasonality_prior_scale={{ $seasonScale ?? 10 }}</strong>,
            mode <strong>{{ $seasonMode ?? 'multiplicative' }}</strong>,
            horizon prediksi <strong>{{ $forecastWeeks ?? 12 }} minggu ke depan</strong>.
            Nilai MAPE (Cross-Validation 80/20) sebesar <strong>{{ number_format($mape ?? 0, 2) }}%</strong>
            menunjukkan {{ ($mape ?? 0) < 5 ? 'akurasi sangat baik' : (($mape ?? 0) < 10 ? 'akurasi baik' : 'perlu penyesuaian hyperparameter') }}.
        </p>
    </div>

@endif

@if($currentTab == 'manage')
    <div class="space-y-6 animate-fade-in">

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1">
                <div class="card-standard p-6">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 mb-6 uppercase tracking-tight">Tambah Data Baru</h3>
                    <div class="flex gap-4 mb-6 border-b dark:border-gray-700 pb-2">
                        <button onclick="switchInputMode('single')" id="btn-tab-single"
                                class="text-blue-600 border-b-2 border-blue-600 text-xs uppercase tracking-wider pb-1 font-semibold">Manual</button>
                        <button onclick="switchInputMode('bulk')" id="btn-tab-bulk"
                                class="text-gray-400 dark:text-gray-500 text-xs uppercase tracking-wider pb-1 font-semibold">Unggah CSV</button>
                    </div>

                    <form id="form-single" action="{{ route('admin.storeData') }}" method="POST" class="space-y-4 tab-single">
                        @csrf
                        <div>
                            <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase mb-1.5 block tracking-tight">Komoditas</label>
                            <select name="komoditas_id" required
                                    class="w-full bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg p-3 text-xs text-gray-900 dark:text-gray-100 font-medium outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">-- Pilih Komoditas --</option>
                                @foreach($commodities ?? [] as $kom)
                                    <option value="{{ $kom->id }}" {{ $selectedKomoditasId == $kom->id ? 'selected' : '' }}>
                                        {{ $kom->display_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase mb-1.5 block tracking-tight">Tanggal</label>
                            <input type="date" name="date" required
                                   class="w-full bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg p-3 text-xs text-gray-600 dark:text-gray-300 font-medium focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase mb-1.5 block tracking-tight">Harga (Rp)</label>
                            <input type="number" name="price" placeholder="Masukkan harga" required min="1"
                                   class="w-full bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg p-3 text-xs text-gray-600 dark:text-gray-300 font-medium focus:ring-2 focus:ring-blue-500">
                        </div>
                        <button type="submit"
                                class="w-full bg-emerald-500 text-white py-3 rounded-lg text-xs font-bold uppercase tracking-wider hover:bg-emerald-600 transition-all">
                            Simpan Data
                        </button>
                    </form>

                    <form id="form-bulk" action="{{ route('admin.manajemen-data.upload-csv') }}" method="POST"
                          enctype="multipart/form-data" class="space-y-4 tab-bulk">
                        @csrf
                        <div>
                            <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase mb-1.5 block tracking-tight">Unggah File CSV</label>
                            <div class="p-8 border-2 border-dashed border-gray-200 dark:border-gray-600 rounded-xl bg-gray-50/50 dark:bg-gray-700/30 text-center relative hover:border-blue-300 transition-colors" id="dropzone">
                                <input type="file" name="csv_file" accept=".csv"
                                       class="absolute inset-0 opacity-0 cursor-pointer"
                                       onchange="showFileName(this)">
                                <i class="fas fa-cloud-upload-alt text-gray-300 dark:text-gray-500 text-2xl mb-2"></i>
                                <p class="text-xs text-gray-400 dark:text-gray-500 font-medium" id="file-name-display">Pilih atau seret file CSV ke sini</p>
                                <p class="text-[9px] text-gray-300 dark:text-gray-600 mt-1">Format: nama_komoditas, nama_varian, tanggal, harga</p>
                            </div>
                        </div>

                        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                            <div class="flex items-start gap-3">
                                <i class="fas fa-info-circle text-blue-500 dark:text-blue-400 text-sm mt-0.5"></i>
                                <div class="flex-1">
                                    <p class="text-xs text-blue-700 dark:text-blue-300 font-semibold uppercase tracking-tight mb-2">Template CSV</p>
                                    <p class="text-[10px] text-blue-600 dark:text-blue-400 mb-3 leading-relaxed">
                                        Gunakan template standar untuk memastikan format data yang benar
                                    </p>
                                    <a href="{{ route('admin.downloadTemplate') }}"
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
                    <div class="p-5 border-b dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50 flex justify-between items-center">
                        <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 uppercase tracking-tight">Riwayat Database</h3>
                        <span class="text-xs text-gray-400 dark:text-gray-500">{{ $selectedCommodity }}</span>
                    </div>
                    <div class="overflow-x-auto" style="max-height: 450px;">
                        <div class="custom-scrollbar">
                            <table class="w-full text-left">
                                <thead class="sticky top-0 bg-white dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700 z-10">
                                    <tr class="text-xs text-gray-400 dark:text-gray-500 uppercase font-bold">
                                        <th class="px-6 py-4">Komoditas</th>
                                        <th class="px-6 py-4">Tanggal</th>
                                        <th class="px-6 py-4">Harga</th>
                                        <th class="px-6 py-4 text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-xs">
                                    @forelse($latestData ?? [] as $data)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors" id="row-{{ $data->id }}">
                                            <td class="px-6 py-4 uppercase font-bold text-blue-600 dark:text-blue-400">
                                                <span class="commodity-view">{{ $data->komoditas->display_name ?? '-' }}</span>
                                                <select class="commodity-edit hidden w-full bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg p-2 text-xs font-medium focus:ring-2 focus:ring-blue-500 text-gray-900 dark:text-gray-100"
                                                        data-id="{{ $data->id }}" onchange="autoSaveData({{ $data->id }})">
                                                    @foreach($commodities ?? [] as $kom)
                                                        <option value="{{ $kom->id }}" {{ $data->komoditas_id == $kom->id ? 'selected' : '' }}>
                                                            {{ $kom->display_name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td class="px-6 py-4 text-gray-500 dark:text-gray-400">
                                                <span class="date-view">{{ \Carbon\Carbon::parse($data->tanggal)->format('d/m/Y') }}</span>
                                                <input type="date" class="date-edit hidden w-full bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg p-2 text-xs focus:ring-2 focus:ring-blue-500 text-gray-900 dark:text-gray-100"
                                                       value="{{ $data->tanggal }}" data-id="{{ $data->id }}" onchange="autoSaveData({{ $data->id }})">
                                            </td>
                                            <td class="px-6 py-4 font-bold text-emerald-600 dark:text-emerald-400">
                                                <span class="price-view">Rp {{ number_format($data->harga, 0, ',', '.') }}</span>
                                                <input type="number" class="price-edit hidden w-full bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg p-2 text-xs focus:ring-2 focus:ring-blue-500 text-gray-900 dark:text-gray-100"
                                                       value="{{ $data->harga }}" data-id="{{ $data->id }}" onchange="autoSaveData({{ $data->id }})">
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center justify-center gap-3">
                                                    <button type="button" onclick="toggleEditMode({{ $data->id }})"
                                                            class="edit-btn text-blue-500 hover:text-blue-700 transition-colors text-sm font-medium">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                    <button type="button" onclick="toggleEditMode({{ $data->id }})"
                                                            class="done-btn hidden text-green-500 hover:text-green-700 transition-colors text-sm font-medium">
                                                        <i class="fas fa-check"></i> Selesai
                                                    </button>
                                                    <form action="{{ route('admin.deleteData', $data->id) }}" method="POST"
                                                          onsubmit="return confirm('Hapus data ini?')" class="inline delete-form">
                                                        @csrf @method('DELETE')
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
                                                <div class="flex flex-col items-center gap-2 text-gray-400 dark:text-gray-500">
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
                        <div class="px-6 py-4 border-t dark:border-gray-700 bg-gray-50/30 dark:bg-gray-800/30">
                            <div class="flex items-center justify-between">
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    Menampilkan {{ $latestData->firstItem() ?? 0 }} - {{ $latestData->lastItem() ?? 0 }}
                                    dari {{ $latestData->total() }} data
                                </div>
                                <div class="flex gap-1">
                                    @if ($latestData->onFirstPage())
                                        <span class="px-3 py-1.5 text-xs text-gray-400 dark:text-gray-500 bg-gray-100 dark:bg-gray-700 rounded cursor-not-allowed"><i class="fas fa-chevron-left"></i></span>
                                    @else
                                        <a href="{{ $latestData->previousPageUrl() }}" class="px-3 py-1.5 text-xs text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded hover:bg-gray-50 dark:hover:bg-gray-700"><i class="fas fa-chevron-left"></i></a>
                                    @endif
                                    @foreach ($latestData->getUrlRange(1, $latestData->lastPage()) as $page => $url)
                                        @if ($page == $latestData->currentPage())
                                            <span class="px-3 py-1.5 text-xs font-bold text-white bg-blue-600 rounded">{{ $page }}</span>
                                        @else
                                            <a href="{{ $url }}" class="px-3 py-1.5 text-xs text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded hover:bg-gray-50 dark:hover:bg-gray-700">{{ $page }}</a>
                                        @endif
                                    @endforeach
                                    @if ($latestData->hasMorePages())
                                        <a href="{{ $latestData->nextPageUrl() }}" class="px-3 py-1.5 text-xs text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded hover:bg-gray-50 dark:hover:bg-gray-700"><i class="fas fa-chevron-right"></i></a>
                                    @else
                                        <span class="px-3 py-1.5 text-xs text-gray-400 dark:text-gray-500 bg-gray-100 dark:bg-gray-700 rounded cursor-not-allowed"><i class="fas fa-chevron-right"></i></span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Data Cleaning --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1">
                <div class="card-standard p-6" style="height: fit-content;">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 uppercase tracking-tight mb-6">Pembersihan Data</h3>

                    <form action="{{ route('admin.predict') }}" method="POST" class="mb-6 pb-6 border-b dark:border-gray-700">
                        @csrf
                        <input type="hidden" name="tab" value="manage">
                        <label class="text-xs text-gray-700 dark:text-gray-300 font-semibold block mb-2 uppercase tracking-tight">Pindai Data Untuk</label>
                        <div class="flex gap-2">
                            <select name="komoditas_id"
                                    class="flex-1 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg p-2 text-xs font-medium outline-none focus:ring-2 focus:ring-blue-500 text-gray-900 dark:text-gray-100">
                                <option value="">-- Pilih Komoditas --</option>
                                @foreach($commodities ?? [] as $kom)
                                    <option value="{{ $kom->id }}" {{ $selectedKomoditasId == $kom->id ? 'selected' : '' }}>
                                        {{ $kom->display_name }}
                                    </option>
                                @endforeach
                            </select>
                            <button type="submit" class="bg-blue-600 text-white px-4 rounded-lg text-xs font-bold uppercase hover:bg-blue-700">Pindai</button>
                        </div>
                    </form>

                    <form action="{{ route('admin.cleanData') }}" method="POST" class="space-y-6">
                        @csrf
                        <input type="hidden" name="komoditas_id" value="{{ $selectedKomoditasId }}">
                        <div class="space-y-4">
                            <div>
                                <label class="text-xs text-gray-700 dark:text-gray-300 font-semibold block mb-2 uppercase tracking-tight">Deteksi Outlier</label>
                                <div class="flex items-center gap-2">
                                    <select name="outlier_method"
                                            class="flex-1 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg p-2.5 text-xs font-medium outline-none focus:ring-2 focus:ring-blue-500 text-gray-900 dark:text-gray-100">
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
                                <label class="text-xs text-gray-700 dark:text-gray-300 font-semibold block mb-2 uppercase tracking-tight">Nilai Hilang</label>
                                <div class="flex items-center gap-2">
                                    <select name="missing_method"
                                            class="flex-1 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg p-2.5 text-xs font-medium outline-none focus:ring-2 focus:ring-blue-500 text-gray-900 dark:text-gray-100">
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
                <div class="card-standard border-orange-200 dark:border-orange-800 overflow-hidden flex flex-col" style="height: fit-content;">
                    <div class="p-4 bg-orange-50/50 dark:bg-orange-900/10 border-b border-orange-100 dark:border-orange-800 flex justify-between items-center">
                        <h3 class="text-xs text-orange-700 dark:text-orange-400 font-bold uppercase tracking-tight">Hasil Pemindaian: {{ $selectedCommodity }}</h3>
                        <span class="bg-orange-100 dark:bg-orange-900/30 text-orange-600 dark:text-orange-400 px-2 py-0.5 rounded text-[10px] font-bold">
                            {{ ($dataIssues instanceof \Illuminate\Pagination\LengthAwarePaginator ? $dataIssues->total() : count($dataIssues ?? [])) }} Temuan
                        </span>
                    </div>
                    <div class="overflow-x-auto" style="max-height: 350px;">
                        <div class="custom-scrollbar">
                            <table class="w-full text-left">
                                <thead class="bg-gray-50 dark:bg-gray-800 sticky top-0 text-xs text-gray-400 dark:text-gray-500 uppercase font-bold z-10">
                                    <tr>
                                        <th class="px-6 py-3">Tanggal</th>
                                        <th class="px-6 py-3">Jenis Masalah</th>
                                        <th class="px-6 py-3">Nilai</th>
                                        <th class="px-6 py-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-xs">
                                    @forelse($dataIssues ?? [] as $issue)
                                        <tr class="bg-orange-50/20 dark:bg-orange-900/5 hover:bg-orange-50/40 dark:hover:bg-orange-900/10 transition-colors">
                                            <td class="px-6 py-3 font-medium text-gray-700 dark:text-gray-300">{{ \Carbon\Carbon::parse($issue->date)->format('d/m/Y') }}</td>
                                            <td class="px-6 py-3">
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $issue->issue == 'Outlier' ? 'bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400' : 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-600 dark:text-yellow-400' }}">
                                                    {{ $issue->issue }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-3 font-medium text-gray-700 dark:text-gray-300">Rp {{ number_format($issue->value, 0, ',', '.') }}</td>
                                            <td class="px-6 py-3 text-gray-500 dark:text-gray-400">{{ $issue->status }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="p-8 text-center">
                                                <div class="flex flex-col items-center gap-2 text-gray-400 dark:text-gray-500">
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
                </div>
            </div>
        </div>

    </div>
@endif

@if($currentTab == 'users')
    <div class="grid grid-cols-12 gap-6 animate-fade-in">
        <div class="col-span-12 lg:col-span-4">
            <div class="card-standard p-6">
                <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 uppercase tracking-tight mb-6">Ringkasan Pengguna</h3>
                <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700/50 rounded-xl border border-gray-100 dark:border-gray-700 mb-6">
                    <div>
                        <p class="text-[10px] text-gray-400 dark:text-gray-500 font-bold uppercase tracking-wider">Total Pengguna</p>
                        <p class="text-lg text-blue-600 dark:text-blue-400 font-bold">
                            {{ $users instanceof \Illuminate\Pagination\LengthAwarePaginator ? $users->total() : count($users ?? []) }} Aktif
                        </p>
                    </div>
                    <div class="bg-blue-100 dark:bg-blue-900/30 p-2.5 rounded-lg">
                        <i class="fas fa-users text-blue-600 dark:text-blue-400"></i>
                    </div>
                </div>

                <div class="p-5 border border-gray-200 dark:border-gray-700 rounded-xl">
                    <h4 class="text-xs text-orange-500 font-bold uppercase mb-4 tracking-tight">Buat Pengguna Baru</h4>
                    <form id="formTambahUser" action="{{ route('admin.storeUser') }}" method="POST"
                          class="space-y-3" onsubmit="return validateTambahUser(event)">
                        @csrf
                        <div>
                            <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase mb-1.5 block tracking-tight">Nama Lengkap</label>
                            <input type="text" id="input-name" name="name" required
                                   class="w-full bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg p-2.5 text-xs text-gray-900 dark:text-gray-100 font-medium outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="Masukkan nama lengkap">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase mb-1.5 block tracking-tight">Alamat Email</label>
                            <input type="email" id="input-email" name="email" required
                                   class="w-full bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg p-2.5 text-xs text-gray-900 dark:text-gray-100 font-medium outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="contoh@email.com">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase mb-1.5 block tracking-tight">
                                Kata Sandi <span class="text-gray-400 font-normal normal-case ml-1">(min. 8 karakter)</span>
                            </label>
                            <div class="relative">
                                <input type="password" id="input-password" name="password" required
                                       class="w-full bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg p-2.5 pr-10 text-xs text-gray-900 dark:text-gray-100 font-medium outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="Minimal 8 karakter"
                                       oninput="checkPasswordStrength(this.value)">
                                <button type="button" onclick="togglePasswordVisibility()"
                                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                    <i class="fas fa-eye text-xs" id="eye-icon"></i>
                                </button>
                            </div>
                            <div class="mt-1.5 h-1 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                                <div id="password-strength-bar" class="h-full rounded-full transition-all duration-300 w-0"></div>
                            </div>
                            <p id="password-strength-text" class="text-[10px] mt-1 text-gray-400 dark:text-gray-500"></p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase mb-1.5 block tracking-tight">Role</label>
                            <select id="input-role" name="role" required
                                    class="w-full bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg p-2.5 text-xs text-gray-900 dark:text-gray-100 font-medium outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="user">Pengguna</option>
                                <option value="operator">Operator</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                        <button type="submit"
                                class="w-full bg-orange-500 text-white py-2.5 rounded-lg text-xs font-bold uppercase tracking-wider hover:bg-orange-600 transition-all">
                            <i class="fas fa-user-plus mr-1"></i> Buat Pengguna
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-span-12 lg:col-span-8 card-standard overflow-hidden">
            <div class="p-5 border-b dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50">
                <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 uppercase tracking-tight">Kelola Akses Pengguna</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-xs text-gray-400 dark:text-gray-500 font-bold uppercase tracking-tight bg-gray-50/30 dark:bg-gray-800/30">
                            <th class="px-6 py-4">Informasi Pengguna</th>
                            <th class="px-6 py-4">Email</th>
                            <th class="px-6 py-4">Peran</th>
                            <th class="px-6 py-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($users ?? [] as $user)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors" id="user-row-{{ $user->id }}">
                            <td class="px-6 py-4">
                                <span class="name-view text-sm text-blue-600 dark:text-blue-400 font-bold">{{ $user->name }}</span>
                                <input type="text" class="name-edit hidden w-full bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg p-2 text-xs font-medium focus:ring-2 focus:ring-blue-500 text-gray-900 dark:text-gray-100"
                                       value="{{ $user->name }}" data-id="{{ $user->id }}" onchange="autoSaveUser({{ $user->id }})">
                            </td>
                            <td class="px-6 py-4">
                                <span class="email-view text-xs text-gray-400 dark:text-gray-500 font-medium">{{ $user->email }}</span>
                                <input type="email" class="email-edit hidden w-full bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg p-2 text-xs font-medium focus:ring-2 focus:ring-blue-500 text-gray-900 dark:text-gray-100"
                                       value="{{ $user->email }}" data-id="{{ $user->id }}" onchange="autoSaveUser({{ $user->id }})">
                            </td>
                            <td class="px-6 py-4">
                                <span class="role-view px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider
                                    {{ $user->role == 'admin' ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400' : ($user->role == 'operator' ? 'bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300') }}">
                                    {{ $user->role == 'admin' ? 'Administrator' : ($user->role == 'operator' ? 'Operator' : 'Pengguna') }}
                                </span>
                                <select class="role-edit hidden w-full bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg p-2 text-xs font-medium focus:ring-2 focus:ring-blue-500 text-gray-900 dark:text-gray-100"
                                        data-id="{{ $user->id }}" onchange="autoSaveUser({{ $user->id }})">
                                    <option value="user"     {{ $user->role == 'user'     ? 'selected' : '' }}>Pengguna</option>
                                    <option value="operator" {{ $user->role == 'operator' ? 'selected' : '' }}>Operator</option>
                                    <option value="admin"    {{ $user->role == 'admin'    ? 'selected' : '' }}>Administrator</option>
                                </select>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-center gap-3">
                                    <button type="button" onclick="toggleEditUserMode({{ $user->id }})"
                                            class="edit-user-btn text-blue-500 hover:text-blue-700 dark:hover:text-blue-400 transition-colors text-sm font-medium">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button type="button" onclick="toggleEditUserMode({{ $user->id }})"
                                            class="done-user-btn hidden text-green-500 hover:text-green-700 dark:hover:text-green-400 transition-colors text-sm font-medium">
                                        <i class="fas fa-check"></i> Selesai
                                    </button>
                                    @if(auth()->id() !== $user->id)
                                    <form action="{{ route('admin.deleteUser', $user->id) }}" method="POST"
                                          class="inline delete-user-form" id="delete-form-{{ $user->id }}">
                                        @csrf @method('DELETE')
                                        <button type="button"
                                                onclick="confirmDeleteUser({{ $user->id }}, '{{ addslashes($user->name) }}')"
                                                class="text-red-400 hover:text-red-600 dark:hover:text-red-400 transition-colors text-sm font-medium">
                                            <i class="fas fa-trash"></i> Hapus
                                        </button>
                                    </form>
                                    @else
                                    <span class="text-gray-300 dark:text-gray-600 text-xs italic">Akun Aktif</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($users instanceof \Illuminate\Pagination\LengthAwarePaginator && $users->hasPages())
                <div class="px-6 py-4 border-t dark:border-gray-700 bg-gray-50/30 dark:bg-gray-800/30">
                    <div class="flex items-center justify-between">
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            Menampilkan {{ $users->firstItem() }} - {{ $users->lastItem() }} dari {{ $users->total() }} pengguna
                        </div>
                        {{ $users->appends(request()->query())->links() }}
                    </div>
                </div>
            @endif
        </div>
    </div>
@endif

</div>{{-- end dashboard-container --}}

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
const CURRENT_TAB   = '{{ $currentTab ?? "insight" }}';
const SELECTED_COMMODITY = '{{ addslashes($selectedCommodity ?? "") }}';

const chartData = {
    weekly: {
        labels:   @json($weeklyLabels   ?? []),
        actual:   @json($weeklyActual   ?? []),
        forecast: @json($weeklyForecast ?? []),
        lower:    @json($weeklyLower    ?? []),
        upper:    @json($weeklyUpper    ?? [])
    },
    monthly: {
        labels:   @json($monthlyLabels   ?? []),
        actual:   @json($monthlyActual   ?? []),
        forecast: @json($monthlyForecast ?? []),
        lower:    @json($monthlyLower    ?? []),
        upper:    @json($monthlyUpper    ?? [])
    },
    yearly: {
        labels:   @json($yearlyLabels   ?? []),
        actual:   @json($yearlyActual   ?? []),
        forecast: @json($yearlyForecast ?? []),
        lower:    @json($yearlyLower    ?? []),
        upper:    @json($yearlyUpper    ?? [])
    }
};

let parametersDirty = false;
let currentPeriod   = 'weekly';
let mainChart       = null;

function isDark() { return document.documentElement.classList.contains('dark'); }

function updateVal(hiddenId, displayId, val, decimals) {
    const parsed = parseFloat(val);
    document.getElementById(hiddenId).value = parsed;
    document.getElementById(displayId).textContent = parsed.toFixed(decimals);
}

function updateMode(value) {
    document.getElementById('hidden_mode').value = value;
    document.getElementById('preview_mode').textContent = value;
}

function updateToggle(hiddenId, isChecked) {
    const stringVal = isChecked ? 'true' : 'false';
    document.getElementById(hiddenId).value = stringVal;
}

function updateForecastWeeks(val) {
    const weeks = parseInt(val);
    document.getElementById('hidden_forecast_weeks').value = weeks;
    document.getElementById('fw_display_text').textContent = weeks + ' minggu';
    document.getElementById('preview_fw').textContent = weeks;
}

function markParamDirty() {
    parametersDirty = true;
    const indicator = document.getElementById('param-changed-indicator');
    if (indicator) indicator.classList.add('visible');
    const notice = document.getElementById('param-dirty-notice');
    if (notice) notice.classList.remove('hidden');
    const btn = document.getElementById('btn-update');
    if (btn) {
        btn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
        btn.classList.add('bg-orange-500', 'hover:bg-orange-600');
    }
    const previewBox = document.getElementById('param-preview-box');
    if (previewBox) previewBox.classList.add('param-dirty');
}

function clearParamDirty() {
    parametersDirty = false;
    const indicator = document.getElementById('param-changed-indicator');
    if (indicator) indicator.classList.remove('visible');
    const notice = document.getElementById('param-dirty-notice');
    if (notice) notice.classList.add('hidden');
    const btn = document.getElementById('btn-update');
    if (btn) {
        btn.classList.add('bg-blue-600', 'hover:bg-blue-700');
        btn.classList.remove('bg-orange-500', 'hover:bg-orange-600');
    }
    const previewBox = document.getElementById('param-preview-box');
    if (previewBox) previewBox.classList.remove('param-dirty');
}

function triggerSubmit() {
    const cp     = document.getElementById('hidden_cp');
    const season = document.getElementById('hidden_season');
    const mode   = document.getElementById('hidden_mode');
    const weekly = document.getElementById('hidden_weekly');
    const yearly = document.getElementById('hidden_yearly');
    const fw     = document.getElementById('hidden_forecast_weeks');

    if (!cp.value || isNaN(parseFloat(cp.value)))         cp.value = '0.05';
    if (!season.value || isNaN(parseFloat(season.value))) season.value = '10';
    if (!mode.value)   mode.value = 'multiplicative';
    if (weekly.value !== 'true' && weekly.value !== 'false') weekly.value = 'false';
    if (yearly.value !== 'true' && yearly.value !== 'false') yearly.value = 'false';
    if (!fw || !fw.value || isNaN(parseInt(fw.value)) || parseInt(fw.value) < 1) fw.value = '12';

    const icon = document.getElementById('btn-refresh-icon');
    if (icon) icon.classList.add('fa-spin');

    document.getElementById('real-content').classList.add('opacity-30');
    const overlay = document.getElementById('skeleton-overlay');
    if (overlay) { overlay.classList.remove('hidden'); overlay.style.opacity = '1'; }

    clearParamDirty();
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
        btnBulk.className   = 'text-gray-400 dark:text-gray-500 text-xs uppercase tracking-wider pb-1 font-semibold';
    } else {
        formSingle.style.display = 'none';
        formBulk.style.display   = 'block';
        btnSingle.className = 'text-gray-400 dark:text-gray-500 text-xs uppercase tracking-wider pb-1 font-semibold';
        btnBulk.className   = 'text-blue-600 border-b-2 border-blue-600 text-xs uppercase tracking-wider pb-1 font-semibold';
    }
}

    function checkFlaskStatus() {
        const badge = document.getElementById('flask-status-badge');
        const dot = document.getElementById('flask-status-dot');
        const text = document.getElementById('flask-status-text');

        // State: loading
        badge.className = 'flex items-center gap-1.5 px-2 py-1 rounded-full text-[10px] font-medium transition-all duration-500 bg-gray-100 text-gray-500';
        dot.className = 'w-2 h-2 rounded-full bg-gray-400 animate-pulse';
        text.textContent = 'Memeriksa...';

        fetch('/api/flask-health') // sesuaikan endpoint-nya
            .then(res => {
                if (res.ok) {
                    // Online - hijau
                    badge.className = 'flex items-center gap-1.5 px-2 py-1 rounded-full text-[10px] font-medium transition-all duration-500 bg-green-100 text-green-700';
                    dot.className = 'w-2 h-2 rounded-full bg-green-500 shadow-[0_0_6px_rgba(34,197,94,0.8)]';
                    text.textContent = 'Online';
                } else {
                    throw new Error('not ok');
                }
            })
            .catch(() => {
                // Offline - merah
                badge.className = 'flex items-center gap-1.5 px-2 py-1 rounded-full text-[10px] font-medium transition-all duration-500 bg-red-100 text-red-700';
                dot.className = 'w-2 h-2 rounded-full bg-red-500 shadow-[0_0_6px_rgba(239,68,68,0.8)]';
                text.textContent = 'Offline';
            });
    }

    // Cek saat halaman load
    checkFlaskStatus();

    // Cek ulang tiap 30 detik
    setInterval(checkFlaskStatus, 30000);


function initializeChart() {
    const canvas = document.getElementById('mainChart');
    if (!canvas) return;

    const ctx  = canvas.getContext('2d');
    const data = chartData[currentPeriod];
    const dark = isDark();

    if (!data.labels || data.labels.length === 0) {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = '#9ca3af';
        ctx.font      = '14px Inter, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('Tidak ada data untuk periode ini', canvas.width / 2, canvas.height / 2);
        return;
    }

    const gradientActual = ctx.createLinearGradient(0, 0, 0, 400);
    gradientActual.addColorStop(0, dark ? 'rgba(96,165,250,0.3)' : 'rgba(4, 50, 119, 0.15)');
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
                    borderColor: dark ? '#60a5fa' : '#043277',
                    backgroundColor: gradientActual,
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 0,
                    pointHoverRadius: 6,
                    pointHoverBackgroundColor: dark ? '#60a5fa' : '#043277',
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
                    text: SELECTED_COMMODITY,
                    color: dark ? '#93c5fd' : '#043277',
                    font: { size: 14, weight: '600', family: 'Inter' },
                    padding: { top: 10, bottom: 15 }
                },
                legend: {
                    display: true, position: 'top', align: 'end',
                    labels: {
                        boxWidth: 12, boxHeight: 12, padding: 15,
                        font: { size: 11, weight: '600' }, color: dark ? '#9ca3af' : '#64748b',
                        usePointStyle: true, pointStyle: 'circle',
                        filter: (item) => !item.text.includes('Rentang')
                    }
                },
                tooltip: {
                    backgroundColor: dark ? '#1e2433' : '#ffffff',
                    titleColor: dark ? '#f3f4f6' : '#1e293b',
                    bodyColor: dark ? '#9ca3af' : '#475569',
                    borderColor: dark ? '#374151' : '#e2e8f0',
                    borderWidth: 1,
                    padding: 12, boxPadding: 6, usePointStyle: true,
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
                    grid: { color: dark ? 'rgba(255,255,255,0.05)' : '#f1f5f9', drawBorder: false },
                    ticks: {
                        color: dark ? '#6b7280' : '#94a3b8', font: { size: 10, weight: '500' },
                        padding: 8,
                        callback: value => 'Rp ' + value.toLocaleString('id-ID')
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: {
                        color: dark ? '#6b7280' : '#94a3b8', font: { size: 9, weight: '500' },
                        maxRotation: 45, minRotation: 0,
                        autoSkip: true, maxTicksLimit: 15
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

    if (!data.labels || data.labels.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="p-8 text-center text-gray-400 dark:text-gray-500">Tidak ada data</td></tr>`;
        return;
    }

    const actualRows   = [];
    const forecastRows = [];

    for (let i = 0; i < data.labels.length; i++) {
        const row = {
            label:    data.labels[i],
            actual:   data.actual[i],
            forecast: data.forecast[i],
            lower:    data.lower[i],
            upper:    data.upper[i],
        };
        if (data.actual[i] !== null) {
            actualRows.push(row);
        }
        if (data.actual[i] === null && data.forecast[i] !== null) {
            forecastRows.push(row);
        }
    }

    const displayActual   = actualRows.slice(-8);
    const displayForecast = forecastRows;

    const display = [...displayActual, ...displayForecast];

    if (display.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="p-8 text-center text-gray-400 dark:text-gray-500 text-xs">Tidak ada data untuk periode ini</td></tr>`;
        return;
    }

    const lastActualRow = actualRows.slice(-1)[0];

    display.forEach((row, idx) => {
        const { label, actual, forecast, lower, upper } = row;
        const isForecastOnly = actual === null && forecast !== null;

        let insight = 'Stabil', insightClass = 'insight-stabil';
        let diff = null;
        let diffColor = 'text-gray-300 dark:text-gray-600';
        let diffText  = '—';

        if (!isForecastOnly && actual !== null && forecast !== null) {
            diff = forecast - actual;
            const threshold = actual * 0.01;
            if (diff > threshold)       { insight = 'Naik';  insightClass = 'insight-naik'; }
            else if (diff < -threshold) { insight = 'Turun'; insightClass = 'insight-turun'; }
            diffColor = diff > 0 ? 'text-red-600 dark:text-red-400' : diff < 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-500 dark:text-gray-400';
            diffText  = (diff > 0 ? '+' : '') + Math.round(diff).toLocaleString('id-ID');

        } else if (!isForecastOnly && actual !== null && forecast === null) {
            const prevRow = idx > 0 ? display[idx - 1] : null;
            const prevActual = prevRow ? prevRow.actual : null;
            if (prevActual !== null && prevActual !== 0) {
                diff = actual - prevActual;
                const threshold = prevActual * 0.01;
                if (diff > threshold)       { insight = 'Naik';  insightClass = 'insight-naik'; }
                else if (diff < -threshold) { insight = 'Turun'; insightClass = 'insight-turun'; }
                diffColor = diff > 0 ? 'text-red-600 dark:text-red-400' : diff < 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-500 dark:text-gray-400';
                diffText  = (diff > 0 ? '+' : '') + Math.round(diff).toLocaleString('id-ID');
            }

        } else if (isForecastOnly) {
            if (lastActualRow && lastActualRow.actual !== null) {
                const diffFromLast  = forecast - lastActualRow.actual;
                const thresholdLast = lastActualRow.actual * 0.01;
                if (diffFromLast > thresholdLast)       { insight = 'Naik';     insightClass = 'insight-naik'; }
                else if (diffFromLast < -thresholdLast) { insight = 'Turun';    insightClass = 'insight-turun'; }
                else                                    { insight = 'Proyeksi'; insightClass = 'insight-stabil'; }
            } else {
                insight = 'Proyeksi'; insightClass = 'insight-stabil';
            }
        }

        const rowBg = isForecastOnly ? 'bg-orange-50/30 dark:bg-orange-900/5' : '';
        const borderTop = (idx === displayActual.length && forecastRows.length > 0)
            ? 'border-t-2 border-orange-200 dark:border-orange-800'
            : '';

        tbody.innerHTML += `
            <tr class="${rowBg} ${borderTop} border-b border-gray-50 dark:border-gray-700 hover:bg-gray-50/80 dark:hover:bg-gray-700/50 transition-colors">
                <td class="px-6 py-4 text-gray-500 dark:text-gray-400 font-medium text-xs">
                    ${label}
                    ${isForecastOnly ? '<span class="ml-1 text-[9px] bg-orange-100 dark:bg-orange-900/30 text-orange-600 dark:text-orange-400 px-1.5 py-0.5 rounded font-bold uppercase">Proyeksi</span>' : ''}
                </td>
                <td class="px-6 py-4 text-right text-xs font-medium text-gray-800 dark:text-gray-200">
                    ${actual !== null ? 'Rp ' + Math.round(actual).toLocaleString('id-ID') : '<span class="text-gray-300 dark:text-gray-600">—</span>'}
                </td>
                <td class="px-6 py-4 text-right text-blue-600 dark:text-blue-400 font-bold text-xs">
                    ${forecast !== null ? 'Rp ' + Math.round(forecast).toLocaleString('id-ID') : '<span class="text-gray-300 dark:text-gray-600">—</span>'}
                </td>
                <td class="px-6 py-4 text-right text-xs text-gray-400 dark:text-gray-500">
                    ${lower !== null ? 'Rp ' + Math.round(lower).toLocaleString('id-ID') : '<span class="text-gray-300 dark:text-gray-600">—</span>'}
                </td>
                <td class="px-6 py-4 text-right text-xs text-gray-400 dark:text-gray-500">
                    ${upper !== null ? 'Rp ' + Math.round(upper).toLocaleString('id-ID') : '<span class="text-gray-300 dark:text-gray-600">—</span>'}
                </td>
                <td class="px-6 py-4 text-right text-xs ${diffColor} font-medium">${diffText}</td>
                <td class="px-6 py-4 text-center">
                    <span class="insight-badge ${insightClass}">${insight}</span>
                </td>
            </tr>
        `;
    });
}

document.addEventListener('DOMContentLoaded', function() {
    if (CURRENT_TAB === 'insight') {
        initializeChart();
        updateInsightTable();
        checkFlaskStatus();
    }
});

const _obs = new MutationObserver(() => {
    if (CURRENT_TAB === 'insight' && mainChart) {
        initializeChart();
    }
});
_obs.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

// ============================================================
// SweetAlert — Tambah User
// ============================================================
function validateTambahUser(event) {
    event.preventDefault();
    const name     = document.getElementById('input-name').value.trim();
    const email    = document.getElementById('input-email').value.trim();
    const password = document.getElementById('input-password').value;
    const role     = document.getElementById('input-role').value;

    if (!name) {
        Swal.fire({ icon: 'warning', title: 'Nama Wajib Diisi', text: 'Silakan masukkan nama lengkap pengguna.', confirmButtonColor: '#f97316' });
        return false;
    }

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!email || !emailRegex.test(email)) {
        Swal.fire({ icon: 'warning', title: 'Email Tidak Valid', text: 'Silakan masukkan alamat email yang benar.', confirmButtonColor: '#f97316' });
        return false;
    }

    if (password.length < 8) {
        Swal.fire({
            icon: 'error', title: 'Password Terlalu Pendek!',
            html: `<div class="text-center">
                <div class="text-5xl mb-3">🔐</div>
                <p class="text-gray-600 dark:text-gray-400 mb-2">Password harus minimal <strong class="text-red-600">8 karakter</strong>.</p>
                <p class="text-gray-400 dark:text-gray-500 text-sm">Saat ini: <strong class="text-red-500">${password.length} karakter</strong></p>
            </div>`,
            confirmButtonColor: '#ef4444', confirmButtonText: 'Perbaiki Password'
        }).then(() => { document.getElementById('input-password').focus(); });
        return false;
    }

    Swal.fire({
        icon: 'question', title: 'Konfirmasi Buat Pengguna',
        html: `<div class="text-left text-sm space-y-2 mt-2">
            <div class="flex gap-2"><span class="text-gray-400 w-20">Nama</span><span class="font-semibold text-gray-800 dark:text-gray-200">: ${name}</span></div>
            <div class="flex gap-2"><span class="text-gray-400 w-20">Email</span><span class="font-semibold text-gray-800 dark:text-gray-200">: ${email}</span></div>
            <div class="flex gap-2"><span class="text-gray-400 w-20">Role</span><span class="font-semibold text-gray-800 dark:text-gray-200">: ${role.charAt(0).toUpperCase() + role.slice(1)}</span></div>
        </div>`,
        showCancelButton: true,
        confirmButtonColor: '#f97316', cancelButtonColor: '#9ca3af',
        confirmButtonText: '<i class="fas fa-user-plus mr-1"></i> Ya, Buat!', cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) document.getElementById('formTambahUser').submit();
    });
    return false;
}

function checkPasswordStrength(password) {
    const bar  = document.getElementById('password-strength-bar');
    const text = document.getElementById('password-strength-text');
    if (!bar || !text) return;
    const len = password.length;
    if (len === 0) { bar.style.width = '0%'; bar.className = 'h-full rounded-full transition-all duration-300 w-0'; text.textContent = ''; return; }

    let strength = 0;
    if (len >= 8)                        strength++;
    if (len >= 12)                       strength++;
    if (/[A-Z]/.test(password))         strength++;
    if (/[0-9]/.test(password))         strength++;
    if (/[^A-Za-z0-9]/.test(password)) strength++;

    const levels = [
        { width: '20%',  color: 'bg-red-500',    label: '⚠️ Sangat Lemah', textColor: 'text-red-500' },
        { width: '40%',  color: 'bg-orange-500', label: '😐 Lemah',         textColor: 'text-orange-500' },
        { width: '60%',  color: 'bg-yellow-500', label: '🙂 Cukup',         textColor: 'text-yellow-600' },
        { width: '80%',  color: 'bg-blue-500',   label: '👍 Kuat',          textColor: 'text-blue-500' },
        { width: '100%', color: 'bg-green-500',  label: '🔒 Sangat Kuat',   textColor: 'text-green-600' },
    ];

    const lvl = levels[Math.min(strength, levels.length - 1)];
    bar.style.width   = lvl.width;
    bar.className     = `h-full rounded-full transition-all duration-300 ${lvl.color}`;
    text.textContent  = lvl.label;
    text.className    = `text-[10px] mt-1 font-semibold ${lvl.textColor}`;

    const input = document.getElementById('input-password');
    if (len > 0 && len < 8) {
        input.classList.add('border-red-400', 'focus:ring-red-400');
        input.classList.remove('border-gray-200', 'focus:ring-blue-500');
    } else {
        input.classList.remove('border-red-400', 'focus:ring-red-400');
        input.classList.add('border-gray-200', 'focus:ring-blue-500');
    }
}

function togglePasswordVisibility() {
    const input = document.getElementById('input-password');
    const icon  = document.getElementById('eye-icon');
    if (input.type === 'password') { input.type = 'text';     icon.classList.replace('fa-eye', 'fa-eye-slash'); }
    else                           { input.type = 'password'; icon.classList.replace('fa-eye-slash', 'fa-eye'); }
}

// ============================================================
// SweetAlert — Hapus User
// ============================================================
function confirmDeleteUser(id, name) {
    Swal.fire({
        icon: 'warning', title: 'Hapus Pengguna?',
        html: `<div class="text-center"><div class="text-4xl mb-3">🗑️</div>
            <p class="text-gray-600 dark:text-gray-400">Anda akan menghapus pengguna:</p>
            <p class="font-bold text-gray-800 dark:text-gray-200 text-lg mt-1">${name}</p>
            <p class="text-red-500 dark:text-red-400 text-xs mt-3">Tindakan ini tidak dapat dibatalkan!</p></div>`,
        showCancelButton: true,
        confirmButtonColor: '#ef4444', cancelButtonColor: '#9ca3af',
        confirmButtonText: '<i class="fas fa-trash mr-1"></i> Ya, Hapus!', cancelButtonText: 'Batal',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) document.getElementById(`delete-form-${id}`).submit();
    });
}

// ============================================================
// Edit Mode — Data Table
// ============================================================
function toggleEditMode(id) {
    const row = document.getElementById(`row-${id}`);
    if (!row) return;
    const isEditing = row.querySelector('.commodity-edit').classList.contains('hidden');

    row.querySelector('.commodity-view').classList.toggle('hidden', isEditing);
    row.querySelector('.commodity-edit').classList.toggle('hidden', !isEditing);
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
    if (isEditing) { row.classList.add('bg-blue-50', 'dark:bg-blue-900/10', 'border-l-4', 'border-l-blue-500'); }
    else           { row.classList.remove('bg-blue-50', 'dark:bg-blue-900/10', 'border-l-4', 'border-l-blue-500'); }
}

function autoSaveData(id) {
    const row         = document.getElementById(`row-${id}`);
    const komoditasId = row.querySelector('.commodity-edit').value;
    const date        = row.querySelector('.date-edit').value;
    const price       = row.querySelector('.price-edit').value;

    if (!komoditasId || !date || !price) { showNotification('Semua field harus diisi!', 'error'); return; }
    if (parseFloat(price) <= 0) { showNotification('Harga harus lebih dari 0!', 'error'); return; }

    row.style.backgroundColor = isDark() ? '#3d3300' : '#fef3c7';

    fetch(`{{ url('/admin/update-data') }}/${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ komoditas_id: komoditasId, date, price })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const selectedOption = row.querySelector('.commodity-edit option:checked');
            row.querySelector('.commodity-view').textContent = selectedOption ? selectedOption.text : komoditasId;
            const [y, m, d] = date.split('-');
            row.querySelector('.date-view').textContent  = `${d}/${m}/${y}`;
            row.querySelector('.price-view').textContent = 'Rp ' + parseInt(price).toLocaleString('id-ID');
            row.style.backgroundColor = isDark() ? '#0f2e1a' : '#d1fae5';
            setTimeout(() => { row.style.backgroundColor = ''; }, 800);
            showNotification('Data tersimpan!', 'success');
        } else {
            showNotification('Gagal menyimpan: ' + (data.message || 'Terjadi kesalahan'), 'error');
            row.style.backgroundColor = '';
        }
    })
    .catch(() => { showNotification('Terjadi kesalahan jaringan', 'error'); row.style.backgroundColor = ''; });
}

// ============================================================
// Edit Mode — User Table
// ============================================================
function toggleEditUserMode(id) {
    const row = document.getElementById(`user-row-${id}`);
    if (!row) return;
    const isEditing = row.querySelector('.name-edit').classList.contains('hidden');

    row.querySelector('.name-view').classList.toggle('hidden', isEditing);
    row.querySelector('.name-edit').classList.toggle('hidden', !isEditing);
    row.querySelector('.email-view').classList.toggle('hidden', isEditing);
    row.querySelector('.email-edit').classList.toggle('hidden', !isEditing);
    row.querySelector('.role-view').classList.toggle('hidden', isEditing);
    row.querySelector('.role-edit').classList.toggle('hidden', !isEditing);
    row.querySelector('.edit-user-btn').classList.toggle('hidden', isEditing);
    row.querySelector('.done-user-btn').classList.toggle('hidden', !isEditing);

    const deleteForm = row.querySelector('.delete-user-form');
    if (deleteForm) {
        deleteForm.style.opacity       = isEditing ? '0.3' : '1';
        deleteForm.style.pointerEvents = isEditing ? 'none' : 'auto';
    }
    if (isEditing) { row.classList.add('bg-blue-50', 'dark:bg-blue-900/10', 'border-l-4', 'border-l-blue-500'); }
    else           { row.classList.remove('bg-blue-50', 'dark:bg-blue-900/10', 'border-l-4', 'border-l-blue-500'); }
}

function autoSaveUser(id) {
    const row   = document.getElementById(`user-row-${id}`);
    const name  = row.querySelector('.name-edit').value.trim();
    const email = row.querySelector('.email-edit').value.trim();
    const role  = row.querySelector('.role-edit').value;

    if (!name || !email || !role) { showNotification('Semua field harus diisi!', 'error'); return; }
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) { showNotification('Format email tidak valid!', 'error'); return; }

    row.style.backgroundColor = isDark() ? '#3d3300' : '#fef3c7';

    fetch(`{{ url('/admin/update-user') }}/${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ name, email, role })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            row.querySelector('.name-view').textContent  = name;
            row.querySelector('.email-view').textContent = email;
            const roleView = row.querySelector('.role-view');
            const roleMap  = {
                admin:    ['bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400', 'Administrator'],
                operator: ['bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400', 'Operator'],
                user:     ['bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300', 'Pengguna']
            };
            const [cls, text] = roleMap[role] || roleMap.user;
            roleView.className   = `role-view px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider ${cls}`;
            roleView.textContent = text;
            row.style.backgroundColor = isDark() ? '#0f2e1a' : '#d1fae5';
            setTimeout(() => { row.style.backgroundColor = ''; }, 800);
            showNotification('Data pengguna tersimpan!', 'success');
        } else {
            showNotification('Gagal: ' + (data.message || 'Terjadi kesalahan'), 'error');
            row.style.backgroundColor = '';
        }
    })
    .catch(() => { showNotification('Terjadi kesalahan jaringan', 'error'); row.style.backgroundColor = ''; });
}

// ============================================================
// Toast Notification
// ============================================================
function showNotification(message, type = 'success') {
    const existing = document.querySelector('.toast-notification');
    if (existing) existing.remove();

    const notification = document.createElement('div');
    notification.className = `toast-notification fixed top-4 right-4 z-50 px-6 py-4 rounded-lg shadow-lg text-white text-sm font-medium animate-fade-in ${type === 'success' ? 'bg-green-500' : 'bg-red-500'}`;
    notification.innerHTML = `<div class="flex items-center gap-3">
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        <span>${message}</span>
    </div>`;
    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.opacity    = '0';
        notification.style.transform  = 'translateX(100%)';
        notification.style.transition = 'all 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}
</script>

@endsection