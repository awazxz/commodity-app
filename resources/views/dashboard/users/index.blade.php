@extends('layouts.app')

@section('content')

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
        box-shadow: 0 1px 3px 0 rgba(0,0,0,0.3);
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
        cursor: pointer;
    }
    html.dark .filter-btn { background: #2d3748; border-color: #4a5568; color: #a0aec0; }
    .filter-btn.active { background: #2563eb; color: white; border-color: #2563eb; }
    html.dark .filter-btn.active { background: #2563eb; color: white; border-color: #2563eb; }
    .filter-btn:hover:not(.active) { background: #f8fafc; }
    html.dark .filter-btn:hover:not(.active) { background: #374151; }

    .insight-badge { padding: 0.25rem 0.625rem; border-radius: 9999px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
    .insight-naik   { background: #fee2e2; color: #991b1b; }
    .insight-turun  { background: #dcfce7; color: #166534; }
    .insight-stabil { background: #f3f4f6; color: #1f2937; }
    html.dark .insight-naik   { background: #7f1d1d; color: #fca5a5; }
    html.dark .insight-turun  { background: #14532d; color: #86efac; }
    html.dark .insight-stabil { background: #374151; color: #d1d5db; }

    .forecast-row { background: rgba(249,115,22,0.03); }
    html.dark .forecast-row { background: rgba(249,115,22,0.06); }

    .fitted-badge {
        display: inline-flex; align-items: center;
        background: #dbeafe; color: #1e40af;
        font-size: 9px; font-weight: 700;
        padding: 2px 6px; border-radius: 4px;
        text-transform: uppercase; letter-spacing: 0.05em;
        margin-left: 4px;
    }
    html.dark .fitted-badge { background: #1e3a5f; color: #93c5fd; }

    .row-has-fitted { background: rgba(59,130,246,0.04); }
    html.dark .row-has-fitted { background: rgba(59,130,246,0.08); }

    .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: #f8fafc; }
    html.dark .custom-scrollbar::-webkit-scrollbar-track { background: #1a202c; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    html.dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #4a5568; }

    html.dark select, html.dark input[type="date"] {
        background-color: #2d3748 !important;
        border-color: #4a5568 !important;
        color: #e2e8f0 !important;
    }

    .horizon-pill {
        display: inline-flex; align-items: center; gap: 3px;
        background: #eef2ff; color: #4338ca;
        font-size: 9px; font-weight: 700;
        padding: 2px 7px; border-radius: 9999px;
        text-transform: uppercase; letter-spacing: 0.04em;
    }
    html.dark .horizon-pill { background: #312e81; color: #a5b4fc; }

    @keyframes fadeIn { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
    .animate-fade-in { animation: fadeIn 0.4s ease-out; }

    /* ── Font size override — keterbacaan untuk user 30+ ── */
    .dashboard-container { font-size: 15px; color: #1a202c; }
    .dashboard-container .text-\[8px\]  { font-size: 12px !important; }
    .dashboard-container .text-\[9px\]  { font-size: 13px !important; }
    .dashboard-container .text-\[10px\] { font-size: 13px !important; }
    .dashboard-container .text-\[11px\] { font-size: 13px !important; }
    .dashboard-container .text-xs       { font-size: 14px !important; }
    .dashboard-container .text-sm       { font-size: 15px !important; }
    .dashboard-container .text-base     { font-size: 16px !important; }
    .dashboard-container .text-lg       { font-size: 18px !important; }
    .dashboard-container .text-xl       { font-size: 20px !important; }
    .dashboard-container input,
    .dashboard-container select,
    .dashboard-container textarea       { font-size: 15px !important; }
    .dashboard-container button         { font-size: 14px !important; }
    .dashboard-container th             { font-size: 13px !important; }
    .dashboard-container td             { font-size: 15px !important; }

    /* Badge & pill kecil — minimal tetap 12px */
    .dashboard-container .rounded-full  { font-size: 12px !important; }
    .dashboard-container .insight-badge { font-size: 12px !important; }
    .dashboard-container .horizon-pill  { font-size: 12px !important; }
    .dashboard-container .fitted-badge  { font-size: 11px !important; }

    /* ── Perbaikan kontras warna teks ── */
    .dashboard-container .text-gray-400 { color: #4b5563 !important; }
    .dashboard-container .text-gray-500 { color: #374151 !important; }
    .dashboard-container .text-gray-300 { color: #6b7280 !important; }

    html.dark .dashboard-container .text-gray-400 { color: #d1d5db !important; }
    html.dark .dashboard-container .text-gray-500 { color: #e5e7eb !important; }
    html.dark .dashboard-container .text-gray-300 { color: #9ca3af !important; }

    /* ── Zebra striping insight table — sama persis dengan admin ── */
    .dashboard-container table tbody tr:nth-child(odd)  { background: #ffffff; }
    .dashboard-container table tbody tr:nth-child(even) { background: #edf2f7; }
    html.dark .dashboard-container table tbody tr:nth-child(odd)  { background: #1e2433; }
    html.dark .dashboard-container table tbody tr:nth-child(even) { background: #161c2a; }
    .dashboard-container table tbody tr { border-bottom: none !important; transition: background .1s; }
    .dashboard-container table tbody td:first-child { min-width: 130px; }
    .dashboard-container table tbody tr:hover { background: #dbeafe !important; }
    html.dark .dashboard-container table tbody tr:hover { background: rgba(59,130,246,.10) !important; }
    .dashboard-container table tbody tr.border-b  { border-bottom: none !important; }
    .dashboard-container .divide-y > tr           { border-top: none !important; }
    .row-has-fitted { border-left: 2px solid #bfdbfe; }
    html.dark .row-has-fitted { border-left: 2px solid #1e40af; }

    .pg-btn {
        display: inline-flex; align-items: center; justify-content: center;
        min-width: 2rem; height: 2rem; padding: 0 0.5rem;
        font-size: 13px; font-weight: 500;
        border-radius: 0.375rem; border: 1px solid #e5e7eb;
        background: white; color: #374151;
        cursor: pointer; transition: all 0.15s;
        text-decoration: none;
    }
    html.dark .pg-btn { background: #1e2433; color: #d1d5db; border-color: #374151; }
    .pg-btn:hover:not(.pg-btn-active):not(.pg-btn-disabled) { background: #f9fafb; border-color: #d1d5db; }
    html.dark .pg-btn:hover:not(.pg-btn-active):not(.pg-btn-disabled) { background: #2d3748; border-color: #4a5568; }
    .pg-btn-active { background: #2563eb; color: white; border-color: #2563eb; font-weight: 700; cursor: default; }
    .pg-btn-disabled { background: #f3f4f6; color: #9ca3af; border-color: #f3f4f6; cursor: not-allowed; }
    html.dark .pg-btn-disabled { background: #1a202c; color: #4b5563; border-color: #1a202c; }

    .dashboard-container .grid.grid-cols-1.sm\:grid-cols-2.md\:grid-cols-4 > div {
        text-align: center !important;
    }
    .dashboard-container .grid.grid-cols-1.sm\:grid-cols-2.md\:grid-cols-4 > div .flex.items-center.gap-2 {
        justify-content: center !important;
    }
</style>

<div class="dashboard-container space-y-6 animate-fade-in">

    {{-- HEADER --}}
    <div class="card-standard p-6">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div class="flex items-center gap-4">
                <div class="bg-blue-600 p-3 rounded-lg text-white shadow-md">
                    <i class="fas fa-chart-line text-xl"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 leading-none">
                        {{ __('messages.judul_sistem') }}
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {{ __('messages.data_historis_vs_proyeksi') }}
                    </p>
                </div>
            </div>

            {{-- Status Flask API --}}
            <div class="flex items-center gap-2">
                <span class="flex items-center gap-1.5 px-2 py-1 rounded-full text-[10px] font-medium transition-all duration-500 bg-gray-100 text-gray-500" id="flask-status-badge">
                    <span class="w-2 h-2 rounded-full bg-gray-400 animate-pulse" id="flask-status-dot"></span>
                    <span id="flask-status-text">Memeriksa...</span>
                </span>
                <span class="text-[9px] text-gray-400">Flask API</span>
            </div>
        </div>

        {{-- FILTER: Komoditas & Tanggal --}}
        <form action="{{ url()->current() }}" method="GET" id="mainForm"
              class="mt-6 pt-6 border-t border-gray-100 dark:border-gray-700">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">

                {{-- Komoditas --}}
                <div class="md:col-span-4">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase mb-2 block tracking-tight">
                        {{ __('messages.komoditas_terpilih') }}
                    </label>
                    <select name="komoditas_id" onchange="this.form.submit()"
                            class="w-full bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md py-2 px-3 text-sm font-medium text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 transition-all">
                        @foreach($allCommodities as $item)
                            <option value="{{ $item->id }}"
                                {{ isset($selectedCommodityId) && $selectedCommodityId == $item->id ? 'selected' : '' }}>
                                {{ $item->nama_komoditas }}{{ $item->nama_varian ? ' ('.$item->nama_varian.')' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Rentang Waktu --}}
                <div class="md:col-span-8">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase mb-2 block tracking-tight">
                        {{ __('messages.rentang_waktu') }}
                    </label>
                    <div class="flex items-center gap-3 bg-gray-50 dark:bg-gray-700 p-1.5 rounded-md border border-gray-300 dark:border-gray-600">
                        <input type="date" name="start_date" value="{{ $startDate }}"
                               onchange="this.form.submit()"
                               class="bg-transparent text-sm p-1 outline-none flex-1 font-medium text-gray-900 dark:text-gray-100">
                        <span class="text-gray-400 font-bold">→</span>
                        <input type="date" name="end_date" value="{{ $endDate }}"
                               onchange="this.form.submit()"
                               class="bg-transparent text-sm p-1 outline-none flex-1 font-medium text-gray-900 dark:text-gray-100">
                    </div>
                </div>
            </div>

            {{-- Parameter diambil otomatis dari preferensi admin --}}
        </form>
    </div>

    {{-- METRIC CARDS --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
        <div class="card-standard hover-card p-5">
            <p class="text-[10px] uppercase text-gray-500 dark:text-gray-400 font-bold tracking-wider mb-2">{{ __('messages.rata_rata_harga') }}</p>
            <p id="avg-price-value" class="text-xl font-bold text-gray-900 dark:text-gray-100">
                Rp {{ number_format($avgPrice, 0, ',', '.') }}
            </p>
            <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1">{{ $countData ?? 0 }} {{ __('messages.data_poin') }}</p>
        </div>
        <div class="card-standard hover-card p-5">
            <p class="text-[10px] uppercase text-gray-500 dark:text-gray-400 font-bold tracking-wider mb-2">{{ __('messages.harga_tertinggi') }}</p>
            <p id="max-price-value" class="text-xl font-bold text-red-600 dark:text-red-400">
                Rp {{ number_format($maxPrice, 0, ',', '.') }}
            </p>
        </div>
        <div class="card-standard hover-card p-5">
            <p class="text-[10px] uppercase text-gray-500 dark:text-gray-400 font-bold tracking-wider mb-2">{{ __('messages.periode_data') }}</p>
            <div class="flex items-center gap-2">
                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                    {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }}
                    <span class="text-gray-400 mx-1">→</span>
                    {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}
                </p>
            </div>
        </div>
        <div class="bg-blue-600 dark:bg-blue-700 rounded-lg p-5 text-white shadow-lg hover-card">
            <p class="text-[10px] uppercase text-blue-100 font-bold tracking-wider mb-2">{{ __('messages.arah_tren') }}</p>
            <p class="text-sm font-bold uppercase flex items-center gap-2">
                @php $trendLower = strtolower($trendDir ?? 'stabil'); @endphp
                <i class="fas {{ str_contains($trendLower, 'naik') ? 'fa-arrow-trend-up' : (str_contains($trendLower, 'turun') ? 'fa-arrow-trend-down' : 'fa-minus') }}"></i>
                {{ $trendDir }}
            </p>
        </div>
    </div>

    {{-- CHART --}}
    <div class="card-standard overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50
                    flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 uppercase tracking-tight">
                    {{ __('messages.visualisasi_tren') }}
                </h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ $selectedCommodity }} — {{ __('messages.data_historis_vs_proyeksi') }}
                    @if(isset($forecastWeeks))
                        <span class="ml-2 horizon-pill">
                            <i class="fas fa-calendar-alt" style="font-size:8px;"></i>
                            {{ $forecastWeeks }} {{ __('messages.bulanan') }}
                        </span>
                    @endif
                </p>
            </div>
            <div class="flex bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 p-1 rounded-md shadow-sm">
                <button onclick="changeChartPeriod('monthly')" class="filter-btn active" id="btn-monthly">{{ __('messages.bulanan') }}</button>
                <button onclick="changeChartPeriod('yearly')"  class="filter-btn"        id="btn-yearly">{{ __('messages.tahunan') }}</button>
            </div>
        </div>
        <div class="p-6" style="min-height: 450px;">
            <canvas id="mainChart"></canvas>
        </div>
    </div>

    {{-- INSIGHT TABLE --}}
    <div class="card-standard overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50
                    flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
            <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 uppercase tracking-tight">
                {{ __('messages.ringkasan_analisis') }}
                <span id="selectedPeriodText" class="text-blue-600 dark:text-blue-400">{{ __('messages.bulanan') }}</span>
            </h3>
            <div class="flex items-center gap-3 flex-wrap">
                <span class="text-xs text-gray-400 dark:text-gray-500">{{ $selectedCommodity }}</span>
                @if(isset($mape))
                    <span class="text-[9px] bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 font-bold px-2 py-0.5 rounded-full">
                        MAPE: {{ number_format($mape, 2) }}%
                    </span>
                @endif
                <span class="flex items-center gap-1 text-[9px] text-gray-400 dark:text-gray-500 cursor-help"
                    data-tooltip-title="In-sample fit"
                    data-tooltip-color="blue"
                    data-tooltip-body="Nilai yang diprediksi model Prophet untuk periode yang sudah ada data aktualnya — digunakan untuk mengukur seberapa baik model mencocokkan data historis.">
                    <span class="inline-block w-2 h-2 rounded-sm bg-blue-200 dark:bg-blue-900 border border-blue-400"></span>
                    <span class="border-b border-dashed border-gray-400">In-sample fit</span>
                </span>
                <span class="flex items-center gap-1 text-[9px] text-gray-400 dark:text-gray-500 cursor-help"
                    data-tooltip-title="Proyeksi"
                    data-tooltip-color="orange"
                    data-tooltip-body="Prediksi harga untuk periode yang belum ada data aktualnya — hasil forecast model ke depan beserta rentang batas bawah/atas kepercayaan 80%.">
                    <span class="inline-block w-2 h-2 rounded-sm bg-orange-100 dark:bg-orange-900/30 border border-orange-300"></span>
                    <span class="border-b border-dashed border-gray-400">Proyeksi</span>
                </span>
                @if(isset($forecastWeeks))
                    <span class="horizon-pill">{{ $forecastWeeks }} {{ __('messages.bulanan') }}</span>
                @endif
            </div>
        </div>
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-[11px] font-bold text-gray-500 dark:text-gray-400 uppercase
                               bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                        <th class="px-6 py-4">{{ __('messages.periode') }}</th>
                        <th class="px-6 py-4 text-right">{{ __('messages.harga_aktual') }}</th>
                        <th class="px-6 py-4 text-right">{{ __('messages.harga_prediksi') }}</th>
                        <th class="px-6 py-4 text-right">{{ __('messages.rentang_bawah') }}</th>
                        <th class="px-6 py-4 text-right">{{ __('messages.rentang_atas') }}</th>
                        <th class="px-6 py-4 text-right">{{ __('messages.selisih') }}</th>
                        <th class="px-6 py-4 text-center">{{ __('messages.indikator') }}</th>
                    </tr>
                </thead>
                <tbody class="text-sm text-gray-700 dark:text-gray-300 divide-y divide-gray-100 dark:divide-gray-700" id="insightTableBody">
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-gray-400 text-sm">
                            <i class="fas fa-spinner fa-spin mr-2"></i> Memuat data...
                        </td>
                    </tr>
                </tbody>
            </table>
            <div id="insightPaginationWrap" class="px-6 py-3 border-t border-gray-100 dark:border-gray-700"></div>
        </div>
        <div id="insightPagination"></div>
    </div>

    {{-- INTERPRETASI --}}
    <div class="card-standard p-6 border-l-4 border-l-blue-600">
        <div class="flex items-center gap-3 mb-3">
            <h4 class="text-sm font-bold text-gray-900 dark:text-gray-100 uppercase">{{ __('messages.interpretasi_tren') }}</h4>
        </div>
        <p id="analysis-text" class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
            {{ __('messages.berdasarkan_analisis') }} <strong>{{ $selectedCommodity }}</strong>,
            {{ __('messages.model_deteksi') }} <strong>{{ strtolower($trendDir ?? 'stabil') }}</strong>
            {{ __('messages.rata_rata_harga_label') }} <strong>Rp {{ number_format($avgPrice ?? 0, 0, ',', '.') }}</strong>
            {{ __('messages.total_label') }} <strong>{{ $countData ?? 0 }} {{ __('messages.data_poin') }}</strong> {{ __('messages.pada_periode') }}
            {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }}
            {{ __('messages.s_d') }} {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}.
            {{ __('messages.nilai_mape_label') }} <strong>{{ number_format($mape ?? 0, 2) }}%</strong>
            {{ __('messages.menunjukkan') }}
            <strong>
                @if(($mape ?? 0) < 10) Sangat Akurat
                @elseif(($mape ?? 0) < 20) Baik
                @elseif(($mape ?? 0) < 50) Cukup
                @else Tidak Akurat
                @endif
            </strong> (Kriteria Lewis, 1982).
        </p>
    </div>

</div>{{-- end dashboard-container --}}

{{-- Global Floating Tooltip --}}
<div id="global-tooltip"
     class="fixed z-[9999] hidden bg-gray-900 dark:bg-gray-700 text-white rounded-xl shadow-2xl pointer-events-none max-w-xs"
     style="padding: 12px 16px; font-size: 13px; line-height: 1.7;">
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const SELECTED_COMMODITY = '{{ addslashes($selectedCommodity ?? "") }}';

// ── FIX 1: MAPE_RATE wajib ada — dipakai untuk fallback interval bawah/atas ──
const MAPE_RATE = Math.min(0.50, Math.max(0.01, {{ ($mape ?? 5) / 100 }}));

const trans = {
    monthly:      "{{ __('messages.bulanan') }}",
    yearly:       "{{ __('messages.tahunan') }}",
    actual:       "{{ __('messages.harga_aktual') }}",
    forecast:     "{{ __('messages.harga_proyeksi') }}",
    lower:        "{{ __('messages.rentang_bawah') }}",
    upper:        "{{ __('messages.rentang_atas') }}",
    naik:         "{{ __('messages.naik') }}",
    turun:        "{{ __('messages.turun') }}",
    stabil:       "{{ __('messages.stabil') }}",
    proyeksi:     "{{ __('messages.proyeksi') }}",
    bulanan:      "{{ __('messages.bulanan') }}",
    tahunan:      "{{ __('messages.tahunan') }}",
    hargaAktual:  "{{ __('messages.harga_aktual') }}",
    hargaProyeksi:"{{ __('messages.harga_proyeksi') }}",
    rentangBawah: "{{ __('messages.rentang_bawah') }}",
    rentangAtas:  "{{ __('messages.rentang_atas') }}",
    tidakAdaData: "{{ __('messages.tidak_ada_data') }}",
    bulan:        "{{ __('messages.bulanan') }}",
    noData:       "{{ __('messages.tidak_ada_data') }}",
};

// ── FIX 2: fitted sekarang disertakan di chartData ──
const chartData = {
    monthly: {
        labels:   @json($monthlyLabels   ?? []),
        actual:   @json($monthlyActual   ?? []),
        forecast: @json($monthlyForecast ?? []),
        fitted:   @json($monthlyFitted   ?? []),
        lower:    @json($monthlyLower    ?? []),
        upper:    @json($monthlyUpper    ?? [])
    },
    yearly: {
        labels:   @json($yearlyLabels   ?? []),
        actual:   @json($yearlyActual   ?? []),
        forecast: @json($yearlyForecast ?? []),
        fitted:   @json($yearlyFitted   ?? []),
        lower:    @json($yearlyLower    ?? []),
        upper:    @json($yearlyUpper    ?? [])
    }
};

let currentPeriod      = 'monthly';
let mainChart          = null;
let insightCurrentPage = 1;
const INSIGHT_PER_PAGE = 10;

const isDark    = () => document.documentElement.classList.contains('dark');
const fmtRupiah = v  => (v !== null && v !== undefined)
    ? 'Rp ' + Math.round(v).toLocaleString('id-ID')
    : '—';

/* ═══════════════════════════════════════════
   FLASK STATUS
═══════════════════════════════════════════ */
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
            } else { throw new Error(); }
        })
        .catch(() => {
            badge.className = 'flex items-center gap-1.5 px-2 py-1 rounded-full text-[10px] font-medium transition-all duration-500 bg-red-100 text-red-700';
            dot.className   = 'w-2 h-2 rounded-full bg-red-500 shadow-[0_0_6px_rgba(239,68,68,0.8)]';
            text.textContent = '{{ __("messages.api_offline") }}';
        });
}

/* ═══════════════════════════════════════════
   CHART — identik dengan admin (doc 1)
   dataset fitted + forecast terpisah
═══════════════════════════════════════════ */
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
                {
                    label: trans.rentangBawah,
                    data: data.lower,
                    backgroundColor: 'rgba(249,115,22,0.08)',
                    borderColor: 'transparent',
                    fill: '+1',
                    pointRadius: 0,
                    tension: 0.4,
                    spanGaps: false,
                    order: 5
                },
                {
                    label: trans.rentangAtas,
                    data: data.upper,
                    borderColor: 'transparent',
                    fill: false,
                    pointRadius: 0,
                    tension: 0.4,
                    spanGaps: false,
                    order: 5
                },
                {
                    label: trans.hargaAktual,
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
                    spanGaps: false,
                    order: 2
                },
                {
                    label: trans.hargaProyeksi,
                    data: data.fitted,
                    borderColor: '#f97316',
                    backgroundColor: 'transparent',
                    borderWidth: 2,
                    fill: false,
                    tension: 0.4,
                    pointRadius: 0,
                    pointHoverRadius: 5,
                    pointHoverBackgroundColor: '#f97316',
                    pointHoverBorderColor: '#fff',
                    pointHoverBorderWidth: 2,
                    spanGaps: false,
                    order: 3
                },
                {
                    label: trans.proyeksi + ' (' + trans.bulanan + ')',
                    data: data.forecast,
                    borderColor: '#f97316',
                    backgroundColor: 'transparent',
                    borderWidth: 2,
                    borderDash: [6, 3],
                    fill: false,
                    tension: 0.4,
                    pointRadius: 0,
                    pointHoverRadius: 5,
                    pointHoverBackgroundColor: '#f97316',
                    pointHoverBorderColor: '#fff',
                    pointHoverBorderWidth: 2,
                    spanGaps: false,
                    order: 3
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
                    display: true,
                    position: 'top',
                    align: 'end',
                    labels: {
                        boxWidth: 12, boxHeight: 12, padding: 15,
                        font: { size: 11, weight: '600' },
                        color: dark ? '#9ca3af' : '#64748b',
                        usePointStyle: true, pointStyle: 'circle',
                        filter: (item) => item.text !== trans.rentangBawah && item.text !== trans.rentangAtas
                    }
                },
                tooltip: {
                    backgroundColor: dark ? '#1e2433' : '#ffffff',
                    titleColor: dark ? '#f3f4f6' : '#1e293b',
                    bodyColor: dark ? '#9ca3af' : '#475569',
                    borderColor: dark ? '#374151' : '#e2e8f0',
                    borderWidth: 1, padding: 12, boxPadding: 6,
                    usePointStyle: true,
                    titleFont: { size: 11, weight: '600' },
                    bodyFont: { size: 11 },
                    callbacks: {
                        label: function(context) {
                            if (context.dataset.label === trans.rentangBawah || context.dataset.label === trans.rentangAtas) return null;
                            let label = context.dataset.label || '';
                            if (label) label += ': ';
                            if (context.parsed.y !== null) {
                                label += new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(context.parsed.y);
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
                    ticks: { color: dark ? '#6b7280' : '#94a3b8', font: { size: 10, weight: '500' }, padding: 8, callback: value => 'Rp ' + value.toLocaleString('id-ID') }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: dark ? '#6b7280' : '#94a3b8', font: { size: 9, weight: '500' }, maxRotation: 45, minRotation: 0, autoSkip: true, maxTicksLimit: 15 }
                }
            }
        }
    });
}

/* ═══════════════════════════════════════════
   PERIOD SWITCHER
═══════════════════════════════════════════ */
function changeChartPeriod(period) {
    currentPeriod = period; insightCurrentPage = 1;
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('btn-' + period)?.classList.add('active');
    const periodText = { 'monthly': trans.bulanan, 'yearly': trans.tahunan };
    const el = document.getElementById('selectedPeriodText');
    if (el) el.textContent = periodText[period] || '';
    const wrap = document.getElementById('mainChart')?.parentElement;
    if (wrap) wrap.style.opacity = '0.5';
    setTimeout(() => {
        initializeChart();
        updateInsightTable(1);
        updateMetricCards();
        if (wrap) wrap.style.opacity = '1';
    }, 150);
}

/* ═══════════════════════════════════════════
   INSIGHT TABLE
   FIX 3: Logika identik dengan admin (doc 1):
   - actualRows pakai fitted sebagai displayForecast
   - forecastRows pakai forecast
   - Badge FIT untuk in-sample, badge Proyeksi untuk forecast
   - MtM dihitung periode vs periode sebelumnya
   - MAPE_RATE dipakai sebagai fallback interval
═══════════════════════════════════════════ */
function updateInsightTable(page) {
    page = page || 1; insightCurrentPage = page;
    const data  = chartData[currentPeriod];
    const tbody = document.getElementById('insightTableBody');
    if (!tbody) return;
    tbody.innerHTML = '';

    if (!data.labels || data.labels.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="p-8 text-center text-gray-400 dark:text-gray-500 text-xs">${trans.tidakAdaData}</td></tr>`;
        renderInsightPagination(1, 1, 0, 0, 0);
        return;
    }

    const actualRows = [], forecastRows = [];
    for (let i = 0; i < data.labels.length; i++) {
        const row = {
            label:    data.labels[i],
            actual:   data.actual[i]   !== undefined ? data.actual[i]   : null,
            forecast: data.forecast[i] !== undefined ? data.forecast[i] : null,
            fitted:   (data.fitted && data.fitted[i] !== undefined && data.fitted[i] !== null) ? data.fitted[i] : null,
            lower:    data.lower[i]    !== undefined ? data.lower[i]    : null,
            upper:    data.upper[i]    !== undefined ? data.upper[i]    : null
        };
        if (row.actual !== null) actualRows.push(row);
        else if (row.forecast !== null) forecastRows.push(row);
    }

    const allRows     = actualRows.concat(forecastRows);
    const totalRows   = allRows.length;
    const totalPages  = Math.max(1, Math.ceil(totalRows / INSIGHT_PER_PAGE));
    const safePage    = Math.min(Math.max(1, page), totalPages);
    const startIdx    = (safePage - 1) * INSIGHT_PER_PAGE;
    const endIdx      = Math.min(startIdx + INSIGHT_PER_PAGE, totalRows);
    const display     = allRows.slice(startIdx, endIdx);
    const actualCount = actualRows.length;

    if (display.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="p-8 text-center text-gray-400 dark:text-gray-500 text-xs">${trans.tidakAdaData}</td></tr>`;
        renderInsightPagination(1, 1, 0, 0, 0);
        return;
    }

    var html = '';
    for (var idx = 0; idx < display.length; idx++) {
        var row = display[idx], globalIdx = startIdx + idx;
        var isForecastOnly = (row.actual === null && row.forecast !== null);

        // FIX: baris historis pakai fitted, baris proyeksi pakai forecast
        var displayForecast = isForecastOnly ? row.forecast : row.fitted;
        var displayLower = row.lower, displayUpper = row.upper;

        // Fallback interval menggunakan MAPE_RATE
        if (!isForecastOnly && displayForecast !== null) {
            if (displayLower === null || displayLower === undefined) displayLower = Math.round(displayForecast * (1 - MAPE_RATE));
            if (displayUpper === null || displayUpper === undefined) displayUpper = Math.round(displayForecast * (1 + MAPE_RATE));
        }

        // MtM: bandingkan periode ini vs periode sebelumnya
        var prevRow = globalIdx > 0 ? allRows[globalIdx - 1] : null;
        var hargaSekarang   = isForecastOnly ? row.forecast : (row.actual !== null ? row.actual : null);
        var hargaSebelumnya = null;
        if (prevRow) {
            hargaSebelumnya = (prevRow.actual !== null) ? prevRow.actual : (prevRow.forecast !== null ? prevRow.forecast : null);
        }

        var mtmPct = null;
        if (hargaSekarang !== null && hargaSebelumnya !== null && hargaSebelumnya !== 0) {
            mtmPct = ((hargaSekarang - hargaSebelumnya) / hargaSebelumnya) * 100;
        }

        var insight = '\u2014', insightClass = 'insight-stabil', mtmText = '\u2014';
        if (mtmPct !== null) {
            var mtmAbs = Math.abs(mtmPct).toFixed(1);
            if (mtmPct > 0) {
                insight = 'INFLASI +' + mtmAbs + '%'; insightClass = 'insight-naik'; mtmText = '+' + mtmAbs + '%';
            } else if (mtmPct < 0) {
                insight = 'DEFLASI -' + mtmAbs + '%'; insightClass = 'insight-turun'; mtmText = '-' + mtmAbs + '%';
            } else {
                insight = 'STABIL 0.0%'; insightClass = 'insight-stabil'; mtmText = '0.0%';
            }
        } else if (isForecastOnly) {
            insight = trans.proyeksi; insightClass = 'insight-stabil';
        }

        var rowBg     = isForecastOnly ? 'bg-orange-50/30 dark:bg-orange-900/5' : (displayForecast !== null ? 'row-has-fitted' : '');
        var borderTop = (globalIdx === actualCount && forecastRows.length > 0) ? 'border-t-2 border-orange-200 dark:border-orange-800' : '';
        var periodBadge = '';
        if (isForecastOnly) {
            periodBadge = `<span class="ml-1 text-[9px] bg-orange-100 dark:bg-orange-900/30 text-orange-600 dark:text-orange-400 px-1.5 py-0.5 rounded font-bold uppercase">${trans.proyeksi}</span>`;
        } else if (displayForecast !== null) {
            periodBadge = `<span class="fitted-badge">FIT</span>`;
        }

        var actualCell   = row.actual !== null   ? 'Rp ' + Math.round(row.actual).toLocaleString('id-ID')          : '<span class="text-gray-300 dark:text-gray-600">\u2014</span>';
        var forecastCell = displayForecast !== null ? 'Rp ' + Math.round(displayForecast).toLocaleString('id-ID')  : '<span class="text-gray-300 dark:text-gray-600">\u2014</span>';
        var lowerCell    = displayLower !== null  ? 'Rp ' + Math.round(displayLower).toLocaleString('id-ID')        : '<span class="text-gray-300 dark:text-gray-600">\u2014</span>';
        var upperCell    = displayUpper !== null  ? 'Rp ' + Math.round(displayUpper).toLocaleString('id-ID')        : '<span class="text-gray-300 dark:text-gray-600">\u2014</span>';
        var mtmColor     = mtmPct !== null ? (mtmPct > 0 ? 'text-red-600 dark:text-red-400' : mtmPct < 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-500 dark:text-gray-400') : 'text-gray-300 dark:text-gray-600';

        html += `<tr class="${rowBg} ${borderTop} border-b border-gray-50 dark:border-gray-700 animate-fade-in">
            <td class="px-6 py-4 text-gray-500 dark:text-gray-400 font-medium text-xs">${row.label}${periodBadge}</td>
            <td class="px-6 py-4 text-right text-xs font-medium text-gray-800 dark:text-gray-200">${actualCell}</td>
            <td class="px-6 py-4 text-right text-blue-600 dark:text-blue-400 font-bold text-xs">${forecastCell}</td>
            <td class="px-6 py-4 text-right text-xs text-gray-400 dark:text-gray-500">${lowerCell}</td>
            <td class="px-6 py-4 text-right text-xs text-gray-400 dark:text-gray-500">${upperCell}</td>
            <td class="px-6 py-4 text-right text-xs font-medium ${mtmColor}">${mtmText}</td>
            <td class="px-6 py-4 text-center"><span class="insight-badge ${insightClass}">${insight}</span></td>
        </tr>`;
    }
    tbody.innerHTML = html;
    renderInsightPagination(safePage, totalPages, totalRows, startIdx + 1, endIdx);
}

function renderInsightPagination(currentPage, totalPages, totalRows, fromRow, toRow) {
    var container = document.getElementById('insightPagination');
    if (!container) return;
    if (totalPages <= 1) { container.innerHTML = ''; return; }
    var btnBase = 'pg-btn', btnActive = 'pg-btn pg-btn-active', btnDisabled = 'pg-btn pg-btn-disabled';
    var prev = currentPage > 1
        ? `<button onclick="updateInsightTable(${currentPage - 1})" class="${btnBase}"><i class="fas fa-chevron-left"></i></button>`
        : `<span class="${btnDisabled}"><i class="fas fa-chevron-left"></i></span>`;
    var next = currentPage < totalPages
        ? `<button onclick="updateInsightTable(${currentPage + 1})" class="${btnBase}"><i class="fas fa-chevron-right"></i></button>`
        : `<span class="${btnDisabled}"><i class="fas fa-chevron-right"></i></span>`;
    var delta = 2, start = Math.max(1, currentPage - delta), end = Math.min(totalPages, currentPage + delta), pages = '';
    if (start > 1) { pages += `<button onclick="updateInsightTable(1)" class="${btnBase}">1</button>`; if (start > 2) pages += '<span class="px-1 text-gray-400 text-xs">\u2026</span>'; }
    for (var p = start; p <= end; p++) pages += `<button onclick="updateInsightTable(${p})" class="${p === currentPage ? btnActive : btnBase}">${p}</button>`;
    if (end < totalPages) { if (end < totalPages - 1) pages += '<span class="px-1 text-gray-400 text-xs">\u2026</span>'; pages += `<button onclick="updateInsightTable(${totalPages})" class="${btnBase}">${totalPages}</button>`; }
    container.innerHTML = `<div class="flex items-center justify-between px-6 py-3 border-t border-gray-100 dark:border-gray-700 bg-gray-50/30 dark:bg-gray-800/30"><span class="text-xs text-gray-500 dark:text-gray-400">Menampilkan ${fromRow}&ndash;${toRow} dari ${totalRows} data</span><div class="flex items-center gap-1">${prev}${pages}${next}</div></div>`;
}

/* ═══════════════════════════════════════════
   METRIC CARDS — recalculate dari period aktif
═══════════════════════════════════════════ */
function updateMetricCards() {
    const actuals = chartData[currentPeriod].actual.filter(v => v !== null);
    if (!actuals.length) return;
    const avg = actuals.reduce((a, b) => a + b, 0) / actuals.length;
    const el1 = document.getElementById('avg-price-value');
    const el2 = document.getElementById('max-price-value');
    if (el1) el1.textContent = fmtRupiah(avg);
    if (el2) el2.textContent = fmtRupiah(Math.max(...actuals));
}

/* ═══════════════════════════════════════════
   GLOBAL FLOATING TOOLTIP
═══════════════════════════════════════════ */
(function () {
    const tooltip = document.getElementById('global-tooltip');
    if (!tooltip) return;
    const colorMap = { blue: '#93c5fd', orange: '#fdba74', green: '#86efac', red: '#fca5a5' };
    function show(e) {
        const el    = e.currentTarget;
        const title = el.getAttribute('data-tooltip-title');
        const body  = el.getAttribute('data-tooltip-body');
        const color = el.getAttribute('data-tooltip-color') || 'blue';
        if (!title && !body) return;
        const titleColor = colorMap[color] || colorMap.blue;
        tooltip.innerHTML = (title ? `<strong style="color:${titleColor};display:block;margin-bottom:4px;">${title}</strong>` : '') + (body || '');
        tooltip.classList.remove('hidden');
        move(e);
    }
    function move(e) {
        const pad = 14, tw = tooltip.offsetWidth || 240, th = tooltip.offsetHeight || 60;
        let x = e.clientX + pad, y = e.clientY - th - pad;
        if (x + tw > window.innerWidth  - pad) x = e.clientX - tw - pad;
        if (y < pad)                            y = e.clientY + pad;
        if (y + th > window.innerHeight - pad)  y = window.innerHeight - th - pad;
        tooltip.style.left = x + 'px';
        tooltip.style.top  = y + 'px';
    }
    function hide() { tooltip.classList.add('hidden'); }
    function bind() {
        document.querySelectorAll('[data-tooltip-title],[data-tooltip-body]').forEach(el => {
            el.removeEventListener('mouseenter', show);
            el.removeEventListener('mousemove',  move);
            el.removeEventListener('mouseleave', hide);
            el.addEventListener('mouseenter', show);
            el.addEventListener('mousemove',  move);
            el.addEventListener('mouseleave', hide);
        });
    }
    document.addEventListener('DOMContentLoaded', bind);
    new MutationObserver(bind).observe(document.body, { childList: true, subtree: true });
})();

/* ═══════════════════════════════════════════
   INIT
═══════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', function() {
    changeChartPeriod('monthly');
    checkFlaskStatus();
    setInterval(checkFlaskStatus, 30000);
});

// Re-render chart saat toggle dark mode
new MutationObserver(() => { if (mainChart) initializeChart(); })
    .observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
</script>

@endsection