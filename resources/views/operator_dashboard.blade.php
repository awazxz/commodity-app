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
    .filter-btn.active { background: #2563eb; color: white; border-color: #2563eb; }
    .filter-btn:hover:not(.active) { background: #f8fafc; border-color: #cbd5e1; }

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

    input[type="range"] { -webkit-appearance: none; appearance: none; }
    input[type="range"]::-webkit-slider-thumb {
        height: 16px; width: 16px; border-radius: 50%;
        background: #2563eb; cursor: pointer;
        border: 2px solid #fff;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        -webkit-appearance: none; appearance: none;
    }
    input[type="range"].indigo-thumb::-webkit-slider-thumb { background: #6366f1; }

    .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: #f8fafc; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in { animation: fadeIn 0.4s ease-out; }

    .tab-single { display: block; }
    .tab-bulk   { display: none; }

    .alert-success {
        background: #dcfce7; border: 1px solid #bbf7d0; color: #166534;
        padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;
    }
    .alert-error {
        background: #fee2e2; border: 1px solid #fecaca; color: #991b1b;
        padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;
    }

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

    .horizon-pill {
        display: inline-flex; align-items: center; gap: 3px;
        background: #eef2ff; color: #4338ca;
        font-size: 9px; font-weight: 700;
        padding: 2px 7px; border-radius: 9999px;
        text-transform: uppercase; letter-spacing: 0.04em;
    }

    /* Pagination button styles */
    .pg-btn {
        display: inline-flex; align-items: center; justify-content: center;
        min-width: 2rem; height: 2rem; padding: 0 0.5rem;
        font-size: 0.75rem; font-weight: 500;
        border-radius: 0.375rem; border: 1px solid #e5e7eb;
        background: white; color: #374151;
        cursor: pointer; transition: all 0.15s;
        text-decoration: none;
    }
    .pg-btn:hover:not(.pg-btn-active):not(.pg-btn-disabled) {
        background: #f9fafb; border-color: #d1d5db;
    }
    .pg-btn-active {
        background: #2563eb; color: white; border-color: #2563eb; font-weight: 700;
        cursor: default;
    }
    .pg-btn-disabled {
        background: #f3f4f6; color: #9ca3af; border-color: #f3f4f6; cursor: not-allowed;
    }
</style>
</div>

{{-- Skeleton overlay --}}
<div id="skeleton-overlay" class="hidden fixed inset-0 bg-white/50 z-50 flex items-center justify-center opacity-0">
    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
</div>

{{-- Floating indicator --}}
<div class="param-changed-indicator" id="param-changed-indicator" onclick="triggerSubmit()">
    <i class="fas fa-sync-alt fa-spin"></i>
    <span>{{ __('messages.perbarui_prediksi') }}</span>
</div>

<div class="dashboard-container space-y-6 animate-fade-in">

{{-- TAB NAVIGATION --}}
<div class="card-standard p-1.5 flex gap-1">
    <a href="{{ route('operator.predict', ['tab' => 'insight']) }}"
       class="flex-1 flex items-center justify-center gap-2 py-2.5 px-4 rounded-md text-xs font-bold uppercase tracking-wider transition-all
              {{ ($currentTab ?? 'insight') === 'insight' ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-500 hover:bg-gray-100' }}">
        <i class="fas fa-chart-line"></i>
        <span>{{ __('messages.tab_insight') }}</span>
    </a>
    <a href="{{ route('operator.predict', ['tab' => 'manage']) }}"
       class="flex-1 flex items-center justify-center gap-2 py-2.5 px-4 rounded-md text-xs font-bold uppercase tracking-wider transition-all
              {{ ($currentTab ?? '') === 'manage' ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-500 hover:bg-gray-100' }}">
        <i class="fas fa-database"></i>
        <span>{{ __('messages.tab_manajemen_data') }}</span>
    </a>
</div>

{{-- Flash Messages --}}
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

    {{-- Header & Form --}}
    <div class="card-standard p-6">
        <div class="flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="flex items-center gap-4">
                <div class="bg-blue-600 p-3 rounded-lg text-white shadow-md">
                    <i class="fas fa-chart-line text-xl"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-gray-900 leading-none">
                        {{ __('messages.judul_sistem') }}
                    </h2>
                    <p class="text-xs text-orange-500 font-medium uppercase tracking-wider mt-1.5">
                        {{ __('messages.panel_operator') }}
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
        <form action="{{ route('operator.predict') }}" method="POST" id="mainForm" class="mt-6 pt-6 border-t border-gray-100">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                <div class="md:col-span-4">
                    <label class="text-xs font-semibold text-gray-700 uppercase mb-2 block tracking-tight">
                        {{ __('messages.komoditas_terpilih') }}
                    </label>
                    <select id="select_komoditas"
                            onchange="handleCommodityChange(this.value)"
                            class="w-full bg-gray-50 border border-gray-300 rounded-md py-2 px-3 text-sm font-medium text-gray-900 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all">
                        @foreach($commodities ?? [] as $kom)
                            <option value="{{ $kom->id }}" {{ $selectedKomoditasId == $kom->id ? 'selected' : '' }}>
                                {{ $kom->display_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="md:col-span-8">
                    <label class="text-xs font-semibold text-gray-700 uppercase mb-2 block tracking-tight">
                        {{ __('messages.rentang_waktu') }}
                    </label>
                    <div class="flex items-center gap-3 bg-gray-50 p-1.5 rounded-md border border-gray-300">
                        <input type="date" name="start_date" id="input_start_date"
                               value="{{ $startDate ?? '2020-01-01' }}"
                               onchange="triggerSubmit()"
                               class="bg-transparent text-sm p-1 outline-none flex-1 font-medium">
                        <span class="text-gray-400 font-bold">→</span>
                        <input type="date" name="end_date" id="input_end_date"
                               value="{{ $endDate ?? date('Y-m-d') }}"
                               onchange="triggerSubmit()"
                               class="bg-transparent text-sm p-1 outline-none flex-1 font-medium">
                    </div>
                </div>
            </div>

            <input type="hidden" name="komoditas_id"            id="hidden_komoditas"      value="{{ $selectedKomoditasId ?? '' }}">
            <input type="hidden" name="changepoint_prior_scale" id="hidden_cp"             value="{{ $cpScale ?? 0.05 }}">
            <input type="hidden" name="seasonality_prior_scale" id="hidden_season"         value="{{ $seasonScale ?? 10 }}">
            <input type="hidden" name="seasonality_mode"        id="hidden_mode"           value="{{ $seasonMode ?? 'multiplicative' }}">
            <input type="hidden" name="weekly_seasonality"      id="hidden_weekly"         value="{{ ($weeklySeason ?? false) ? 'true' : 'false' }}">
            <input type="hidden" name="yearly_seasonality"      id="hidden_yearly"         value="{{ ($yearlySeason ?? false) ? 'true' : 'false' }}">
            <input type="hidden" name="forecast_weeks"          id="hidden_forecast_weeks" value="{{ $forecastWeeks ?? 12 }}">
            <input type="hidden" name="tab" value="insight">
        </form>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
        <div class="card-standard hover-card p-5">
            <p class="text-[10px] uppercase text-gray-500 font-bold tracking-wider mb-2">{{ __('messages.rata_rata_harga') }}</p>
            <p class="text-xl font-bold text-gray-900">Rp {{ number_format($avgPrice ?? 0, 0, ',', '.') }}</p>
            <p class="text-[10px] text-gray-400 mt-1">{{ $countData ?? 0 }} {{ __('messages.data_poin') }}</p>
        </div>

        <div class="card-standard hover-card p-5">
            <p class="text-[10px] uppercase text-gray-500 font-bold tracking-wider mb-2">{{ __('messages.harga_tertinggi') }}</p>
            <p class="text-xl font-bold text-red-600">Rp {{ number_format($maxPrice ?? 0, 0, ',', '.') }}</p>
        </div>

        <div class="card-standard hover-card p-5">
            <p class="text-[10px] uppercase text-gray-500 font-bold tracking-wider mb-2">{{ __('messages.periode_data') }}</p>
            <p class="text-sm font-semibold text-gray-900">
                {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }}
                <span class="text-gray-400 mx-1">→</span>
                {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}
            </p>
        </div>

        <div class="bg-blue-600 rounded-lg p-5 text-white shadow-lg hover-card">
            <p class="text-[10px] uppercase text-blue-100 font-bold tracking-wider mb-2">{{ __('messages.arah_tren') }}</p>
            <p class="text-sm font-bold uppercase flex items-center gap-2">
                @php
                    $trendIcon = match(strtolower($trendDir ?? 'stabil')) {
                        'naik'  => 'fa-arrow-trend-up',
                        'turun' => 'fa-arrow-trend-down',
                        default => 'fa-minus'
                    };
                @endphp
                <i class="fas {{ $trendIcon }}"></i>
                {{ $trendDir ?? __('messages.stabil') }}
            </p>
        </div>
    </div>

    <div class="grid grid-cols-12 gap-5">

        {{-- Hyperparameter Panel --}}
        <div class="col-span-12 lg:col-span-4 space-y-4">
            <div class="card-standard p-5">
                <div class="flex items-center justify-between mb-5 pb-3 border-b border-gray-100">
                    <h4 class="text-sm font-bold text-gray-900 uppercase tracking-tight">
                        {{ __('messages.pengaturan_hyperparameter') }}
                    </h4>
                    <span class="text-[9px] bg-blue-50 text-blue-600 font-bold px-2 py-0.5 rounded-full uppercase">
                        Prophet Model
                    </span>
                </div>

                <div class="space-y-5">

                    {{-- Changepoint Prior Scale --}}
                    <div>
                        <div class="flex justify-between mb-2">
                            <span class="text-xs text-gray-500 font-semibold uppercase">{{ __('messages.changepoint_prior') }}</span>
                            <span class="text-xs font-mono font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded"
                                  id="cp_display">{{ number_format($cpScale ?? 0.05, 3) }}</span>
                        </div>
                        <input type="range" min="0.001" max="0.5" step="0.001"
                               value="{{ $cpScale ?? 0.05 }}"
                               class="w-full h-1 bg-gray-100 rounded-lg appearance-none cursor-pointer"
                               id="range_cp"
                               oninput="updateVal('hidden_cp', 'cp_display', 'preview_cp', this.value, 3)">
                        <p class="text-[9px] text-gray-400 mt-1">{{ __('messages.fleksibilitas_tren') }}</p>
                    </div>

                    {{-- Seasonality Prior Scale --}}
                    <div>
                        <div class="flex justify-between mb-2">
                            <span class="text-xs text-gray-500 font-semibold uppercase">{{ __('messages.seasonality_prior') }}</span>
                            <span class="text-xs font-mono font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded"
                                  id="season_display">{{ number_format($seasonScale ?? 10, 2) }}</span>
                        </div>
                        <input type="range" min="0.01" max="50" step="0.01"
                               value="{{ $seasonScale ?? 10 }}"
                               class="w-full h-1 bg-gray-100 rounded-lg appearance-none cursor-pointer"
                               id="range_season"
                               oninput="updateVal('hidden_season', 'season_display', 'preview_season', this.value, 2)">
                        <p class="text-[9px] text-gray-400 mt-1">{{ __('messages.kekuatan_musiman') }}</p>
                    </div>

                    {{-- Seasonality Mode --}}
                    <div>
                        <label class="text-xs text-gray-500 font-semibold uppercase mb-2 block">{{ __('messages.mode_musiman') }}</label>
                        <select id="select_mode"
                                onchange="updateMode(this.value)"
                                class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-xs text-gray-600 font-medium outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="multiplicative" {{ ($seasonMode ?? 'multiplicative') === 'multiplicative' ? 'selected' : '' }}>
                                {{ __('messages.multiplikatif') }}
                            </option>
                            <option value="additive" {{ ($seasonMode ?? '') === 'additive' ? 'selected' : '' }}>
                                {{ __('messages.aditif') }}
                            </option>
                        </select>
                        <p class="text-[9px] text-gray-400 mt-1">{{ __('messages.metode_musiman') }}</p>
                    </div>

                    {{-- Horizon Prediksi --}}
                    <div class="pt-2 border-t border-gray-100">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-xs text-gray-500 font-semibold uppercase">{{ __('messages.periode_prediksi') }}</span>
                            <span class="horizon-pill" id="fw_display">
                                <i class="fas fa-calendar-alt" style="font-size:8px;"></i>
                                <span id="fw_display_text">{{ $forecastWeeks ?? 12 }} {{ __('messages.mingguan') }}</span>
                            </span>
                        </div>
                        <input type="range" min="1" max="52" step="1"
                               value="{{ $forecastWeeks ?? 12 }}"
                               class="w-full h-1 bg-gray-100 rounded-lg appearance-none cursor-pointer indigo-thumb"
                               id="range_fw"
                               oninput="updateForecastWeeks(this.value)">
                        <div class="flex justify-between text-[8px] text-gray-300 mt-1">
                            <span>1 {{ __('messages.mingguan') }}</span>
                            <span>26 {{ __('messages.mingguan') }}</span>
                            <span>52 {{ __('messages.mingguan') }}</span>
                        </div>
                        <p class="text-[9px] text-gray-400 mt-1">{{ __('messages.periode_prediksi') }} (1 – 52)</p>
                    </div>

                    {{-- Toggle Seasonality --}}
                    <div class="space-y-2 pt-2 border-t border-gray-100">
                        <label class="text-xs text-gray-500 font-semibold uppercase block mb-2">{{ __('messages.komponen_musiman') }}</label>

                        <div class="flex items-center justify-between p-2.5 bg-gray-50 rounded-lg border border-gray-100">
                            <div>
                                <span class="text-xs text-gray-500 font-medium uppercase">{{ __('messages.mingguan') }}</span>
                                <p class="text-[9px] text-gray-400">{{ __('messages.deteksi_pola_minggu') }}</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox"
                                       id="checkbox_weekly"
                                       {{ ($weeklySeason ?? false) ? 'checked' : '' }}
                                       onchange="updateToggle('hidden_weekly', 'preview_weekly', this.checked)"
                                       class="sr-only peer">
                                <div class="w-9 h-5 bg-gray-200 rounded-full peer peer-checked:bg-blue-600
                                            after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                                            after:bg-white after:rounded-full after:h-4 after:w-4
                                            after:transition-all peer-checked:after:translate-x-4"></div>
                            </label>
                        </div>

                        <div class="flex items-center justify-between p-2.5 bg-gray-50 rounded-lg border border-gray-100">
                            <div>
                                <span class="text-xs text-gray-500 font-medium uppercase">{{ __('messages.tahunan') }}</span>
                                <p class="text-[9px] text-gray-400">{{ __('messages.deteksi_pola_tahun') }}</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox"
                                       id="checkbox_yearly"
                                       {{ ($yearlySeason ?? false) ? 'checked' : '' }}
                                       onchange="updateToggle('hidden_yearly', 'preview_yearly', this.checked)"
                                       class="sr-only peer">
                                <div class="w-9 h-5 bg-gray-200 rounded-full peer peer-checked:bg-blue-600
                                            after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                                            after:bg-white after:rounded-full after:h-4 after:w-4
                                            after:transition-all peer-checked:after:translate-x-4"></div>
                            </label>
                        </div>
                    </div>

                    {{-- Preview parameter aktif --}}
                    <div class="bg-gray-50 rounded-lg p-3 border border-gray-100 space-y-1" id="param-preview-box">
                        <p class="text-[9px] text-gray-400 font-bold uppercase mb-2">Parameter Aktif (Flask)</p>
                        <div class="flex justify-between text-[10px]">
                            <span class="text-gray-500">cp_scale</span>
                            <span class="font-mono text-blue-600" id="preview_cp">{{ $cpScale ?? 0.05 }}</span>
                        </div>
                        <div class="flex justify-between text-[10px]">
                            <span class="text-gray-500">season_scale</span>
                            <span class="font-mono text-emerald-600" id="preview_season">{{ $seasonScale ?? 10 }}</span>
                        </div>
                        <div class="flex justify-between text-[10px]">
                            <span class="text-gray-500">mode</span>
                            <span class="font-mono text-purple-600" id="preview_mode">{{ $seasonMode ?? 'multiplicative' }}</span>
                        </div>
                        <div class="flex justify-between text-[10px]">
                            <span class="text-gray-500">weekly</span>
                            <span class="font-mono" id="preview_weekly">{{ ($weeklySeason ?? false) ? 'true' : 'false' }}</span>
                        </div>
                        <div class="flex justify-between text-[10px]">
                            <span class="text-gray-500">yearly</span>
                            <span class="font-mono" id="preview_yearly">{{ ($yearlySeason ?? false) ? 'true' : 'false' }}</span>
                        </div>
                        <div class="flex justify-between text-[10px]">
                            <span class="text-gray-500">forecast_weeks</span>
                            <span class="font-mono text-indigo-600" id="preview_fw">{{ $forecastWeeks ?? 12 }}</span>
                        </div>

                        <div id="param-dirty-notice" class="hidden mt-2 pt-2 border-t border-orange-200 text-[9px] text-orange-600 font-bold flex items-center gap-1">
                            <i class="fas fa-exclamation-triangle"></i>
                            {{ __('messages.perbarui_prediksi') }}
                        </div>
                    </div>

                    {{-- Tombol submit --}}
                    <button type="button" onclick="triggerSubmit()"
                            id="btn-update"
                            class="w-full bg-blue-600 text-white py-3 rounded-lg text-xs font-bold uppercase tracking-wider hover:bg-blue-700 transition-all shadow-sm flex items-center justify-center gap-2">
                        <i class="fas fa-sync-alt" id="btn-refresh-icon"></i>
                        {{ __('messages.perbarui_prediksi') }}
                    </button>

                    <div class="text-[9px] text-gray-400 text-center">
                        {{ __('messages.prediksi_terakhir_note') }}<br>
                        {{ __('messages.ubah_parameter_note') }}
                    </div>
                </div>
            </div>

            {{-- Statistik Model --}}
            <div class="bg-gradient-to-br from-blue-600 to-blue-700 rounded-lg shadow-lg p-5 text-white">
                <h4 class="text-xs font-bold uppercase tracking-wider mb-3 opacity-90">{{ __('messages.ringkasan_statistik') }}</h4>
                <div class="space-y-3">
                    <div class="flex justify-between items-end border-b border-white/10 pb-2">
                        <div>
                            <span class="text-[10px] opacity-70 font-semibold uppercase">{{ __('messages.mape') }}</span>
                            <p class="text-[8px] opacity-50 mt-0.5">{{ __('messages.berubah_saat_hyperparameter') }}</p>
                        </div>
                        <span class="text-sm font-bold">{{ number_format($mape ?? 0, 2) }}%</span>
                    </div>
                    <div class="flex justify-between items-end border-b border-white/10 pb-2">
                        <span class="text-[10px] opacity-70 font-semibold uppercase">{{ __('messages.r_squared') }}</span>
                        <span class="text-sm font-bold">{{ number_format($rSquared ?? 0, 3) }}</span>
                    </div>
                    <div class="flex justify-between items-end border-b border-white/10 pb-2">
                        <span class="text-[10px] opacity-70 font-semibold uppercase">{{ __('messages.total_data_poin') }}</span>
                        <span class="text-sm font-bold">{{ $countData ?? 0 }}</span>
                    </div>
                    <div class="flex justify-between items-end">
                        <span class="text-[10px] opacity-70 font-semibold uppercase">{{ __('messages.periode_prediksi') }}</span>
                        <span class="text-sm font-bold">{{ $forecastWeeks ?? 12 }} {{ __('messages.mingguan') }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Chart --}}
        <div class="col-span-12 lg:col-span-8 card-standard overflow-hidden flex flex-col">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex flex-col lg:flex-row justify-between items-center gap-4 flex-shrink-0">
                <div>
                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-tight">{{ __('messages.visualisasi_tren') }}</h3>
                    <p class="text-xs text-gray-500">
                        {{ $selectedCommodity }} — {{ __('messages.data_historis_vs_proyeksi') }}
                        <span class="ml-2 horizon-pill">
                            <i class="fas fa-calendar-alt" style="font-size:8px;"></i>
                            {{ $forecastWeeks ?? 12 }} {{ __('messages.mingguan') }}
                        </span>
                    </p>
                </div>
                <div class="flex bg-white border border-gray-300 p-1 rounded-md shadow-sm">
                    <button onclick="changeChartPeriod('weekly')"  class="filter-btn active" id="btn-weekly">{{ __('messages.mingguan') }}</button>
                    <button onclick="changeChartPeriod('monthly')" class="filter-btn border-none" id="btn-monthly">{{ __('messages.bulanan') }}</button>
                    <button onclick="changeChartPeriod('yearly')"  class="filter-btn border-none" id="btn-yearly">{{ __('messages.tahunan') }}</button>
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
                {{ __('messages.ringkasan_analisis') }}
                <span id="selectedPeriodText" class="text-blue-600">{{ __('messages.mingguan') }}</span>
            </h3>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-400">{{ $selectedCommodity }}</span>
                <span class="text-[9px] bg-blue-50 text-blue-700 font-bold px-2 py-0.5 rounded-full">
                    MAPE: {{ number_format($mape ?? 0, 2) }}%
                </span>
                <span class="horizon-pill">{{ $forecastWeeks ?? 12 }} {{ __('messages.mingguan') }}</span>
            </div>
        </div>
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-[11px] font-bold text-gray-500 uppercase bg-gray-50 border-b border-gray-200">
                        <th class="px-6 py-4">{{ __('messages.periode') }}</th>
                        <th class="px-6 py-4 text-right">{{ __('messages.harga_aktual') }}</th>
                        <th class="px-6 py-4 text-right">{{ __('messages.harga_prediksi') }}</th>
                        <th class="px-6 py-4 text-right">{{ __('messages.rentang_bawah') }}</th>
                        <th class="px-6 py-4 text-right">{{ __('messages.rentang_atas') }}</th>
                        <th class="px-6 py-4 text-right">{{ __('messages.selisih') }}</th>
                        <th class="px-6 py-4 text-center">{{ __('messages.indikator') }}</th>
                    </tr>
                </thead>
                <tbody class="text-sm text-gray-700 divide-y divide-gray-100" id="insightTableBody">
                    {{-- Diisi oleh JavaScript --}}
                </tbody>
            </table>
        </div>
        {{-- Pagination insight — diisi oleh JS --}}
        <div id="insightPagination"></div>
    </div>

    {{-- Interpretasi --}}
    <div class="card-standard p-6 border-l-4 border-l-blue-600">
        <div class="flex items-center gap-3 mb-3">
            <h4 class="text-sm font-bold text-gray-900 dark:text-gray-100 uppercase">
                {{ __('messages.interpretasi_tren') }}
            </h4>
        </div>
        <p id="dynamic-analysis" class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
            {{ __('messages.berdasarkan_analisis') }} <strong>{{ $selectedCommodity }}</strong>,
            {{ __('messages.model_deteksi') }} <strong>{{ __('messages.' . strtolower($trendDir ?? 'stabil')) }}</strong>
            {{ __('messages.rata_rata_harga_label') }} <strong>Rp {{ number_format($avgPrice ?? 0, 0, ',', '.') }}</strong>
            {{ __('messages.total_label') }} <strong>{{ $countData ?? 0 }} {{ __('messages.data_poin') }}</strong> {{ __('messages.pada_periode') }}
            {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }}
            {{ __('messages.s_d') }} {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}.

            {{ __('messages.model_prophet_dilatih') }} <strong>changepoint_prior_scale={{ $cpScale ?? 0.05 }}</strong>,
            <strong>seasonality_prior_scale={{ $seasonScale ?? 10 }}</strong>,
            {{ __('messages.mode_musiman') }} <strong>{{ $seasonMode ?? 'multiplicative' }}</strong>,
            {{ __('messages.horizon_prediksi_label') }} <strong>{{ $forecastWeeks ?? 12 }} {{ __('messages.minggu_ke_depan') }}</strong>.

            {{ __('messages.nilai_mape_label') }} <strong>{{ number_format($mape ?? 0, 2) }}%</strong>
            {{ __('messages.menunjukkan') }}
            <strong>
                @if(($mape ?? 0) < 5)
                    {{ __('messages.akurasi_sangat_baik') }}
                @elseif(($mape ?? 0) < 10)
                    {{ __('messages.akurasi_baik') }}
                @else
                    {{ __('messages.perlu_penyesuaian') }}
                @endif
            </strong>.
        </p>
    </div>

@endif

@if($currentTab == 'manage')
    <div class="space-y-6 animate-fade-in">

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1">
                <div class="card-standard p-6">
                    <h3 class="text-sm font-bold text-gray-900 mb-6 uppercase tracking-tight">{{ __('messages.tambah_data_baru') }}</h3>
                    <div class="flex gap-4 mb-6 border-b pb-2">
                        <button onclick="switchInputMode('single')" id="btn-tab-single"
                                class="text-blue-600 border-b-2 border-blue-600 text-xs uppercase tracking-wider pb-1 font-semibold">{{ __('messages.manual') }}</button>
                        <button onclick="switchInputMode('bulk')" id="btn-tab-bulk"
                                class="text-gray-400 text-xs uppercase tracking-wider pb-1 font-semibold">{{ __('messages.unggah_csv') }}</button>
                    </div>

                    <form id="form-single" action="{{ route('operator.storeData') }}" method="POST" class="space-y-4 tab-single">
                        @csrf
                        <div>
                            <label class="text-xs font-semibold text-gray-700 uppercase mb-1.5 block tracking-tight">{{ __('messages.komoditas') }}</label>
                            <select name="komoditas_id" required
                                    class="w-full bg-gray-50 border border-gray-200 rounded-lg p-3 text-xs text-gray-900 font-medium outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">{{ __('messages.pilih_komoditas') }}</option>
                                @foreach($commodities ?? [] as $kom)
                                    <option value="{{ $kom->id }}" {{ $selectedKomoditasId == $kom->id ? 'selected' : '' }}>
                                        {{ $kom->display_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-700 uppercase mb-1.5 block tracking-tight">{{ __('messages.tanggal') }}</label>
                            <input type="date" name="date" required
                                   class="w-full bg-gray-50 border border-gray-200 rounded-lg p-3 text-xs text-gray-600 font-medium focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-700 uppercase mb-1.5 block tracking-tight">{{ __('messages.harga') }}</label>
                            <input type="number" name="price" placeholder="{{ __('messages.masukkan_harga') }}" required min="1"
                                   class="w-full bg-gray-50 border border-gray-200 rounded-lg p-3 text-xs text-gray-600 font-medium focus:ring-2 focus:ring-blue-500">
                        </div>
                        <button type="submit"
                                class="w-full bg-emerald-500 text-white py-3 rounded-lg text-xs font-bold uppercase tracking-wider hover:bg-emerald-600 transition-all">
                            {{ __('messages.simpan_data') }}
                        </button>
                    </form>

                    <form id="form-bulk" action="{{ route('operator.manajemen-data.upload-csv') }}" method="POST"
                          enctype="multipart/form-data" class="space-y-4 tab-bulk">
                        @csrf
                        <div>
                            <label class="text-xs font-semibold text-gray-700 uppercase mb-1.5 block tracking-tight">{{ __('messages.unggah_file_csv') }}</label>
                            <div class="p-8 border-2 border-dashed border-gray-200 rounded-xl bg-gray-50/50 text-center relative hover:border-blue-300 transition-colors" id="dropzone">
                                <input type="file" name="csv_file" accept=".csv"
                                       class="absolute inset-0 opacity-0 cursor-pointer"
                                       onchange="showFileName(this)">
                                <i class="fas fa-cloud-upload-alt text-gray-300 text-2xl mb-2"></i>
                                <p class="text-xs text-gray-400 font-medium" id="file-name-display">{{ __('messages.pilih_seret_csv') }}</p>
                                <p class="text-[9px] text-gray-300 mt-1">{{ __('messages.format_csv_operator') }}</p>
                            </div>
                        </div>

                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <div class="flex items-start gap-3">
                                <i class="fas fa-info-circle text-blue-500 text-sm mt-0.5"></i>
                                <div class="flex-1">
                                    <p class="text-xs text-blue-700 font-semibold uppercase tracking-tight mb-2">{{ __('messages.template_csv') }}</p>
                                    <p class="text-[10px] text-blue-600 mb-3 leading-relaxed">
                                        {{ __('messages.gunakan_template_standar') }}
                                    </p>
                                    <a href="{{ route('operator.downloadTemplate') }}"
                                       class="inline-flex items-center gap-2 bg-blue-600 text-white px-3 py-2 rounded-lg text-[10px] font-bold uppercase tracking-wider hover:bg-blue-700 transition-colors">
                                        <i class="fas fa-download"></i>
                                        {{ __('messages.unduh_template_csv') }}
                                    </a>
                                </div>
                            </div>
                        </div>

                        <button type="submit"
                                class="w-full bg-blue-600 text-white py-3 rounded-lg text-xs font-bold uppercase tracking-wider hover:bg-blue-700 transition-all">
                            {{ __('messages.unggah_dataset') }}
                        </button>
                    </form>
                </div>
            </div>

            {{-- Tabel Riwayat Database --}}
            <div class="lg:col-span-2">
                <div class="card-standard overflow-hidden flex flex-col" style="height: fit-content;">
                    <div class="p-5 border-b bg-gray-50/50 flex justify-between items-center">
                        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-tight">{{ __('messages.riwayat_database') }}</h3>
                        <span class="text-xs text-gray-400">{{ $selectedCommodity }}</span>
                    </div>
                    <div class="overflow-x-auto custom-scrollbar" style="max-height: 450px;">
                        <table class="w-full text-left">
                            <thead class="sticky top-0 bg-white border-b border-gray-100 z-10">
                                <tr class="text-xs text-gray-400 uppercase font-bold">
                                    <th class="px-6 py-4">{{ __('messages.komoditas') }}</th>
                                    <th class="px-6 py-4">{{ __('messages.tanggal') }}</th>
                                    <th class="px-6 py-4">{{ __('messages.harga') }}</th>
                                    <th class="px-6 py-4 text-center">{{ __('messages.aksi') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-xs">
                                @forelse($latestData ?? [] as $data)
                                    <tr class="hover:bg-gray-50 transition-colors" id="row-{{ $data->id }}">
                                        <td class="px-6 py-4 uppercase font-bold text-blue-600">
                                            <span class="commodity-view">{{ $data->komoditas->display_name ?? '-' }}</span>
                                            <select class="commodity-edit hidden w-full bg-gray-50 border border-gray-300 rounded-lg p-2 text-xs font-medium focus:ring-2 focus:ring-blue-500"
                                                    data-id="{{ $data->id }}" onchange="autoSaveData({{ $data->id }})">
                                                @foreach($commodities ?? [] as $kom)
                                                    <option value="{{ $kom->id }}" {{ $data->komoditas_id == $kom->id ? 'selected' : '' }}>
                                                        {{ $kom->display_name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="px-6 py-4 text-gray-500">
                                            <span class="date-view">{{ \Carbon\Carbon::parse($data->tanggal)->format('d/m/Y') }}</span>
                                            <input type="date" class="date-edit hidden w-full bg-gray-50 border border-gray-300 rounded-lg p-2 text-xs focus:ring-2 focus:ring-blue-500"
                                                   value="{{ $data->tanggal }}" data-id="{{ $data->id }}" onchange="autoSaveData({{ $data->id }})">
                                        </td>
                                        <td class="px-6 py-4 font-bold text-emerald-600">
                                            <span class="price-view">Rp {{ number_format($data->harga, 0, ',', '.') }}</span>
                                            <input type="number" class="price-edit hidden w-full bg-gray-50 border border-gray-300 rounded-lg p-2 text-xs focus:ring-2 focus:ring-blue-500"
                                                   value="{{ $data->harga }}" data-id="{{ $data->id }}" onchange="autoSaveData({{ $data->id }})">
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center justify-center gap-3">
                                                <button type="button" onclick="toggleEditMode({{ $data->id }})"
                                                        class="edit-btn text-blue-500 hover:text-blue-700 transition-colors text-sm font-medium">
                                                    <i class="fas fa-edit"></i> {{ __('messages.edit') }}
                                                </button>
                                                <button type="button" onclick="toggleEditMode({{ $data->id }})"
                                                        class="done-btn hidden text-green-500 hover:text-green-700 transition-colors text-sm font-medium">
                                                    <i class="fas fa-check"></i> {{ __('messages.selesai') }}
                                                </button>
                                                <form action="{{ route('operator.deleteData', $data->id) }}" method="POST"
                                                      onsubmit="return confirm('{{ __('messages.hapus') }}?')" class="inline delete-form">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="text-red-400 hover:text-red-600 transition-colors text-sm font-medium">
                                                        <i class="fas fa-trash"></i> {{ __('messages.hapus') }}
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
                                                <p class="text-sm font-medium">{{ __('messages.data_tidak_ditemukan') }}</p>
                                                <p class="text-xs">{{ __('messages.pilih_atau_tambah') }}</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Pagination Riwayat Database --}}
                    @if(isset($latestData) && method_exists($latestData, 'hasPages') && $latestData->hasPages())
                        <div class="px-6 py-4 border-t bg-gray-50/30 flex items-center justify-between">
                            <div class="text-xs text-gray-500">
                                {{ __('messages.menampilkan') }}
                                {{ $latestData->firstItem() ?? 0 }}–{{ $latestData->lastItem() ?? 0 }}
                                {{ __('messages.dari') }} {{ $latestData->total() }} {{ __('messages.data') }}
                            </div>
                            <div class="flex items-center gap-1">
                                {{-- Prev --}}
                                @if($latestData->onFirstPage())
                                    <span class="pg-btn pg-btn-disabled"><i class="fas fa-chevron-left"></i></span>
                                @else
                                    <a href="{{ $latestData->appends(request()->except('dataPage'))->previousPageUrl() }}" class="pg-btn">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                @endif

                                {{-- Pages --}}
                                @php
                                    $currentDataPage = $latestData->currentPage();
                                    $lastDataPage    = $latestData->lastPage();
                                    $deltaData       = 2;
                                    $startData       = max(1, $currentDataPage - $deltaData);
                                    $endData         = min($lastDataPage, $currentDataPage + $deltaData);
                                @endphp

                                @if($startData > 1)
                                    <a href="{{ $latestData->appends(request()->except('dataPage'))->url(1) }}" class="pg-btn">1</a>
                                    @if($startData > 2)<span class="px-1 text-gray-400 text-xs">…</span>@endif
                                @endif

                                @for($p = $startData; $p <= $endData; $p++)
                                    @if($p == $currentDataPage)
                                        <span class="pg-btn pg-btn-active">{{ $p }}</span>
                                    @else
                                        <a href="{{ $latestData->appends(request()->except('dataPage'))->url($p) }}" class="pg-btn">{{ $p }}</a>
                                    @endif
                                @endfor

                                @if($endData < $lastDataPage)
                                    @if($endData < $lastDataPage - 1)<span class="px-1 text-gray-400 text-xs">…</span>@endif
                                    <a href="{{ $latestData->appends(request()->except('dataPage'))->url($lastDataPage) }}" class="pg-btn">{{ $lastDataPage }}</a>
                                @endif

                                {{-- Next --}}
                                @if($latestData->hasMorePages())
                                    <a href="{{ $latestData->appends(request()->except('dataPage'))->nextPageUrl() }}" class="pg-btn">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                @else
                                    <span class="pg-btn pg-btn-disabled"><i class="fas fa-chevron-right"></i></span>
                                @endif
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
                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-tight mb-6">{{ __('messages.pembersihan_data') }}</h3>

                    <form action="{{ route('operator.predict') }}" method="POST" class="mb-6 pb-6 border-b border-gray-100">
                        @csrf
                        <input type="hidden" name="tab" value="manage">
                        <label class="text-xs text-gray-700 font-semibold block mb-2 uppercase tracking-tight">{{ __('messages.pindai_data') }}</label>
                        <div class="flex gap-2">
                            <select name="komoditas_id"
                                    class="flex-1 bg-gray-50 border border-gray-200 rounded-lg p-2 text-xs font-medium outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">{{ __('messages.pilih_komoditas') }}</option>
                                @foreach($commodities ?? [] as $kom)
                                    <option value="{{ $kom->id }}" {{ $selectedKomoditasId == $kom->id ? 'selected' : '' }}>
                                        {{ $kom->display_name }}
                                    </option>
                                @endforeach
                            </select>
                            <button type="submit" class="bg-blue-600 text-white px-4 rounded-lg text-xs font-bold uppercase hover:bg-blue-700">{{ __('messages.pindai') }}</button>
                        </div>
                    </form>

                    <form action="{{ route('operator.cleanData') }}" method="POST" class="space-y-6">
                        @csrf
                        <input type="hidden" name="komoditas_id" value="{{ $selectedKomoditasId }}">
                        <div class="space-y-4">
                            <div>
                                <label class="text-xs text-gray-700 font-semibold block mb-2 uppercase tracking-tight">{{ __('messages.deteksi_outlier') }}</label>
                                <div class="flex items-center gap-2">
                                    <select name="outlier_method"
                                            class="flex-1 bg-gray-50 border border-gray-200 rounded-lg p-2.5 text-xs font-medium outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="remove">{{ __('messages.hapus_outlier') }}</option>
                                        <option value="mean">{{ __('messages.ganti_rata_rata') }}</option>
                                        <option value="median">{{ __('messages.ganti_median') }}</option>
                                    </select>
                                    <button type="submit" name="action" value="outlier"
                                            class="bg-orange-500 text-white px-4 py-2.5 rounded-lg text-xs font-bold hover:bg-orange-600 whitespace-nowrap">
                                        {{ __('messages.terapkan') }}
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label class="text-xs text-gray-700 font-semibold block mb-2 uppercase tracking-tight">{{ __('messages.nilai_hilang') }}</label>
                                <div class="flex items-center gap-2">
                                    <select name="missing_method"
                                            class="flex-1 bg-gray-50 border border-gray-200 rounded-lg p-2.5 text-xs font-medium outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="mean">{{ __('messages.isi_rata_rata') }}</option>
                                        <option value="median">{{ __('messages.isi_median') }}</option>
                                        <option value="remove">{{ __('messages.hapus_data_kosong') }}</option>
                                    </select>
                                    <button type="submit" name="action" value="missing"
                                            class="bg-blue-600 text-white px-4 py-2.5 rounded-lg text-xs font-bold hover:bg-blue-700 whitespace-nowrap">
                                        {{ __('messages.terapkan') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Tabel Hasil Pemindaian --}}
            <div class="lg:col-span-2">
                <div class="card-standard border-orange-200 overflow-hidden flex flex-col" style="height: fit-content;">
                    <div class="p-4 bg-orange-50/50 border-b border-orange-100 flex justify-between items-center">
                        <h3 class="text-xs text-orange-700 font-bold uppercase tracking-tight">{{ __('messages.hasil_pemindaian') }}: {{ $selectedCommodity }}</h3>
                        <span class="bg-orange-100 text-orange-600 px-2 py-0.5 rounded text-[10px] font-bold">
                            {{ ($dataIssues instanceof \Illuminate\Pagination\LengthAwarePaginator ? $dataIssues->total() : count($dataIssues ?? [])) }} {{ __('messages.temuan') }}
                        </span>
                    </div>
                    <div class="overflow-x-auto custom-scrollbar" style="max-height: 350px;">
                        <table class="w-full text-left">
                            <thead class="bg-gray-50 sticky top-0 text-xs text-gray-400 uppercase font-bold z-10">
                                <tr>
                                    <th class="px-6 py-3">{{ __('messages.tanggal') }}</th>
                                    <th class="px-6 py-3">{{ __('messages.jenis_masalah') }}</th>
                                    <th class="px-6 py-3">{{ __('messages.nilai') }}</th>
                                    <th class="px-6 py-3">{{ __('messages.status') }}</th>
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
                                                <p class="text-sm font-medium">{{ __('messages.tidak_ada_masalah') }}</p>
                                                <p class="text-xs">{{ __('messages.data_sudah_bersih') }}</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Pagination Hasil Pemindaian --}}
                    @if(isset($dataIssues) && $dataIssues instanceof \Illuminate\Pagination\LengthAwarePaginator && $dataIssues->hasPages())
                        <div class="px-4 py-3 border-t border-orange-100 bg-orange-50/20 flex items-center justify-between">
                            <div class="text-xs text-orange-600">
                                {{ $dataIssues->firstItem() }}–{{ $dataIssues->lastItem() }}
                                dari {{ $dataIssues->total() }} {{ __('messages.temuan') }}
                            </div>
                            <div class="flex items-center gap-1">
                                {{-- Prev --}}
                                @if($dataIssues->onFirstPage())
                                    <span class="pg-btn pg-btn-disabled"><i class="fas fa-chevron-left"></i></span>
                                @else
                                    <a href="{{ $dataIssues->appends(request()->except('issuePage'))->previousPageUrl() }}" class="pg-btn">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                @endif

                                {{-- Pages --}}
                                @php
                                    $currentIssuePage = $dataIssues->currentPage();
                                    $lastIssuePage    = $dataIssues->lastPage();
                                    $deltaIssue       = 2;
                                    $startIssue       = max(1, $currentIssuePage - $deltaIssue);
                                    $endIssue         = min($lastIssuePage, $currentIssuePage + $deltaIssue);
                                @endphp

                                @if($startIssue > 1)
                                    <a href="{{ $dataIssues->appends(request()->except('issuePage'))->url(1) }}" class="pg-btn">1</a>
                                    @if($startIssue > 2)<span class="px-1 text-gray-400 text-xs">…</span>@endif
                                @endif

                                @for($p = $startIssue; $p <= $endIssue; $p++)
                                    @if($p == $currentIssuePage)
                                        <span class="pg-btn pg-btn-active">{{ $p }}</span>
                                    @else
                                        <a href="{{ $dataIssues->appends(request()->except('issuePage'))->url($p) }}" class="pg-btn">{{ $p }}</a>
                                    @endif
                                @endfor

                                @if($endIssue < $lastIssuePage)
                                    @if($endIssue < $lastIssuePage - 1)<span class="px-1 text-gray-400 text-xs">…</span>@endif
                                    <a href="{{ $dataIssues->appends(request()->except('issuePage'))->url($lastIssuePage) }}" class="pg-btn">{{ $lastIssuePage }}</a>
                                @endif

                                {{-- Next --}}
                                @if($dataIssues->hasMorePages())
                                    <a href="{{ $dataIssues->appends(request()->except('issuePage'))->nextPageUrl() }}" class="pg-btn">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                @else
                                    <span class="pg-btn pg-btn-disabled"><i class="fas fa-chevron-right"></i></span>
                                @endif
                            </div>
                        </div>
                    @endif

                </div>
            </div>
        </div>

    </div>
@endif

</div>{{-- end dashboard-container --}}

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>

const trans = {
    naik:         '{{ __("messages.naik") }}',
    turun:        '{{ __("messages.turun") }}',
    stabil:       '{{ __("messages.stabil") }}',
    proyeksi:     '{{ __("messages.proyeksi") }}',
    mingguan:     '{{ __("messages.mingguan") }}',
    bulanan:      '{{ __("messages.bulanan") }}',
    tahunan:      '{{ __("messages.tahunan") }}',
    hargaAktual:  '{{ __("messages.harga_aktual") }}',
    hargaProyeksi:'{{ __("messages.harga_proyeksi") }}',
    rentangBawah: '{{ __("messages.rentang_bawah") }}',
    rentangAtas:  '{{ __("messages.rentang_atas") }}',
    tidakAdaData: '{{ __("messages.tidak_ada_data") }}',
};

const chartData = {
    weekly: {
        labels:   {!! json_encode($weeklyLabels   ?? []) !!},
        actual:   {!! json_encode($weeklyActual   ?? []) !!},
        forecast: {!! json_encode($weeklyForecast ?? []) !!},
        lower:    {!! json_encode($weeklyLower    ?? []) !!},
        upper:    {!! json_encode($weeklyUpper    ?? []) !!}
    },
    monthly: {
        labels:   {!! json_encode($monthlyLabels   ?? []) !!},
        actual:   {!! json_encode($monthlyActual   ?? []) !!},
        forecast: {!! json_encode($monthlyForecast ?? []) !!},
        lower:    {!! json_encode($monthlyLower    ?? []) !!},
        upper:    {!! json_encode($monthlyUpper    ?? []) !!}
    },
    yearly: {
        labels:   {!! json_encode($yearlyLabels   ?? []) !!},
        actual:   {!! json_encode($yearlyActual   ?? []) !!},
        forecast: {!! json_encode($yearlyForecast ?? []) !!},
        lower:    {!! json_encode($yearlyLower    ?? []) !!},
        upper:    {!! json_encode($yearlyUpper    ?? []) !!}
    }
};

// ── Variabel state — WAJIB di sini sebelum semua fungsi ──
let parametersDirty    = false;
let currentPeriod      = 'weekly';
let mainChart          = null;
let insightCurrentPage = 1;
const INSIGHT_PER_PAGE = 10;

// ─────────────────────────────────────────────
// FUNGSI HYPERPARAMETER
// ─────────────────────────────────────────────
function updateVal(hiddenId, displayId, previewId, val, decimals) {
    const parsed = parseFloat(val);
    document.getElementById(hiddenId).value = parsed;
    document.getElementById(displayId).textContent = parsed.toFixed(decimals);
    if (previewId) document.getElementById(previewId).textContent = parsed.toFixed(decimals);
    markParamDirty();
}

function updateMode(value) {
    document.getElementById('hidden_mode').value = value;
    document.getElementById('preview_mode').textContent = value;
    markParamDirty();
}

function updateToggle(hiddenId, previewId, isChecked) {
    const stringVal = isChecked ? 'true' : 'false';
    document.getElementById(hiddenId).value = stringVal;
    if (previewId) document.getElementById(previewId).textContent = stringVal;
    markParamDirty();
}

function updateForecastWeeks(val) {
    const weeks = parseInt(val);
    document.getElementById('hidden_forecast_weeks').value = weeks;
    document.getElementById('fw_display_text').textContent = weeks + ' ' + trans.mingguan;
    document.getElementById('preview_fw').textContent = weeks;
    markParamDirty();
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

    if (cp     && (!cp.value     || isNaN(parseFloat(cp.value))))         cp.value     = '0.05';
    if (season && (!season.value || isNaN(parseFloat(season.value))))     season.value = '10';
    if (mode   && !mode.value)                                            mode.value   = 'multiplicative';
    if (weekly && weekly.value !== 'true' && weekly.value !== 'false')    weekly.value = 'false';
    if (yearly && yearly.value !== 'true' && yearly.value !== 'false')    yearly.value = 'false';
    if (fw     && (!fw.value || isNaN(parseInt(fw.value)) || parseInt(fw.value) < 1)) fw.value = '12';

    const icon = document.getElementById('btn-refresh-icon');
    if (icon) icon.classList.add('fa-spin');

    const realContent = document.getElementById('real-content');
    if (realContent) realContent.classList.add('opacity-30');
    const overlay = document.getElementById('skeleton-overlay');
    if (overlay) { overlay.classList.remove('hidden'); overlay.style.opacity = '1'; }

    clearParamDirty();
    const form = document.getElementById('mainForm');
    if (form) setTimeout(() => form.submit(), 100);
}

function handleCommodityChange(val) {
    const hidden = document.getElementById('hidden_komoditas');
    if (hidden) hidden.value = val;
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

// ─────────────────────────────────────────────
// INSIGHT TAB FUNCTIONS (hanya jalan kalau tab insight)
// ─────────────────────────────────────────────
@if(($currentTab ?? 'insight') == 'insight')

function checkFlaskStatus() {
    const badge = document.getElementById('flask-status-badge');
    const dot   = document.getElementById('flask-status-dot');
    const text  = document.getElementById('flask-status-text');
    if (!badge) return;

    badge.className = 'flex items-center gap-1.5 px-2 py-1 rounded-full text-[10px] font-medium transition-all duration-500 bg-gray-100 text-gray-500';
    dot.className   = 'w-2 h-2 rounded-full bg-gray-400 animate-pulse';
    text.textContent = 'Memeriksa...';

    fetch('/api/flask-health')
        .then(res => {
            if (res.ok) {
                badge.className = 'flex items-center gap-1.5 px-2 py-1 rounded-full text-[10px] font-medium transition-all duration-500 bg-green-100 text-green-700';
                dot.className   = 'w-2 h-2 rounded-full bg-green-500 shadow-[0_0_6px_rgba(34,197,94,0.8)]';
                text.textContent = '{{ __("messages.API_aktif") }}';
            } else { throw new Error('not ok'); }
        })
        .catch(() => {
            badge.className = 'flex items-center gap-1.5 px-2 py-1 rounded-full text-[10px] font-medium transition-all duration-500 bg-red-100 text-red-700';
            dot.className   = 'w-2 h-2 rounded-full bg-red-500 shadow-[0_0_6px_rgba(239,68,68,0.8)]';
            text.textContent = '{{ __("messages.api_offline") }}';
        });
}

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
        ctx.fillText(trans.tidakAdaData, canvas.width / 2, canvas.height / 2);
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
                    label: trans.rentangBawah,
                    data: data.lower,
                    backgroundColor: 'rgba(34, 197, 94, 0.08)',
                    borderColor: 'transparent',
                    fill: '+1', pointRadius: 0, tension: 0.4
                },
                {
                    label: trans.rentangAtas,
                    data: data.upper,
                    borderColor: 'transparent',
                    fill: false, pointRadius: 0, tension: 0.4
                },
                {
                    label: trans.hargaAktual,
                    data: data.actual,
                    borderColor: '#043277',
                    backgroundColor: gradientActual,
                    borderWidth: 2.5, fill: true, tension: 0.4,
                    pointRadius: 0, pointHoverRadius: 6,
                    pointHoverBackgroundColor: '#043277',
                    pointHoverBorderColor: '#fff', pointHoverBorderWidth: 2,
                    spanGaps: false
                },
                {
                    label: trans.hargaProyeksi,
                    data: data.forecast,
                    borderColor: '#f97316',
                    backgroundColor: gradientForecast,
                    borderDash: [8, 4], borderWidth: 2.5, fill: true, tension: 0.4,
                    pointRadius: 0, pointHoverRadius: 6,
                    pointHoverBackgroundColor: '#f97316',
                    pointHoverBorderColor: '#fff', pointHoverBorderWidth: 2,
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
                    display: true, position: 'top', align: 'end',
                    labels: {
                        boxWidth: 12, boxHeight: 12, padding: 15,
                        font: { size: 11, weight: '600' }, color: '#64748b',
                        usePointStyle: true, pointStyle: 'circle',
                        filter: (item) => item.text !== trans.rentangBawah && item.text !== trans.rentangAtas
                    }
                },
                tooltip: {
                    backgroundColor: '#ffffff',
                    titleColor: '#1e293b', bodyColor: '#475569',
                    borderColor: '#e2e8f0', borderWidth: 1,
                    padding: 12, boxPadding: 6, usePointStyle: true,
                    titleFont: { size: 11, weight: '600' }, bodyFont: { size: 11 },
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label === trans.rentangBawah || label === trans.rentangAtas) return null;
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
                        color: '#94a3b8', font: { size: 10, weight: '500' }, padding: 8,
                        callback: value => 'Rp ' + value.toLocaleString('id-ID')
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: {
                        color: '#94a3b8', font: { size: 9, weight: '500' },
                        maxRotation: 45, minRotation: 0, autoSkip: true, maxTicksLimit: 20
                    }
                }
            }
        }
    });
}

function changeChartPeriod(period) {
    currentPeriod      = period;
    insightCurrentPage = 1; // reset ke halaman 1 saat ganti periode

    document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
    document.getElementById('btn-' + period).classList.add('active');

    const periodText = { weekly: trans.mingguan, monthly: trans.bulanan, yearly: trans.tahunan };
    document.getElementById('selectedPeriodText').textContent = periodText[period];

    initializeChart();
    updateInsightTable(1);
}

// ─── Tabel Insight dengan Pagination Client-side ───
function updateInsightTable(page) {
    page = page || 1;
    insightCurrentPage = page;

    const data  = chartData[currentPeriod];
    const tbody = document.getElementById('insightTableBody');
    if (!tbody) return;
    tbody.innerHTML = '';

    if (!data.labels || data.labels.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="p-8 text-center text-gray-400 text-xs">' + trans.tidakAdaData + '</td></tr>';
        renderInsightPagination(1, 1, 0, 0, 0);
        return;
    }

    // Pisahkan baris aktual dan forecast
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
        } else if (data.forecast[i] !== null) {
            forecastRows.push(row);
        }
    }

    const allRows    = actualRows.concat(forecastRows);
    const totalRows  = allRows.length;
    const totalPages = Math.max(1, Math.ceil(totalRows / INSIGHT_PER_PAGE));
    const safePage   = Math.min(Math.max(1, page), totalPages);
    const startIdx   = (safePage - 1) * INSIGHT_PER_PAGE;
    const endIdx     = Math.min(startIdx + INSIGHT_PER_PAGE, totalRows);
    const display    = allRows.slice(startIdx, endIdx);
    const lastActual = actualRows.length > 0 ? actualRows[actualRows.length - 1] : null;
    const actualCount = actualRows.length;

    if (display.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="p-8 text-center text-gray-400 text-xs">' + trans.tidakAdaData + '</td></tr>';
        renderInsightPagination(1, 1, 0, 0, 0);
        return;
    }

    var html = '';
    for (var idx = 0; idx < display.length; idx++) {
        var row           = display[idx];
        var label         = row.label;
        var actual        = row.actual;
        var forecast      = row.forecast;
        var lower         = row.lower;
        var upper         = row.upper;
        var isForecastOnly = (actual === null && forecast !== null);
        var globalIdx     = startIdx + idx;
        var diff          = (actual !== null && forecast !== null) ? (forecast - actual) : null;

        var insight      = trans.stabil;
        var insightClass = 'insight-stabil';

        if (diff !== null) {
            var threshold = (actual || 1) * 0.01;
            if (diff > threshold)       { insight = trans.naik;  insightClass = 'insight-naik'; }
            else if (diff < -threshold) { insight = trans.turun; insightClass = 'insight-turun'; }
        } else if (isForecastOnly && lastActual !== null) {
            var diffFromLast  = forecast - lastActual.actual;
            var threshLast    = lastActual.actual * 0.01;
            if (diffFromLast > threshLast)       { insight = trans.naik;     insightClass = 'insight-naik'; }
            else if (diffFromLast < -threshLast) { insight = trans.turun;    insightClass = 'insight-turun'; }
            else                                 { insight = trans.proyeksi; insightClass = 'insight-stabil'; }
        } else if (isForecastOnly) {
            insight = trans.proyeksi; insightClass = 'insight-stabil';
        }

        var diffColor = diff !== null
            ? (diff > 0 ? 'text-red-600' : diff < 0 ? 'text-emerald-600' : 'text-gray-500')
            : 'text-gray-300';
        var diffText = diff !== null
            ? (diff > 0 ? '+' : '') + Math.round(diff).toLocaleString('id-ID')
            : '—';

        var rowBg     = isForecastOnly ? 'bg-orange-50/30' : '';
        var borderTop = (globalIdx === actualCount && forecastRows.length > 0) ? 'border-t-2 border-orange-200' : '';

        var forecastBadge = isForecastOnly
            ? '<span class="ml-1 text-[9px] bg-orange-100 text-orange-600 px-1.5 py-0.5 rounded font-bold uppercase">' + trans.proyeksi + '</span>'
            : '';

        var actualCell   = actual   !== null ? 'Rp ' + Math.round(actual).toLocaleString('id-ID')   : '<span class="text-gray-300">—</span>';
        var forecastCell = forecast !== null ? 'Rp ' + Math.round(forecast).toLocaleString('id-ID') : '<span class="text-gray-300">—</span>';
        var lowerCell    = lower    !== null ? 'Rp ' + Math.round(lower).toLocaleString('id-ID')    : '<span class="text-gray-300">—</span>';
        var upperCell    = upper    !== null ? 'Rp ' + Math.round(upper).toLocaleString('id-ID')    : '<span class="text-gray-300">—</span>';

        html +=
            '<tr class="' + rowBg + ' ' + borderTop + ' border-b border-gray-50 hover:bg-orange-50/50 animate-fade-in">' +
                '<td class="px-6 py-4 text-gray-500 font-medium text-xs">' + label + forecastBadge + '</td>' +
                '<td class="px-6 py-4 text-right text-xs font-medium">' + actualCell + '</td>' +
                '<td class="px-6 py-4 text-right text-blue-600 font-bold text-xs">' + forecastCell + '</td>' +
                '<td class="px-6 py-4 text-right text-xs text-gray-400">' + lowerCell + '</td>' +
                '<td class="px-6 py-4 text-right text-xs text-gray-400">' + upperCell + '</td>' +
                '<td class="px-6 py-4 text-right text-xs ' + diffColor + ' font-medium">' + diffText + '</td>' +
                '<td class="px-6 py-4 text-center"><span class="insight-badge ' + insightClass + '">' + insight + '</span></td>' +
            '</tr>';
    }
    tbody.innerHTML = html;

    renderInsightPagination(safePage, totalPages, totalRows, startIdx + 1, endIdx);
}

