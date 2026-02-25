<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Menampilkan halaman login.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Menangani permintaan autentikasi.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // Ambil user yang baru saja login
        $user = Auth::user();

        // Logika Pengalihan Berdasarkan Role (Relasi Model)
        if ($user->isAdmin()) {
            return redirect()->intended(route('admin.dashboard'));
        } 
        
        if ($user->isOperator()) {
            return redirect()->intended(route('operator.dashboard'));
        }

        // Default redirect jika role tidak spesifik
        return redirect()->intended(RouteServiceProvider::HOME);
    }

    /**
     * Menghapus sesi autentikasi (Logout).
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}