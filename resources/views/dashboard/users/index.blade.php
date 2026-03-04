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

    @keyframes fadeIn { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
    .animate-fade-in { animation: fadeIn 0.4s ease-out; }
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
                    <select name="commodity" onchange="this.form.submit()"
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

            {{-- Teruskan hyperparameter sebagai hidden agar controller tidak error --}}
            <input type="hidden" name="changepoint_prior_scale" value="{{ $cpScale }}">
            <input type="hidden" name="seasonality_prior_scale" value="{{ $seasonScale }}">
            <input type="hidden" name="seasonality_mode"        value="{{ $seasonalityMode }}">
            <input type="hidden" name="weekly"                  value="{{ $weeklyActive ? 'on' : 'off' }}">
            <input type="hidden" name="yearly"                  value="{{ $yearlyActive ? 'on' : 'off' }}">
        </form>
    </div>

    {{-- METRIC CARDS --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
        <div class="card-standard hover-card p-5">
            <p class="text-[10px] uppercase text-gray-500 dark:text-gray-400 font-bold tracking-wider mb-2">{{ __('messages.rata_rata_harga') }}</p>
            <p id="avg-price-value" class="text-xl font-bold text-gray-900 dark:text-gray-100">
                Rp {{ number_format($avgPrice, 0, ',', '.') }}
            </p>
            <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1">{{ $countData ?? 0 }} data poin</p>
        </div>
        <div class="card-standard hover-card p-5">
            <p class="text-[10px] uppercase text-gray-500 dark:text-gray-400 font-bold tracking-wider mb-2">{{ __('messages.harga_tertinggi') }}</p>
            <p id="max-price-value" class="text-xl font-bold text-red-600 dark:text-red-400">
                Rp {{ number_format($maxPrice, 0, ',', '.') }}
            </p>
        </div>
        <div class="card-standard hover-card p-5">
            <p class="text-[10px] uppercase text-gray-500 dark:text-gray-400 font-bold tracking-wider mb-2">{{ __('messages.periode_data') }}</p>
            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }}
                <span class="text-gray-400 mx-1">→</span>
                {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}
            </p>
        </div>
        <div class="bg-blue-600 dark:bg-blue-700 rounded-lg p-5 text-white shadow-lg hover-card">
            <p class="text-[10px] uppercase text-blue-100 font-bold tracking-wider mb-2">{{ __('messages.arah_tren') }}</p>
            <p class="text-sm font-bold uppercase flex items-center gap-2">
                @php $trendLower = strtolower($trendDir); @endphp
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
                        <span class="ml-2 inline-flex items-center gap-1 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400 text-[9px] font-bold px-2 py-0.5 rounded-full uppercase">
                            <i class="fas fa-calendar-alt" style="font-size:8px;"></i>
                            {{ $forecastWeeks }} minggu ke depan
                        </span>
                    @endif
                </p>
            </div>
            <div class="flex bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 p-1 rounded-md shadow-sm">
                <button onclick="changeChartPeriod('weekly')"  class="filter-btn active" id="btn-weekly">{{ __('messages.mingguan') }}</button>
                <button onclick="changeChartPeriod('monthly')" class="filter-btn"         id="btn-monthly">{{ __('messages.bulanan') }}</button>
                <button onclick="changeChartPeriod('yearly')"  class="filter-btn"         id="btn-yearly">{{ __('messages.tahunan') }}</button>
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
                <span id="selectedPeriodText" class="text-blue-600 dark:text-blue-400">{{ __('messages.mingguan') }}</span>
            </h3>
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-4 text-[10px] text-gray-400 dark:text-gray-500 font-semibold">
                    <span class="flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-blue-500 inline-block"></span> Historis
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-orange-400 inline-block"></span> Prediksi
                    </span>
                </div>
                @if(isset($mape))
                    <span class="text-[9px] bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 font-bold px-2 py-0.5 rounded-full">
                        MAPE: {{ number_format($mape, 2) }}%
                    </span>
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
                        <th class="px-6 py-4 text-right">Interval Bawah</th>
                        <th class="px-6 py-4 text-right">Interval Atas</th>
                        <th class="px-6 py-4 text-right">{{ __('messages.selisih') }}</th>
                        <th class="px-6 py-4 text-center">{{ __('messages.indikator') }}</th>
                    </tr>
                </thead>
                <tbody id="insightTableBody"
                       class="text-sm text-gray-700 dark:text-gray-300 divide-y divide-gray-100 dark:divide-gray-700">
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-gray-400 text-sm">
                            <i class="fas fa-spinner fa-spin mr-2"></i> Memuat data...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- INTERPRETASI --}}
    <div class="card-standard p-6 border-l-4 border-l-blue-600">
        <div class="flex items-center gap-3 mb-3">
            <h4 class="text-sm font-bold text-gray-900 dark:text-gray-100 uppercase">{{ __('messages.interpretasi_tren') }}</h4>
        </div>
        <p id="analysis-text" class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
            Berdasarkan analisis data historis untuk komoditas <strong>{{ $selectedCommodity }}</strong>,
            model mendeteksi tren harga <strong>{{ strtolower($trendDir ?? 'stabil') }}</strong>
            dengan rata-rata harga <strong>Rp {{ number_format($avgPrice ?? 0, 0, ',', '.') }}</strong>
            dan total <strong>{{ $countData ?? 0 }} data poin</strong> pada periode
            {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }}
            s/d {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}.
            Model Prophet dilatih dengan <strong>changepoint_prior_scale={{ $cpScale ?? 0.05 }}</strong>,
            <strong>seasonality_prior_scale={{ $seasonScale ?? 10 }}</strong>,
            mode <strong>{{ $seasonalityMode ?? 'multiplicative' }}</strong>,
            horizon prediksi <strong>{{ $forecastWeeks ?? 12 }} minggu ke depan</strong>.
            Nilai MAPE (Cross-Validation 80/20) sebesar <strong>{{ number_format($mape ?? 0, 2) }}%</strong>
            menunjukkan {{ ($mape ?? 0) < 5 ? 'akurasi sangat baik' : (($mape ?? 0) < 10 ? 'akurasi baik' : 'perlu penyesuaian hyperparameter') }}.
        </p>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
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

