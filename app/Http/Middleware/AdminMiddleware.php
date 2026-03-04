<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    /**
     * Pastikan pengguna yang login memiliki role 'admin'.
     * Menangani request biasa (redirect) maupun AJAX (JSON).
     */
    public function handle(Request $request, Closure $next)
    {
        // Belum login
        if (!auth()->check()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sesi habis, silakan login kembali.',
                ], 401);
            }
            return redirect()->route('login');
        }

        // Sudah login tapi bukan admin
        if (!auth()->user()->isAdmin()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak. Halaman ini hanya untuk Administrator.',
                ], 403);
            }
            abort(403, 'Akses ditolak. Halaman ini hanya untuk Administrator.');
        }

        return $next($request);
    }
}