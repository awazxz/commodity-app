@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto italic">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="bps-blue px-8 py-6 text-white flex justify-between items-center">
            <div>
                <h3 class="text-lg font-black tracking-tight">Penanganan Missing Value</h3>
                <p class="text-xs opacity-80">Deteksi data null pada dataset terpilih</p>
            </div>
            <button class="bg-white text-blue-700 px-4 py-2 rounded-lg text-xs font-black shadow-lg hover:bg-blue-50 transition">
                <i class="fas fa-download mr-2 text-green-600"></i> Unduh File Bersih
            </button>
        </div>

        <div class="p-8 grid grid-cols-1 md:grid-cols-12 gap-8">
            <div class="md:col-span-4 space-y-4">
                <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Pilih Metode Penanganan</h4>
                <div class="space-y-2">
                    @foreach(['Hapus Baris', 'Isi Nilai Sebelumnya', 'Isi Nilai Berikutnya', 'Isi Rata-rata (Mean)', 'Isi Median', 'Isi Modus'] as $method)
                    <label class="flex items-center p-3 border border-slate-100 rounded-xl cursor-pointer hover:bg-blue-50 hover:border-blue-200 transition group">
                        <input type="radio" name="impute_method" class="w-4 h-4 text-blue-600">
                        <span class="ml-3 text-sm font-bold text-slate-600 group-hover:text-blue-700">{{ $method }}</span>
                    </label>
                    @endforeach
                </div>
                <button class="w-full bg-[#0059a4] text-white py-3 rounded-xl font-black text-xs mt-4 shadow-md uppercase tracking-widest">
                    Proses Sekarang
                </button>
            </div>

            <div class="md:col-span-8">
                <div class="bg-rose-50 border border-rose-100 p-4 rounded-xl mb-4 flex items-center gap-3">
                    <i class="fas fa-exclamation-triangle text-rose-500"></i>
                    <p class="text-xs text-rose-700 font-bold italic">Terdeteksi 12 data kosong pada kolom Harga (y). Segera lakukan tindakan.</p>
                </div>
                <div class="border rounded-xl overflow-hidden shadow-sm">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-slate-50 border-b font-black text-slate-500 uppercase tracking-tighter">
                            <tr>
                                <th class="p-4 border-r">No</th>
                                <th class="p-4 border-r">Tanggal (ds)</th>
                                <th class="p-4">Harga (y)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-slate-600 font-medium">
                            @for($i = 1; $i <= 5; $i++)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="p-3 border-r text-center">{{ $i }}</td>
                                <td class="p-3 border-r italic text-slate-400">2023-12-{{ $i }}</td>
                                <td class="p-3 bg-rose-50 text-rose-600 font-black italic">NULL / MISSING</td>
                            </tr>
                            @endfor
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection