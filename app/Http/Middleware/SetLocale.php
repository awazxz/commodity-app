<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        // Prioritas 1: dari session (setelah user switch bahasa)
        if (session()->has('locale')) {
            $locale = session('locale');
        }
        // Prioritas 2: dari preferensi user di database
        elseif (Auth::check() && Auth::user()->locale) {
            $locale = Auth::user()->locale;
            session(['locale' => $locale]); // simpan ke session biar konsisten
        }
        // Prioritas 3: default dari config/app.php (sekarang sudah 'id')
        else {
            $locale = config('app.locale', 'id');
        }

        // Validasi locale hanya boleh 'id' atau 'en'
        if (!in_array($locale, ['id', 'en'])) {
            $locale = 'id';
        }

        App::setLocale($locale);

        return $next($request);
    }
}