function renderInsightPagination(currentPage, totalPages, totalRows, fromRow, toRow) {
    var container = document.getElementById('insightPagination');
    if (!container) return;

    if (totalPages <= 1) {
        container.innerHTML = '';
        return;
    }

    var btnBase     = 'pg-btn';
    var btnActive   = 'pg-btn pg-btn-active';
    var btnDisabled = 'pg-btn pg-btn-disabled';

    var prev = currentPage > 1
        ? '<button onclick="updateInsightTable(' + (currentPage - 1) + ')" class="' + btnBase + '"><i class="fas fa-chevron-left"></i></button>'
        : '<span class="' + btnDisabled + '"><i class="fas fa-chevron-left"></i></span>';

    var next = currentPage < totalPages
        ? '<button onclick="updateInsightTable(' + (currentPage + 1) + ')" class="' + btnBase + '"><i class="fas fa-chevron-right"></i></button>'
        : '<span class="' + btnDisabled + '"><i class="fas fa-chevron-right"></i></span>';

    var delta = 2;
    var start = Math.max(1, currentPage - delta);
    var end   = Math.min(totalPages, currentPage + delta);
    var pages = '';

    if (start > 1) {
        pages += '<button onclick="updateInsightTable(1)" class="' + btnBase + '">1</button>';
        if (start > 2) pages += '<span class="px-1 text-gray-400 text-xs">…</span>';
    }
    for (var p = start; p <= end; p++) {
        pages += '<button onclick="updateInsightTable(' + p + ')" class="' + (p === currentPage ? btnActive : btnBase) + '">' + p + '</button>';
    }
    if (end < totalPages) {
        if (end < totalPages - 1) pages += '<span class="px-1 text-gray-400 text-xs">…</span>';
        pages += '<button onclick="updateInsightTable(' + totalPages + ')" class="' + btnBase + '">' + totalPages + '</button>';
    }

    container.innerHTML =
        '<div class="flex items-center justify-between px-6 py-3 border-t border-gray-100 bg-gray-50/30">' +
            '<span class="text-xs text-gray-500">Menampilkan ' + fromRow + '–' + toRow + ' dari ' + totalRows + ' data</span>' +
            '<div class="flex items-center gap-1">' + prev + pages + next + '</div>' +
        '</div>';
}

