<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        // Baris ini yang bertugas memanggil file di resources/views/auth/login.blade.php
        return view('auth.login');
    }

    public function login(Request $request)
{
    // Kita abaikan pengecekan database untuk sementara
    // Langsung arahkan ke dashboard
    return redirect()->route('dashboard.index');
}
}