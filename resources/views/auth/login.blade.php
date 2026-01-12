<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Otentikasi User - BPS Provinsi Riau</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, sans-serif;
            letter-spacing: -0.01em;
        }
        .tracking-bps { letter-spacing: 0.15em; }
        input { transition: all 0.2s ease; }
        /* Memastikan footer tidak tergeser pada layar kecil */
        @media (max-height: 700px) {
            main { padding-top: 1rem; padding-bottom: 1rem; }
            .login-card { padding: 1.5rem !important; }
        }
    </style>
</head>
<body class="bg-[#F4F7FA] min-h-screen flex flex-col">

    <nav class="bg-[#00337C] py-2.5 px-6 flex items-center shadow-lg border-b-2 border-[#FFA500]">
        <div class="flex items-center space-x-3">
            <img src="https://upload.wikimedia.org/wikipedia/commons/2/28/Lambang_Badan_Pusat_Statistik_%28BPS%29_Indonesia.svg" alt="Logo BPS" class="h-9">
            <div class="border-l border-white/20 pl-3 text-white">
                <h1 class="font-extrabold text-[15px] leading-tight uppercase tracking-tight">Badan Pusat Statistik</h1>
                <p class="text-[10px] font-medium opacity-90 uppercase tracking-bps text-[#FFA500]">Provinsi Riau</p>
            </div>
        </div>
    </nav>

    <main class="flex-grow flex items-center justify-center px-4 py-6">
        <div class="w-full max-w-[370px]"> 
            <div class="login-card bg-white p-8 rounded-[40px] shadow-[0_20px_60px_rgba(0,51,124,0.08)] border border-gray-100">
                
                <div class="flex justify-center mb-5">
                    <div class="bg-[#F0F7FF] p-4 rounded-2xl ring-4 ring-[#F0F7FF]/50">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 11C12 12.6569 10.6569 14 9 14C7.34315 14 6 12.6569 6 11C6 7.13401 9.13401 4 13 4C16.866 4 20 7.13401 20 11C20 11.4142 19.6569 11.75 19.25 11.75C18.8431 11.75 18.5 11.4142 18.5 11C18.5 7.96243 16.0376 5.5 13 5.5C9.96243 5.5 7.5 7.96243 7.5 11C7.5 11.8284 8.17157 12.5 9 12.5C9.82843 12.5 10.5 11.8284 10.5 11V10C10.5 9.17157 11.1716 8.5 12 8.5C12.8284 8.5 13.5 9.17157 13.5 10V14.5M15 11V16M15 19.5C15 20.3284 14.3284 21 13.5 21C12.6716 21 12 20.3284 12 19.5" stroke="#00337C" stroke-width="1.8" stroke-linecap="round"/>
                            <path d="M5 14.5C5 17.5 7 20 10 20M19 14.5C19 17.5 17 20 14 20" stroke="#00337C" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </div>
                </div>

                <div class="text-center mb-7">
                    <h2 class="text-[22px] font-black text-slate-800 uppercase tracking-tight leading-none">Otentikasi User</h2>
                    <p class="text-slate-400 text-[10px] font-bold mt-2 uppercase tracking-bps italic">Sistem Analisis Prediksi</p>
                </div>

                <form method="POST" action="{{ route('login') }}" class="space-y-5">
                    @csrf
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase mb-2 ml-1 tracking-widest">Alamat Email</label>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400 group-focus-within:text-[#00337C]">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.206" />
                                </svg>
                            </div>
                            <input type="email" name="email" value="{{ old('email') }}" required autofocus
                                class="w-full pl-11 pr-4 py-3.5 bg-[#F8FAFC] border border-slate-200 rounded-2xl focus:bg-white focus:border-[#00337C] focus:ring-4 focus:ring-blue-50 outline-none text-sm font-medium text-slate-700 placeholder:text-slate-300"
                                placeholder="nama@bps.go.id">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase mb-2 ml-1 tracking-widest">Sandi</label>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400 group-focus-within:text-[#00337C]">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                            <input type="password" name="password" required
                                class="w-full pl-11 pr-4 py-3.5 bg-[#F8FAFC] border border-slate-200 rounded-2xl focus:bg-white focus:border-[#00337C] focus:ring-4 focus:ring-blue-50 outline-none text-sm font-medium text-slate-700 placeholder:text-slate-300"
                                placeholder="••••••••">
                        </div>
                    </div>

                    <div class="flex items-center px-1">
                        <input id="remember_me" type="checkbox" name="remember" class="w-4 h-4 rounded border-slate-300 text-[#00337C] focus:ring-[#00337C]">
                        <label for="remember_me" class="ml-2.5 text-[11px] font-semibold text-slate-500 uppercase tracking-tight cursor-pointer">Tetap Login</label>
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="w-full bg-[#00337C] text-white font-extrabold py-4 rounded-2xl hover:bg-[#002861] active:scale-[0.98] transition-all uppercase tracking-[0.15em] shadow-lg shadow-blue-900/10 text-xs">
                            Masuk Ke Sistem
                        </button>
                    </div>
                </form>
            </div>
            
            <p class="text-center mt-5 text-slate-400 text-[10px] font-bold uppercase tracking-widest">
                Butuh Bantuan? <a href="#" class="text-[#00337C] hover:underline decoration-2 underline-offset-4">Hubungi IT Support</a>
            </p>
        </div>
    </main>

    <footer class="bg-white border-t border-slate-200 py-5 mt-auto">
        <div class="max-w-7xl mx-auto px-8 text-center">
            <p class="text-slate-500 text-[10px] font-bold uppercase tracking-[0.25em]">
                © 2026 Badan Pusat Statistik - Provinsi Riau
            </p>
        </div>
    </footer>

</body>
</html>