// Init saat DOM ready
document.addEventListener('DOMContentLoaded', function () {
    initializeChart();
    updateInsightTable(1);
    checkFlaskStatus();
    setInterval(checkFlaskStatus, 30000);
});

@endif

// ─────────────────────────────────────────────
// EDIT MODE DATA TABLE
// ─────────────────────────────────────────────
function toggleEditMode(id) {
    var row = document.getElementById('row-' + id);
    if (!row) return;
    var isEditing = row.querySelector('.commodity-edit').classList.contains('hidden');
    row.querySelector('.commodity-view').classList.toggle('hidden', isEditing);
    row.querySelector('.commodity-edit').classList.toggle('hidden', !isEditing);
    row.querySelector('.date-view').classList.toggle('hidden', isEditing);
    row.querySelector('.date-edit').classList.toggle('hidden', !isEditing);
    row.querySelector('.price-view').classList.toggle('hidden', isEditing);
    row.querySelector('.price-edit').classList.toggle('hidden', !isEditing);
    row.querySelector('.edit-btn').classList.toggle('hidden', isEditing);
    row.querySelector('.done-btn').classList.toggle('hidden', !isEditing);
    var deleteForm = row.querySelector('.delete-form');
    if (deleteForm) {
        deleteForm.style.opacity       = isEditing ? '0.3' : '1';
        deleteForm.style.pointerEvents = isEditing ? 'none' : 'auto';
    }
    if (isEditing) row.classList.add('bg-blue-50', 'border-l-4', 'border-l-blue-500');
    else           row.classList.remove('bg-blue-50', 'border-l-4', 'border-l-blue-500');
}

