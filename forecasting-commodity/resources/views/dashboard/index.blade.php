@extends('layouts.app') 

@section('content')

<div id="real-content">
<style>
    /* Reset & Typography Standar */
    .dashboard-container {
        font-family: 'Inter', sans-serif;
    }


    .card-standard {
        background: white;
        border-radius: 0.5rem; /* rounded-lg */
        border: 1px solid #e5e7eb; /* border-gray-200 */
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
    }

    /* Filter Button Standard */
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
        background: #2563eb; /* Blue-600 */
        color: white;
        border-color: #2563eb;
    }

    /* Badge Insight identik dengan Status Laporan */
    .insight-badge {
        padding: 0.25rem 0.625rem;
        border-radius: 9999px;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
    }

    .insight-naik { background: #fee2e2; color: #991b1b; } /* Red-100 & 800 */
    .insight-turun { background: #dcfce7; color: #166534; } /* Green-100 & 800 */
    .insight-stabil { background: #f3f4f6; color: #1f2937; } /* Gray-100 & 800 */

    [x-cloak] { display: none !important; }
    
</style>
</div>
<div id="skeleton-overlay" class="hidden fixed inset-0 bg-white/50 z-50 flex items-center justify-center opacity-0">
    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
</div>

<div class="dashboard-container space-y-6 animate-fade-in">

    {{-- HEADER SECTION --}}
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
                    <!-- <p class="text-xs text-gray-500 font-medium uppercase tracking-wider mt-1.5">
                        Provinsi Riau • Peramalan Berbasis Data Real-time
                    </p> -->
                </div>
            </div>
        </div>

        {{-- FILTER FORM --}}
        <form action="{{ url()->current() }}" method="GET" id="mainForm" class="mt-6 pt-6 border-t border-gray-100">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                {{-- Komoditas Filter --}}
                <div class="md:col-span-4">
                    <label class="text-xs font-semibold text-gray-700 uppercase mb-2 block tracking-tight">
                        Komoditas Terpilih
                    </label>
                    <select name="commodity" onchange="handleCommodityChange()" 
                            class="w-full bg-gray-50 border border-gray-300 rounded-md py-2 px-3 text-sm font-medium text-gray-900 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all">
                        @foreach($allCommodities as $item)
                            <option value="{{ $item->id }}" {{ (isset($selectedCommodityId) && $selectedCommodityId == $item->id) || $selectedCommodity == $item->nama_komoditas ? 'selected' : '' }}>
                                {{ $item->nama_komoditas }} {{ $item->nama_varian ? '('.$item->nama_varian.')' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Rentang Waktu Filter --}}
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
            <input type="hidden" name="changepoint_prior_scale" value="{{ $cpScale }}">
            <input type="hidden" name="seasonality_prior_scale" value="{{ $seasonScale }}">
            <input type="hidden" name="seasonality_mode" value="{{ $seasonalityMode }}">
            <input type="hidden" name="weekly" value="{{ $weeklyActive ? 'on' : 'off' }}">
            <input type="hidden" name="yearly" value="{{ $yearlyActive ? 'on' : 'off' }}">
        </form>
    </div>

    {{-- METRIC CARDS --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
        <!-- <div class="card-standard p-5">
            <p class="text-[10px] uppercase text-gray-500 font-bold tracking-wider mb-2">Rata-rata Harga</p>
            <p class="text-xl font-bold text-gray-900">Rp {{ number_format($avgPrice,0,',','.') }}</p>
        </div> -->

        <!-- <div class="card-standard p-5">
            <p class="text-[10px] uppercase text-gray-500 font-bold tracking-wider mb-2">Harga Tertinggi</p>
            <p class="text-xl font-bold text-red-600">Rp {{ number_format($maxPrice,0,',','.') }}</p>
        </div> -->
        <div class="card-standard p-5">
            <p class="text-[10px] uppercase text-gray-500 font-bold tracking-wider mb-2">Rata-rata Harga</p>
            <p id="avg-price-value" class="text-xl font-bold text-gray-900">Rp {{ number_format($avgPrice,0,',','.') }}</p>
        </div>

        <div class="card-standard p-5">
            <p class="text-[10px] uppercase text-gray-500 font-bold tracking-wider mb-2">Harga Tertinggi</p>
            <p id="max-price-value" class="text-xl font-bold text-red-600">Rp {{ number_format($maxPrice,0,',','.') }}</p>
        </div>

        
        <div class="card-standard p-5">
            <p class="text-[10px] uppercase text-gray-500 font-bold tracking-wider mb-2">Periode Data</p>
            <div class="flex items-center gap-2">
                <p class="text-sm font-semibold text-gray-900">
                    {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }} 
                    <span class="text-gray-400 mx-1">→</span> 
                    {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}
                </p>
            </div>
        </div>
        <div class="bg-blue-600 rounded-lg p-5 text-white shadow-lg">
            <p class="text-[10px] uppercase text-blue-100 font-bold tracking-wider mb-2">Arah Tren</p>
            <p class="text-sm font-bold uppercase flex items-center gap-2">
                <i class="fas {{ str_contains(strtolower($trendDir), 'naik') ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' }}"></i>
                {{ $trendDir }}
            </p>
        </div>
    </div>

    {{-- CHART SECTION --}}
    <div class="card-standard overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex flex-col lg:flex-row justify-between items-center gap-4">
            <div>
                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-tight">Visualisasi Tren & Proyeksi</h3>
                <p class="text-xs text-gray-500">Menampilkan data historis vs hasil algoritma Prophet</p>
            </div>

            <div class="flex bg-white border border-gray-300 p-1 rounded-md shadow-sm">
                <button onclick="changeChartPeriod('weekly')" class="filter-btn active" id="btn-weekly">Mingguan</button>
                <button onclick="changeChartPeriod('monthly')" class="filter-btn border-none" id="btn-monthly">Bulanan</button>
                <button onclick="changeChartPeriod('yearly')" class="filter-btn border-none" id="btn-yearly">Tahunan</button>
            </div>
        </div>
        
        <div class="p-6 h-[450px]">
            <canvas id="mainChart"></canvas>
        </div>
    </div>

    {{-- INSIGHT TABLE --}}
    <div class="card-standard overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-tight">Ringkasan Analisis <span id="selectedPeriodText" class="text-blue-600">Mingguan</span></h3>
        </div>

        <div class="overflow-x-auto">
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


    <!-- <div class="card-standard p-6 border-l-4 border-l-blue-600">
        <div class="flex items-center gap-3 mb-3">
            <h4 class="text-sm font-bold text-gray-900 uppercase">Interpretasi Model Prophet</h4>
        </div>
        <p class="text-sm text-gray-600 leading-relaxed">
            Berdasarkan analisis data historis untuk komoditas <strong>{{ $selectedCommodity }}</strong>, model mendeteksi pola musiman dan tren jangka panjang. Hasil proyeksi ini dimaksudkan sebagai alat bantu dalam pengambilan kebijakan stabilisasi pasokan dan harga di wilayah Provinsi Riau.
        </p>
    </div> -->
    {{-- Analisis Deskriptif --}}
    <div class="card-standard p-6 border-l-4 border-l-blue-600">
        <div class="flex items-center gap-3 mb-3">
            <h4 class="text-sm font-bold text-gray-900 uppercase">Interpretasi Model Prophet</h4>
    </div>
    
    <p id="dynamic-analysis" class="text-sm text-gray-600 leading-relaxed">
        Berdasarkan analisis data historis untuk komoditas <strong>{{ $selectedCommodity }}</strong>, 
        <span id="analysis-text">model sedang memproses tren terbaru...</span>
    </p>
</div>

</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
/* =========================================================
    DATA INITIALIZATION
   ========================================================= */
const chartData = {
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

let currentPeriod = 'weekly';
let mainChart = null;

/* =========================================================
    CHART FUNCTIONS
   ========================================================= */
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
                    pointRadius: 0,
                    fill: '+1', // Mengisi ke dataset 'Rentang Atas'
                    tension: 0.4,
                    order: 4
                },
                {
                    label: 'Rentang Atas',
                    data: data.upper,
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    borderColor: 'transparent',
                    pointRadius: 0,
                    fill: false,
                    tension: 0.4,
                    order: 4
                },
                {
                    label: 'Harga Aktual',
                    data: data.actual,
                    borderColor: '#043277',
                    backgroundColor: gradientActual,
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 0,
                    pointHoverRadius: 7,
                    pointHoverBackgroundColor: '#043277',
                    pointHoverBorderColor: '#fff',
                    pointHoverBorderWidth: 3,
                    order: 1
                },
                {
                    label: 'Harga Prediksi',
                    data: data.forecast,
                    borderColor: '#f97316',
                    backgroundColor: gradientForecast,
                    borderDash: [8, 4],
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 0,
                    pointHoverRadius: 7,
                    pointHoverBackgroundColor: '#f97316',
                    pointHoverBorderColor: '#fff',
                    pointHoverBorderWidth: 3,
                    order: 2
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
                    font: { size: 16, weight: '600', family: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif' },
                    padding: { top: 15, bottom: 20 }
                },
                legend: {
                    display: true,
                    position: 'top',
                    align: 'end',
                    labels: {
                        boxWidth: 12,
                        usePointStyle: true,
                        filter: (item) => !item.text.includes('Rentang')
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(255, 255, 255, 0.95)',
                    titleColor: '#1e293b',
                    bodyColor: '#475569',
                    borderColor: '#e2e8f0',
                    borderWidth: 1,
                    padding: 12,
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
                    ticks: { callback: value => 'Rp ' + value.toLocaleString('id-ID') } 
                },
                x: { 
                    ticks: { maxRotation: 45, autoSkip: true, maxTicksLimit: 15 } 
                }
            }
        }
    });
}

function changeChartPeriod(period) {
    currentPeriod = period;
    
    // Logic Filter Button Active
    document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
    const activeBtn = document.getElementById(`btn-${period}`);
    if (activeBtn) activeBtn.classList.add('active');
    
    // Logic Selected Period Text
    const periodText = { 'weekly': 'Mingguan', 'monthly': 'Bulanan', 'yearly': 'Tahunan' };
    const textEl = document.getElementById('selectedPeriodText');
    if (textEl) textEl.textContent = periodText[period];
    
    // Logic Transition Effect
    const chartContainer = document.getElementById('mainChart').parentElement;
    if (chartContainer) chartContainer.style.opacity = '0.5';
    
    setTimeout(() => {
        initializeChart();
        updateInsightTable();
        updateMetricCards();
        updateDescriptionText();
        if (chartContainer) chartContainer.style.opacity = '1';
    }, 150);
}

/* =========================================================
    TABLE & INSIGHT FUNCTIONS
   ========================================================= */
function updateInsightTable() {
    const data = chartData[currentPeriod];
    const tbody = document.getElementById('insightTableBody');
    if (!tbody || !data.labels || !data.labels.length) return;
    
    tbody.innerHTML = '';
    const start = Math.max(0, data.labels.length - 10);
    
    for (let i = start; i < data.labels.length; i++) {
        const actual = data.actual[i];
        const forecast = data.forecast[i];
        const lower = data.lower[i];
        const upper = data.upper[i];
        const diff = (actual && forecast) ? forecast - actual : null;
        
        let insight = 'Stabil';
        let insightClass = 'insight-stabil';
        if (diff !== null) {
            if (diff > 500) { insight = 'Naik'; insightClass = 'insight-naik'; }
            else if (diff < -500) { insight = 'Turun'; insightClass = 'insight-turun'; }
        }

        const rangePrice = (lower && upper && lower !== upper) 
            ? `<div class="text-[10px] text-slate-400 mt-1">Rentang Prediksi:</div>
               <div class="text-[11px] text-slate-500 italic">Rp ${Math.round(lower).toLocaleString('id-ID')} - Rp ${Math.round(upper).toLocaleString('id-ID')}</div>`
            : '';
        
        tbody.innerHTML += `
            <tr class="border-b border-slate-50 hover:bg-slate-50 transition-colors">
                <td class="px-6 py-4 text-slate-500">${data.labels[i]}</td>
                <td class="px-6 py-4 text-right">${actual ? 'Rp ' + actual.toLocaleString('id-ID') : '—'}</td>
                <td class="px-6 py-4 text-right">
                    <span class="text-[#043277] font-bold">Rp ${forecast ? forecast.toLocaleString('id-ID') : '—'}</span>
                    ${rangePrice}
                </td>
                <td class="px-6 py-4 text-right ${diff > 0 ? 'text-red-600' : diff < 0 ? 'text-emerald-600' : 'text-slate-500'}">
                    ${diff !== null ? (diff > 0 ? '+' : '') + diff.toLocaleString('id-ID') : '—'}
                </td>
                <td class="px-6 py-4 text-center">
                    <span class="insight-badge ${insightClass}">${insight}</span>
                </td>
            </tr>`;
    }
}

/* =========================================================
    ANALYSIS & METRICS
   ========================================================= */
function updateDescriptionText() {
    const commodity = "{{ $selectedCommodity }}";
    const data = chartData[currentPeriod];
    const forecasts = data.forecast.filter(v => v !== null);
    const analysisElement = document.getElementById('analysis-text');
    
    if (!analysisElement || forecasts.length < 2) return;

    const firstF = forecasts[0];
    const lastF = forecasts[forecasts.length - 1];
    const diff = lastF - firstF;
    const percent = ((diff / firstF) * 100).toFixed(1);
    const periodName = { 'weekly': 'Mingguan', 'monthly': 'Bulanan', 'yearly': 'Tahunan' }[currentPeriod];
    
    let trend = diff > 0 ? `tren <b class="text-red-600">peningkatan</b> (${percent}%)` : 
                diff < 0 ? `tren <b class="text-emerald-600">penurunan</b> (${Math.abs(percent)}%)` : "stabil";

    analysisElement.innerHTML = `Berdasarkan analisis model Prophet pada periode <b>${periodName}</b>, harga <b>${commodity}</b> menunjukkan ${trend}.`;
}

function updateMetricCards() {
    const data = chartData[currentPeriod];
    const actuals = data.actual.filter(v => v !== null);
    
    const avgEl = document.getElementById('avg-price-value');
    const maxEl = document.getElementById('max-price-value');

    if (actuals.length > 0) {
        const avg = actuals.reduce((a, b) => a + b, 0) / actuals.length;
        if (avgEl) avgEl.textContent = 'Rp ' + Math.round(avg).toLocaleString('id-ID');
        if (maxEl) maxEl.textContent = 'Rp ' + Math.max(...actuals).toLocaleString('id-ID');
    }
}

/* =========================================================
    FORM HANDLING
   ========================================================= */
function handleCommodityChange() { triggerSubmit(); }

function triggerSubmit() {
    const content = document.getElementById('real-content');
    if (content) { content.classList.add('opacity-30', 'blur-[2px]'); }
    const form = document.getElementById('mainForm');
    if (form) form.submit();
}

/* =========================================================
    INITIALIZATION ON LOAD
   ========================================================= */
document.addEventListener('DOMContentLoaded', function() {
    initializeChart();
    updateInsightTable();
    updateMetricCards();
    updateDescriptionText();
});
</script>
@endsection