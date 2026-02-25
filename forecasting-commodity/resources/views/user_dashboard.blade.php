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

    .insight-naik   { background: #fee2e2; color: #991b1b; }
    .insight-turun  { background: #dcfce7; color: #166534; }
    .insight-stabil { background: #f3f4f6; color: #1f2937; }

    .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: #f8fafc; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in { animation: fadeIn 0.4s ease-out; }

    /* Alert styles */
    .alert-success {
        background: #dcfce7; border: 1px solid #bbf7d0; color: #166534;
        padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;
    }
    .alert-error {
        background: #fee2e2; border: 1px solid #fecaca; color: #991b1b;
        padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;
    }

    /* Status badge API */
    .api-badge-ok {
        display: inline-flex; align-items: center; gap: 0.375rem;
        padding: 0.25rem 0.75rem;
        background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534;
        border-radius: 9999px; font-size: 10px; font-weight: 700;
        text-transform: uppercase; letter-spacing: 0.05em;
    }
    .api-badge-offline {
        display: inline-flex; align-items: center; gap: 0.375rem;
        padding: 0.25rem 0.75rem;
        background: #fff7ed; border: 1px solid #fed7aa; color: #9a3412;
        border-radius: 9999px; font-size: 10px; font-weight: 700;
        text-transform: uppercase; letter-spacing: 0.05em;
    }
</style>
</div>

<div id="skeleton-overlay" class="hidden fixed inset-0 bg-white/50 z-50 flex items-center justify-center opacity-0">
    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
</div>

<div class="dashboard-container space-y-6 animate-fade-in">

    {{-- ✅ TAB NAVIGATION --}}
    <div class="card-standard p-1.5 flex gap-1">
        <a href="{{ route('user.dashboard') }}"
           class="flex-1 flex items-center justify-center gap-2 py-2.5 px-4 rounded-md text-xs font-bold uppercase tracking-wider transition-all bg-blue-600 text-white shadow-sm">
            <i class="fas fa-chart-line"></i>
            <span>Insight & Prediksi</span>
        </a>
        <a href="{{ route('laporan.komoditas.index') }}"
           class="flex-1 flex items-center justify-center gap-2 py-2.5 px-4 rounded-md text-xs font-bold uppercase tracking-wider transition-all text-gray-500 hover:bg-gray-100">
            <i class="fas fa-file-alt"></i>
            <span>Laporan Komoditas</span>
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

    {{-- ==========================================
         HEADER CARD & FILTER
         ========================================== --}}
    <div class="card-standard p-6">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div class="flex items-center gap-4">
                <div class="bg-blue-600 p-3 rounded-lg text-white shadow-md">
                    <i class="fas fa-chart-line text-xl"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-gray-900 leading-none">
                        Sistem Analisis Prediksi Harga Komoditas
                    </h2>
                    <p class="text-xs text-violet-500 font-medium uppercase tracking-wider mt-1.5">
                        Panel Pengguna — BPS Provinsi Riau
                    </p>
                </div>
            </div>
            {{-- Status API --}}
            @if($forecastSuccess ?? false)
                <span class="api-badge-ok">
                    <i class="fas fa-circle" style="font-size:6px"></i> Prophet Aktif
                </span>
            @elseif(($trendDir ?? '') === 'API Offline')
                <span class="api-badge-offline">
                    <i class="fas fa-exclamation-triangle" style="font-size:8px"></i> API Offline
                </span>
            @endif
        </div>

        {{-- FILTER FORM --}}
        <form action="{{ route('user.dashboard') }}" method="GET" id="mainForm" class="mt-6 pt-6 border-t border-gray-100">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                <div class="md:col-span-4">
                    <label class="text-xs font-semibold text-gray-700 uppercase mb-2 block tracking-tight">
                        Komoditas Terpilih
                    </label>
                    <select name="commodity" onchange="handleCommodityChange()"
                            class="w-full bg-gray-50 border border-gray-300 rounded-md py-2 px-3 text-sm font-medium text-gray-900 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all">
                        @forelse($allCommodities ?? [] as $kom)
                            <option value="{{ $kom->id }}"
                                {{ ($selectedCommodityId ?? null) == $kom->id ? 'selected' : '' }}>
                                {{ $kom->nama_varian ? $kom->nama_komoditas . ' (' . $kom->nama_varian . ')' : $kom->nama_komoditas }}
                            </option>
                        @empty
                            <option value="">-- Tidak ada komoditas --</option>
                        @endforelse
                    </select>
                </div>

                <div class="md:col-span-4">
                    <label class="text-xs font-semibold text-gray-700 uppercase mb-2 block tracking-tight">
                        Periode Prediksi (Hari)
                    </label>
                    <input type="number" name="periods" value="{{ request('periods', 30) }}"
                           min="7" max="365"
                           class="w-full bg-gray-50 border border-gray-300 rounded-md py-2 px-3 text-sm font-medium text-gray-900 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all">
                </div>

                <div class="md:col-span-4 flex items-end">
                    <button type="submit"
                            class="w-full bg-blue-600 text-white py-2 px-4 rounded-md text-sm font-bold uppercase tracking-wider hover:bg-blue-700 transition-all shadow-sm flex items-center justify-center gap-2">
                        <i class="fas fa-sync-alt"></i>
                        Perbarui Prediksi
                    </button>
                </div>
            </div>
        </form>
    </div>

    {{-- ✅ Stats Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
        <div class="card-standard hover-card p-5">
            <p class="text-[10px] uppercase text-gray-500 font-bold tracking-wider mb-2">Rata-rata Harga</p>
            <p class="text-xl font-bold text-gray-900">Rp {{ number_format($avgPrice ?? 0, 0, ',', '.') }}</p>
            <p class="text-[10px] text-gray-400 mt-1">Data historis</p>
        </div>

        <div class="card-standard hover-card p-5">
            <p class="text-[10px] uppercase text-gray-500 font-bold tracking-wider mb-2">Harga Tertinggi</p>
            <p class="text-xl font-bold text-red-600">Rp {{ number_format($maxPrice ?? 0, 0, ',', '.') }}</p>
        </div>

        <div class="card-standard hover-card p-5">
            <p class="text-[10px] uppercase text-gray-500 font-bold tracking-wider mb-2">Harga Terendah</p>
            <p class="text-xl font-bold text-emerald-600">Rp {{ number_format($minPrice ?? 0, 0, ',', '.') }}</p>
        </div>

        <div class="bg-blue-600 rounded-lg p-5 text-white shadow-lg hover-card">
            <p class="text-[10px] uppercase text-blue-100 font-bold tracking-wider mb-2">Arah Tren</p>
            <p class="text-sm font-bold uppercase flex items-center gap-2">
                @php
                    $trendIcon = match(strtolower($trendDir ?? 'n/a')) {
                        'naik'  => 'fa-arrow-trend-up',
                        'turun' => 'fa-arrow-trend-down',
                        default => 'fa-minus'
                    };
                @endphp
                <i class="fas {{ $trendIcon }}"></i>
                {{ $trendDir ?? 'N/A' }}
            </p>
        </div>
    </div>

    <div class="grid grid-cols-12 gap-5">

        {{-- PANEL KIRI: Info Komoditas + Statistik --}}
        <div class="col-span-12 lg:col-span-4 space-y-4">

            {{-- Info Komoditas --}}
            <div class="card-standard p-5">
                <h4 class="text-sm font-bold text-gray-900 uppercase tracking-tight mb-5 pb-3 border-b border-gray-100">
                    Informasi Komoditas
                </h4>
                <div class="space-y-3">
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg border border-gray-100">
                        <span class="text-xs text-gray-500 font-semibold uppercase">Komoditas</span>
                        <span class="text-xs font-bold text-blue-700 bg-blue-50 px-2 py-1 rounded max-w-[60%] text-right">
                            {{ $selectedCommodity ?? '—' }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg border border-gray-100">
                        <span class="text-xs text-gray-500 font-semibold uppercase">Periode</span>
                        <span class="text-xs font-bold text-gray-700">
                            {{ \Carbon\Carbon::parse($startDate ?? now())->format('d/m/Y') }}
                            <span class="text-gray-400 mx-1">→</span>
                            {{ \Carbon\Carbon::parse($endDate ?? now())->format('d/m/Y') }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg border border-gray-100">
                        <span class="text-xs text-gray-500 font-semibold uppercase">Harga Min</span>
                        <span class="text-xs font-bold text-emerald-700">Rp {{ number_format($minPrice ?? 0, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg border border-gray-100">
                        <span class="text-xs text-gray-500 font-semibold uppercase">Harga Max</span>
                        <span class="text-xs font-bold text-red-600">Rp {{ number_format($maxPrice ?? 0, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg border border-gray-100">
                        <span class="text-xs text-gray-500 font-semibold uppercase">Status Prediksi</span>
                        @if($forecastSuccess ?? false)
                            <span class="text-xs font-bold text-emerald-700 bg-emerald-50 px-2 py-1 rounded">✓ Tersedia</span>
                        @else
                            <span class="text-xs font-bold text-orange-600 bg-orange-50 px-2 py-1 rounded">⚠ Tidak Tersedia</span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Notif API Offline --}}
            @if(!($forecastSuccess ?? false) && ($trendDir ?? '') === 'API Offline')
            <div class="card-standard p-5 border-l-4 border-l-orange-400">
                <div class="flex items-start gap-3">
                    <i class="fas fa-exclamation-triangle text-orange-400 mt-0.5"></i>
                    <div>
                        <p class="text-xs font-bold text-orange-700 uppercase mb-1">Model Prediksi Offline</p>
                        <p class="text-xs text-gray-500 leading-relaxed">
                            Server Python (Prophet API) sedang tidak aktif. Grafik menampilkan data historis saja tanpa proyeksi.
                        </p>
                    </div>
                </div>
            </div>
            @endif

            {{-- Link Akses Cepat --}}
            <div class="card-standard p-5">
                <h4 class="text-xs font-bold text-gray-700 uppercase tracking-tight mb-3">Akses Cepat</h4>
                <div class="space-y-2">
                    <a href="{{ route('laporan.komoditas.index') }}"
                       class="flex items-center gap-3 p-3 bg-emerald-50 border border-emerald-200 rounded-lg hover:bg-emerald-100 transition-colors group">
                        <div class="bg-emerald-500 text-white p-2 rounded-lg group-hover:bg-emerald-600 transition-colors flex-shrink-0">
                            <i class="fas fa-file-alt text-sm"></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-emerald-800">Laporan Komoditas</p>
                            <p class="text-[10px] text-emerald-600 mt-0.5">Lihat data lengkap & cetak laporan</p>
                        </div>
                        <i class="fas fa-chevron-right text-emerald-400 ml-auto text-xs"></i>
                    </a>
                    <a href="{{ route('laporan.komoditas.cetak') }}" target="_blank"
                       class="flex items-center gap-3 p-3 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition-colors group">
                        <div class="bg-blue-500 text-white p-2 rounded-lg group-hover:bg-blue-600 transition-colors flex-shrink-0">
                            <i class="fas fa-print text-sm"></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-blue-800">Cetak Laporan</p>
                            <p class="text-[10px] text-blue-600 mt-0.5">Buka halaman cetak PDF</p>
                        </div>
                        <i class="fas fa-chevron-right text-blue-400 ml-auto text-xs"></i>
                    </a>
                </div>
            </div>
        </div>

        {{-- PANEL KANAN: Chart --}}
        <div class="col-span-12 lg:col-span-8 card-standard overflow-hidden flex flex-col">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex flex-col lg:flex-row justify-between items-center gap-4 flex-shrink-0">
                <div>
                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-tight">Visualisasi Tren & Proyeksi</h3>
                    <p class="text-xs text-gray-500">{{ $selectedCommodity ?? '—' }} — Data Historis vs Proyeksi Prophet</p>
                </div>

                <div class="flex bg-white border border-gray-300 p-1 rounded-md shadow-sm">
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
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-tight">
                Ringkasan Analisis <span id="selectedPeriodText" class="text-blue-600">Mingguan</span>
            </h3>
            <span class="text-xs text-gray-400">{{ $selectedCommodity ?? '—' }}</span>
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

    {{-- Interpretasi --}}
    <div class="card-standard p-6 border-l-4 border-l-blue-600">
        <div class="flex items-center gap-3 mb-3">
            <h4 class="text-sm font-bold text-gray-900 uppercase">Interpretasi Analisis Tren</h4>
        </div>
        <p class="text-sm text-gray-600 leading-relaxed">
            Berdasarkan analisis data historis untuk komoditas <strong>{{ $selectedCommodity ?? '—' }}</strong>,
            model mendeteksi tren harga <strong>{{ strtolower($trendDir ?? 'n/a') }}</strong>
            dengan rata-rata harga <strong>Rp {{ number_format($avgPrice ?? 0, 0, ',', '.') }}</strong>
            pada periode {{ \Carbon\Carbon::parse($startDate ?? now())->format('d/m/Y') }}
            s/d {{ \Carbon\Carbon::parse($endDate ?? now())->format('d/m/Y') }}.
            @if($forecastSuccess ?? false)
                Proyeksi menggunakan algoritma <strong>Facebook Prophet</strong> dengan rentang kepercayaan 95%.
            @else
                <span class="text-orange-600">Proyeksi tidak tersedia karena API sedang offline.</span>
            @endif
        </p>
    </div>

    {{-- Komoditas Lainnya --}}
    <div class="card-standard overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-tight">Komoditas Tersedia</h3>
            <p class="text-xs text-gray-400 mt-1">Klik untuk melihat analisis komoditas lainnya</p>
        </div>
        <div class="p-4">
            <div class="flex flex-wrap gap-2">
                @forelse($allCommodities ?? [] as $kom)
                    <a href="{{ route('user.dashboard', ['commodity' => $kom->id]) }}"
                       class="inline-flex items-center px-3 py-2 rounded-lg text-xs font-semibold transition-all border
                              {{ ($selectedCommodityId ?? null) == $kom->id
                                 ? 'bg-blue-600 text-white border-blue-600 shadow-sm'
                                 : 'bg-gray-50 text-gray-600 border-gray-200 hover:bg-blue-50 hover:border-blue-300 hover:text-blue-700' }}">
                        {{ $kom->nama_varian ? $kom->nama_komoditas . ' ' . $kom->nama_varian : $kom->nama_komoditas }}
                    </a>
                @empty
                    <p class="text-xs text-gray-400 italic">Tidak ada komoditas tersedia.</p>
                @endforelse
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// ✅ Data dari controller (variabel sesuai ForecastingController asli)
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

const forecastSuccess = {{ ($forecastSuccess ?? false) ? 'true' : 'false' }};

let currentPeriod = 'weekly';
let mainChart     = null;

function triggerSubmit() {
    document.getElementById('real-content').classList.add('opacity-30');
    document.getElementById('skeleton-overlay').classList.remove('hidden');
    document.getElementById('skeleton-overlay').style.opacity = '1';
    setTimeout(() => document.getElementById('mainForm').submit(), 100);
}

function handleCommodityChange() {
    triggerSubmit();
}

// =========================================================
// CHART
// =========================================================

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

    const datasets = [
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
        }
    ];

    // Tambahkan dataset prediksi hanya jika API sukses
    if (forecastSuccess) {
        datasets.unshift({
            label: 'Rentang Bawah',
            data: data.lower,
            backgroundColor: 'rgba(34, 197, 94, 0.08)',
            borderColor: 'transparent',
            fill: '+1',
            pointRadius: 0,
            tension: 0.4
        });
        datasets.splice(1, 0, {
            label: 'Rentang Atas',
            data: data.upper,
            borderColor: 'transparent',
            fill: false,
            pointRadius: 0,
            tension: 0.4
        });
        datasets.push({
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
        });
    }

    mainChart = new Chart(ctx, {
        type: 'line',
        data: { labels: data.labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            plugins: {
                title: {
                    display: true,
                    text: '{{ $selectedCommodity ?? "" }}',
                    color: '#043277',
                    font: { size: 14, weight: '600', family: 'Inter' },
                    padding: { top: 10, bottom: 15 }
                },
                legend: {
                    display: true,
                    position: 'top',
                    align: 'end',
                    labels: {
                        boxWidth: 12, boxHeight: 12, padding: 15,
                        font: { size: 11, weight: '600' }, color: '#64748b',
                        usePointStyle: true, pointStyle: 'circle',
                        filter: (item) => !item.text.includes('Rentang')
                    }
                },
                tooltip: {
                    backgroundColor: '#ffffff', titleColor: '#1e293b', bodyColor: '#475569',
                    borderColor: '#e2e8f0', borderWidth: 1, padding: 12, boxPadding: 6,
                    usePointStyle: true,
                    titleFont: { size: 11, weight: '600' }, bodyFont: { size: 11 },
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
                        color: '#94a3b8', font: { size: 10, weight: '500' }, padding: 8,
                        callback: value => 'Rp ' + value.toLocaleString('id-ID')
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: {
                        color: '#94a3b8', font: { size: 9, weight: '500' },
                        maxRotation: 45, minRotation: 0, autoSkip: true, maxTicksLimit: 15
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
        tbody.innerHTML = `<tr><td colspan="5" class="p-8 text-center text-gray-400 text-xs">Tidak ada data</td></tr>`;
        return;
    }

    const rows = [];
    for (let i = 0; i < data.labels.length; i++) {
        if (data.actual[i] !== null || data.forecast[i] !== null) {
            rows.push({
                label:    data.labels[i],
                actual:   data.actual[i],
                forecast: data.forecast[i]
            });
        }
    }

    const display = rows.slice(-10);

    display.forEach(row => {
        const { label, actual, forecast } = row;
        const diff = (actual !== null && actual !== undefined && forecast !== null && forecast !== undefined)
            ? (forecast - actual) : null;

        let insight = 'Stabil', insightClass = 'insight-stabil';
        if (diff !== null) {
            const threshold = (actual || 0) * 0.01;
            if (diff > threshold)       { insight = 'Naik';  insightClass = 'insight-naik'; }
            else if (diff < -threshold) { insight = 'Turun'; insightClass = 'insight-turun'; }
        } else if (forecast !== null && forecast !== undefined) {
            insight = 'Proyeksi'; insightClass = 'insight-stabil';
        }

        tbody.innerHTML += `
            <tr class="border-b border-gray-50 hover:bg-gray-50 animate-fade-in">
                <td class="px-6 py-4 text-gray-500 font-medium text-xs">${label}</td>
                <td class="px-6 py-4 text-right text-xs">
                    ${actual !== null && actual !== undefined ? 'Rp ' + actual.toLocaleString('id-ID') : '<span class="text-gray-300">—</span>'}
                </td>
                <td class="px-6 py-4 text-right text-blue-600 font-bold text-xs">
                    ${forecast !== null && forecast !== undefined ? 'Rp ' + forecast.toLocaleString('id-ID') : '<span class="text-gray-300">—</span>'}
                </td>
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
</script>

@endsection