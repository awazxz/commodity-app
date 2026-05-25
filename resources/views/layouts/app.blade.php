<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light">
<head>
    <meta charset="utf-8">
    {{-- FIX 1: maximum-scale=1 agar Safari tidak auto-zoom dan layout tidak bergeser --}}
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'SIGMAPRO') }} - BPS Provinsi Riau</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%2300337c' fill-opacity='0.02'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            /* FIX 2: Cegah horizontal scroll global */
            overflow-x: hidden;
        }
        html { overflow-x: hidden; }

        html.dark body {
            background-color: #111827;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        .font-arial-bold-italic {
            font-family: Arial, Helvetica, sans-serif !important;
            font-weight: bold !important;
            font-style: italic !important;
        }

        .nav-link-active {
            background-color: rgba(255,255,255,0.1);
            border-bottom: 4px solid #00a2e9;
            color: white !important;
        }

        /* Mobile nav aktif pakai border-left */
        .mobile-nav-link-active {
            background-color: rgba(255,255,255,0.1);
            border-left: 3px solid #00a2e9;
            color: white !important;
        }

        .transition-standard { transition: all 0.2s cubic-bezier(0.4,0,0.2,1); }

        .dark-toggle-thumb { transition: transform 0.25s cubic-bezier(0.4,0,0.2,1); }
        html.dark .dark-toggle-thumb { transform: translateX(18px); }

        /* FIX 3: Mobile menu slide animation */
        #mobile-menu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out, opacity 0.2s ease-out;
            opacity: 0;
        }
        #mobile-menu.open {
            max-height: 500px;
            opacity: 1;
        }

        /* FIX 4: Dropdown tidak keluar layar kanan di mobile */
        @media (max-width: 639px) {
            .user-dropdown-panel {
                right: -0.25rem !important;
                width: calc(100vw - 1.5rem) !important;
                max-width: 300px !important;
            }
        }

        /* FIX 5: Cegah auto-zoom Safari saat focus input */
        @supports (-webkit-touch-callout: none) {
            input[type="text"],
            input[type="email"],
            input[type="password"],
            input[type="number"],
            input[type="date"],
            select, textarea {
                font-size: 16px !important;
            }
        }
        @media (max-width: 768px) {
    .grid2 {
        grid-template-columns: 1fr !important;
    }
}
    </style>

    <script>
        (function() {
            var t = localStorage.getItem('theme') || 'light';
            document.documentElement.classList.toggle('dark', t === 'dark');
        })();
    </script>
</head>