const forecastSuccess = {{ isset($forecastSuccess) && $forecastSuccess ? 'true' : 'false' }};

const trans = {
    weekly:   "{{ __('messages.mingguan') }}",
    monthly:  "{{ __('messages.bulanan') }}",
    yearly:   "{{ __('messages.tahunan') }}",
    actual:   "{{ __('messages.harga_aktual') }}",
    forecast: "{{ __('messages.harga_proyeksi') }}",
    lower:    "{{ __('messages.rentang_bawah') }}",
    upper:    "{{ __('messages.rentang_atas') }}",
    naik:     "{{ __('messages.naik') }}",
    turun:    "{{ __('messages.turun') }}",
    stabil:   "{{ __('messages.stabil') }}",
    noData:   "{{ __('messages.tidak_ada_data') }}",
};

let currentPeriod = 'weekly';
let mainChart     = null;

const isDark    = () => document.documentElement.classList.contains('dark');
const fmtRupiah = v  => (v !== null && v !== undefined) ? 'Rp ' + Math.round(v).toLocaleString('id-ID') : '—';

/* ── FLASK STATUS ── */
function checkFlaskStatus() {
    const badge = document.getElementById('flask-status-badge');
    const dot   = document.getElementById('flask-status-dot');
    const text  = document.getElementById('flask-status-text');

    badge.className = 'flex items-center gap-1.5 px-2 py-1 rounded-full text-[10px] font-medium transition-all duration-500 bg-gray-100 text-gray-500';
    dot.className   = 'w-2 h-2 rounded-full bg-gray-400 animate-pulse';
    text.textContent = 'Memeriksa...';

    fetch('/api/flask-health')
        .then(res => {
            if (res.ok) {
                badge.className = 'flex items-center gap-1.5 px-2 py-1 rounded-full text-[10px] font-medium transition-all duration-500 bg-green-100 text-green-700';
                dot.className   = 'w-2 h-2 rounded-full bg-green-500 shadow-[0_0_6px_rgba(34,197,94,0.8)]';
                text.textContent = 'Online';
            } else { throw new Error(); }
        })
        .catch(() => {
            badge.className = 'flex items-center gap-1.5 px-2 py-1 rounded-full text-[10px] font-medium transition-all duration-500 bg-red-100 text-red-700';
            dot.className   = 'w-2 h-2 rounded-full bg-red-500 shadow-[0_0_6px_rgba(239,68,68,0.8)]';
            text.textContent = 'Offline';
        });
}

