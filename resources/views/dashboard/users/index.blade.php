@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-[0_10px_25px_-5px_rgba(0,0,0,0.1)] flex flex-col md:flex-row justify-between items-center gap-4">
        <div>
            <h2 class="text-2xl font-extrabold text-[#043277] tracking-tight uppercase">Panel Kendali Otoritas</h2>
            <p class="text-slate-400 text-[10px] font-bold uppercase tracking-widest mt-1">Manajemen Pengguna & Status Sistem</p>
        </div>
        
        @if(auth()->user()->role === 'admin')
        <button class="bg-[#043277] hover:bg-blue-900 text-white px-6 py-2.5 rounded-lg font-bold text-xs uppercase transition shadow-md hover:shadow-blue-200 active:scale-95">
            <i class="fas fa-user-plus mr-2"></i> Tambah User Baru
        </button>
        @endif
    </div>

    <div class="grid grid-cols-12 gap-6 items-start">
        <div class="col-span-12 lg:col-span-4 sticky top-24 space-y-6">
            <div class="bg-white rounded-xl border border-slate-200 shadow-[0_20px_25px_-5px_rgba(0,0,0,0.05)] overflow-hidden">
                <div class="bg-slate-50 px-5 py-3 border-b border-slate-100 flex items-center gap-2">
                    <i class="fas fa-info-circle text-blue-600 text-xs"></i>
                    <span class="text-[11px] font-black text-slate-500 uppercase tracking-widest">Informasi Role</span>
                </div>
                <div class="p-6 space-y-4">
                    <div class="flex items-start gap-3 p-3 bg-blue-50 rounded-lg border border-blue-100">
                        <div class="bg-blue-600 text-white p-2 rounded shadow-sm text-xs">AD</div>
                        <div>
                            <p class="text-[11px] font-bold text-blue-900 uppercase">Administrator</p>
                            <p class="text-[10px] text-blue-700 leading-tight mt-1">Akses penuh ke sistem, manajemen user, dan pengaturan database.</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 p-3 bg-green-50 rounded-lg border border-green-100">
                        <div class="bg-green-600 text-white p-2 rounded shadow-sm text-xs">OP</div>
                        <div>
                            <p class="text-[11px] font-bold text-green-900 uppercase">Operator</p>
                            <p class="text-[10px] text-green-700 leading-tight mt-1">Bertanggung jawab atas upload data dan pemrosesan model Prophet.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-span-12 lg:col-span-8">
            <div class="bg-white rounded-xl border border-slate-200 shadow-[0_20px_25px_-5px_rgba(0,0,0,0.05)] overflow-hidden transition-all duration-300 hover:shadow-[0_25px_50px_-12px_rgba(0,0,0,0.1)]">
                <div class="p-6 border-b border-slate-50 flex justify-between items-center">
                    <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wide">Daftar Pengguna Aktif</h3>
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input type="text" placeholder="Cari user..." class="pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none focus:ring-2 focus:ring-blue-100 w-48 transition-all">
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50/50">
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">User</th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">Role</th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">Status</th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-slate-200 border-2 border-white shadow-sm flex items-center justify-center text-xs font-bold text-slate-500">A</div>
                                        <div>
                                            <p class="text-xs font-bold text-slate-700">Administrator Utama</p>
                                            <p class="text-[10px] text-slate-400">admin@bpsriau.go.id</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-xs">
                                    <span class="px-2.5 py-1 bg-blue-100 text-blue-700 rounded-md font-bold text-[9px] uppercase tracking-wider">Admin</span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-1.5 text-green-500">
                                        <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span>
                                        <span class="text-[10px] font-bold uppercase">Online</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex gap-2">
                                        <button class="p-2 text-slate-400 hover:text-blue-600 transition-colors"><i class="fas fa-edit text-xs"></i></button>
                                        <button class="p-2 text-slate-400 hover:text-red-600 transition-colors"><i class="fas fa-trash-alt text-xs"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-slate-200 border-2 border-white shadow-sm flex items-center justify-center text-xs font-bold text-slate-500">O</div>
                                        <div>
                                            <p class="text-xs font-bold text-slate-700">Operator Harga Riau</p>
                                            <p class="text-[10px] text-slate-400">operator@bpsriau.go.id</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-xs">
                                    <span class="px-2.5 py-1 bg-green-100 text-green-700 rounded-md font-bold text-[9px] uppercase tracking-wider">Operator</span>
                                </td>
                                <td class="px-6 py-4 text-xs">
                                    <div class="flex items-center gap-1.5 text-slate-400">
                                        <span class="w-1.5 h-1.5 bg-slate-300 rounded-full"></span>
                                        <span class="text-[10px] font-bold uppercase">Offline</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-xs">
                                    <div class="flex gap-2">
                                        <button class="p-2 text-slate-400 hover:text-blue-600 transition-colors"><i class="fas fa-edit text-xs"></i></button>
                                        <button class="p-2 text-slate-400 hover:text-red-600 transition-colors"><i class="fas fa-trash-alt text-xs"></i></button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="p-4 bg-slate-50/50 border-t border-slate-100 flex justify-center">
                    <button class="text-[10px] font-bold text-blue-600 uppercase tracking-widest hover:underline">Lihat Semua Pengguna</button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection