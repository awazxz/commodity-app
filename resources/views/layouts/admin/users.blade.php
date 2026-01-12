@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-[0_10px_25px_-5px_rgba(0,0,0,0.1)] flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-extrabold text-[#043277] tracking-tight uppercase">Admin Control Center</h2>
            <p class="text-slate-400 text-[10px] font-bold uppercase tracking-widest mt-1 italic">Security & User Management</p>
        </div>
        <button class="bg-[#043277] text-white px-5 py-2.5 rounded-lg font-bold text-xs uppercase shadow-lg hover:shadow-blue-200 active:scale-95 transition-all">
            <i class="fas fa-user-plus mr-2"></i> Buat Akun Baru
        </button>
    </div>

    <div class="grid grid-cols-12 gap-6">
        <div class="col-span-12 lg:col-span-8">
            <div class="bg-white rounded-xl border border-slate-200 shadow-[0_20px_25px_-5px_rgba(0,0,0,0.05)] overflow-hidden transition-all hover:shadow-[0_25px_50px_-12px_rgba(0,0,0,0.1)]">
                <div class="p-5 border-b border-slate-50 flex justify-between items-center bg-slate-50/30">
                    <h3 class="text-xs font-black text-slate-800 uppercase tracking-widest italic">Daftar Pengguna Aktif</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-slate-50/50 border-b border-slate-100">
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase">Nama & Email</th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase">Role</th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase">Terakhir Aktif</th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase text-center">Opsi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-6 py-4">
                                    <p class="text-xs font-bold text-slate-700">Administrator Utama</p>
                                    <p class="text-[10px] text-slate-400">admin@bpsriau.go.id</p>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-[9px] font-black uppercase tracking-wider">Admin</span>
                                </td>
                                <td class="px-6 py-4 text-[10px] text-slate-500 font-bold">2 Menit Lalu</td>
                                <td class="px-6 py-4 flex justify-center gap-3">
                                    <i class="fas fa-shield-alt text-slate-300 hover:text-green-500 cursor-pointer"></i>
                                    <i class="fas fa-trash-alt text-slate-300 hover:text-red-500 cursor-pointer"></i>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-span-12 lg:col-span-4 sticky top-24">
            <div class="bg-[#1e293b] rounded-xl shadow-2xl overflow-hidden border border-slate-700">
                <div class="bg-slate-800 px-5 py-3 flex items-center gap-2 border-b border-slate-700">
                    <i class="fas fa-terminal text-green-400 text-xs"></i>
                    <span class="text-[10px] font-black text-slate-300 uppercase tracking-widest">Aktivitas Sistem Terbaru</span>
                </div>
                <div class="p-5 space-y-4 h-[400px] overflow-y-auto font-mono">
                    <div class="border-l-2 border-green-500 pl-3 py-1">
                        <p class="text-[9px] text-green-400">[10:15:22] LOGIN SUCCESS</p>
                        <p class="text-[10px] text-slate-400 italic">User "Operator Riau" masuk ke sistem.</p>
                    </div>
                    <div class="border-l-2 border-blue-500 pl-3 py-1">
                        <p class="text-[9px] text-blue-400">[10:18:05] DATA UPLOADED</p>
                        <p class="text-[10px] text-slate-400 italic">Operator mengunggah file "cabai_merah.xlsx".</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection