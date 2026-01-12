<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Insight View - BPS Provinsi Riau</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        body {
            font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, sans-serif;
            letter-spacing: -0.01em;
        }
        .nav-link-active {
            background-color: rgba(255, 255, 255, 0.1);
            border-bottom: 3px solid #FFA500;
        }
    </style>
</head>
<body class="bg-[#F4F7FA] min-h-screen flex flex-col">

    <nav class="bg-[#00337C] shadow-lg border-b-2 border-[#FFA500] sticky top-0 z-50">
        <div class="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                
                {{-- LOGO --}}
                <div class="flex items-center space-x-4">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/2/28/Lambang_Badan_Pusat_Statistik_%28BPS%29_Indonesia.svg" alt="Logo BPS" class="h-10">
                    <div class="border-l border-white/20 pl-4 hidden md:block">
                        <h1 class="text-white font-extrabold text-sm uppercase leading-tight tracking-tight">Badan Pusat Statistik</h1>
                        <p class="text-[#FFA500] text-[10px] font-bold uppercase tracking-[0.15em]">Provinsi Riau</p>
                    </div>
                </div>

                {{-- NAV MENU (ROLE BASED) --}}
                @auth
                <div class="hidden md:flex items-center space-x-1">

                    {{-- BERANDA (SEMUA ROLE) --}}
                    <a href="{{ route('dashboard') }}"
                       class="text-white px-4 py-5 text-[11px] font-bold uppercase tracking-widest hover:bg-white/5 transition
                       {{ request()->routeIs('dashboard') ? 'nav-link-active' : '' }}">
                        Beranda
                    </a>

                    {{-- MANAGEMEN DATA (ADMIN & OPERATOR) --}}
                    @if(auth()->user()->isAdmin() || auth()->user()->isOperator())
                        <a href="{{ auth()->user()->isAdmin()
                            ? route('admin.dashboard', ['tab' => 'manage'])
                            : route('operator.dashboard', ['tab' => 'manage']) }}"
                            class="text-white/70 px-4 py-5 text-[11px] font-bold uppercase tracking-widest hover:text-white transition">
                            Manajemen Data
                         </a>

                    @endif

                    {{-- USER CONTROL (ADMIN ONLY) --}}
                    @if(auth()->user()->isAdmin())
                        <a href="{{ route('admin.dashboard', ['tab' => 'users']) }}"
                           class="text-white/70 px-4 py-5 text-[11px] font-bold uppercase tracking-widest hover:text-white transition">
                            User Control
                        </a>
                    @endif

                </div>
                @endauth

                {{-- USER DROPDOWN --}}
                @auth
                <div class="flex items-center space-x-4">
                    <div class="flex flex-col text-right hidden sm:block">
                        <span class="text-[#FFA500] text-[9px] font-black uppercase tracking-tighter">
                            {{ auth()->user()->isAdmin() ? 'Administrator' : (auth()->user()->isOperator() ? 'Operator' : 'User') }}
                        </span>
                        <span class="text-white text-xs font-semibold">{{ Auth::user()->name }}</span>
                    </div>
                    
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="flex items-center bg-white/10 p-1.5 rounded-full border border-white/20 hover:bg-white/20 transition">
                            <div class="h-7 w-7 bg-orange-500 rounded-full flex items-center justify-center text-white font-bold text-xs">
                                {{ substr(Auth::user()->name, 0, 1) }}
                            </div>
                        </button>

                        <div x-show="open" @click.outside="open = false"
                             class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-xl border border-gray-100 py-2 z-[60]">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit"
                                    class="w-full text-left px-4 py-2 text-xs font-bold text-red-600 hover:bg-red-50 transition uppercase tracking-widest">
                                    Logout
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                @endauth

            </div>
        </div>
    </nav>

    {{-- CONTENT --}}
    <main class="flex-grow">
        <div class="max-w-[1440px] mx-auto py-6 px-4 sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 p-4 bg-green-50 border-l-4 border-green-500 text-green-700 text-xs font-bold rounded-r-lg shadow-sm">
                    {{ session('success') }}
                </div>
            @endif

            @yield('content')

        </div>
    </main>

    {{-- FOOTER --}}
   <footer class="bg-white border-t border-gray-200 py-4">
    <div class="max-w-[1440px] mx-auto px-8 flex justify-center items-center text-[#94A3B8] text-center">
        <p class="text-[10px] font-bold uppercase tracking-[0.2em]">
            © 2026 Badan Pusat Statistik Provinsi Riau
        </p>
    </div>
</footer>

</body>
</html>
