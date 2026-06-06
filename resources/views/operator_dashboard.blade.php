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
    .fitted-badge {
        display: inline-block;
        margin-left: 4px;
        font-size: 9px;
        background: #eff6ff;
        color: #1d4ed8;
        border: 1px solid #bfdbfe;
        border-radius: 4px;
        padding: 0px 4px;
        font-weight: 700;
        text-transform: uppercase;
        vertical-align: middle;
    }
    .row-has-fitted {
        border-left: 2px solid #bfdbfe;
    }

    .form-flash {
        display: none; align-items: center; gap: 0.625rem;
        padding: 0.75rem 1rem; border-radius: 0.5rem;
        font-size: 13px; font-weight: 600; margin-bottom: 1rem;
    }
    .form-flash.show    { display: flex; animation: fadeIn 0.3s ease-out; }
    .form-flash.success { background:#dcfce7; border:1px solid #86efac; color:#166534; }
    .form-flash.error   { background:#fee2e2; border:1px solid #fca5a5; color:#991b1b; }

    .issue-price-input {
        width: 110px; padding: 3px 6px; font-size: 12px;
        border: 1px solid #3b82f6; border-radius: 4px;
        background: #eff6ff; color: #1e40af;
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

{{-- Flash Messages --}}
@if(session('success'))
    <div id="flash-message" class="alert-success flex items-center gap-3">
        <i class="fas fa-check-circle"></i>
        <span>{{ session('success') }}</span>
    </div>
@endif
@if(session('error'))
    <div id="flash-message" class="alert-error flex items-center gap-3">
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

            <input type="hidden" name="komoditas_id"            id="hidden_komoditas"       value="{{ $selectedKomoditasId ?? '' }}">
            <input type="hidden" name="changepoint_prior_scale" id="hidden_cp"              value="{{ $cpScale ?? 0.05 }}">
            <input type="hidden" name="seasonality_prior_scale" id="hidden_season"          value="{{ $seasonScale ?? 1 }}">
            <input type="hidden" name="seasonality_mode"        id="hidden_mode"            value="{{ $seasonMode ?? 'multiplicative' }}">
            <input type="hidden" name="yearly_seasonality"      id="hidden_yearly"          value="{{ ($yearlySeason ?? false) ? 'true' : 'false' }}">
            <input type="hidden" name="forecast_months"         id="hidden_forecast_months" value="{{ $forecastMonths ?? 12 }}">
            <input type="hidden" name="force_retrain"           id="hidden_force_retrain"   value="false">
            <input type="hidden" name="preview_only"            id="hidden_preview_only"    value="false">
            <input type="hidden" name="confirm_save"            id="hidden_confirm_save"    value="false">
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
                                  id="season_display">{{ number_format($seasonScale ?? 1, 2) }}</span>
                        </div>
                        <input type="range" min="0.01" max="50" step="0.01"
                               value="{{ $seasonScale ?? 1 }}"
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
                            <span class="horizon-pill" id="fm_display">
                                <i class="fas fa-calendar-alt" style="font-size:8px;"></i>
                                <span id="fm_display_text">{{ $forecastMonths ?? 12 }} {{ __('messages.bulanan') }}</span>
                            </span>
                        </div>
                        <input type="range" min="1" max="12" step="1"
                               value="{{ $forecastMonths ?? 12 }}"
                               class="w-full h-1 bg-gray-100 rounded-lg appearance-none cursor-pointer indigo-thumb"
                               id="range_fm"
                               oninput="updateForecastMonths(this.value)">
                        <div class="flex justify-between text-[8px] text-gray-300 mt-1">
                            <span>1 {{ __('messages.bulanan') }}</span>
                            <span>6 {{ __('messages.bulanan') }}</span>
                            <span>12 {{ __('messages.bulanan') }}</span>
                        </div>
                        <p class="text-[9px] text-gray-400 mt-1">{{ __('messages.periode_prediksi') }} (1 – 12)</p>
                    </div>

                    {{-- Toggle Yearly Seasonality --}}
                    <div class="space-y-2 pt-2 border-t border-gray-100">
                        <label class="text-xs text-gray-500 font-semibold uppercase block mb-2">{{ __('messages.komponen_musiman') }}</label>

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
                            <span class="font-mono text-emerald-600" id="preview_season">{{ $seasonScale ?? 1 }}</span>
                        </div>
                        <div class="flex justify-between text-[10px]">
                            <span class="text-gray-500">mode</span>
                            <span class="font-mono text-purple-600" id="preview_mode">{{ $seasonMode ?? 'multiplicative' }}</span>
                        </div>
                        <div class="flex justify-between text-[10px]">
                            <span class="text-gray-500">yearly</span>
                            <span class="font-mono" id="preview_yearly">{{ ($yearlySeason ?? false) ? 'true' : 'false' }}</span>
                        </div>
                        <div class="flex justify-between text-[10px]">
                            <span class="text-gray-500">forecast_months</span>
                            <span class="font-mono text-indigo-600" id="preview_fm">{{ $forecastMonths ?? 12 }}</span>
                        </div>

                        <div id="param-dirty-notice" class="hidden mt-2 pt-2 border-t border-orange-200 text-[9px] text-orange-600 font-bold flex items-center gap-1">
                            <i class="fas fa-exclamation-triangle"></i>
                            {{ __('messages.perbarui_prediksi') }}
                        </div>
                    </div>

                    {{-- Tombol Reset + Submit --}}
                    <div class="flex gap-2">
                        <button type="button" onclick="triggerReset()"
                                id="btn-reset"
                                title="Reset ke parameter otomatis terbaik (grid search)"
                                class="flex-none bg-gray-100 text-gray-500 border border-gray-200 py-3 px-4 rounded-lg text-xs font-bold uppercase tracking-wider hover:bg-red-50 hover:text-red-500 hover:border-red-200 transition-all flex items-center gap-2">
                            <i class="fas fa-rotate-left"></i>
                        </button>
                        <button type="button" onclick="triggerSubmit()"
                                id="btn-update"
                                class="flex-1 bg-blue-600 text-white py-3 rounded-lg text-xs font-bold uppercase tracking-wider hover:bg-blue-700 transition-all shadow-sm flex items-center justify-center gap-2">
                            <i class="fas fa-sync-alt" id="btn-refresh-icon"></i>
                            {{ __('messages.perbarui_prediksi') }}
                        </button>
                    </div>

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
                        <span class="text-sm font-bold">{{ $forecastMonths ?? 12 }} {{ __('messages.bulanan') }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Chart --}}
        <div class="col-span-12 lg:col-span-8 card-standard overflow-hidden flex flex-col" id="hasil-prediksi">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex flex-col lg:flex-row justify-between items-center gap-4 flex-shrink-0">
                <div>
                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-tight">{{ __('messages.visualisasi_tren') }}</h3>
                    <p class="text-xs text-gray-500">
                        {{ $selectedCommodity }} — {{ __('messages.data_historis_vs_proyeksi') }}
                        <span class="ml-2 horizon-pill">
                            <i class="fas fa-calendar-alt" style="font-size:8px;"></i>
                            {{ $forecastMonths ?? 12 }} {{ __('messages.bulanan') }}
                        </span>
                    </p>
                </div>
                <div class="flex bg-white border border-gray-300 p-1 rounded-md shadow-sm">
                    <button onclick="changeChartPeriod('monthly')" class="filter-btn active" id="btn-monthly">{{ __('messages.bulanan') }}</button>
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
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center flex-wrap gap-2">
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-tight">
                {{ __('messages.ringkasan_analisis') }}
                <span id="selectedPeriodText" class="text-blue-600">{{ __('messages.bulanan') }}</span>
            </h3>
            <div class="flex items-center gap-3 flex-wrap">
                <span class="text-xs text-gray-400">{{ $selectedCommodity }}</span>
                <span class="text-[9px] bg-blue-50 text-blue-700 font-bold px-2 py-0.5 rounded-full">
                    MAPE: {{ number_format($mape ?? 0, 2) }}%
                </span>
                <span class="horizon-pill">{{ $forecastMonths ?? 12 }} {{ __('messages.bulanan') }}</span>
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
                </tbody>
            </table>
        </div>
        <div id="insightPagination"></div>
    </div>

    {{-- Interpretasi --}}
    <div class="card-standard p-6 border-l-4 border-l-blue-600">
        <div class="flex items-center gap-3 mb-3">
            <h4 class="text-sm font-bold text-gray-900 uppercase">
                {{ __('messages.interpretasi_tren') }}
            </h4>
        </div>
        <p id="dynamic-analysis" class="text-sm text-gray-600 leading-relaxed">
            {{ __('messages.berdasarkan_analisis') }} <strong>{{ $selectedCommodity }}</strong>,
            {{ __('messages.model_deteksi') }} <strong>{{ __('messages.' . strtolower($trendDir ?? 'stabil')) }}</strong>
            {{ __('messages.rata_rata_harga_label') }} <strong>Rp {{ number_format($avgPrice ?? 0, 0, ',', '.') }}</strong>
            {{ __('messages.total_label') }} <strong>{{ $countData ?? 0 }} {{ __('messages.data_poin') }}</strong> {{ __('messages.pada_periode') }}
            {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }}
            {{ __('messages.s_d') }} {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}.

            {{ __('messages.model_prophet_dilatih') }} <strong>changepoint_prior_scale={{ $cpScale ?? 0.05 }}</strong>,
            <strong>seasonality_prior_scale={{ $seasonScale ?? 1 }}</strong>,
            {{ __('messages.mode_musiman') }} <strong>{{ $seasonMode ?? 'multiplicative' }}</strong>,
            {{ __('messages.horizon_prediksi_label') }} <strong>{{ $forecastMonths ?? 12 }} {{ __('messages.bulan_ke_depan') }}</strong>.

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

        {{-- ── BARIS 1: Tambah Data Baru + Riwayat Database ── --}}
        <div id="section-tambah-data" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1">
                <div class="card-standard p-6">
                    <h3 class="text-sm font-bold text-gray-900 mb-4 uppercase tracking-tight">{{ __('messages.tambah_data_baru') }}</h3>
                    <div class="flex gap-4 mb-4 border-b pb-2">
                        <button onclick="switchInputMode('single')" id="btn-tab-single"
                                class="text-blue-600 border-b-2 border-blue-600 text-xs uppercase tracking-wider pb-1 font-semibold">{{ __('messages.manual') }}</button>
                        <button onclick="switchInputMode('bulk')" id="btn-tab-bulk"
                                class="text-gray-400 text-xs uppercase tracking-wider pb-1 font-semibold">{{ __('messages.unggah_csv') }}</button>
                    </div>

                    <div id="flash-add-single" class="form-flash"></div>

                    <form id="form-single" action="{{ route('operator.storeData') }}" method="POST" class="space-y-4 tab-single">
                        @csrf
                        <input type="hidden" name="anchor" value="section-tambah-data">
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

                    <div id="flash-add-bulk" class="form-flash" style="display:none;"></div>

                    <form id="form-bulk" action="{{ route('operator.manajemen-data.upload-csv') }}" method="POST"
                          enctype="multipart/form-data" class="space-y-4 tab-bulk" style="display:none;">
                        @csrf
                        <input type="hidden" name="anchor" value="section-tambah-data">
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
            <div id="section-riwayat" class="lg:col-span-2">
                <div class="card-standard overflow-hidden flex flex-col">
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
                                                   value="{{ \Carbon\Carbon::parse($data->tanggal)->format('Y-m-d') }}" data-id="{{ $data->id }}" onchange="autoSaveData({{ $data->id }})">
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
                                                      class="inline delete-form">
                                                    @csrf @method('DELETE')
                                                    <button type="button"
                                                            onclick="confirmDeleteData(this, '{{ \Carbon\Carbon::parse($data->tanggal)->format('d/m/Y') }}', 'Rp {{ number_format($data->harga, 0, ',', '.') }}')"
                                                            class="text-red-400 hover:text-red-600 transition-colors text-sm font-medium">
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
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if(isset($latestData) && method_exists($latestData, 'hasPages') && $latestData->hasPages())
                        <div class="px-6 py-4 border-t bg-gray-50/30 flex items-center justify-between">
                            <div class="text-xs text-gray-500">
                                {{ __('messages.menampilkan') }}
                                {{ $latestData->firstItem() ?? 0 }}–{{ $latestData->lastItem() ?? 0 }}
                                {{ __('messages.dari') }} {{ $latestData->total() }} {{ __('messages.data') }}
                            </div>
                            <div class="flex items-center gap-1">
                                @if($latestData->onFirstPage())
                                    <span class="pg-btn pg-btn-disabled"><i class="fas fa-chevron-left"></i></span>
                                @else
                                    <a href="{{ $latestData->appends(request()->except('dataPage'))->previousPageUrl() }}" class="pg-btn">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                @endif
                                @php
                                    $currentDataPage = $latestData->currentPage();
                                    $lastDataPage    = $latestData->lastPage();
                                    $startData       = max(1, $currentDataPage - 2);
                                    $endData         = min($lastDataPage, $currentDataPage + 2);
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

        {{-- ── BARIS 2: Input Bobot + Riwayat Bobot ── --}}
        <div id="section-bobot" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1">
                <div class="card-standard p-6">
                    <h3 class="text-sm font-bold text-gray-900 mb-4 uppercase tracking-tight">Input Bobot Komoditas</h3>
                    <div id="flash-bobot" class="form-flash"></div>
                    <form id="form-bobot" action="{{ route('operator.storeBobot') }}" method="POST" class="space-y-4">
                        @csrf
                        <input type="hidden" name="anchor" value="section-bobot">
                        <div>
                            <label class="text-xs font-semibold text-gray-700 uppercase mb-1.5 block tracking-tight">Komoditas</label>
                            <select name="komoditas_id" required
                                    class="w-full bg-gray-50 border border-gray-200 rounded-lg p-3 text-xs text-gray-900 font-medium outline-none focus:ring-2 focus:ring-indigo-500">
                                <option value="">— Pilih Komoditas —</option>
                                @foreach($commodities ?? [] as $kom)
                                    <option value="{{ $kom->id }}" {{ $selectedKomoditasId == $kom->id ? 'selected' : '' }}>
                                        {{ $kom->display_name ?? trim($kom->nama_komoditas . ' ' . ($kom->nama_varian ?? '')) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-700 uppercase mb-1.5 block tracking-tight">Tanggal</label>
                            <input type="date" name="tanggal" required
                                   class="w-full bg-gray-50 border border-gray-200 rounded-lg p-3 text-xs text-gray-600 font-medium focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-700 uppercase mb-1.5 block tracking-tight">Nilai Bobot</label>
                            <input type="number" name="nilai_bobot" placeholder="Contoh: 3.4116"
                                   step="0.0001" min="0" required
                                   class="w-full bg-gray-50 border border-gray-200 rounded-lg p-3 text-xs text-gray-600 font-medium focus:ring-2 focus:ring-indigo-500">
                            <p class="text-[9px] text-gray-400 mt-1">Gunakan titik (.) sebagai pemisah desimal</p>
                        </div>
                        <button type="submit"
                                class="w-full bg-indigo-600 text-white py-3 rounded-lg text-xs font-bold uppercase tracking-wider hover:bg-indigo-700 transition-all">
                            Simpan Bobot
                        </button>
                    </form>
                </div>
            </div>

            <div class="lg:col-span-2">
                <div class="card-standard overflow-hidden flex flex-col">
                    <div class="p-5 border-b bg-gray-50/50 flex justify-between items-center">
                        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-tight">Riwayat Bobot Komoditas</h3>
                        <span class="text-xs text-gray-400">
                            {{ ($bobotList instanceof \Illuminate\Pagination\LengthAwarePaginator ? $bobotList->total() : count($bobotList ?? [])) }} entri
                        </span>
                    </div>
                    <div class="overflow-x-auto custom-scrollbar" style="max-height: 450px;">
                        <table class="w-full text-left">
                            <thead class="sticky top-0 bg-white border-b border-gray-100 z-10">
                                <tr class="text-xs text-gray-400 uppercase font-bold">
                                    <th class="px-5 py-4">Komoditas</th>
                                    <th class="px-5 py-4">Tanggal</th>
                                    <th class="px-5 py-4 text-right">Nilai Bobot</th>
                                    <th class="px-5 py-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-xs">
                                @forelse($bobotList ?? [] as $bobot)
                                    <tr class="hover:bg-gray-50 transition-colors" id="bobot-row-{{ $bobot->id }}">
                                        <td class="px-5 py-4 font-bold text-indigo-600">
                                            <span class="bobot-komoditas-view">{{ $bobot->nama_komoditas }}</span>
                                            <select class="bobot-komoditas-edit hidden w-full bg-gray-50 border border-gray-300 rounded-lg p-2 text-xs font-medium focus:ring-2 focus:ring-indigo-500"
                                                    data-id="{{ $bobot->id }}" onchange="autoSaveBobot({{ $bobot->id }})">
                                                @foreach($commodities ?? [] as $kom)
                                                    <option value="{{ $kom->id }}" {{ $bobot->komoditas_id == $kom->id ? 'selected' : '' }}>
                                                        {{ $kom->display_name ?? trim($kom->nama_komoditas . ' ' . ($kom->nama_varian ?? '')) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="px-5 py-4 text-gray-500">
                                            <span class="bobot-tanggal-view">{{ \Carbon\Carbon::parse($bobot->tanggal)->format('d/m/Y') }}</span>
                                            <input type="date" class="bobot-tanggal-edit hidden w-full bg-gray-50 border border-gray-300 rounded-lg p-2 text-xs focus:ring-2 focus:ring-indigo-500"
                                                   value="{{ \Carbon\Carbon::parse($bobot->tanggal)->format('Y-m-d') }}" data-id="{{ $bobot->id }}" onchange="autoSaveBobot({{ $bobot->id }})">
                                        </td>
                                        <td class="px-5 py-4 text-right font-bold text-emerald-600">
                                            <span class="bobot-nilai-view">{{ number_format($bobot->nilai_bobot, 4, '.', '') }}</span>
                                            <input type="number" step="0.0001" min="0"
                                                   class="bobot-nilai-edit hidden w-full bg-gray-50 border border-gray-300 rounded-lg p-2 text-xs text-right focus:ring-2 focus:ring-indigo-500"
                                                   value="{{ $bobot->nilai_bobot }}" data-id="{{ $bobot->id }}" onchange="autoSaveBobot({{ $bobot->id }})">
                                        </td>
                                        <td class="px-5 py-4">
                                            <div class="flex items-center justify-center gap-3">
                                                <button type="button" onclick="toggleBobotEdit({{ $bobot->id }})"
                                                        class="bobot-edit-btn text-indigo-500 hover:text-indigo-700 transition-colors text-sm font-medium">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <button type="button" onclick="toggleBobotEdit({{ $bobot->id }})"
                                                        class="bobot-done-btn hidden text-green-500 hover:text-green-700 transition-colors text-sm font-medium">
                                                    <i class="fas fa-check"></i> Selesai
                                                </button>
                                                <form action="{{ route('operator.deleteBobot', $bobot->id) }}" method="POST"
                                                      class="inline bobot-delete-form">
                                                    @csrf @method('DELETE')
                                                    <button type="button"
                                                            onclick="confirmDeleteBobot(this, '{{ addslashes($bobot->nama_komoditas) }}', '{{ \Carbon\Carbon::parse($bobot->tanggal)->format('d/m/Y') }}', '{{ number_format($bobot->nilai_bobot, 4, '.', '') }}')"
                                                            class="text-red-400 hover:text-red-600 transition-colors text-sm font-medium">
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
                                                <i class="fas fa-weight-hanging text-3xl opacity-30"></i>
                                                <p class="text-sm font-medium">Belum ada data bobot</p>
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

        {{-- ── BARIS 3: Data Cleaning + Hasil Pemindaian ── --}}
        <div id="section-outlier" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1 space-y-4">
                <div class="card-standard p-5">
                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-tight mb-4">{{ __('messages.pindai_data') }}</h3>
                    <form action="{{ route('operator.predict') }}" method="POST">
                        @csrf
                        <input type="hidden" name="tab" value="manage">
                        <input type="hidden" name="anchor" value="section-outlier">
                        <div class="flex items-center gap-2 overflow-hidden">
                            <select name="komoditas_id"
                                    class="min-w-0 flex-1 bg-gray-50 border border-gray-200 rounded-lg p-2 text-xs font-medium outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">{{ __('messages.pilih_komoditas') }}</option>
                                @foreach($commodities ?? [] as $kom)
                                    <option value="{{ $kom->id }}" {{ $selectedKomoditasId == $kom->id ? 'selected' : '' }}>
                                        {{ $kom->display_name }}
                                    </option>
                                @endforeach
                            </select>
                            <button type="submit"
                                    class="shrink-0 bg-blue-600 text-white px-3 py-2 rounded-lg text-xs font-bold uppercase hover:bg-blue-700 transition-all flex items-center gap-1">
                                {{ __('messages.pindai') }}
                            </button>
                        </div>
                    </form>
                </div>

                <div class="card-standard p-5">
                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-tight mb-4">{{ __('messages.pembersihan_data') }}</h3>
                    <div id="flash-outlier" class="form-flash"></div>
                    <form id="form-clean" action="{{ route('operator.cleanData') }}" method="POST" class="space-y-4">
                        @csrf
                        <input type="hidden" name="komoditas_id" value="{{ $selectedKomoditasId }}">
                        <input type="hidden" name="anchor" value="section-outlier">
                        <div>
                            <label class="text-xs text-gray-700 font-semibold block mb-2 uppercase tracking-tight">{{ __('messages.deteksi_outlier') }}</label>
                            <div class="flex items-center gap-2">
                                <select name="outlier_method" class="flex-1 bg-gray-50 border border-gray-200 rounded-lg p-2 text-xs font-medium outline-none focus:ring-2 focus:ring-orange-400">
                                    <option value="remove">{{ __('messages.hapus_outlier') }}</option>
                                    <option value="mean">{{ __('messages.ganti_rata_rata') }}</option>
                                    <option value="median">{{ __('messages.ganti_median') }}</option>
                                </select>
                                <button type="button" onclick="confirmCleanAction('outlier', this)"
                                        class="shrink-0 bg-orange-500 text-white px-3 py-2 rounded-lg text-xs font-bold uppercase hover:bg-orange-600 transition-all">
                                    {{ __('messages.terapkan') }}
                                </button>
                            </div>
                        </div>
                        <div class="pt-3 border-t border-gray-100">
                            <label class="text-xs text-gray-700 font-semibold block mb-2 uppercase tracking-tight">{{ __('messages.nilai_hilang') }}</label>
                            <div class="flex items-center gap-2">
                                <select name="missing_method" class="flex-1 bg-gray-50 border border-gray-200 rounded-lg p-2 text-xs font-medium outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="mean">{{ __('messages.isi_rata_rata') }}</option>
                                    <option value="median">{{ __('messages.isi_median') }}</option>
                                    <option value="remove">{{ __('messages.hapus_data_kosong') }}</option>
                                </select>
                                <button type="button" onclick="confirmCleanAction('missing', this)"
                                        class="shrink-0 bg-blue-600 text-white px-3 py-2 rounded-lg text-xs font-bold uppercase hover:bg-blue-700 transition-all">
                                    {{ __('messages.terapkan') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="lg:col-span-2">
                <div class="card-standard border-orange-200 overflow-hidden flex flex-col">
                    <div class="p-4 bg-orange-50/50 border-b border-orange-100 flex justify-between items-center">
                        <h3 class="text-xs text-orange-700 font-bold uppercase tracking-tight">
                            {{ __('messages.hasil_pemindaian') }}: {{ $selectedCommodity }}
                        </h3>
                        <span class="bg-orange-100 text-orange-600 px-2 py-0.5 rounded text-[10px] font-bold">
                            {{ ($dataIssues instanceof \Illuminate\Pagination\LengthAwarePaginator ? $dataIssues->total() : count($dataIssues ?? [])) }} {{ __('messages.temuan') }}
                        </span>
                    </div>
                    <div class="overflow-x-auto custom-scrollbar" style="max-height: 420px;">
                        <table class="w-full text-left">
                            <thead class="bg-gray-50 sticky top-0 text-xs text-gray-400 uppercase font-bold z-10">
                                <tr>
                                    <th class="px-4 py-3">{{ __('messages.tanggal') }}</th>
                                    <th class="px-4 py-3">{{ __('messages.jenis_masalah') }}</th>
                                    <th class="px-4 py-3 text-right">{{ __('messages.nilai') }}</th>
                                    <th class="px-4 py-3">{{ __('messages.status') }}</th>
                                    <th class="px-4 py-3 text-center" style="min-width:120px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-xs">
                                @forelse($dataIssues ?? [] as $issue)
                                    <tr class="bg-orange-50/20 hover:bg-orange-50/40 transition-colors" id="issue-row-{{ $issue->id }}">
                                        <td class="px-4 py-3 font-medium text-gray-700">
                                            {{ \Carbon\Carbon::parse($issue->date)->format('d/m/Y') }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold
                                                {{ $issue->issue == 'Outlier' ? 'bg-red-100 text-red-600' : 'bg-yellow-100 text-yellow-600' }}">
                                                {{ $issue->issue }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right font-medium text-gray-700">
                                            <span class="issue-val-view" id="issue-val-{{ $issue->id }}">
                                                Rp {{ number_format($issue->value, 0, ',', '.') }}
                                            </span>
                                            <input type="number" id="issue-price-input-{{ $issue->id }}"
                                                   class="issue-price-input hidden"
                                                   value="{{ $issue->value }}" min="1" step="1">
                                        </td>
                                        <td class="px-4 py-3 text-gray-500">{{ $issue->status }}</td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center justify-center gap-2">
                                                <button type="button" id="issue-edit-btn-{{ $issue->id }}"
                                                        onclick="toggleIssueEdit({{ $issue->id }})"
                                                        class="text-blue-500 hover:text-blue-700 text-xs font-bold flex items-center gap-1 transition-colors">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <button type="button" id="issue-save-btn-{{ $issue->id }}"
                                                        onclick="saveIssuePrice({{ $issue->id }})"
                                                        class="hidden text-green-500 hover:text-green-700 text-xs font-bold flex items-center gap-1 transition-colors">
                                                    <i class="fas fa-check"></i> Simpan
                                                </button>
                                                <button type="button" id="issue-cancel-btn-{{ $issue->id }}"
                                                        onclick="cancelIssueEdit({{ $issue->id }})"
                                                        class="hidden text-gray-400 hover:text-gray-600 text-xs font-bold transition-colors">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                                <form action="{{ route('operator.deleteData', $issue->id) }}" method="POST"
                                                      class="inline" id="issue-delete-form-{{ $issue->id }}">
                                                    @csrf @method('DELETE')
                                                    <button type="button"
                                                            onclick="confirmDeleteIssue({{ $issue->id }}, '{{ \Carbon\Carbon::parse($issue->date)->format('d/m/Y') }}', '{{ $issue->issue }}')"
                                                            class="text-red-400 hover:text-red-600 transition-colors text-xs font-bold flex items-center gap-1">
                                                        <i class="fas fa-trash"></i> Hapus
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="p-8 text-center">
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

                    @if(isset($dataIssues) && $dataIssues instanceof \Illuminate\Pagination\LengthAwarePaginator && $dataIssues->hasPages())
                        <div class="px-4 py-3 border-t border-orange-100 bg-orange-50/20 flex items-center justify-between">
                            <div class="text-xs text-orange-600">
                                {{ $dataIssues->firstItem() }}–{{ $dataIssues->lastItem() }} dari {{ $dataIssues->total() }} temuan
                            </div>
                            <div class="flex gap-1">
                                @if(!$dataIssues->onFirstPage())
                                    <a href="{{ $dataIssues->previousPageUrl() }}" class="pg-btn"><i class="fas fa-chevron-left"></i></a>
                                @endif
                                @if($dataIssues->hasMorePages())
                                    <a href="{{ $dataIssues->nextPageUrl() }}" class="pg-btn"><i class="fas fa-chevron-right"></i></a>
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
const SELECTED_COMMODITY = '{{ addslashes($selectedCommodity ?? "") }}';
const MAPE_RATE = Math.min(0.50, Math.max(0.01, {{ ($mape ?? 5) / 100 }}));

const trans = {
    naik:         '{{ __("messages.naik") }}',
    turun:        '{{ __("messages.turun") }}',
    stabil:       '{{ __("messages.stabil") }}',
    proyeksi:     '{{ __("messages.proyeksi") }}',
    bulanan:      '{{ __("messages.bulanan") }}',
    tahunan:      '{{ __("messages.tahunan") }}',
    hargaAktual:  '{{ __("messages.harga_aktual") }}',
    hargaProyeksi:'{{ __("messages.harga_proyeksi") }}',
    rentangBawah: '{{ __("messages.rentang_bawah") }}',
    rentangAtas:  '{{ __("messages.rentang_atas") }}',
    tidakAdaData: '{{ __("messages.tidak_ada_data") }}',
    bulan:        '{{ __("messages.bulanan") }}',
};

const chartData = {
    monthly: {
        labels:   {!! json_encode($monthlyLabels   ?? []) !!},
        actual:   {!! json_encode($monthlyActual   ?? []) !!},
        forecast: {!! json_encode($monthlyForecast ?? []) !!},
        fitted:   {!! json_encode($monthlyFitted   ?? []) !!},
        lower:    {!! json_encode($monthlyLower    ?? []) !!},
        upper:    {!! json_encode($monthlyUpper    ?? []) !!}
    },
    yearly: {
        labels:   {!! json_encode($yearlyLabels   ?? []) !!},
        actual:   {!! json_encode($yearlyActual   ?? []) !!},
        forecast: {!! json_encode($yearlyForecast ?? []) !!},
        fitted:   {!! json_encode($yearlyFitted   ?? []) !!},
        lower:    {!! json_encode($yearlyLower    ?? []) !!},
        upper:    {!! json_encode($yearlyUpper    ?? []) !!}
    }
};

let parametersDirty    = false;
let currentPeriod      = 'monthly';
let mainChart          = null;
let insightCurrentPage = 1;
const INSIGHT_PER_PAGE = 10;

var _CSRF    = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';
var _UPDATA  = '{{ url("/operator/update-data") }}';
var _UPBOBOT = '{{ url("/operator/update-bobot") }}';

function isDark() { return document.documentElement.classList.contains('dark'); }

// ─────────────────────────────────────────────
// HYPERPARAMETER
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
function updateForecastMonths(val) {
    const months = parseInt(val);
    document.getElementById('hidden_forecast_months').value = months;
    document.getElementById('fm_display_text').textContent  = months + ' ' + trans.bulan;
    document.getElementById('preview_fm').textContent       = months;
    markParamDirty();
}
function markParamDirty() {
    parametersDirty = true;
    const indicator = document.getElementById('param-changed-indicator');
    if (indicator) indicator.classList.add('visible');
    const notice = document.getElementById('param-dirty-notice');
    if (notice) notice.classList.remove('hidden');
    const btn = document.getElementById('btn-update');
    if (btn) { btn.classList.remove('bg-blue-600','hover:bg-blue-700'); btn.classList.add('bg-orange-500','hover:bg-orange-600'); }
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
    if (btn) { btn.classList.add('bg-blue-600','hover:bg-blue-700'); btn.classList.remove('bg-orange-500','hover:bg-orange-600'); }
    const previewBox = document.getElementById('param-preview-box');
    if (previewBox) previewBox.classList.remove('param-dirty');
}
function triggerSubmit() {
    const cp     = document.getElementById('hidden_cp');
    const season = document.getElementById('hidden_season');
    const mode   = document.getElementById('hidden_mode');
    const yearly = document.getElementById('hidden_yearly');
    const fm     = document.getElementById('hidden_forecast_months');
    if (cp     && (!cp.value     || isNaN(parseFloat(cp.value))))     cp.value     = '0.05';
    if (season && (!season.value || isNaN(parseFloat(season.value)))) season.value = '1.0';
    if (mode   && !mode.value)                                        mode.value   = 'multiplicative';
    if (yearly && yearly.value !== 'true' && yearly.value !== 'false') yearly.value = 'false';
    if (fm     && (!fm.value || isNaN(parseInt(fm.value)) || parseInt(fm.value) < 1 || parseInt(fm.value) > 12)) fm.value = '12';
    if (parametersDirty) {
        document.getElementById('hidden_force_retrain').value = 'true';
    }
    // Set preview_only=true jika parameter diubah (akan trigger popup MAPE)
    document.getElementById('hidden_preview_only').value = parametersDirty ? 'true' : 'false';
    document.getElementById('hidden_confirm_save').value = 'false';
    const icon = document.getElementById('btn-refresh-icon');
    if (icon) icon.classList.add('fa-spin');
    const realContent = document.getElementById('real-content');
    if (realContent) realContent.classList.add('opacity-30');
    const overlay = document.getElementById('skeleton-overlay');
    if (overlay) { overlay.classList.remove('hidden'); overlay.style.opacity = '1'; }
    clearParamDirty();
    setTimeout(() => document.getElementById('mainForm')?.submit(), 100);
}
function triggerReset() {
    const defaults = { cp: 0.05, season: 1.0, mode: 'multiplicative', yearly: false, fm: 12 };
    document.getElementById('range_cp').value = defaults.cp;
    document.getElementById('hidden_cp').value = defaults.cp;
    document.getElementById('cp_display').textContent = defaults.cp.toFixed(3);
    document.getElementById('preview_cp').textContent = defaults.cp;
    document.getElementById('range_season').value = defaults.season;
    document.getElementById('hidden_season').value = defaults.season;
    document.getElementById('season_display').textContent = defaults.season.toFixed(2);
    document.getElementById('preview_season').textContent = defaults.season;
    document.getElementById('select_mode').value = defaults.mode;
    document.getElementById('hidden_mode').value = defaults.mode;
    document.getElementById('preview_mode').textContent = defaults.mode;
    document.getElementById('checkbox_yearly').checked = defaults.yearly;
    document.getElementById('hidden_yearly').value = 'false';
    document.getElementById('preview_yearly').textContent = 'false';
    document.getElementById('range_fm').value = defaults.fm;
    document.getElementById('hidden_forecast_months').value = defaults.fm;
    document.getElementById('fm_display_text').textContent = defaults.fm + ' ' + trans.bulan;
    document.getElementById('preview_fm').textContent = defaults.fm;
    document.getElementById('hidden_force_retrain').value = 'true';
    document.getElementById('hidden_preview_only').value  = 'false';
    document.getElementById('hidden_confirm_save').value  = 'false';
    const icon = document.getElementById('btn-refresh-icon');
    if (icon) icon.classList.add('fa-spin');
    const realContent = document.getElementById('real-content');
    if (realContent) realContent.classList.add('opacity-30');
    const overlay = document.getElementById('skeleton-overlay');
    if (overlay) { overlay.classList.remove('hidden'); overlay.style.opacity = '1'; }
    clearParamDirty();
    setTimeout(() => document.getElementById('mainForm')?.submit(), 100);
}
function handleCommodityChange(val) {
    const hidden = document.getElementById('hidden_komoditas');
    if (hidden) hidden.value = val;
    triggerSubmit();
}
function showFileName(input) {
    const display = document.getElementById('file-name-display');
    if (input.files && input.files[0]) { display.textContent = input.files[0].name; display.classList.add('text-blue-600'); }
}
function switchInputMode(mode) {
    const formSingle = document.getElementById('form-single');
    const formBulk   = document.getElementById('form-bulk');
    const btnSingle  = document.getElementById('btn-tab-single');
    const btnBulk    = document.getElementById('btn-tab-bulk');
    if (mode === 'single') {
        formSingle.style.display = 'block'; formBulk.style.display = 'none';
        btnSingle.className = 'text-blue-600 border-b-2 border-blue-600 text-xs uppercase tracking-wider pb-1 font-semibold';
        btnBulk.className   = 'text-gray-400 text-xs uppercase tracking-wider pb-1 font-semibold';
    } else {
        formSingle.style.display = 'none'; formBulk.style.display = 'block';
        btnSingle.className = 'text-gray-400 text-xs uppercase tracking-wider pb-1 font-semibold';
        btnBulk.className   = 'text-blue-600 border-b-2 border-blue-600 text-xs uppercase tracking-wider pb-1 font-semibold';
    }
}

// ─────────────────────────────────────────────
// FLASK STATUS CHECK
// ─────────────────────────────────────────────
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

// ─────────────────────────────────────────────
// CHART
// ─────────────────────────────────────────────
function initializeChart() {
    const canvas = document.getElementById('mainChart');
    if (!canvas) return;
    const ctx  = canvas.getContext('2d');
    const data = chartData[currentPeriod];
    const dark = isDark();
    if (!data.labels || data.labels.length === 0) {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = '#9ca3af'; ctx.font = '14px Inter, sans-serif'; ctx.textAlign = 'center';
        ctx.fillText(trans.tidakAdaData, canvas.width / 2, canvas.height / 2);
        return;
    }
    const gradientActual = ctx.createLinearGradient(0, 0, 0, 400);
    gradientActual.addColorStop(0, dark ? 'rgba(96,165,250,0.20)' : 'rgba(4,50,119,0.10)');
    gradientActual.addColorStop(1, 'rgba(4,50,119,0)');
    if (mainChart) mainChart.destroy();
    mainChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [
                { label: trans.rentangBawah, data: data.lower, backgroundColor: 'rgba(249,115,22,0.08)', borderColor: 'transparent', fill: '+1', pointRadius: 0, tension: 0.4, spanGaps: false, order: 5 },
                { label: trans.rentangAtas, data: data.upper, borderColor: 'transparent', fill: false, pointRadius: 0, tension: 0.4, spanGaps: false, order: 5 },
                { label: trans.hargaAktual, data: data.actual, borderColor: dark ? '#60a5fa' : '#043277', backgroundColor: gradientActual, borderWidth: 2.5, fill: true, tension: 0.4, pointRadius: 0, pointHoverRadius: 6, pointHoverBackgroundColor: dark ? '#60a5fa' : '#043277', pointHoverBorderColor: '#fff', pointHoverBorderWidth: 2, spanGaps: false, order: 2 },
                { label: trans.hargaProyeksi, data: data.fitted, borderColor: '#f97316', backgroundColor: 'transparent', borderWidth: 2, fill: false, tension: 0.4, pointRadius: 0, pointHoverRadius: 5, pointHoverBackgroundColor: '#f97316', pointHoverBorderColor: '#fff', pointHoverBorderWidth: 2, spanGaps: false, order: 3 },
                { label: trans.proyeksi + ' (' + trans.bulanan + ')', data: data.forecast, borderColor: '#f97316', backgroundColor: 'transparent', borderWidth: 2, borderDash: [6, 3], fill: false, tension: 0.4, pointRadius: 0, pointHoverRadius: 5, pointHoverBackgroundColor: '#f97316', pointHoverBorderColor: '#fff', pointHoverBorderWidth: 2, spanGaps: false, order: 3 }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            plugins: {
                title: { display: true, text: SELECTED_COMMODITY, color: dark ? '#93c5fd' : '#043277', font: { size: 14, weight: '600', family: 'Inter' }, padding: { top: 10, bottom: 15 } },
                legend: { display: true, position: 'top', align: 'end', labels: { boxWidth: 12, boxHeight: 12, padding: 15, font: { size: 11, weight: '600' }, color: dark ? '#9ca3af' : '#64748b', usePointStyle: true, pointStyle: 'circle', filter: (item) => item.text !== trans.rentangBawah && item.text !== trans.rentangAtas } },
                tooltip: { backgroundColor: dark ? '#1e2433' : '#ffffff', titleColor: dark ? '#f3f4f6' : '#1e293b', bodyColor: dark ? '#9ca3af' : '#475569', borderColor: dark ? '#374151' : '#e2e8f0', borderWidth: 1, padding: 12, boxPadding: 6, usePointStyle: true, titleFont: { size: 11, weight: '600' }, bodyFont: { size: 11 },
                    callbacks: { label: function(context) {
                        if (context.dataset.label === trans.rentangBawah || context.dataset.label === trans.rentangAtas) return null;
                        let label = context.dataset.label || '';
                        if (label) label += ': ';
                        if (context.parsed.y !== null) label += new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(context.parsed.y);
                        return label;
                    }}
                }
            },
            scales: {
                y: { beginAtZero: false, grid: { color: dark ? 'rgba(255,255,255,0.05)' : '#f1f5f9', drawBorder: false }, ticks: { color: dark ? '#6b7280' : '#94a3b8', font: { size: 10, weight: '500' }, padding: 8, callback: value => 'Rp ' + value.toLocaleString('id-ID') } },
                x: { grid: { display: false }, ticks: { color: dark ? '#6b7280' : '#94a3b8', font: { size: 9, weight: '500' }, maxRotation: 45, minRotation: 0, autoSkip: true, maxTicksLimit: 15 } }
            }
        }
    });
}
function changeChartPeriod(period) {
    currentPeriod = period; insightCurrentPage = 1;
    document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
    document.getElementById('btn-' + period).classList.add('active');
    const periodText = { monthly: trans.bulanan, yearly: trans.tahunan };
    const el = document.getElementById('selectedPeriodText');
    if (el) el.textContent = periodText[period] || '';
    initializeChart();
    updateInsightTable(1);
}

// ─────────────────────────────────────────────
// INSIGHT TABLE
// ─────────────────────────────────────────────
function updateInsightTable(page) {
    page = page || 1; insightCurrentPage = page;
    const data  = chartData[currentPeriod];
    const tbody = document.getElementById('insightTableBody');
    if (!tbody) return;
    tbody.innerHTML = '';
    if (!data.labels || data.labels.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="p-8 text-center text-gray-400 text-xs">${trans.tidakAdaData}</td></tr>`;
        renderInsightPagination(1, 1, 0, 0, 0); return;
    }
    const actualRows = [], forecastRows = [];
    for (let i = 0; i < data.labels.length; i++) {
        const row = { label: data.labels[i], actual: data.actual[i] !== undefined ? data.actual[i] : null, forecast: data.forecast[i] !== undefined ? data.forecast[i] : null, fitted: (data.fitted && data.fitted[i] !== undefined && data.fitted[i] !== null) ? data.fitted[i] : null, lower: data.lower[i] !== undefined ? data.lower[i] : null, upper: data.upper[i] !== undefined ? data.upper[i] : null };
        if (row.actual !== null) actualRows.push(row); else if (row.forecast !== null) forecastRows.push(row);
    }
    const allRows = actualRows.concat(forecastRows), totalRows = allRows.length;
    const totalPages = Math.max(1, Math.ceil(totalRows / INSIGHT_PER_PAGE));
    const safePage = Math.min(Math.max(1, page), totalPages);
    const startIdx = (safePage - 1) * INSIGHT_PER_PAGE, endIdx = Math.min(startIdx + INSIGHT_PER_PAGE, totalRows);
    const display = allRows.slice(startIdx, endIdx);
    const lastActual = actualRows.length > 0 ? actualRows[actualRows.length - 1] : null;
    const actualCount = actualRows.length;
    if (display.length === 0) { tbody.innerHTML = `<tr><td colspan="7" class="p-8 text-center text-gray-400 text-xs">${trans.tidakAdaData}</td></tr>`; renderInsightPagination(1, 1, 0, 0, 0); return; }
    var html = '';
    for (var idx = 0; idx < display.length; idx++) {
        var row = display[idx], globalIdx = startIdx + idx;
        var isForecastOnly = (row.actual === null && row.forecast !== null);
        var displayForecast = isForecastOnly ? row.forecast : row.fitted;
        var displayLower = row.lower, displayUpper = row.upper;
        if (!isForecastOnly && displayForecast !== null) {
            if (displayLower === null || displayLower === undefined) displayLower = Math.round(displayForecast * (1 - MAPE_RATE));
            if (displayUpper === null || displayUpper === undefined) displayUpper = Math.round(displayForecast * (1 + MAPE_RATE));
        }
        var prevRow = globalIdx > 0 ? allRows[globalIdx - 1] : null;
        var hargaSekarang = isForecastOnly ? row.forecast : (row.actual !== null ? row.actual : null);
        var hargaSebelumnya = null;
        if (prevRow) { hargaSebelumnya = (prevRow.actual !== null) ? prevRow.actual : (prevRow.forecast !== null ? prevRow.forecast : null); }
        var mtmPct = null;
        if (hargaSekarang !== null && hargaSebelumnya !== null && hargaSebelumnya !== 0) {
            mtmPct = ((hargaSekarang - hargaSebelumnya) / hargaSebelumnya) * 100;
        }
        var insight = '—', insightClass = 'insight-stabil', mtmText = '—';
        if (mtmPct !== null) {
            var mtmAbs = Math.abs(mtmPct).toFixed(1);
            if (mtmPct > 0)      { insight = 'INFLASI +' + mtmAbs + '%'; insightClass = 'insight-naik';   mtmText = '+' + mtmAbs + '%'; }
            else if (mtmPct < 0) { insight = 'DEFLASI -' + mtmAbs + '%'; insightClass = 'insight-turun';  mtmText = '-' + mtmAbs + '%'; }
            else                 { insight = 'STABIL 0.0%';               insightClass = 'insight-stabil'; mtmText = '0.0%'; }
        } else if (isForecastOnly) { insight = trans.proyeksi; insightClass = 'insight-stabil'; }
        var rowBg = isForecastOnly ? 'bg-orange-50/30' : (displayForecast !== null ? 'row-has-fitted' : '');
        var borderTop = (globalIdx === actualCount && forecastRows.length > 0) ? 'border-t-2 border-orange-200' : '';
        var periodBadge = '';
        if (isForecastOnly) periodBadge = `<span class="ml-1 text-[9px] bg-orange-100 text-orange-600 px-1.5 py-0.5 rounded font-bold uppercase">${trans.proyeksi}</span>`;
        else if (displayForecast !== null) periodBadge = `<span class="fitted-badge">FIT</span>`;
        var actualCell   = row.actual !== null ? 'Rp ' + Math.round(row.actual).toLocaleString('id-ID') : '<span class="text-gray-300">—</span>';
        var forecastCell = displayForecast !== null ? 'Rp ' + Math.round(displayForecast).toLocaleString('id-ID') : '<span class="text-gray-300">—</span>';
        var lowerCell    = displayLower !== null ? 'Rp ' + Math.round(displayLower).toLocaleString('id-ID') : '<span class="text-gray-300">—</span>';
        var upperCell    = displayUpper !== null ? 'Rp ' + Math.round(displayUpper).toLocaleString('id-ID') : '<span class="text-gray-300">—</span>';
        html += `<tr class="${rowBg} ${borderTop} border-b border-gray-50 hover:bg-gray-50/80 animate-fade-in"><td class="px-6 py-4 text-gray-500 font-medium text-xs">${row.label}${periodBadge}</td><td class="px-6 py-4 text-right text-xs font-medium">${actualCell}</td><td class="px-6 py-4 text-right text-blue-600 font-bold text-xs">${forecastCell}</td><td class="px-6 py-4 text-right text-xs text-gray-400">${lowerCell}</td><td class="px-6 py-4 text-right text-xs text-gray-400">${upperCell}</td><td class="px-6 py-4 text-right text-xs font-medium ${mtmPct !== null ? (mtmPct > 0 ? 'text-red-600' : mtmPct < 0 ? 'text-emerald-600' : 'text-gray-500') : 'text-gray-300'}">${mtmText}</td><td class="px-6 py-4 text-center"><span class="insight-badge ${insightClass}">${insight}</span></td></tr>`;
    }
    tbody.innerHTML = html;
    renderInsightPagination(safePage, totalPages, totalRows, startIdx + 1, endIdx);
}
function renderInsightPagination(currentPage, totalPages, totalRows, fromRow, toRow) {
    var container = document.getElementById('insightPagination');
    if (!container) return;
    if (totalPages <= 1) { container.innerHTML = ''; return; }
    var btnBase = 'pg-btn', btnActive = 'pg-btn pg-btn-active', btnDisabled = 'pg-btn pg-btn-disabled';
    var prev = currentPage > 1 ? `<button onclick="updateInsightTable(${currentPage - 1})" class="${btnBase}"><i class="fas fa-chevron-left"></i></button>` : `<span class="${btnDisabled}"><i class="fas fa-chevron-left"></i></span>`;
    var next = currentPage < totalPages ? `<button onclick="updateInsightTable(${currentPage + 1})" class="${btnBase}"><i class="fas fa-chevron-right"></i></button>` : `<span class="${btnDisabled}"><i class="fas fa-chevron-right"></i></span>`;
    var delta = 2, start = Math.max(1, currentPage - delta), end = Math.min(totalPages, currentPage + delta), pages = '';
    if (start > 1) { pages += `<button onclick="updateInsightTable(1)" class="${btnBase}">1</button>`; if (start > 2) pages += '<span class="px-1 text-gray-400 text-xs">…</span>'; }
    for (var p = start; p <= end; p++) pages += `<button onclick="updateInsightTable(${p})" class="${p === currentPage ? btnActive : btnBase}">${p}</button>`;
    if (end < totalPages) { if (end < totalPages - 1) pages += '<span class="px-1 text-gray-400 text-xs">…</span>'; pages += `<button onclick="updateInsightTable(${totalPages})" class="${btnBase}">${totalPages}</button>`; }
    container.innerHTML = `<div class="flex items-center justify-between px-6 py-3 border-t border-gray-100 bg-gray-50/30"><span class="text-xs text-gray-500">Menampilkan ${fromRow}–${toRow} dari ${totalRows} data</span><div class="flex items-center gap-1">${prev}${pages}${next}</div></div>`;
}

document.addEventListener('DOMContentLoaded', function () {
    const currentTab = '{{ $currentTab ?? "insight" }}';
    if (currentTab === 'insight') { changeChartPeriod('monthly'); checkFlaskStatus(); setInterval(checkFlaskStatus, 30000); }

    // Auto-scroll ke flash message global
    var flash = document.getElementById('flash-message');
    if (flash) {
        setTimeout(function () {
            flash.scrollIntoView({ behavior: 'smooth', block: 'center' });
            flash.style.transition = 'box-shadow 0.3s';
            flash.style.boxShadow = '0 0 0 3px rgba(37,99,235,0.3)';
            setTimeout(function () { flash.style.boxShadow = ''; }, 1500);
        }, 200);
    }

    // Form single — konfirmasi sebelum submit
    var fSingle = document.getElementById('form-single');
    if (fSingle) {
        fSingle.addEventListener('submit', function (e) {
            e.preventDefault();
            var kEl = fSingle.querySelector('[name="komoditas_id"]');
            var dEl = fSingle.querySelector('[name="date"]');
            var pEl = fSingle.querySelector('[name="price"]');
            if (!kEl.value) { showFormFlash('flash-add-single', 'Pilih komoditas terlebih dahulu!', 'error'); return; }
            if (!dEl.value) { showFormFlash('flash-add-single', 'Tanggal wajib diisi!', 'error'); return; }
            if (!pEl.value || parseFloat(pEl.value) <= 0) { showFormFlash('flash-add-single', 'Harga harus lebih dari 0!', 'error'); return; }
            var kText = kEl.options[kEl.selectedIndex]?.text || '—';
            var dp = dEl.value.split('-');
            Swal.fire({
                icon: 'question', title: 'Simpan Data Harga?',
                html: '<div class="text-left text-sm space-y-1.5 mt-2">' +
                    '<div class="flex gap-2"><span class="text-gray-400 w-24">Komoditas</span><strong>: ' + kText + '</strong></div>' +
                    '<div class="flex gap-2"><span class="text-gray-400 w-24">Tanggal</span><strong>: ' + dp[2]+'/'+dp[1]+'/'+dp[0] + '</strong></div>' +
                    '<div class="flex gap-2"><span class="text-gray-400 w-24">Harga</span><strong class="text-emerald-600">: Rp ' + parseInt(pEl.value).toLocaleString('id-ID') + '</strong></div></div>',
                showCancelButton: true, confirmButtonColor: '#10b981', cancelButtonColor: '#9ca3af',
                confirmButtonText: '<i class="fas fa-save mr-1"></i> Ya, Simpan!', cancelButtonText: 'Batal', reverseButtons: true
            }).then(function (r) { if (r.isConfirmed) fSingle.submit(); });
        });
    }

    var fBulk = document.getElementById('form-bulk');
    if (fBulk) {
        fBulk.addEventListener('submit', function (e) {
            e.preventDefault();
            var fi = fBulk.querySelector('[name="csv_file"]');
            if (!fi || !fi.files[0]) { showFormFlash('flash-add-bulk', 'Pilih file CSV terlebih dahulu!', 'error'); return; }
            var fname = fi.files[0].name;
            var fsize = (fi.files[0].size / 1024).toFixed(1) + ' KB';
            Swal.fire({
                icon: 'question', title: 'Unggah Dataset CSV?',
                html: '<div class="text-center"><p class="font-semibold text-gray-700">' + fname + '</p><p class="text-xs text-gray-400 mt-1">Ukuran: ' + fsize + '</p><p class="text-xs text-blue-500 mt-2">Data akan ditambahkan ke database</p></div>',
                showCancelButton: true, confirmButtonColor: '#2563eb', cancelButtonColor: '#9ca3af',
                confirmButtonText: '<i class="fas fa-upload mr-1"></i> Ya, Unggah!', cancelButtonText: 'Batal', reverseButtons: true
            }).then(function (r) { if (r.isConfirmed) fBulk.submit(); });
        });
    }

    var fBobot = document.getElementById('form-bobot');
    if (fBobot) {
        fBobot.addEventListener('submit', function (e) {
            e.preventDefault();
            var kEl = fBobot.querySelector('[name="komoditas_id"]');
            var tEl = fBobot.querySelector('[name="tanggal"]');
            var nEl = fBobot.querySelector('[name="nilai_bobot"]');
            if (!kEl.value) { showFormFlash('flash-bobot', 'Pilih komoditas!', 'error'); return; }
            if (!tEl.value) { showFormFlash('flash-bobot', 'Tanggal wajib diisi!', 'error'); return; }
            if (nEl.value === '' || parseFloat(nEl.value) < 0) { showFormFlash('flash-bobot', 'Nilai bobot tidak valid!', 'error'); return; }
            var kText = kEl.options[kEl.selectedIndex]?.text || '—';
            var dp = tEl.value.split('-');
            Swal.fire({
                icon: 'question', title: 'Simpan Bobot?',
                html: '<div class="text-left text-sm space-y-1.5 mt-2">' +
                    '<div class="flex gap-2"><span class="text-gray-400 w-28">Komoditas</span><strong>: ' + kText + '</strong></div>' +
                    '<div class="flex gap-2"><span class="text-gray-400 w-28">Tanggal</span><strong>: ' + dp[2]+'/'+dp[1]+'/'+dp[0] + '</strong></div>' +
                    '<div class="flex gap-2"><span class="text-gray-400 w-28">Nilai Bobot</span><strong class="text-indigo-600">: ' + parseFloat(nEl.value).toFixed(4) + '</strong></div></div>',
                showCancelButton: true, confirmButtonColor: '#6366f1', cancelButtonColor: '#9ca3af',
                confirmButtonText: '<i class="fas fa-save mr-1"></i> Ya, Simpan!', cancelButtonText: 'Batal', reverseButtons: true
            }).then(function (r) { if (r.isConfirmed) fBobot.submit(); });
        });
    }
});

const _obs = new MutationObserver(() => { if (mainChart) initializeChart(); });
_obs.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

// ─────────────────────────────────────────────
// EDIT MODE DATA HARGA
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
    if (deleteForm) { deleteForm.style.opacity = isEditing ? '0.3' : '1'; deleteForm.style.pointerEvents = isEditing ? 'none' : 'auto'; }
    if (isEditing) row.classList.add('bg-blue-50', 'border-l-4', 'border-l-blue-500');
    else           row.classList.remove('bg-blue-50', 'border-l-4', 'border-l-blue-500');
}
function autoSaveData(id) {
    var row = document.getElementById('row-' + id);
    var komoditasId = row.querySelector('.commodity-edit').value;
    var date  = row.querySelector('.date-edit').value;
    var price = row.querySelector('.price-edit').value;
    if (!komoditasId || !date || !price) { showNotification('Semua field harus diisi!', 'error'); return; }
    if (parseFloat(price) <= 0) { showNotification('Harga harus lebih dari 0!', 'error'); return; }
    row.style.backgroundColor = '#fef3c7';
    fetch(_UPDATA + '/' + id, { method: 'PUT', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': _CSRF }, body: JSON.stringify({ komoditas_id: komoditasId, date: date, price: price }) })
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
        } else { showNotification('Gagal: ' + (data.message || 'Terjadi kesalahan'), 'error'); row.style.backgroundColor = ''; }
    })
    .catch(function() { showNotification('Terjadi kesalahan jaringan', 'error'); row.style.backgroundColor = ''; });
}

// ─────────────────────────────────────────────
// EDIT MODE BOBOT
// ─────────────────────────────────────────────
function toggleBobotEdit(id) {
    var row = document.getElementById('bobot-row-' + id);
    if (!row) return;
    var isEditing = row.querySelector('.bobot-komoditas-edit').classList.contains('hidden');
    row.querySelector('.bobot-komoditas-view').classList.toggle('hidden', isEditing);
    row.querySelector('.bobot-komoditas-edit').classList.toggle('hidden', !isEditing);
    row.querySelector('.bobot-tanggal-view').classList.toggle('hidden', isEditing);
    row.querySelector('.bobot-tanggal-edit').classList.toggle('hidden', !isEditing);
    row.querySelector('.bobot-nilai-view').classList.toggle('hidden', isEditing);
    row.querySelector('.bobot-nilai-edit').classList.toggle('hidden', !isEditing);
    row.querySelector('.bobot-edit-btn').classList.toggle('hidden', isEditing);
    row.querySelector('.bobot-done-btn').classList.toggle('hidden', !isEditing);
    var deleteForm = row.querySelector('.bobot-delete-form');
    if (deleteForm) { deleteForm.style.opacity = isEditing ? '0.3' : '1'; deleteForm.style.pointerEvents = isEditing ? 'none' : 'auto'; }
    if (isEditing) row.classList.add('bg-indigo-50', 'border-l-4', 'border-l-indigo-500');
    else           row.classList.remove('bg-indigo-50', 'border-l-4', 'border-l-indigo-500');
}
function autoSaveBobot(id) {
    var row = document.getElementById('bobot-row-' + id);
    var komoditasId = row.querySelector('.bobot-komoditas-edit').value;
    var tanggal     = row.querySelector('.bobot-tanggal-edit').value;
    var nilaiBobot  = row.querySelector('.bobot-nilai-edit').value;
    if (!komoditasId || !tanggal || nilaiBobot === '') { showNotification('Semua field harus diisi!', 'error'); return; }
    if (parseFloat(nilaiBobot) < 0) { showNotification('Nilai bobot tidak boleh negatif!', 'error'); return; }
    row.style.backgroundColor = '#ede9fe';
    fetch(_UPBOBOT + '/' + id, { method: 'PUT', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': _CSRF }, body: JSON.stringify({ komoditas_id: komoditasId, tanggal: tanggal, nilai_bobot: nilaiBobot }) })
    .then(function(res) { return res.json(); })
    .then(function(data) {
        if (data.success) {
            var selectedOption = row.querySelector('.bobot-komoditas-edit option:checked');
            row.querySelector('.bobot-komoditas-view').textContent = selectedOption ? selectedOption.text : komoditasId;
            var parts = tanggal.split('-');
            row.querySelector('.bobot-tanggal-view').textContent = parts[2] + '/' + parts[1] + '/' + parts[0];
            row.querySelector('.bobot-nilai-view').textContent   = parseFloat(nilaiBobot).toFixed(4);
            row.style.backgroundColor = '#d1fae5';
            setTimeout(function() { row.style.backgroundColor = ''; }, 800);
            showNotification('Bobot berhasil disimpan!', 'success');
        } else { showNotification('Gagal: ' + (data.message || 'Terjadi kesalahan'), 'error'); row.style.backgroundColor = ''; }
    })
    .catch(function() { showNotification('Terjadi kesalahan jaringan', 'error'); row.style.backgroundColor = ''; });
}

// ─────────────────────────────────────────────
// TOAST NOTIFICATION
// ─────────────────────────────────────────────
function showNotification(message, type) {
    type = type || 'success';
    var existing = document.querySelector('.toast-notification');
    if (existing) existing.remove();
    var notification = document.createElement('div');
    notification.className = 'toast-notification fixed top-4 right-4 z-50 px-6 py-4 rounded-lg shadow-lg text-white text-sm font-medium animate-fade-in ' + (type === 'success' ? 'bg-green-500' : 'bg-red-500');
    notification.innerHTML = '<div class="flex items-center gap-3"><i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i><span>' + message + '</span></div>';
    document.body.appendChild(notification);
    setTimeout(function() {
        notification.style.opacity = '0'; notification.style.transform = 'translateX(100%)'; notification.style.transition = 'all 0.3s ease';
        setTimeout(function() { notification.remove(); }, 300);
    }, 3000);
}

// ─────────────────────────────────────────────
// PER-FORM FLASH
// ─────────────────────────────────────────────
function showFormFlash(id, msg, type) {
    type = type || 'success';
    var el = document.getElementById(id);
    if (!el) { showNotification(msg, type); return; }
    el.className = 'form-flash show ' + type;
    el.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i><span>' + msg + '</span>';
    clearTimeout(el._tmr);
    el._tmr = setTimeout(function () {
        el.style.transition = 'opacity 0.4s'; el.style.opacity = '0';
        setTimeout(function () { el.className = 'form-flash'; el.style.opacity = ''; }, 400);
    }, 5000);
}

// ─────────────────────────────────────────────
// RESTORE FLASH DARI SESSION + SCROLL KE ANCHOR
// ─────────────────────────────────────────────
(function () {
    var params  = new URLSearchParams(window.location.search);
    var anchor  = params.get('anchor') || '';
    var success = @json(session('success'));
    var error   = @json(session('error'));
    var map = {
        'section-tambah-data': ['flash-add-single', 'flash-add-bulk'],
        'section-bobot':       ['flash-bobot'],
        'section-outlier':     ['flash-outlier'],
    };
    document.addEventListener('DOMContentLoaded', function () {
        if (anchor && (success || error)) {
            var ids = map[anchor] || [];
            ids.forEach(function (id) { showFormFlash(id, success || error, success ? 'success' : 'error'); });
        }
        if (anchor) {
            var el = document.getElementById(anchor);
            if (el) setTimeout(function () { el.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 300);
        }
    });
})();

// ─────────────────────────────────────────────
// SWEETALERT: HAPUS DATA HARGA
// ─────────────────────────────────────────────
function confirmDeleteData(btn, tanggal, harga) {
    var form = btn.closest('.delete-form');
    Swal.fire({
        icon: 'warning', title: 'Hapus Data Harga?',
        html: '<div class="text-left text-sm space-y-1 mt-2">' +
            '<div class="flex gap-2"><span class="text-gray-400 w-20">Tanggal</span><strong>: ' + tanggal + '</strong></div>' +
            '<div class="flex gap-2"><span class="text-gray-400 w-20">Harga</span><strong class="text-red-500">: ' + harga + '</strong></div>' +
            '<p class="text-red-500 text-xs mt-2">Tindakan ini tidak dapat dibatalkan!</p></div>',
        showCancelButton: true, confirmButtonColor: '#ef4444', cancelButtonColor: '#9ca3af',
        confirmButtonText: '<i class="fas fa-trash mr-1"></i> Ya, Hapus!', cancelButtonText: 'Batal', reverseButtons: true
    }).then(function (r) { if (r.isConfirmed) form.submit(); });
}

// ─────────────────────────────────────────────
// SWEETALERT: HAPUS BOBOT
// ─────────────────────────────────────────────
function confirmDeleteBobot(btn, komoditas, tanggal, nilai) {
    var form = btn.closest('.bobot-delete-form');
    Swal.fire({
        icon: 'warning', title: 'Hapus Data Bobot?',
        html: '<div class="text-left text-sm space-y-1 mt-2">' +
            '<div class="flex gap-2"><span class="text-gray-400 w-28">Komoditas</span><strong>: ' + komoditas + '</strong></div>' +
            '<div class="flex gap-2"><span class="text-gray-400 w-28">Tanggal</span><strong>: ' + tanggal + '</strong></div>' +
            '<div class="flex gap-2"><span class="text-gray-400 w-28">Nilai Bobot</span><strong class="text-indigo-600">: ' + nilai + '</strong></div>' +
            '<p class="text-red-500 text-xs mt-2">Tindakan ini tidak dapat dibatalkan!</p></div>',
        showCancelButton: true, confirmButtonColor: '#ef4444', cancelButtonColor: '#9ca3af',
        confirmButtonText: '<i class="fas fa-trash mr-1"></i> Ya, Hapus!', cancelButtonText: 'Batal', reverseButtons: true
    }).then(function (r) { if (r.isConfirmed) form.submit(); });
}

// ─────────────────────────────────────────────
// SWEETALERT: CLEAN DATA
// ─────────────────────────────────────────────
function confirmCleanAction(action, btn) {
    var fClean = document.getElementById('form-clean');
    var isOut  = (action === 'outlier');
    var selEl  = fClean.querySelector('[name="' + (isOut ? 'outlier_method' : 'missing_method') + '"]');
    var mText  = selEl ? selEl.options[selEl.selectedIndex].text : '—';
    Swal.fire({
        icon: 'warning',
        title: isOut ? 'Terapkan Deteksi Outlier?' : 'Tangani Nilai Hilang?',
        html: '<div class="text-center"><p class="text-gray-600">Metode: <strong>' + mText + '</strong></p><p class="text-orange-500 text-xs mt-2"><i class="fas fa-exclamation-triangle mr-1"></i>Tindakan ini mengubah data di database!</p></div>',
        showCancelButton: true,
        confirmButtonColor: isOut ? '#f97316' : '#2563eb', cancelButtonColor: '#9ca3af',
        confirmButtonText: '<i class="fas fa-check mr-1"></i> Ya, Terapkan!',
        cancelButtonText: 'Batal', reverseButtons: true
    }).then(function (r) {
        if (r.isConfirmed) {
            var inp = fClean.querySelector('[name="action"]');
            if (!inp) { inp = document.createElement('input'); inp.type = 'hidden'; inp.name = 'action'; fClean.appendChild(inp); }
            inp.value = action;
            fClean.submit();
        }
    });
}

// ─────────────────────────────────────────────
// INLINE EDIT TABEL PINDAI (ISSUE)
// ─────────────────────────────────────────────
function toggleIssueEdit(id) {
    var valEl     = document.getElementById('issue-val-' + id);
    var inputEl   = document.getElementById('issue-price-input-' + id);
    var editBtn   = document.getElementById('issue-edit-btn-' + id);
    var saveBtn   = document.getElementById('issue-save-btn-' + id);
    var cancelBtn = document.getElementById('issue-cancel-btn-' + id);
    if (!inputEl) return;
    if (valEl)    valEl.classList.add('hidden');
    inputEl.classList.remove('hidden');
    if (editBtn)   editBtn.classList.add('hidden');
    if (saveBtn)   saveBtn.classList.remove('hidden');
    if (cancelBtn) cancelBtn.classList.remove('hidden');
    setTimeout(function () { inputEl.focus(); inputEl.select(); }, 50);
}
function cancelIssueEdit(id) {
    var valEl     = document.getElementById('issue-val-' + id);
    var inputEl   = document.getElementById('issue-price-input-' + id);
    var editBtn   = document.getElementById('issue-edit-btn-' + id);
    var saveBtn   = document.getElementById('issue-save-btn-' + id);
    var cancelBtn = document.getElementById('issue-cancel-btn-' + id);
    if (valEl)    valEl.classList.remove('hidden');
    if (inputEl)  inputEl.classList.add('hidden');
    if (editBtn)  editBtn.classList.remove('hidden');
    if (saveBtn)  saveBtn.classList.add('hidden');
    if (cancelBtn)cancelBtn.classList.add('hidden');
}
function saveIssuePrice(id) {
    var inputEl = document.getElementById('issue-price-input-' + id);
    if (!inputEl) return;
    var newPrice = inputEl.value;
    if (!newPrice || parseFloat(newPrice) <= 0) {
        Swal.fire({ icon: 'error', title: 'Harga Tidak Valid', text: 'Masukkan harga lebih dari 0', confirmButtonColor: '#ef4444' });
        return;
    }
    Swal.fire({
        icon: 'question', title: 'Simpan Perubahan Harga?',
        html: '<p class="text-gray-600">Harga baru: <strong class="text-emerald-600">Rp ' + parseInt(newPrice).toLocaleString('id-ID') + '</strong></p>',
        showCancelButton: true, confirmButtonColor: '#10b981', cancelButtonColor: '#9ca3af',
        confirmButtonText: '<i class="fas fa-save mr-1"></i> Simpan', cancelButtonText: 'Batal'
    }).then(function (r) {
        if (!r.isConfirmed) return;
        fetch(_UPDATA + '/' + id, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': _CSRF },
            body: JSON.stringify({ price: newPrice })
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.success) {
                var valEl = document.getElementById('issue-val-' + id);
                if (valEl) valEl.textContent = 'Rp ' + parseInt(newPrice).toLocaleString('id-ID');
                cancelIssueEdit(id);
                var row = document.getElementById('issue-row-' + id);
                if (row) { row.style.backgroundColor = '#d1fae5'; setTimeout(function () { row.style.backgroundColor = ''; }, 1000); }
                showFormFlash('flash-outlier', 'Harga berhasil diperbarui!', 'success');
            } else { showFormFlash('flash-outlier', 'Gagal: ' + (data.message || 'Terjadi kesalahan'), 'error'); }
        })
        .catch(function () { showFormFlash('flash-outlier', 'Kesalahan jaringan!', 'error'); });
    });
}

// ─────────────────────────────────────────────
// SWEETALERT: HAPUS ISSUE
// ─────────────────────────────────────────────
function confirmDeleteIssue(id, tanggal, jenis) {
    Swal.fire({
        icon: 'warning', title: 'Hapus Data ' + jenis + '?',
        html: '<div class="text-center"><p class="text-gray-600">Tanggal: <strong>' + tanggal + '</strong></p><p class="text-red-500 text-xs mt-2">Data akan dihapus permanen dari database!</p></div>',
        showCancelButton: true, confirmButtonColor: '#ef4444', cancelButtonColor: '#9ca3af',
        confirmButtonText: '<i class="fas fa-trash mr-1"></i> Ya, Hapus!', cancelButtonText: 'Batal', reverseButtons: true
    }).then(function (r) {
        if (r.isConfirmed) document.getElementById('issue-delete-form-' + id).submit();
    });
}

// ─────────────────────────────────────────────
// POPUP PERBANDINGAN MAPE — identik dengan admin
// ─────────────────────────────────────────────
(function() {
    const showPopup  = {{ $showSavePopup ?? false ? 'true' : 'false' }};
    const mapeBefore = {{ $mapeBefore ?? 0 }};
    const mapeAfter  = {{ $mapeAfter  ?? 0 }};
    const improved   = mapeAfter < mapeBefore;
    const noBefore   = mapeBefore === 0;

    if (!showPopup) return;

    const diffAbs = Math.abs(mapeBefore - mapeAfter).toFixed(2);
    const arrow   = improved ? '↓' : '↑';
    const icon    = improved ? 'success' : 'warning';

    let titleText, bodyHtml, confirmText, denyText, cancelText;

    if (noBefore) {
        titleText   = 'Parameter Baru Diterapkan';
        bodyHtml    = `<div class="text-center">
            <p class="text-gray-500 text-sm mb-3">Belum ada data MAPE sebelumnya untuk komoditas ini.</p>
            <div class="bg-blue-50 rounded-lg p-3 inline-block">
                <p class="text-xs text-gray-500">MAPE Sekarang</p>
                <p class="text-2xl font-bold text-blue-600">${mapeAfter.toFixed(2)}%</p>
            </div>
            <p class="text-xs text-gray-400 mt-3">Simpan parameter ini sebagai preferensi komoditas?</p>
        </div>`;
        confirmText = 'Simpan';
        denyText    = null;
        cancelText  = 'Tidak';
    } else if (improved) {
        titleText   = 'Akurasi Meningkat!';
        bodyHtml    = `<div class="text-center">
            <div class="flex justify-center gap-6 mb-4">
                <div class="bg-gray-50 rounded-lg p-3">
                    <p class="text-xs text-gray-500">MAPE Sebelumnya</p>
                    <p class="text-xl font-bold text-gray-600">${mapeBefore.toFixed(2)}%</p>
                </div>
                <div class="flex items-center text-green-500 text-2xl font-bold">${arrow}</div>
                <div class="bg-green-50 rounded-lg p-3">
                    <p class="text-xs text-gray-500">MAPE Sekarang</p>
                    <p class="text-xl font-bold text-green-600">${mapeAfter.toFixed(2)}%</p>
                </div>
            </div>
            <p class="text-sm text-green-600 font-semibold">Model lebih akurat sebesar ${diffAbs}%</p>
            <p class="text-xs text-gray-400 mt-2">Mau simpan parameter ini?</p>
        </div>`;
        confirmText = 'Simpan';
        denyText    = null;
        cancelText  = 'Tidak';
    } else {
        titleText   = 'Akurasi Menurun';
        bodyHtml    = `<div class="text-center">
            <div class="flex justify-center gap-6 mb-4">
                <div class="bg-gray-50 rounded-lg p-3">
                    <p class="text-xs text-gray-500">MAPE Sebelumnya</p>
                    <p class="text-xl font-bold text-gray-600">${mapeBefore.toFixed(2)}%</p>
                </div>
                <div class="flex items-center text-red-500 text-2xl font-bold">${arrow}</div>
                <div class="bg-red-50 rounded-lg p-3">
                    <p class="text-xs text-gray-500">MAPE Sekarang</p>
                    <p class="text-xl font-bold text-red-600">${mapeAfter.toFixed(2)}%</p>
                </div>
            </div>
            <p class="text-sm text-red-600 font-semibold">Model kurang akurat sebesar ${diffAbs}%</p>
            <p class="text-xs text-gray-400 mt-2">Parameter tetap disimpan atau kembali ke sebelumnya?</p>
        </div>`;
        confirmText = 'Simpan Tetap';
        denyText    = 'Kembali ke Default';
        cancelText  = 'Atur Ulang';
    }

    Swal.fire({
        icon:              icon,
        title:             titleText,
        html:              bodyHtml,
        showConfirmButton: true,
        showDenyButton:    denyText !== null,
        showCancelButton:  true,
        confirmButtonText: confirmText,
        denyButtonText:    denyText,
        cancelButtonText:  cancelText,
        confirmButtonColor: '#2563eb',
        denyButtonColor:    '#6b7280',
        cancelButtonColor:  '#9ca3af',
        reverseButtons:    false,
    }).then(function(result) {
        if (result.isConfirmed) {
            // Simpan permanen ke DB
            document.getElementById('hidden_preview_only').value  = 'false';
            document.getElementById('hidden_confirm_save').value  = 'true';
            document.getElementById('hidden_force_retrain').value = 'true';
            const icon2 = document.getElementById('btn-refresh-icon');
            if (icon2) icon2.classList.add('fa-spin');
            document.getElementById('real-content').classList.add('opacity-30');
            document.getElementById('mainForm').submit();
        } else if (result.isDenied) {
            // Kembali ke default — reset slider lalu submit
            triggerReset();
        }
        // Atur Ulang / Tidak — tidak submit, biarkan user ubah slider lagi
    });
})();
</script>
@endsection