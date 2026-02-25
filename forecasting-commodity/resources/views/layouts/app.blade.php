<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Insight View - BPS Provinsi Riau</title>

    {{-- Font Standard: Inter --}}
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    {{-- FontAwesome untuk Icon --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        /* Standar Font dari Komoditas.blade.php */
        body {
            font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Arial Bold Italic untuk Header BPS */
        .font-arial-bold-italic {
            font-family: Arial, Helvetica, sans-serif !important;
            font-weight: bold !important;
            font-style: italic !important;
        }

        /* Styling Navigasi Aktif */
        .nav-link-active {
            background-color: rgba(255, 255, 255, 0.08);
            border-bottom: 4px solid #FFA500;
            color: white !important;
        }

        /* Custom Transition */
        .transition-standard {
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen flex flex-col">

    <nav class="bg-[#00337C] shadow-md border-b-2 border-[#FFA500] sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                
                {{-- LOGO --}}
                <div class="flex items-center space-x-4">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/2/28/Lambang_Badan_Pusat_Statistik_%28BPS%29_Indonesia.svg" alt="Logo BPS" class="h-10">
                    <div class="border-l border-white/20 pl-4 hidden md:block">
                        <p class="text-white font-arial-bold-italic text-[17px] uppercase leading-tight tracking-tight">Badan Pusat Statistik</p>
                        <p class="text-white font-arial-bold-italic text-[13px] uppercase tracking-[0.15em]">Provinsi Riau</p>
                    </div>
                </div>

                {{-- NAV MENU --}}
                @auth
                <div class="hidden md:flex items-center space-x-1">
                    <a href="{{ route('laporan.komoditas.index') }}"
                       class="text-white/80 px-4 py-[22px] text-[11px] font-bold uppercase tracking-widest hover:text-white hover:bg-white/5 transition-standard
                       {{ request()->routeIs('laporan.komoditas.*') ? 'nav-link-active' : '' }}">
                        Beranda
                    </a>
                    <a href="{{ route('dashboard') }}"
                       class="text-white/80 px-4 py-[22px] text-[11px] font-bold uppercase tracking-widest hover:text-white hover:bg-white/5 transition-standard
                       {{ request()->routeIs('dashboard') ? 'nav-link-active' : '' }}">
                        Analisis
                    </a>

                    @if(auth()->user()->isAdmin() || auth()->user()->isOperator())
                        <a href="{{ auth()->user()->isAdmin() ? route('admin.dashboard', ['tab' => 'manage']) : route('operator.dashboard', ['tab' => 'manage']) }}"
                           class="text-white/80 px-4 py-[22px] text-[11px] font-bold uppercase tracking-widest hover:text-white hover:bg-white/5 transition-standard
                           {{ request()->get('tab') == 'manage' ? 'nav-link-active' : '' }}">
                            Manajemen Data
                        </a>
                    @endif

                    @if(auth()->user()->isAdmin())
                        <a href="{{ route('admin.dashboard', ['tab' => 'users']) }}"
                           class="text-white/80 px-4 py-[22px] text-[11px] font-bold uppercase tracking-widest hover:text-white hover:bg-white/5 transition-standard
                           {{ request()->get('tab') == 'users' ? 'nav-link-active' : '' }}">
                            Manajemen Pengguna
                        </a>
                    @endif
                </div>
                @endauth

                {{-- USER DROPDOWN --}}
                @auth
                <div class="flex items-center space-x-4">
                    <div class="flex flex-col text-right hidden sm:block">
                        <span class="text-[#FFA500] text-[9px] font-black uppercase tracking-wider">
                            {{ auth()->user()->isAdmin() ? 'Administrator' : (auth()->user()->isOperator() ? 'Operator' : 'User') }}
                        </span>
                        <span class="text-white text-xs font-semibold block">{{ Auth::user()->name }}</span>
                    </div>
                    
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="flex items-center bg-white/10 p-1 rounded-full border border-white/20 hover:bg-white/20 transition-standard">
                            <div class="h-8 w-8 bg-orange-500 rounded-full flex items-center justify-center text-white font-bold text-sm shadow-inner">
                                {{ substr(Auth::user()->name, 0, 1) }}
                            </div>
                        </button>

                        <div x-show="open" 
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             @click.outside="open = false"
                             class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-xl border border-gray-100 py-2 z-[60]">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit"
                                    class="w-full text-left px-4 py-3 text-xs font-bold text-red-600 hover:bg-red-50 transition-standard uppercase tracking-widest flex items-center">
                                    <i class="fas fa-sign-out-alt mr-2"></i> Logout
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
        {{-- Padding dan Max-Width disesuaikan dengan standar komoditas --}}
        <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-500 text-green-800 text-xs font-bold rounded-lg shadow-sm flex items-center">
                    <i class="fas fa-check-circle mr-3 text-green-500 text-lg"></i>
                    {{ session('success') }}
                </div>
            @endif

            @yield('content')

        </div>
    </main>

    {{-- FOOTER --}}
    <footer class="bg-white border-t border-gray-200 py-6">
        <div class="max-w-7xl mx-auto px-8 flex justify-center items-center">
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">
                © 2026 Badan Pusat Statistik Provinsi Riau
            </p>
        </div>
    </footer>

</body>
</html>