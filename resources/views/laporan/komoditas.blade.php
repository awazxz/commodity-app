@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="md:flex md:items-center md:justify-between mb-8">
            <div class="flex-1 min-w-0">
                <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                    Laporan Komoditas
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    Analisis perbandingan harga aktual dan prediksi harian.
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
                            <p class="text-sm text-blue-700 font-medium">
                                {{ $analisis['kesimpulan'] }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white shadow rounded-lg mb-8 border border-gray-200">
            <form action="{{ route('laporan.komoditas.index') }}" method="GET" class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div class="col-span-1 md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Filter Komoditas</label>
                        <select name="komoditas_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md border shadow-sm">
                            <option value="">Semua Komoditas</option>
                            @foreach($daftarKomoditas as $k)
                                <option value="{{ $k->id }}" {{ request('komoditas_id') == $k->id ? 'selected' : '' }}>
                                    {{ $k->nama_komoditas }} - {{ $k->nama_varian }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Pilih Tanggal</label>
                        <input type="date" name="tanggal" value="{{ request('tanggal') }}" 
                               class="mt-1 block w-full border-gray-300 focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md border py-2 px-3 shadow-sm">
                    </div>
                    <div class="flex items-end space-x-2">
                        <button type="submit" class="w-full inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Filter
                        </button>
                        <a href="{{ route('laporan.komoditas.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
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
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Komoditas & Varian</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Harga Aktual</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Harga Prediksi</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Insight</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200 text-sm">
                                @forelse($data as $item)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-600">
                                        {{ \Carbon\Carbon::parse($item->tanggal)->format('d/m/Y') }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-semibold text-gray-900">{{ $item->nama_komoditas }}</div>
                                        <div class="text-xs text-gray-500">{{ $item->nama_varian }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-right font-medium">Rp {{ number_format($item->harga_aktual, 0, ',', '.') }}</td>
                                    <td class="px-6 py-4 text-right font-medium text-blue-600">Rp {{ number_format($item->harga_prediksi ?? 0, 0, ',', '.') }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        @php $diff = ($item->harga_prediksi ?? 0) - $item->harga_aktual; @endphp
                                        @if($diff > 0)
                                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Naik</span>
                                        @elseif($diff < 0)
                                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Turun</span>
                                        @else
                                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Stabil</span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center text-gray-500 italic">Data tidak ditemukan.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                        
                        @if($data->hasPages())
                        <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                            {{ $data->links() }}
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</div>
@endsection