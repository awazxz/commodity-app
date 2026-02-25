<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SIGMAPRO | BPS Provinsi Riau</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * { font-family: 'Inter', sans-serif; }
        
        .font-arial-bold-italic {
            font-family: Arial, Helvetica, sans-serif !important;
            font-weight: bold !important;
            font-style: italic !important;
        }

        /* Pattern Background BPS agar selaras dengan halaman kontak */
        .bps-bg {
            background-color: #f8fafc;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%2300337c' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        input:focus {
            border-color: #00a2e9 !important;
            box-shadow: 0 0 0 4px rgba(0, 162, 233, 0.1) !important;
        }
    </style>
</head>
<body class="bps-bg min-h-screen flex flex-col text-gray-800">

    <nav class="bg-[#00337C] py-3 px-6 shadow-lg border-b-4 border-[#00a2e9]">
        <div class="max-w-7xl mx-auto flex items-center space-x-4">
            <img src="https://upload.wikimedia.org/wikipedia/commons/2/28/Lambang_Badan_Pusat_Statistik_%28BPS%29_Indonesia.svg" 
                 alt="Logo BPS" class="h-12 drop-shadow-md">
            <div class="border-l border-white/30 pl-4 hidden md:block">
                <p class="text-white font-arial-bold-italic text-[17px] uppercase leading-tight tracking-tight">Badan Pusat Statistik</p>
                <p class="text-white font-arial-bold-italic text-[13px] uppercase tracking-[0.15em]">Provinsi Riau</p>
            </div>
        </div>
    </nav>

    <main class="flex-grow flex items-center justify-center px-4 py-12">
        <div class="w-full max-w-md"> 
            
            <div class="bg-white rounded-2xl shadow-xl shadow-blue-900/10 overflow-hidden border border-gray-100">
                <!-- <div class="h-2 bg-gradient-to-r from-[#00337C] via-[#00a2e9] to-[#00337C]"></div> -->

                    <div class="p-8 md:p-10">
                        <div class="text-center mb-8">
                            <div class="flex justify-center mb-4">
                                <img src="{{ asset('images/chartlogo.png') }}" 
                                    alt="SIGMAPRO Logo" 
                                    class="w-32 h-auto object-contain"
                                    onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                
                                <svg style="display:none;" class="w-16 h-16 text-[#00337C] mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                            </div>

                            <h1 class="text-3xl font-extrabold text-[#00337C] tracking-tight uppercase">SIGMAPRO</h1>
                            <p class="text-[13px] font-bold text-[#00a2e9] uppercase tracking-[0.1em] mt-1">Sistem Prediksi Komoditas</p>
                            
                            <div class="w-16 h-0.5 bg-gray-100 mx-auto mt-4"></div>
                            
                            <p class="text-gray-500 text-sm mt-3 italic">Silakan masuk dengan akun SSO BPS Anda</p>
                    </div>
                                    
                    @if ($errors->any())
                    <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-100 flex items-center space-x-3">
                        <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                        <p class="text-xs font-medium text-red-700 leading-tight">Email atau kata sandi salah. Silakan coba lagi.</p>
                    </div>
                    @endif

                    <form method="POST" action="{{ route('login') }}" class="space-y-5">
                        @csrf
                        
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Alamat Email</label>
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400 group-focus-within:text-[#00a2e9] transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.206" />
                                    </svg>
                                </div>
                                <input type="email" name="email" value="{{ old('email') }}" required autofocus
                                       class="w-full pl-11 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl outline-none transition-all text-sm placeholder-gray-400"
                                       placeholder="contoh@bps.go.id">
                            </div>
                        </div>

                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <label class="text-xs font-bold text-gray-700 uppercase tracking-wider">Kata Sandi</label>
                            </div>
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400 group-focus-within:text-[#00a2e9] transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                </div>
                                <input type="password" name="password" required
                                       class="w-full pl-11 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl outline-none transition-all text-sm placeholder-gray-400"
                                       placeholder="••••••••">
                            </div>
                        </div>

                        <div class="flex items-center">
                            <input id="remember_me" type="checkbox" name="remember" 
                                   class="w-4 h-4 rounded border-gray-300 text-[#00337C] focus:ring-[#00a2e9]">
                            <label for="remember_me" class="ml-2 text-sm text-gray-600 cursor-pointer">Biarkan saya tetap masuk</label>
                        </div>

                        <button type="submit" 
                                class="w-full bg-[#00337C] hover:bg-[#002861] text-white font-bold py-3.5 rounded-xl shadow-lg shadow-blue-900/20 transition-all active:scale-[0.98] flex items-center justify-center space-x-2">
                            <span>Masuk ke Sistem</span>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>
                        </button>
                    </form>

                    <div class="mt-8 text-center pt-6 border-t border-gray-50">
                        <p class="text-sm text-gray-500">
                            Ada masalah akses? 
                            <a href="{{ route('contact.admin') }}" class="text-[#00a2e9] font-bold hover:underline">Hubungi IT Support</a>
                        </p>
                    </div>
                </div>
            </div>
            
            <p class="text-center mt-8 text-gray-400 text-[11px] uppercase tracking-widest leading-loose px-4">
                Gunakan peramban versi terbaru untuk pengalaman prediksi harga komoditas yang optimal
            </p>
        </div>
    </main>

    <footer class="bg-white border-t border-gray-200 py-6 mt-auto">
        <div class="max-w-7xl mx-auto px-6 text-center">
            <p class="text-gray-500 text-xs font-medium">
                &copy; 2026 <span class="text-[#00337C] font-bold">Badan Pusat Statistik Provinsi Riau</span>
            </p>
    </footer>

</body>
</html>