function autoSaveData(id) {
    var row         = document.getElementById('row-' + id);
    var komoditasId = row.querySelector('.commodity-edit').value;
    var date        = row.querySelector('.date-edit').value;
    var price       = row.querySelector('.price-edit').value;
    if (!komoditasId || !date || !price) { showNotification('Semua field harus diisi!', 'error'); return; }
    if (parseFloat(price) <= 0)          { showNotification('Harga harus lebih dari 0!', 'error'); return; }

    row.style.backgroundColor = '#fef3c7';
    fetch('{{ url("/operator/update-data") }}/' + id, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ komoditas_id: komoditasId, date: date, price: price })
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
        if (data.success) {
            var selectedOption = row.querySelector('.commodity-edit option:checked');
            row.querySelector('.commodity-view').textContent = selectedOption ? selectedOption.text : komoditasId;
            var parts = date.split('-');
            row.querySelector('.date-view').textContent  = parts[2] + '/' + parts[1] + '/' + parts[0];
            row.querySelector('.price-view').textContent = 'Rp ' + parseInt(price).toLocaleString('id-ID');
            row.style.backgroundColor = '#d1fae5';
            setTimeout(function() { row.style.backgroundColor = ''; }, 800);
            showNotification('Data tersimpan!', 'success');
        } else {
            showNotification('Gagal: ' + (data.message || 'Terjadi kesalahan'), 'error');
            row.style.backgroundColor = '';
        }
    })
    .catch(function() { showNotification('Terjadi kesalahan jaringan', 'error'); row.style.backgroundColor = ''; });
}

function showNotification(message, type) {
    type = type || 'success';
    var existing = document.querySelector('.toast-notification');
    if (existing) existing.remove();
    var notification = document.createElement('div');
    notification.className = 'toast-notification fixed top-4 right-4 z-50 px-6 py-4 rounded-lg shadow-lg text-white text-sm font-medium animate-fade-in ' +
        (type === 'success' ? 'bg-green-500' : 'bg-red-500');
    notification.innerHTML = '<div class="flex items-center gap-3"><i class="fas fa-' +
        (type === 'success' ? 'check-circle' : 'exclamation-circle') +
        '"></i><span>' + message + '</span></div>';
    document.body.appendChild(notification);
    setTimeout(function() {
        notification.style.opacity    = '0';
        notification.style.transform  = 'translateX(100%)';
        notification.style.transition = 'all 0.3s ease';
        setTimeout(function() { notification.remove(); }, 300);
    }, 3000);
}
</script>

@endsection