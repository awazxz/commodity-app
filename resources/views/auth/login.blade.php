<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SIGMAPRO | BPS Provinsi Riau</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        body {
            letter-spacing: 0.01em;
        }
          /* Arial Bold Italic untuk Header BPS */
        .font-arial-bold-italic {
            font-family: Arial, Helvetica, sans-serif !important;
            font-weight: bold !important;
            font-style: italic !important;
        }
        
        input { 
            transition: all 0.2s ease; 
        }
        
        /* Memastikan footer tidak tergeser pada layar kecil */
        @media (max-height: 700px) {
            main { padding-top: 1rem; padding-bottom: 1rem; }
            .login-card { padding: 1.5rem !important; }
        }
    </style>
</head>
<body class="bg-[#F5F5F5] min-h-screen flex flex-col">

    <!-- Header Navigation -->
    <nav class="bg-[#00337C] py-3 px-6 shadow-md">
        <div class="flex items-center space-x-4">
            <img src="https://upload.wikimedia.org/wikipedia/commons/2/28/Lambang_Badan_Pusat_Statistik_%28BPS%29_Indonesia.svg" 
                 alt="Logo BPS" 
                 class="h-12">
                <div class="border-l border-white/20 pl-4 hidden md:block">
                        <p class="text-white font-arial-bold-italic text-[17px] uppercase leading-tight tracking-tight">Badan Pusat Statistik</p>
                        <p class="text-white font-arial-bold-italic text-[13px] uppercase tracking-[0.15em]">Provinsi Riau</p>
                </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-grow flex items-center justify-center px-4 py-8">
        <div class="w-full max-w-md"> 
            <div class="login-card bg-white p-10 rounded-lg shadow-lg">
                
                <!-- Icon Header with Custom Image -->
                <div class="flex justify-center mb-6">
                    <img src="{{ asset('images/chartlogo.png') }}" 
                         alt="SIGMAPRO Logo" 
                         class="w-20 h-20 object-contain"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                    <!-- Fallback SVG icon jika gambar tidak ditemukan -->
                    <svg style="display:none;" width="80" height="80" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M3 3v18h18" stroke="#00337C" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M18 17l-5-5-3 3-4-4" stroke="#00337C" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="18" cy="17" r="1.5" fill="#00337C"/>
                        <circle cx="13" cy="12" r="1.5" fill="#00337C"/>
                        <circle cx="10" cy="15" r="1.5" fill="#00337C"/>
                        <circle cx="6" cy="11" r="1.5" fill="#00337C"/>
                    </svg>
                </div>
               
                <!-- Title -->
                <div class="text-center mb-8">
                    <h1 class="text-2xl font-bold text-[#00337C] mb-1">SIGMAPRO</h1>
                    <p class="text-base font-semibold text-gray-700 mb-2">Sistem Informasi Harga & Prediksi Komoditas</p>
                    <p class="text-gray-600 text-sm">Silakan masuk dengan akun BPS Anda</p>
                </div>
                
                <!-- Error Message (uncomment when using Laravel blade) -->
                @if ($errors->any())
                <div class="mb-6 p-4 rounded-md bg-red-50 border border-red-200">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-red-500 mt-0.5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z" />
                        </svg>
                        <div>
                            <p class="text-sm font-bold text-red-700">Login gagal</p>
                            <p class="text-sm text-red-600 mt-1">Email atau kata sandi yang Anda masukkan tidak valid.</p>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Login Form -->
                <form method="POST" action="{{ route('login') }}" class="space-y-5">
                    @csrf
                    
                    <!-- Email Field -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.206" />
                                </svg>
                            </div>
                            <input type="email" 
                                   name="email" 
                                   value="{{ old('email') }}" 
                                   required 
                                   autofocus
                                   class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-md focus:border-[#00337C] focus:ring-2 focus:ring-[#00337C]/20 outline-none text-sm"
                                   placeholder="nama@bps.go.id">
                        </div>
                    </div>

                    <!-- Password Field -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Kata sandi</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                            <input type="password" 
                                   name="password" 
                                   required
                                   class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-md focus:border-[#00337C] focus:ring-2 focus:ring-[#00337C]/20 outline-none text-sm"
                                   placeholder="Masukkan kata sandi">
                        </div>
                    </div>

                    <!-- Remember Me -->
                    <div class="flex items-center">
                        <input id="remember_me" 
                               type="checkbox" 
                               name="remember" 
                               class="w-4 h-4 rounded border-gray-300 text-[#00337C] focus:ring-[#00337C]">
                        <label for="remember_me" class="ml-2 text-sm text-gray-700 cursor-pointer">Ingat saya</label>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-2">
                        <button type="submit" 
                                class="w-full bg-[#00337C] text-white font-semibold py-3 rounded-md hover:bg-[#002861] active:scale-[0.99] transition-all text-sm shadow-md">
                            Masuk
                        </button>
                    </div>
                </form>

                <!-- Help Link -->
                <div class="mt-6 text-center">
                    <p class="text-sm text-gray-600">
                        Lupa kata sandi? 
                        <a href="#" class="text-[#00337C] font-semibold hover:underline">Hubungi admin</a>
                    </p>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 py-4 mt-auto">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <p class="text-gray-600 text-xs">
                © 2026 Badan Pusat Statistik Provinsi Riau
            </p>
        </div>
    </footer>

</body>
</html>