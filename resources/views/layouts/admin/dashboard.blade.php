@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="bg-white p-8 rounded-2xl border border-slate-200 shadow-[0_10px_30px_-10px_rgba(4,50,119,0.2)] flex justify-between items-center">
        <div>
            <span class="bg-blue-100 text-blue-700 text-[10px] font-black px-3 py-1 rounded-full uppercase tracking-widest">Administrator Portal</span>
            <h2 class="text-3xl font-extrabold text-[#043277] tracking-tight mt-2">KONTROL SISTEM UTAMA</h2>
            <p class="text-slate-400 text-xs font-bold uppercase tracking-tighter mt-1">Pantau performa infrastruktur dan akses pengguna dalam satu panel.</p>
        </div>
        <div class="flex gap-4">
            <div class="text-right">
                <p class="text-[10px] font-black text-slate-400 uppercase">Server Status</p>
                <p class="text-sm font-bold text-green-500 uppercase flex items-center gap-2 justify-end">
                    <span class="w-2 h-2 bg-green-500 rounded-full animate-ping"></span> Optimal
                </p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-12 gap-6">
        <div class="col-span-12 md:col-span-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-sm hover:shadow-xl transition-all group">
            <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center mb-4 group-hover:scale-110 transition">
                <i class="fas fa-users text-xl"></i>
            </div>
            <h3 class="text-slate-400 text-[10px] font-black uppercase tracking-widest">Total Pengguna</h3>
            <p class="text-3xl font-black text-slate-800">24 <span class="text-xs text-slate-300 font-bold italic">User Aktif</span></p>
        </div>

        <div class="col-span-12 md:col-span-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-sm hover:shadow-xl transition-all group">
            <div class="w-12 h-12 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center mb-4 group-hover:scale-110 transition">
                <i class="fas fa-database text-xl"></i>
            </div>
            <h3 class="text-slate-400 text-[10px] font-black uppercase tracking-widest">Penyimpanan</h3>
            <p class="text-3xl font-black text-slate-800">1.2 <span class="text-xs text-slate-300 font-bold italic">GB Digunakan</span></p>
        </div>

        <div class="col-span-12 md:col-span-4 bg-[#043277] p-6 rounded-2xl shadow-lg hover:brightness-110 transition-all cursor-pointer">
            <div class="flex flex-col h-full justify-between">
                <h3 class="text-white/60 text-[10px] font-black uppercase tracking-widest">Shortcut Cepat</h3>
                <p class="text-white text-lg font-bold">MANAJEMEN USER <i class="fas fa-arrow-right ml-2 text-sm opacity-50"></i></p>
            </div>
        </div>
    </div>
</div>
@endsection