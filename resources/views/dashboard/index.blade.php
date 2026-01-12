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

    .overflow-x-auto::-webkit-scrollbar {
        height: 6px;
    }

    .overflow-x-auto::-webkit-scrollbar-thumb {
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
</style>

<div id="skeleton-overlay" class="hidden space-y-4">
    <div class="h-16 bg-slate-200 animate-pulse rounded-xl"></div>
    <div class="h-[400px] bg-slate-200 animate-pulse rounded-2xl"></div>
</div>

<div id="real-content" class="space-y-5 transition-all duration-500 font-clean">

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
                <p class="text-[9px] text-slate-400 font-medium-clean uppercase tracking-widest mt-1">
                    Peramalan Berbasis Data • Dasbor Publik
                </p>
            </div>
        </div>
        <button onclick="window.print()" class="bg-[#58a832] hover:bg-green-600 text-white px-4 py-2 rounded-lg font-medium-clean text-[9px] uppercase tracking-widest shadow-sm flex items-center gap-2">
            <i class="fas fa-print"></i> Cetak Laporan
        </button>
    </div>

    <form action="{{ route('predict') }}" method="POST" id="mainForm" class="mt-6 pt-5 border-t border-slate-100">
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

        <input type="hidden" name="changepoint_prior_scale" value="{{ $cpScale }}">
        <input type="hidden" name="seasonality_prior_scale" value="{{ $seasonScale }}">
        <input type="hidden" name="seasonality_mode" value="{{ $seasonalityMode }}">
        <input type="hidden" name="weekly" value="{{ $weeklyActive ? 'on' : 'off' }}">
        <input type="hidden" name="yearly" value="{{ $yearlyActive ? 'on' : 'off' }}">
    </form>
</div>

{{-- METRIC CARDS --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <div class="card-custom hover-card p-4">
        <p class="text-[8px] uppercase text-slate-400 tracking-widest">Rata-rata Harga</p>
        <p class="text-lg font-medium-clean text-[#043277]">
            Rp {{ number_format($avgPrice,0,',','.') }}
        </p>
    </div>

    <div class="card-custom hover-card p-4">
        <p class="text-[8px] uppercase text-red-400 tracking-widest">Harga Tertinggi</p>
        <p class="text-lg font-medium-clean text-red-600">
            Rp {{ number_format($maxPrice,0,',','.') }}
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
            {{ $trendDir }}
        </p>
    </div>
</div>

{{-- GRAFIK UTAMA DENGAN FILTER PERIODE --}}
<div class="card-custom overflow-hidden">
    <div class="px-5 py-4 border-b bg-slate-50/50 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h3 class="text-[10px] font-medium-clean uppercase tracking-widest text-[#043277]">
                Grafik Tren Harga & Proyeksi
            </h3>
            <p class="text-[9px] text-slate-400 mt-1">
                Visualisasi data historis dan prediksi masa depan
            </p>
        </div>

        <div class="flex gap-2">
            <button onclick="changeChartPeriod('daily')" class="filter-btn active" id="btn-daily">
                <i class="fas fa-calendar-day mr-1"></i> Harian
            </button>
            <button onclick="changeChartPeriod('weekly')" class="filter-btn" id="btn-weekly">
                <i class="fas fa-calendar-week mr-1"></i> Mingguan
            </button>
            <button onclick="changeChartPeriod('monthly')" class="filter-btn" id="btn-monthly">
                <i class="fas fa-calendar-alt mr-1"></i> Bulanan
            </button>
            <button onclick="changeChartPeriod('yearly')" class="filter-btn" id="btn-yearly">
                <i class="fas fa-calendar mr-1"></i> Tahunan
            </button>
        </div>
    </div>
    
    <div class="p-6 h-[450px]">
        <canvas id="mainChart"></canvas>
    </div>
</div>

{{-- TABEL INSIGHT PROYEKSI --}}
<div class="card-custom overflow-hidden">
    <div class="px-5 py-4 border-b bg-slate-50/50">
        <div class="flex items-center gap-2 mb-1">
            <i class="fas fa-table text-[#043277] text-xs"></i>
            <h3 class="text-[10px] font-medium-clean text-[#043277] uppercase tracking-widest">
                Ringkasan Proyeksi & Analisis Tren
            </h3>
        </div>
        <p class="text-[9px] text-slate-400">
            Data proyeksi berdasarkan periode waktu terpilih: <strong id="selectedPeriodText">Harian</strong>
        </p>
    </div>

    <div class="overflow-x-auto">
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

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
/* =========================================================
   DATA INITIALIZATION
   ========================================================= */
const chartData = {
    daily: {
        labels: {!! json_encode($chartLabels) !!},
        actual: {!! json_encode($actualData) !!},
        forecast: {!! json_encode($forecastData) !!},
        lower: {!! json_encode($lowerBand ?? array_fill(0, count($chartLabels), null)) !!},
        upper: {!! json_encode($upperBand ?? array_fill(0, count($chartLabels), null)) !!}
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
   CHART FUNCTIONS
   ========================================================= */
function initializeChart() {
    const canvas = document.getElementById('mainChart');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    const data = chartData[currentPeriod];

    // Gradient setup
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
                legend: {
                    display: true,
                    position: 'top',
                    align: 'end',
                    labels: {
                        boxWidth: 12,
                        boxHeight: 12,
                        padding: 15,
                        font: {
                            size: 11,
                            weight: '600'
                        },
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
                    titleFont: {
                        size: 11,
                        weight: '600'
                    },
                    bodyFont: {
                        size: 11
                    },
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
                    grid: { 
                        color: '#f1f5f9',
                        drawBorder: false
                    },
                    ticks: {
                        color: '#94a3b8',
                        font: { 
                            size: 10,
                            weight: '500'
                        },
                        padding: 8,
                        callback: value => 'Rp ' + value.toLocaleString('id-ID')
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: {
                        color: '#94a3b8',
                        font: { 
                            size: 9,
                            weight: '500'
                        },
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
    
    // Update button states
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.getElementById(`btn-${period}`).classList.add('active');
    
    // Update period text
    const periodText = {
        'daily': 'Harian',
        'weekly': 'Mingguan',
        'monthly': 'Bulanan',
        'yearly': 'Tahunan'
    };
    document.getElementById('selectedPeriodText').textContent = periodText[period];
    
    // Update chart
    initializeChart();
    
    // Update table
    updateInsightTable();
}

/* =========================================================
   TABLE FUNCTIONS
   ========================================================= */
function updateInsightTable() {
    const data = chartData[currentPeriod];
    const tbody = document.getElementById('insightTableBody');
    
    if (!tbody || !data.labels.length) return;
    
    tbody.innerHTML = '';
    
    // Ambil 10 data terakhir
    const start = Math.max(0, data.labels.length - 10);
    
    for (let i = start; i < data.labels.length; i++) {
        const actual = data.actual[i];
        const forecast = data.forecast[i];
        const diff = actual && forecast ? forecast - actual : null;
        
        // Tentukan insight
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

/* =========================================================
   FORM HANDLERS
   ========================================================= */
function handleCommodityChange() {
    triggerSubmit();
}

function triggerSubmit() {
    const content = document.getElementById('real-content');
    const skeleton = document.getElementById('skeleton-overlay');

    if (content && skeleton) {
        content.classList.add('opacity-30', 'blur-[2px]');
        skeleton.classList.remove('hidden');
    }

    setTimeout(() => {
        document.getElementById('mainForm').submit();
    }, 100);
}

/* =========================================================
   INITIALIZATION
   ========================================================= */
document.addEventListener('DOMContentLoaded', function() {
    initializeChart();
    updateInsightTable();
});
</script>

@endsection