<body class="min-h-screen flex flex-col bg-gray-50 dark:bg-gray-900 transition-colors duration-200">

    {{-- ═══ NAVBAR ═══ --}}
    <nav class="bg-[#00337C] dark:bg-[#001530] shadow-lg border-b-4 border-[#00a2e9] sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">

                {{-- LOGO --}}
                <div class="flex items-center gap-3 flex-shrink-0">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/2/28/Lambang_Badan_Pusat_Statistik_%28BPS%29_Indonesia.svg"
                         alt="Logo BPS" class="h-9 filter drop-shadow-sm">
                    {{-- Desktop: nama lengkap --}}
                    <div class="border-l border-white/20 pl-3 hidden sm:block">
                        <p class="text-white font-arial-bold-italic text-[14px] uppercase leading-tight tracking-tight">Badan Pusat Statistik</p>
                        <p class="text-white font-arial-bold-italic text-[11px] uppercase tracking-[0.12em]">Provinsi Riau</p>
                    </div>
                    {{-- Mobile: nama singkat --}}
                        <div class="border-l border-white/20 pl-3 sm:hidden">
                            <p class="text-white font-arial-bold-italic text-[11px] uppercase leading-tight tracking-tight">Badan Pusat Statistik</p>
                            <p class="text-white font-arial-bold-italic text-[9px] uppercase tracking-[0.12em]">Provinsi Riau</p>
                        </div>
                    </div>

                {{-- NAV LINKS — desktop only --}}
                @auth
                    <div class="hidden md:flex items-center">
                        <a href="{{ route('laporan.komoditas.index') }}"
                        class="text-white/80 px-3 py-[22px] text-[10px] font-bold uppercase tracking-wider hover:text-white hover:bg-white/5 transition-standard flex items-center gap-1.5
                        {{ request()->routeIs('laporan.komoditas.*') ? 'nav-link-active' : '' }}">
                        <i class="fas fa-home opacity-50 text-[10px]"></i> {{ __('messages.beranda') }}
                        </a>
                        <a href="{{ route('dashboard') }}"
                        class="text-white/80 px-3 py-[22px] text-[10px] font-bold uppercase tracking-wider hover:text-white hover:bg-white/5 transition-standard flex items-center gap-1.5
                        {{ request()->routeIs('dashboard') ? 'nav-link-active' : '' }}">
                        <i class="fas fa-chart-line opacity-50 text-[10px]"></i> {{ __('messages.analisis') }}
                        </a>
                        @if(auth()->user()->isAdmin() || auth()->user()->isOperator())
                            <a href="{{ auth()->user()->isAdmin() ? route('admin.dashboard', ['tab' => 'manage']) : route('operator.dashboard', ['tab' => 'manage']) }}"
                            class="text-white/80 px-3 py-[22px] text-[10px] font-bold uppercase tracking-wider hover:text-white hover:bg-white/5 transition-standard flex items-center gap-1.5
                            {{ request()->get('tab') == 'manage' ? 'nav-link-active' : '' }}">
                            <i class="fas fa-database opacity-50 text-[10px]"></i> <span class="hidden lg:inline">{{ __('messages.manajemen_data') }}</span><span class="lg:hidden">Data</span>
                            </a>
                        @endif
                        @if(auth()->user()->isAdmin())
                            <a href="{{ route('admin.dashboard', ['tab' => 'users']) }}"
                            class="text-white/80 px-3 py-[22px] text-[10px] font-bold uppercase tracking-wider hover:text-white hover:bg-white/5 transition-standard flex items-center gap-1.5
                            {{ request()->get('tab') == 'users' ? 'nav-link-active' : '' }}">
                            <i class="fas fa-users opacity-50 text-[10px]"></i> <span class="hidden lg:inline">{{ __('messages.manajemen_pengguna') }}</span><span class="lg:hidden">Users</span>
                            </a>
                        @endif
                    </div>
                @endauth

                {{-- KANAN: User avatar + hamburger --}}
                @auth
                <div class="flex items-center gap-2">

                    {{-- Nama & role — desktop saja --}}
                    <div class="hidden sm:block text-right">
                        <span class="text-[#00a2e9] text-[9px] font-black uppercase tracking-wider block">
                            {{ auth()->user()->isAdmin() ? __('messages.administrator') : (auth()->user()->isOperator() ? __('messages.operator') : __('messages.pengguna')) }}
                        </span>
                        <span class="text-white text-xs font-semibold block">{{ Auth::user()->name }}</span>
                    </div>

                    {{-- User dropdown --}}
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open"
                                class="flex items-center gap-1 group"
                                aria-label="Menu pengguna">
                            <div class="h-9 w-9 bg-gradient-to-tr from-[#00337C] to-[#00a2e9] rounded-full flex items-center justify-center text-white font-bold text-sm shadow-md border-2 border-white/20 group-hover:border-white/50 transition-all">
                                {{ substr(Auth::user()->name, 0, 1) }}
                            </div>
                            <i class="fas fa-chevron-down text-white/60 text-[9px] hidden sm:block transition-transform duration-200"
                               :class="{ 'rotate-180': open }"></i>
                        </button>

                        <div x-show="open"
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             @click.outside="open = false"
                             class="user-dropdown-panel absolute right-0 mt-3 w-80 bg-white dark:bg-gray-800 rounded-2xl shadow-2xl border border-gray-100 dark:border-gray-700 py-2 z-[60] overflow-hidden">

                            {{-- Dark mode toggle --}}
                            <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700">
                                <p class="text-[9px] font-black uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-2">{{ __('messages.tampilan') }}</p>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span id="theme-icon" class="text-sm">☀️</span>
                                        <span id="theme-label"
                                              class="text-xs font-semibold text-gray-700 dark:text-gray-300"
                                              data-label-light="{{ __('messages.mode_terang') }}"
                                              data-label-dark="{{ __('messages.mode_gelap') }}">
                                        </span>
                                    </div>
                                    <button onclick="toggleDarkMode()" id="dark-toggle"
                                            class="relative w-10 h-5 rounded-full focus:outline-none bg-gray-200 dark:bg-blue-600 transition-colors duration-200">
                                        <span class="dark-toggle-thumb absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow block transform transition-transform duration-200 dark:translate-x-5"></span>
                                    </button>
                                </div>
                            </div>

                            {{-- Language switcher --}}
                            <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700">
                                <p class="text-[9px] font-black uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-2">{{ __('messages.pilih_bahasa') }}</p>
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

                            {{-- User info --}}
                            <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                                <p class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase">{{ __('messages.login_sebagai') }}</p>
                                <p class="text-sm font-bold text-[#00337C] dark:text-[#00a2e9] truncate">{{ Auth::user()->email }}</p>
                                {{-- Nama hanya di mobile (karena header name disembunyikan) --}}
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 sm:hidden">{{ Auth::user()->name }}</p>
                            </div>

                            {{-- Logout --}}
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit"
                                        class="w-full text-left px-4 py-3 text-xs font-bold text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-standard uppercase tracking-widest flex items-center">
                                    <i class="fas fa-sign-out-alt mr-3"></i> {{ __('messages.logout') }}
                                </button>
                            </form>
                        </div>
                    </div>

                    {{-- HAMBURGER — mobile only --}}
                    <button id="hamburger-btn"
                            onclick="toggleMobileMenu()"
                            class="md:hidden flex flex-col justify-center items-center w-9 h-9 rounded-lg hover:bg-white/10 transition-standard gap-[5px]"
                            aria-label="Buka menu navigasi">
                        <span class="ham-line block w-5 h-0.5 bg-white transition-all duration-300 origin-center"></span>
                        <span class="ham-line block w-5 h-0.5 bg-white transition-all duration-300"></span>
                        <span class="ham-line block w-5 h-0.5 bg-white transition-all duration-300 origin-center"></span>
                    </button>

                </div>
                @endauth

            </div>
        </div>

        {{-- MOBILE NAV MENU (dropdown di bawah navbar) --}}
        @auth
        <div id="mobile-menu" class="md:hidden border-t border-white/10">
            <div class="px-4 py-3 space-y-1 bg-[#002a6b] dark:bg-[#001020]">

                <a href="{{ route('laporan.komoditas.index') }}"
                   class="flex items-center gap-3 px-3 py-3 rounded-lg text-white/80 hover:text-white hover:bg-white/10 transition-standard text-sm font-semibold
                   {{ request()->routeIs('laporan.komoditas.*') ? 'mobile-nav-link-active' : '' }}">
                    <i class="fas fa-home w-4 text-center opacity-70"></i>
                    <span>{{ __('messages.beranda') }}</span>
                </a>

                <a href="{{ route('dashboard') }}"
                   class="flex items-center gap-3 px-3 py-3 rounded-lg text-white/80 hover:text-white hover:bg-white/10 transition-standard text-sm font-semibold
                   {{ request()->routeIs('dashboard') ? 'mobile-nav-link-active' : '' }}">
                    <i class="fas fa-chart-line w-4 text-center opacity-70"></i>
                    <span>{{ __('messages.analisis') }}</span>
                </a>

                @if(auth()->user()->isAdmin() || auth()->user()->isOperator())
                    <a href="{{ auth()->user()->isAdmin() ? route('admin.dashboard', ['tab' => 'manage']) : route('operator.dashboard', ['tab' => 'manage']) }}"
                       class="flex items-center gap-3 px-3 py-3 rounded-lg text-white/80 hover:text-white hover:bg-white/10 transition-standard text-sm font-semibold
                       {{ request()->get('tab') == 'manage' ? 'mobile-nav-link-active' : '' }}">
                        <i class="fas fa-database w-4 text-center opacity-70"></i>
                        <span>{{ __('messages.manajemen_data') }}</span>
                    </a>
                @endif

                @if(auth()->user()->isAdmin())
                    <a href="{{ route('admin.dashboard', ['tab' => 'users']) }}"
                       class="flex items-center gap-3 px-3 py-3 rounded-lg text-white/80 hover:text-white hover:bg-white/10 transition-standard text-sm font-semibold
                       {{ request()->get('tab') == 'users' ? 'mobile-nav-link-active' : '' }}">
                        <i class="fas fa-users w-4 text-center opacity-70"></i>
                        <span>{{ __('messages.manajemen_pengguna') }}</span>
                    </a>
                @endif

                {{-- Divider + info user --}}
                <div class="pt-2 mt-1 border-t border-white/10 px-3 pb-1">
                    <p class="text-[9px] text-white/40 uppercase font-bold tracking-wider">
                        {{ auth()->user()->isAdmin() ? __('messages.administrator') : (auth()->user()->isOperator() ? __('messages.operator') : __('messages.pengguna')) }}
                    </p>
                    <p class="text-white/80 text-xs font-semibold mt-0.5">{{ Auth::user()->name }}</p>
                </div>

            </div>
        </div>
        @endauth
    </nav>

    {{-- MAIN CONTENT --}}
    <main class="flex-grow">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-6 p-4 bg-white dark:bg-gray-800 border-l-4 border-green-500 shadow-sm rounded-r-xl flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="bg-green-100 dark:bg-green-900/30 p-2 rounded-full flex-shrink-0">
                            <i class="fas fa-check text-green-600 dark:text-green-400"></i>
                        </div>
                        <p class="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-tight">{{ session('success') }}</p>
                    </div>
                    <button onclick="this.parentElement.remove()"
                            class="text-gray-300 dark:text-gray-600 hover:text-gray-500 dark:hover:text-gray-400 ml-3 flex-shrink-0">
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
        /* ── Dark mode ── */
        function toggleDarkMode() {
            const isDark = document.documentElement.classList.toggle('dark');
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
            updateThemeUI();
        }

        function updateThemeUI() {
            const isDark = document.documentElement.classList.contains('dark');
            const label  = document.getElementById('theme-label');
            const icon   = document.getElementById('theme-icon');
            const thumb  = document.querySelector('.dark-toggle-thumb');
            if (!label) return;
            if (isDark) {
                label.innerText = label.getAttribute('data-label-dark') || 'Mode Gelap';
                icon.innerText  = '🌙';
                if (thumb) thumb.style.transform = 'translateX(1.25rem)';
            } else {
                label.innerText = label.getAttribute('data-label-light') || 'Mode Terang';
                icon.innerText  = '☀️';
                if (thumb) thumb.style.transform = 'translateX(0)';
            }
        }

        document.addEventListener('DOMContentLoaded', updateThemeUI);

        /* ── Mobile hamburger ── */
        let mobileMenuOpen = false;

        function toggleMobileMenu() {
            const menu  = document.getElementById('mobile-menu');
            const lines = document.querySelectorAll('.ham-line');
            mobileMenuOpen = !mobileMenuOpen;

            if (mobileMenuOpen) {
                menu.classList.add('open');
                lines[0].style.transform = 'rotate(45deg) translate(4px, 5px)';
                lines[1].style.opacity   = '0';
                lines[2].style.transform = 'rotate(-45deg) translate(4px, -5px)';
            } else {
                menu.classList.remove('open');
                lines[0].style.transform = '';
                lines[1].style.opacity   = '';
                lines[2].style.transform = '';
            }
        }

        /* Tutup saat klik di luar */
        document.addEventListener('click', function(e) {
            if (!mobileMenuOpen) return;
            const menu = document.getElementById('mobile-menu');
            const btn  = document.getElementById('hamburger-btn');
            if (menu && btn && !menu.contains(e.target) && !btn.contains(e.target)) {
                toggleMobileMenu();
            }
        });

        /* Tutup saat resize ke desktop */
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 768 && mobileMenuOpen) toggleMobileMenu();
        });
    </script>

</body>
</html>