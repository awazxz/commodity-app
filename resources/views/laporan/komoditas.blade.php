@extends('layouts.app')

@section('content')
<style>
    body { margin: 0 !important; padding: 0 !important; }
    .min-h-screen { padding: 0 !important; margin: 0 !important; }
    .max-w-7xl { max-width: 100vw !important; padding-left: 1.5rem !important; padding-right: 1.5rem !important; padding-top: 0.5rem !important; margin: 0 !important; }
    * { margin-top: 0 !important; }
    select { margin-top: 0.25rem !important; }
    tr.has-both      { background-color: #f0fdf4; }
    tr.only-forecast { background-color: #eff6ff; }

    html.dark .bg-white            { background-color: #1e2433 !important; }
    html.dark .bg-gray-50          { background-color: #161d2e !important; }
    html.dark .text-gray-800,
    html.dark .text-gray-900       { color: #f3f4f6 !important; }
    html.dark .text-gray-500,
    html.dark .text-gray-600       { color: #9ca3af !important; }
    html.dark .text-gray-700       { color: #d1d5db !important; }
    html.dark .border-gray-200     { border-color: #2d3748 !important; }
    html.dark table thead          { background-color: #161d2e !important; }
    html.dark table thead th       { color: #6b7280 !important; }
    html.dark table tbody tr:hover { background-color: rgba(255,255,255,0.03) !important; }
    html.dark .shadow              { box-shadow: 0 1px 3px rgba(0,0,0,0.4) !important; }
    html.dark select, html.dark input { background-color: #2d3748 !important; border-color: #4a5568 !important; color: #e2e8f0 !important; }
    html.dark tr.has-both          { background-color: rgba(16,185,129,0.08) !important; }
    html.dark tr.only-forecast     { background-color: rgba(59,130,246,0.08) !important; }
</style>

@php
    $namaBulanList = [
        1  => __('messages.bulan_januari'),
        2  => __('messages.bulan_februari'),
        3  => __('messages.bulan_maret'),
        4  => __('messages.bulan_april'),
        5  => __('messages.bulan_mei'),
        6  => __('messages.bulan_juni'),
        7  => __('messages.bulan_juli'),
        8  => __('messages.bulan_agustus'),
        9  => __('messages.bulan_september'),
        10 => __('messages.bulan_oktober'),
        11 => __('messages.bulan_november'),
        12 => __('messages.bulan_desember'),
    ];
@endphp

<div class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6">

        {{-- ══════════════════════════════════════
             HEADER + TOMBOL EKSPOR (DROPDOWN)
        ═══════════════════════════════════════ --}}
        <div class="md:flex md:items-center md:justify-between mb-6">
            <div class="flex-1 min-w-0">
                <h2 class="text-2xl font-bold leading-7 text-gray-800 dark:text-gray-100 sm:text-3xl sm:truncate">
                    {{ __('messages.laporan_harga') }}
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('messages.analisis_deskriptif') }}
                </p>
            </div>

            {{-- Dropdown Ekspor --}}
            <div class="mt-4 md:mt-0 md:ml-4" x-data="{ open: false }">

                <button @click="open = !open" @click.outside="open = false"
                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                    <svg class="-ml-1 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    {{ __('messages.cetak_laporan') }}
                    <svg class="ml-2 h-4 w-4 transition-transform duration-200"
                         :class="open ? 'rotate-180' : ''"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                <div x-show="open"
                     x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="transform opacity-0 scale-95"
                     x-transition:enter-end="transform opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-75"
                     x-transition:leave-start="transform opacity-100 scale-100"
                     x-transition:leave-end="transform opacity-0 scale-95"
                     class="absolute right-6 mt-2 w-48 rounded-md shadow-lg bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5 z-50"
                     style="display: none;">
                    <div class="py-1">

                        {{-- Cetak Browser --}}
                        <a href="{{ route('laporan.komoditas.cetak', request()->all()) }}"
                           target="_blank"
                           class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                            <svg class="mr-3 h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                            </svg>
                            {{ __('messages.cetak_laporan') }}
                        </a>

                        <div class="border-t border-gray-100 dark:border-gray-700 my-1"></div>

                        {{-- Unduh PDF --}}
                        <a href="{{ route('laporan.komoditas.pdf', request()->all()) }}"
                           class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                            <svg class="mr-3 h-4 w-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Unduh PDF
                        </a>

                        {{-- Unduh CSV --}}
                        <a href="{{ route('laporan.komoditas.csv', request()->all()) }}"
                           class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                            <svg class="mr-3 h-4 w-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Unduh CSV
                        </a>

                    </div>
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════
             RINGKASAN ANALISIS
        ═══════════════════════════════════════ --}}
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg mb-8 border border-gray-200 dark:border-gray-700">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100 mb-4 flex items-center">
                    <span class="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg mr-3">
                        <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                    </span>
                    {{ __('messages.ringkasan_analisis_desk') }}
                </h3>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-3 mb-6">
                    <div class="px-4 py-5 bg-green-50 dark:bg-green-900/20 shadow-sm rounded-lg border border-green-100 dark:border-green-800">
                        <dt class="text-sm font-medium text-green-800 dark:text-green-300 truncate">{{ __('messages.prediksi_naik') }}</dt>
                        <dd class="mt-1 text-3xl font-semibold text-green-900 dark:text-green-200">{{ $analisis['naik'] }}</dd>
                    </div>
                    <div class="px-4 py-5 bg-red-50 dark:bg-red-900/20 shadow-sm rounded-lg border border-red-100 dark:border-red-800">
                        <dt class="text-sm font-medium text-red-800 dark:text-red-300 truncate">{{ __('messages.prediksi_turun') }}</dt>
                        <dd class="mt-1 text-3xl font-semibold text-red-900 dark:text-red-200">{{ $analisis['turun'] }}</dd>
                    </div>
                    <div class="px-4 py-5 bg-gray-50 dark:bg-gray-700/50 shadow-sm rounded-lg border border-gray-100 dark:border-gray-700">
                        <dt class="text-sm font-medium text-gray-800 dark:text-gray-300 truncate">{{ __('messages.harga_stabil') }}</dt>
                        <dd class="mt-1 text-3xl font-semibold text-gray-900 dark:text-gray-100">{{ $analisis['stabil'] }}</dd>
                    </div>
                </div>

                <div class="bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-400 p-4 rounded-r-lg">
                    @php
                        $totalAn = ($analisis['naik'] ?? 0) + ($analisis['turun'] ?? 0) + ($analisis['stabil'] ?? 0);
                        $maxVal  = max($analisis['naik'] ?? 0, $analisis['turun'] ?? 0, $analisis['stabil'] ?? 0);
                    @endphp
                    @if($totalAn > 0)
                        @if($maxVal === ($analisis['naik'] ?? 0))
                            <p class="text-sm text-blue-700 dark:text-blue-300 font-medium italic">
                                {{ __('messages.kesimpulan_naik', ['naik' => $analisis['naik'], 'turun' => $analisis['turun'], 'stabil' => $analisis['stabil'], 'total' => $totalAn]) }}
                            </p>
                        @elseif($maxVal === ($analisis['turun'] ?? 0))
                            <p class="text-sm text-blue-700 dark:text-blue-300 font-medium italic">
                                {{ __('messages.kesimpulan_turun', ['naik' => $analisis['naik'], 'turun' => $analisis['turun'], 'stabil' => $analisis['stabil'], 'total' => $totalAn]) }}
                            </p>
                        @else
                            <p class="text-sm text-blue-700 dark:text-blue-300 font-medium italic">
                                {{ __('messages.kesimpulan_stabil', ['naik' => $analisis['naik'], 'turun' => $analisis['turun'], 'stabil' => $analisis['stabil'], 'total' => $totalAn]) }}
                            </p>
                        @endif
                    @else
                        <p class="text-sm text-blue-700 dark:text-blue-300 font-medium italic">
                            {{ __('messages.kesimpulan_kosong') }}
                        </p>
                    @endif
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════
             FILTER
        ═══════════════════════════════════════ --}}
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-8 border border-gray-200 dark:border-gray-700">
            <form action="{{ route('laporan.komoditas.index') }}" method="GET" class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('messages.komoditas') }}</label>
                        <select name="komoditas_id" class="mt-1 block w-full border-gray-300 dark:border-gray-600 focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md border py-2 px-2 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <option value="">{{ __('messages.semua_komoditas') }}</option>
                            @foreach($daftarKomoditas as $k)
                                <option value="{{ $k->id }}" {{ request('komoditas_id') == $k->id ? 'selected' : '' }}>
                                    {{ $k->nama_komoditas }}{{ $k->nama_varian ? ' - ' . $k->nama_varian : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('messages.tahun') }}</label>
                        <select name="tahun" class="mt-1 block w-full border-gray-300 dark:border-gray-600 focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md border py-2 px-2 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            @for($i = $tahunMax; $i >= $tahunMin; $i--)
                                <option value="{{ $i }}" {{ $tahun == $i ? 'selected' : '' }}>{{ $i }}</option>
                            @endfor
                        </select>
                    </div> -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('messages.tahun') }}</label>
                        <select name="tahun" class="mt-1 block w-full border-gray-300 dark:border-gray-600 focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md border py-2 px-2 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            @php $tahunSekarang = (int) date('Y'); @endphp
                            @foreach($tahunTersedia as $t)
                                <option value="{{ $t }}" {{ (int)$tahun === (int)$t ? 'selected' : '' }}>
                                    {{ $t }}{{ (int)$t > $tahunSekarang ? ' (Forecast)' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('messages.bulan') }}</label>
                        <select name="bulan" class="mt-1 block w-full border-gray-300 dark:border-gray-600 focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md border py-2 px-2 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <option value="">{{ __('messages.semua_bulan') }}</option>
                            @foreach($namaBulanList as $num => $nama)
                                <option value="{{ $num }}" {{ request('bulan') == $num ? 'selected' : '' }}>{{ $nama }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('messages.minggu_ke') }}</label>
                        <select name="minggu" class="mt-1 block w-full border-gray-300 dark:border-gray-600 focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md border py-2 px-2 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <option value="">{{ __('messages.semua_minggu') }}</option>
                            @foreach([1,2,3,4,5] as $w)
                                <option value="{{ $w }}" {{ request('minggu') == $w ? 'selected' : '' }}>
                                    {{ __('messages.minggu_ke') }} {{ $w }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex items-end space-x-2">
                        <button type="submit" class="flex-1 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 transition-colors">
                            {{ __('messages.filter') }}
                        </button>
                        <a href="{{ route('laporan.komoditas.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                            {{ __('messages.reset') }}
                        </a>
                    </div>

                </div>
            </form>
        </div>

        {{-- ══════════════════════════════════════
             LEGENDA
        ═══════════════════════════════════════ --}}
        <div class="flex items-center gap-4 mb-3 text-xs text-gray-500 dark:text-gray-400">
            <span class="flex items-center gap-1">
                <span class="inline-block w-3 h-3 rounded bg-green-100 dark:bg-green-900/30 border border-green-300 dark:border-green-700"></span>
                {{ __('messages.aktual&prediksi') }}
            </span>
            <span class="flex items-center gap-1">
                <span class="inline-block w-3 h-3 rounded bg-blue-100 dark:bg-blue-900/30 border border-blue-300 dark:border-blue-700"></span>
                {{ __('messages.hanya_prediksi') }}
            </span>
            <span class="flex items-center gap-1">
                <span class="inline-block w-3 h-3 rounded bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600"></span>
                {{ __('messages.hanya_aktual') }}
            </span>
        </div>

        {{-- ══════════════════════════════════════
             TABEL DATA
        ═══════════════════════════════════════ --}}
        <div class="flex flex-col">
            <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                    <div class="shadow overflow-hidden border-b border-gray-200 dark:border-gray-700 sm:rounded-lg bg-white dark:bg-gray-800">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('messages.periode') }}</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('messages.komoditas_varian') }}</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('messages.harga_aktual') }}</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('messages.harga_prediksi') }}</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('messages.batas_bawah') }}</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('messages.batas_atas') }}</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('messages.trend') }}</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                @forelse($data as $item)
                                @php
                                    $hasAktual   = !is_null($item->harga_aktual)   && (float)$item->harga_aktual   > 0;
                                    $hasPrediksi = !is_null($item->harga_prediksi) && (float)$item->harga_prediksi > 0;
                                    $rowClass    = $hasAktual && $hasPrediksi ? 'has-both' : ($hasPrediksi ? 'only-forecast' : '');
                                    $tglCarbon   = \Carbon\Carbon::parse($item->tanggal)->locale(app()->getLocale());
                                    $namaBulan   = $namaBulanList[$tglCarbon->month] ?? $tglCarbon->format('F');
                                    $periodeStr  = $namaBulan . ' ' . $tglCarbon->year;
                                @endphp
                                <tr class="{{ $rowClass }} hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">

                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="font-medium text-gray-900 dark:text-gray-100">
                                            {{ __('messages.minggu_ke') }} {{ $tglCarbon->weekOfMonth }}, {{ $periodeStr }}
                                        </div>
                                        <div class="text-xs text-gray-400 dark:text-gray-500">{{ $tglCarbon->format('d/m/Y') }}</div>
                                    </td>

                                    <td class="px-6 py-4">
                                        <div class="font-semibold text-gray-900 dark:text-gray-100">{{ $item->nama_komoditas }}</div>
                                        @if($item->nama_varian)
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $item->nama_varian }}</div>
                                        @endif
                                    </td>

                                    <td class="px-6 py-4 text-right font-medium text-gray-800 dark:text-gray-300">
                                        @if($hasAktual)
                                            Rp {{ number_format($item->harga_aktual, 0, ',', '.') }}
                                        @else
                                            <span class="text-gray-300 dark:text-gray-600">-</span>
                                        @endif
                                    </td>

                                    <td class="px-6 py-4 text-right font-medium text-blue-600 dark:text-blue-400">
                                        @if($hasPrediksi)
                                            Rp {{ number_format($item->harga_prediksi, 0, ',', '.') }}
                                        @else
                                            <span class="text-gray-300 dark:text-gray-600">-</span>
                                        @endif
                                    </td>

                                    <td class="px-6 py-4 text-right text-xs text-gray-500 dark:text-gray-400">
                                        {{ ($item->harga_lower && (float)$item->harga_lower > 0) ? 'Rp ' . number_format($item->harga_lower, 0, ',', '.') : '-' }}
                                    </td>

                                    <td class="px-6 py-4 text-right text-xs text-gray-500 dark:text-gray-400">
                                        {{ ($item->harga_upper && (float)$item->harga_upper > 0) ? 'Rp ' . number_format($item->harga_upper, 0, ',', '.') : '-' }}
                                    </td>

                                    <td class="px-6 py-4 text-center whitespace-nowrap">
                                        @if($hasAktual && $hasPrediksi)
                                            @php
                                                $diff      = (float)$item->harga_prediksi - (float)$item->harga_aktual;
                                                $threshold = (float)$item->harga_aktual * 0.01;
                                            @endphp
                                            @if($diff > $threshold)
                                                <span class="px-3 py-1 inline-flex text-xs font-semibold rounded-full bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300">
                                                    ▲ {{ __('messages.naik') }}
                                                </span>
                                            @elseif($diff < -$threshold)
                                                <span class="px-3 py-1 inline-flex text-xs font-semibold rounded-full bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300">
                                                    ▼ {{ __('messages.turun') }}
                                                </span>
                                            @else
                                                <span class="px-3 py-1 inline-flex text-xs font-semibold rounded-full bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300">
                                                    — {{ __('messages.stabil') }}
                                                </span>
                                            @endif
                                        @elseif($hasPrediksi)
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300">
                                                {{ __('messages.proyeksi') }}
                                            </span>
                                        @else
                                            <span class="text-gray-300 dark:text-gray-600">-</span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400 italic">
                                        {{ __('messages.data_tidak_ditemukan') }}
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>

                        @if($data->hasPages())
                        <div class="bg-white dark:bg-gray-800 px-4 py-3 border-t border-gray-200 dark:border-gray-700 sm:px-6">
                            {{ $data->appends(request()->all())->links() }}
                        </div>
                        @endif

                    </div>
                </div>
            </div>
        </div>

        <div class="h-8"></div>

    </div>
</div>
@endsection

<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>