/* ── CHART ── */
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

    const gradActual = ctx.createLinearGradient(0, 0, 0, 400);
    gradActual.addColorStop(0, dark ? 'rgba(96,165,250,0.3)' : 'rgba(4,50,119,0.15)');
    gradActual.addColorStop(1, 'rgba(4,50,119,0)');

    const gradForecast = ctx.createLinearGradient(0, 0, 0, 400);
    gradForecast.addColorStop(0, 'rgba(249,115,22,0.15)');
    gradForecast.addColorStop(1, 'rgba(249,115,22,0)');

    if (mainChart) mainChart.destroy();

    const datasets = [
        {
            label: trans.lower, data: data.lower,
            backgroundColor: 'rgba(34,197,94,0.08)', borderColor: 'transparent',
            fill: '+1', pointRadius: 0, tension: 0.4, order: 4
        },
        {
            label: trans.upper, data: data.upper,
            borderColor: 'transparent', fill: false,
            pointRadius: 0, tension: 0.4, order: 4
        },
        {
            label: trans.actual, data: data.actual,
            borderColor: dark ? '#60a5fa' : '#043277',
            backgroundColor: gradActual,
            borderWidth: 2.5, fill: true, tension: 0.4,
            pointRadius: 0, pointHoverRadius: 6,
            pointHoverBackgroundColor: dark ? '#60a5fa' : '#043277',
            pointHoverBorderColor: '#fff', pointHoverBorderWidth: 2,
            spanGaps: false, order: 1
        },
    ];

    if (forecastSuccess) {
        datasets.push({
            label: trans.forecast, data: data.forecast,
            borderColor: '#f97316', backgroundColor: gradForecast,
            borderDash: [8, 4], borderWidth: 2.5, fill: true, tension: 0.4,
            pointRadius: 0, pointHoverRadius: 6,
            pointHoverBackgroundColor: '#f97316',
            pointHoverBorderColor: '#fff', pointHoverBorderWidth: 2,
            spanGaps: false, order: 2
        });
    }

    mainChart = new Chart(ctx, {
        type: 'line',
        data: { labels: data.labels, datasets },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            plugins: {
                title: {
                    display: true, text: SELECTED_COMMODITY,
                    color: dark ? '#93c5fd' : '#043277',
                    font: { size: 14, weight: '600', family: 'Inter' },
                    padding: { top: 10, bottom: 15 }
                },
                legend: {
                    display: true, position: 'top', align: 'end',
                    labels: {
                        boxWidth: 12, usePointStyle: true,
                        color: dark ? '#9ca3af' : '#64748b',
                        filter: item => !item.text.includes(trans.lower) && !item.text.includes(trans.upper)
                    }
                },
                tooltip: {
                    backgroundColor: dark ? '#1e2433' : '#ffffff',
                    titleColor: dark ? '#f3f4f6' : '#1e293b',
                    bodyColor: dark ? '#9ca3af' : '#475569',
                    borderColor: dark ? '#374151' : '#e2e8f0',
                    borderWidth: 1, padding: 12, usePointStyle: true,
                    callbacks: {
                        label: ctx => {
                            if (ctx.dataset.label === trans.lower || ctx.dataset.label === trans.upper) return null;
                            let lbl = (ctx.dataset.label || '') + ': ';
                            if (ctx.parsed.y !== null)
                                lbl += new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(ctx.parsed.y);
                            return lbl;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: false,
                    grid: { color: dark ? 'rgba(255,255,255,0.05)' : '#f1f5f9', drawBorder: false },
                    ticks: {
                        color: dark ? '#6b7280' : '#94a3b8',
                        font: { size: 10, weight: '500' },
                        padding: 8,
                        callback: v => 'Rp ' + v.toLocaleString('id-ID')
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: {
                        color: dark ? '#6b7280' : '#94a3b8',
                        font: { size: 9, weight: '500' },
                        maxRotation: 45, autoSkip: true, maxTicksLimit: 15
                    }
                }
            }
        }
    });
}

/* ── PERIOD SWITCHER ── */
function changeChartPeriod(period) {
    currentPeriod = period;
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    document.getElementById(`btn-${period}`)?.classList.add('active');
    document.getElementById('selectedPeriodText').textContent = trans[period];

    const wrap = document.getElementById('mainChart')?.parentElement;
    if (wrap) wrap.style.opacity = '0.5';
    setTimeout(() => {
        initializeChart();
        updateInsightTable();
        updateMetricCards();
        if (wrap) wrap.style.opacity = '1';
    }, 150);
}

/* ── INSIGHT TABLE ── */
function updateInsightTable() {
    const data  = chartData[currentPeriod];
    const tbody = document.getElementById('insightTableBody');
    if (!tbody) return;

    if (!data.labels?.length) {
        tbody.innerHTML = `<tr><td colspan="7" class="px-6 py-8 text-center text-gray-400 text-sm">
            <i class="fas fa-database mr-2"></i>${trans.noData}</td></tr>`;
        return;
    }

    tbody.innerHTML = '';

    const actualRows   = [];
    const forecastRows = [];

    for (let i = 0; i < data.labels.length; i++) {
        const row = {
            label: data.labels[i], actual: data.actual[i],
            forecast: data.forecast[i], lower: data.lower[i], upper: data.upper[i]
        };
        if (data.actual[i] !== null) actualRows.push(row);
        if (data.actual[i] === null && data.forecast[i] !== null) forecastRows.push(row);
    }

    const display = [...actualRows.slice(-8), ...forecastRows];
    const lastActualRow = actualRows.slice(-1)[0];
    const displayActualLen = Math.min(actualRows.length, 8);

    if (display.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="px-6 py-8 text-center text-gray-400 text-sm">
            <i class="fas fa-database mr-2"></i>${trans.noData}</td></tr>`;
        return;
    }

    display.forEach((row, idx) => {
        const { label, actual, forecast, lower, upper } = row;
        const isForecastOnly = actual === null && forecast !== null;

        let insight = trans.stabil, insightClass = 'insight-stabil';
        let diff = null, diffColor = 'text-gray-400', diffText = '—';

        if (!isForecastOnly && actual !== null && forecast !== null) {
            diff = forecast - actual;
            const threshold = actual * 0.01;
            if (diff > threshold)       { insight = trans.naik;  insightClass = 'insight-naik'; }
            else if (diff < -threshold) { insight = trans.turun; insightClass = 'insight-turun'; }
            diffColor = diff > 0 ? 'text-red-600 dark:text-red-400' : diff < 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-500 dark:text-gray-400';
            diffText  = (diff > 0 ? '+' : '') + Math.round(diff).toLocaleString('id-ID');

        } else if (!isForecastOnly && actual !== null && forecast === null) {
            const prevActual = idx > 0 ? display[idx - 1].actual : null;
            if (prevActual !== null && prevActual !== 0) {
                diff = actual - prevActual;
                const threshold = prevActual * 0.01;
                if (diff > threshold)       { insight = trans.naik;  insightClass = 'insight-naik'; }
                else if (diff < -threshold) { insight = trans.turun; insightClass = 'insight-turun'; }
                diffColor = diff > 0 ? 'text-red-600 dark:text-red-400' : diff < 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-500 dark:text-gray-400';
                diffText  = (diff > 0 ? '+' : '') + Math.round(diff).toLocaleString('id-ID');
            }

        } else if (isForecastOnly && lastActualRow) {
            const diffFromLast  = forecast - lastActualRow.actual;
            const thresholdLast = lastActualRow.actual * 0.01;
            if (diffFromLast > thresholdLast)       { insight = trans.naik;  insightClass = 'insight-naik'; }
            else if (diffFromLast < -thresholdLast) { insight = trans.turun; insightClass = 'insight-turun'; }
            else                                    { insight = 'Proyeksi';  insightClass = 'insight-stabil'; }
        }

        const borderTop = (idx === displayActualLen && forecastRows.length > 0)
            ? 'border-t-2 border-orange-200 dark:border-orange-800' : '';

        tbody.innerHTML += `
            <tr class="${isForecastOnly ? 'forecast-row' : ''} ${borderTop} hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                <td class="px-6 py-4 text-gray-600 dark:text-gray-400 font-medium text-xs">
                    ${label}
                    ${isForecastOnly ? `<span class="ml-1 text-[9px] bg-orange-100 dark:bg-orange-900/30 text-orange-600 dark:text-orange-400 px-1.5 py-0.5 rounded font-bold uppercase">Proyeksi</span>` : ''}
                </td>
                <td class="px-6 py-4 text-right text-xs font-medium text-gray-800 dark:text-gray-200">
                    ${actual !== null ? fmtRupiah(actual) : '<span class="text-gray-300 dark:text-gray-600">—</span>'}
                </td>
                <td class="px-6 py-4 text-right text-xs font-bold text-orange-600 dark:text-orange-400">
                    ${forecast !== null ? fmtRupiah(forecast) : '<span class="text-gray-300 dark:text-gray-600">—</span>'}
                </td>
                <td class="px-6 py-4 text-right text-xs text-gray-400 dark:text-gray-500">
                    ${lower !== null ? fmtRupiah(lower) : '<span class="text-gray-300 dark:text-gray-600">—</span>'}
                </td>
                <td class="px-6 py-4 text-right text-xs text-gray-400 dark:text-gray-500">
                    ${upper !== null ? fmtRupiah(upper) : '<span class="text-gray-300 dark:text-gray-600">—</span>'}
                </td>
                <td class="px-6 py-4 text-right text-xs font-medium ${diffColor}">${diffText}</td>
                <td class="px-6 py-4 text-center">
                    <span class="insight-badge ${insightClass}">${insight}</span>
                </td>
            </tr>`;
    });
}



/* ── METRIC CARDS ── */
function updateMetricCards() {
    const actuals = chartData[currentPeriod].actual.filter(v => v !== null);
    if (!actuals.length) return;
    const avg = actuals.reduce((a, b) => a + b, 0) / actuals.length;
    document.getElementById('avg-price-value').textContent = fmtRupiah(avg);
    document.getElementById('max-price-value').textContent = fmtRupiah(Math.max(...actuals));
}

/* ── INIT ── */
document.addEventListener('DOMContentLoaded', () => {
    initializeChart();
    updateInsightTable();
    updateMetricCards();
    checkFlaskStatus();
    setInterval(checkFlaskStatus, 30000);
});

new MutationObserver(muts => {
    muts.forEach(m => { if (m.attributeName === 'class') initializeChart(); });
}).observe(document.documentElement, { attributes: true });
</script>

@endsection