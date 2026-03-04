<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'SIGMAPRO') }} - BPS Provinsi Riau</title>

    {{-- Font Standard: Inter --}}
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    {{-- FontAwesome untuk Icon --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class' }
    </script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        body {
            font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, sans-serif;
            -webkit-font-smoothing: antialiased;
            background-color: #f8fafc;
            /* Pattern Background Tipis agar selaras dengan Login */
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%2300337c' fill-opacity='0.02'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        body.dark {
            background-color: #111827;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        .font-arial-bold-italic {
            font-family: Arial, Helvetica, sans-serif !important;
            font-weight: bold !important;
            font-style: italic !important;
        }

        /* Navigasi Aktif dengan warna Biru Langit BPS */
        .nav-link-active {
            background-color: rgba(255, 255, 255, 0.1);
            border-bottom: 4px solid #00a2e9;
            color: white !important;
        }

        .transition-standard {
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .dark-toggle-thumb {
            transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        html.dark .dark-toggle-thumb {
            transform: translateX(18px);
        }
    </style>

    {{-- Prevent FOUC: apply theme before render --}}
    <script>
        (function() {
            var t = localStorage.getItem('theme') || 'light';
            document.documentElement.classList.toggle('dark', t === 'dark');
        })();
    </script>
</head>

<body class="min-h-screen flex flex-col bg-gray-50 dark:bg-gray-900 transition-colors duration-200">

    {{-- NAV BAR --}}
    <nav class="bg-[#00337C] dark:bg-[#001530] shadow-lg border-b-4 border-[#00a2e9] sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                
                {{-- LOGO SECTION --}}
                <div class="flex items-center space-x-4">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/2/28/Lambang_Badan_Pusat_Statistik_%28BPS%29_Indonesia.svg" 
                         alt="Logo BPS" class="h-10 filter drop-shadow-sm">
                    <div class="border-l border-white/20 pl-4 hidden md:block">
                        <p class="text-white font-arial-bold-italic text-[16px] uppercase leading-tight tracking-tight">Badan Pusat Statistik</p>
                        <p class="text-white font-arial-bold-italic text-[12px] uppercase tracking-[0.12em]">Provinsi Riau</p>
                    </div>
                </div>

                {{-- NAV MENU --}}
                @auth
                <div class="hidden md:flex items-center space-x-1">
                    <a href="{{ route('laporan.komoditas.index') }}"
                       class="text-white/80 px-4 py-[22px] text-[11px] font-bold uppercase tracking-widest hover:text-white hover:bg-white/5 transition-standard
                       {{ request()->routeIs('laporan.komoditas.*') ? 'nav-link-active' : '' }}">
                       <i class="fas fa-home mr-1.5 opacity-50"></i> Beranda
                    </a>
                    
                    <a href="{{ route('dashboard') }}"
                       class="text-white/80 px-4 py-[22px] text-[11px] font-bold uppercase tracking-widest hover:text-white hover:bg-white/5 transition-standard
                       {{ request()->routeIs('dashboard') ? 'nav-link-active' : '' }}">
                       <i class="fas fa-chart-line mr-1.5 opacity-50"></i> Analisis
                    </a>

                    @if(auth()->user()->isAdmin() || auth()->user()->isOperator())
                        <a href="{{ auth()->user()->isAdmin() ? route('admin.dashboard', ['tab' => 'manage']) : route('operator.dashboard', ['tab' => 'manage']) }}"
                           class="text-white/80 px-4 py-[22px] text-[11px] font-bold uppercase tracking-widest hover:text-white hover:bg-white/5 transition-standard
                           {{ request()->get('tab') == 'manage' ? 'nav-link-active' : '' }}">
                           <i class="fas fa-database mr-1.5 opacity-50"></i> Manajemen Data
                        </a>
                    @endif

                    @if(auth()->user()->isAdmin())
                        <a href="{{ route('admin.dashboard', ['tab' => 'users']) }}"
                           class="text-white/80 px-4 py-[22px] text-[11px] font-bold uppercase tracking-widest hover:text-white hover:bg-white/5 transition-standard
                           {{ request()->get('tab') == 'users' ? 'nav-link-active' : '' }}">
                           <i class="fas fa-users mr-1.5 opacity-50"></i> Pengguna
                        </a>
                    @endif
                </div>
                @endauth

                {{-- USER DROPDOWN --}}
                @auth
                <div class="flex items-center space-x-4">
                    <div class="flex flex-col text-right hidden sm:block">
                        <span class="text-[#00a2e9] text-[9px] font-black uppercase tracking-wider">
                            {{ auth()->user()->isAdmin() ? 'Administrator' : (auth()->user()->isOperator() ? 'Operator' : 'User') }}
                        </span>
                        <span class="text-white text-xs font-semibold block">{{ Auth::user()->name }}</span>
                    </div>
                    
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="flex items-center group">
                            <div class="h-9 w-9 bg-gradient-to-tr from-[#00337C] to-[#00a2e9] rounded-full flex items-center justify-center text-white font-bold text-sm shadow-md border-2 border-white/20 group-hover:border-white/50 transition-all">
                                {{ substr(Auth::user()->name, 0, 1) }}
                            </div>
                        </button>

                        <div x-show="open" 
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             @click.outside="open = false"
                             class="absolute right-0 mt-3 w-80 bg-white dark:bg-gray-800 rounded-2xl shadow-2xl border border-gray-100 dark:border-gray-700 py-2 z-[60] overflow-hidden">
                            
                            {{-- ── DARK MODE TOGGLE ── --}}
                            <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700">
                                <p class="text-[9px] font-black uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-2">Tampilan</p>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span id="theme-icon" class="text-sm">☀️</span>
                                        <span id="theme-label" class="text-xs font-semibold text-gray-700 dark:text-gray-300">Mode Terang</span>
                                    </div>
                                    <button onclick="toggleDarkMode()" id="dark-toggle"
                                            class="relative w-10 h-5 rounded-full focus:outline-none bg-gray-200 dark:bg-blue-600 transition-colors duration-200">
                                        <span class="dark-toggle-thumb absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow block"></span>
                                    </button>
                                </div>
                            </div>

                            {{-- ── LANGUAGE SWITCHER ── --}}
                            <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700">
                                <p class="text-[9px] font-black uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-2">Pilih Bahasa</p>
                                <div class="flex gap-2">
                                    <form method="POST" action="{{ route('language.switch') }}">
                                        @csrf
                                        <input type="hidden" name="locale" value="id">
                                        <button type="submit" class="px-3 py-1.5 text-[10px] font-bold rounded-lg border transition-standard flex items-center gap-1
                                            {{ (session('locale','id') === 'id') ? 'bg-[#00337C] text-white border-[#00337C]' : 'bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600' }}">
                                            🇮🇩 ID
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('language.switch') }}">
                                        @csrf
                                        <input type="hidden" name="locale" value="en">
                                        <button type="submit" class="px-3 py-1.5 text-[10px] font-bold rounded-lg border transition-standard flex items-center gap-1
                                            {{ (session('locale','id') === 'en') ? 'bg-[#00337C] text-white border-[#00337C]' : 'bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600' }}">
                                            🇬🇧 EN
                                        </button>
                                    </form>
                                </div>
                            </div>

                            {{-- ── USER INFO ── --}}
                            <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                                <p class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase">Login sebagai:</p>
                                <p class="text-sm font-bold text-[#00337C] dark:text-[#00a2e9] truncate">{{ Auth::user()->email }}</p>
                            </div>

                            {{-- ── LOGOUT ── --}}
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit"
                                    class="w-full text-left px-4 py-3 text-xs font-bold text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-standard uppercase tracking-widest flex items-center">
                                    <i class="fas fa-sign-out-alt mr-3"></i> Keluar Aplikasi
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                @endauth

            </div>
        </div>
    </nav>

    {{-- MAIN CONTENT --}}
    <main class="flex-grow">
        <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-6 p-4 bg-white dark:bg-gray-800 border-l-4 border-green-500 shadow-sm rounded-r-xl flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="bg-green-100 dark:bg-green-900/30 p-2 rounded-full mr-4">
                            <i class="fas fa-check text-green-600 dark:text-green-400"></i>
                        </div>
                        <p class="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-tight">{{ session('success') }}</p>
                    </div>
                    <button onclick="this.parentElement.remove()" class="text-gray-300 dark:text-gray-600 hover:text-gray-500 dark:hover:text-gray-400">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            @endif

            @yield('content')

        </div>
    </main>

    {{-- FOOTER --}}
    <footer class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 py-6 mt-auto">
        <div class="max-w-7xl mx-auto px-6 text-center">
            <p class="text-gray-500 dark:text-gray-400 text-xs font-medium">
                &copy; 2026 <span class="text-[#00337C] dark:text-[#00a2e9] font-bold">Badan Pusat Statistik Provinsi Riau</span>
            </p>
        </div>
    </footer>

    <script>
        function toggleDarkMode() {
            var isDark = document.documentElement.classList.toggle('dark');
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
            updateThemeUI();
        }

        function updateThemeUI() {
            var isDark = document.documentElement.classList.contains('dark');
            var icon  = document.getElementById('theme-icon');
            var label = document.getElementById('theme-label');
            if (icon)  icon.textContent  = isDark ? '🌙' : '☀️';
            if (label) label.textContent = isDark ? 'Mode Gelap' : 'Mode Terang';
        }

        document.addEventListener('DOMContentLoaded', updateThemeUI);
    </script>

</body>
</html>