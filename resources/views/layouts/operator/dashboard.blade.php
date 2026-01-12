@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="bg-white p-8 rounded-2xl border border-slate-200 shadow-[0_10px_30px_-10px_rgba(88,168,50,0.2)] flex justify-between items-center">
        <div>
            <span class="bg-green-100 text-green-700 text-[10px] font-black px-3 py-1 rounded-full uppercase tracking-widest">Operator Workspace</span>
            <h2 class="text-3xl font-extrabold text-slate-800 tracking-tight mt-2">DASHBOARD PENGOLAHAN DATA</h2>
            <p class="text-slate-400 text-xs font-bold uppercase tracking-tighter mt-1 italic">Mulai dengan membersihkan missing value atau langsung ke database harga.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <div class="relative bg-white p-8 rounded-2xl border border-slate-200 shadow-sm hover:-translate-y-2 transition-all">
            <div class="absolute -top-4 -left-4 w-10 h-10 bg-slate-800 text-white rounded-full flex items-center justify-center font-black italic">01</div>
            <h3 class="text-sm font-black text-[#043277] uppercase mb-4 tracking-widest">Cek Missing Value</h3>
            <p class="text-xs text-slate-500 leading-relaxed mb-6 italic font-medium">Pastikan tidak ada data kosong agar akurasi model Prophet maksimal.</p>
            <a href="/missing-value" class="block text-center py-3 bg-slate-50 text-[#043277] rounded-xl text-[10px] font-extrabold uppercase hover:bg-blue-600 hover:text-white transition">Buka Modul</a>
        </div>

        <div class="relative bg-white p-8 rounded-2xl border border-slate-200 shadow-sm hover:-translate-y-2 transition-all">
            <div class="absolute -top-4 -left-4 w-10 h-10 bg-slate-800 text-white rounded-full flex items-center justify-center font-black italic">02</div>
            <h3 class="text-sm font-black text-[#043277] uppercase mb-4 tracking-widest">Analisis Outlier</h3>
            <p class="text-xs text-slate-500 leading-relaxed mb-6 italic font-medium">Hapus lonjakan harga yang tidak wajar akibat kesalahan input.</p>
            <a href="/analisis-outlier" class="block text-center py-3 bg-slate-50 text-[#043277] rounded-xl text-[10px] font-extrabold uppercase hover:bg-blue-600 hover:text-white transition">Buka Modul</a>
        </div>

        <div class="relative bg-[#58a832] p-8 rounded-2xl shadow-xl hover:-translate-y-2 transition-all">
            <div class="absolute -top-4 -left-4 w-10 h-10 bg-white text-[#58a832] rounded-full flex items-center justify-center font-black italic shadow-lg">03</div>
            <h3 class="text-sm font-black text-white uppercase mb-4 tracking-widest">Update Database</h3>
            <p class="text-white/80 text-xs leading-relaxed mb-6 italic font-medium">Simpan hasil pembersihan ke database pusat harga BPS.</p>
            <a href="/database-harga" class="block text-center py-3 bg-white/20 text-white border border-white/40 rounded-xl text-[10px] font-extrabold uppercase hover:bg-white hover:text-[#58a832] transition">Update Sekarang</a>
        </div>
    </div>
</div>
@endsection