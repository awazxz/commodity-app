@extends('layouts.app')

@section('content')
<style>
    body {
        margin: 0 !important;
        padding: 0 !important;
    }
    
    .min-h-screen {
        padding: 0 !important;
        margin: 0 !important;
    }
    
    .max-w-7xl {
        max-width: 100vw !important;
        padding-left: 1.5rem !important;
        padding-right: 1.5rem !important;
        padding-top: 0.5rem !important;
        margin: 0 !important;
    }
    
    * {
        margin-top: 0 !important;
    }
    
    .mb-6:first-child {
        margin-top: 0 !important;
        padding-top: 0 !important;
    }

    select {
        margin-top: 0.25rem !important;
    }
</style>

<div class="min-h-screen bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6">
        
        <div class="md:flex md:items-center md:justify-between mb-6">
            <div class="flex-1 min-w-0">
                <h2 class="text-2xl font-bold leading-7 text-gray-800 sm:text-3xl sm:truncate">
                    Laporan Harga Komoditas
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    Analisis perbandingan harga aktual dan prediksi periode mingguan.
                </p>
            </div>
            <div class="mt-4 flex md:mt-0 md:ml-4">
                <a href="{{ route('laporan.komoditas.cetak', request()->all()) }}" target="_blank" 
                   class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                    <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Cetak Laporan
                </a>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg mb-8 border border-gray-200">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4 flex items-center">
                    <span class="p-2 bg-blue-100 rounded-lg mr-3">
                        <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </span>
                    Ringkasan Analisis Deskriptif
                </h3>
                
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-3 mb-6">
                    <div class="px-4 py-5 bg-green-50 shadow-sm rounded-lg overflow-hidden border border-green-100">
                        <dt class="text-sm font-medium text-green-800 truncate">Prediksi Naik</dt>
                        <dd class="mt-1 text-3xl font-semibold text-green-900">{{ $analisis['naik'] }}</dd>
                    </div>
                    <div class="px-4 py-5 bg-red-50 shadow-sm rounded-lg overflow-hidden border border-red-100">
                        <dt class="text-sm font-medium text-red-800 truncate">Prediksi Turun</dt>
                        <dd class="mt-1 text-3xl font-semibold text-red-900">{{ $analisis['turun'] }}</dd>
                    </div>
                    <div class="px-4 py-5 bg-gray-50 shadow-sm rounded-lg overflow-hidden border border-gray-100">
                        <dt class="text-sm font-medium text-gray-800 truncate">Harga Stabil</dt>
                        <dd class="mt-1 text-3xl font-semibold text-gray-900">{{ $analisis['stabil'] }}</dd>
                    </div>
                </div>

                <div class="bg-blue-50 border-l-4 border-blue-400 p-4">
                    <div class="flex">
                        <div class="ml-3">
                            <p class="text-sm text-blue-700 font-medium italic">
                                {{ $analisis['kesimpulan'] }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white shadow rounded-lg mb-8 border border-gray-200">
            <form action="{{ route('laporan.komoditas.index') }}" method="GET" class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Komoditas</label>
                        <select name="komoditas_id" class="block w-full border-gray-300 focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md border py-2 px-2 shadow-sm">
                            <option value="">Semua Komoditas</option>
                            @foreach($daftarKomoditas as $k)
                                <option value="{{ $k->id }}" {{ request('komoditas_id') == $k->id ? 'selected' : '' }}>
                                    {{ $k->nama_komoditas }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Tahun</label>
                        <select name="tahun" class="block w-full border-gray-300 focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md border py-2 px-2 shadow-sm">
                            @for($i = date('Y'); $i >= date('Y')-3; $i--)
                                <option value="{{ $i }}" {{ request('tahun', date('Y')) == $i ? 'selected' : '' }}>{{ $i }}</option>
                            @endfor
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Bulan</label>
                        <select name="bulan" class="block w-full border-gray-300 focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md border py-2 px-2 shadow-sm">
                            <option value="">Semua Bulan</option>
                            @foreach(['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'] as $idx => $namaBulan)
                                <option value="{{ $idx + 1 }}" {{ request('bulan') == ($idx + 1) ? 'selected' : '' }}>{{ $namaBulan }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Minggu Ke-</label>
                        <select name="minggu" class="block w-full border-gray-300 focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md border py-2 px-2 shadow-sm">
                            <option value="">Semua Minggu</option>
                            @foreach([1,2,3,4,5] as $w)
                                <option value="{{ $w }}" {{ request('minggu') == $w ? 'selected' : '' }}>Minggu {{ $w }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex items-end space-x-2">
                        <button type="submit" class="flex-1 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                            Filter
                        </button>
                        <a href="{{ route('laporan.komoditas.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <div class="flex flex-col">
            <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                    <div class="shadow overflow-hidden border-b border-gray-200 sm:rounded-lg bg-white">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Periode</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Komoditas & Varian</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Harga Aktual</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Harga Prediksi</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Trend</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200 text-sm">
                                @forelse($data as $item)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-600">
                                        <div class="font-medium text-gray-900">
                                            Minggu {{ \Carbon\Carbon::parse($item->tanggal)->weekOfMonth }}, 
                                            {{ \Carbon\Carbon::parse($item->tanggal)->translatedFormat('F') }}
                                        </div>
                                        <div class="text-xs text-gray-400">{{ $item->tanggal ? \Carbon\Carbon::parse($item->tanggal)->format('d/m/Y') : '' }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-semibold text-gray-900">{{ $item->nama_komoditas }}</div>
                                        <div class="text-xs text-gray-500">{{ $item->nama_varian }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-right font-medium">
                                        {{ $item->harga_aktual ? 'Rp ' . number_format($item->harga_aktual, 0, ',', '.') : '-' }}
                                    </td>
                                    <td class="px-6 py-4 text-right font-medium text-blue-600">
                                        {{ $item->harga_prediksi ? 'Rp ' . number_format($item->harga_prediksi, 0, ',', '.') : '-' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        @php 
                                            $aktual = (float)($item->harga_aktual ?? 0);
                                            $prediksi = (float)($item->harga_prediksi ?? 0);
                                            $diff = $prediksi - $aktual; 
                                        @endphp

                                        @if($aktual > 0 && $prediksi > 0)
                                            @if($diff > 0)
                                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">▲ Naik</span>
                                            @elseif($diff < 0)
                                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">▼ Turun</span>
                                            @else
                                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">● Stabil</span>
                                            @endif
                                        @else
                                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-50 text-gray-400">-</span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center text-gray-500 italic">Data tidak ditemukan untuk periode {{ request('minggu') ? 'Minggu ke-'.request('minggu') : '' }} {{ request('bulan') ? 'Bulan '.request('bulan') : '' }} {{ request('tahun') }}.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                        
                        @if($data->hasPages())
                        <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                            {{ $data->appends(request()->all())->links() }}
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</div>
@endsection