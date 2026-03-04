<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LanguageController extends Controller
{
    protected array $allowedLocales = ['id', 'en'];

    public function switch(Request $request)
    {
        $locale = $request->input('locale');

        if (!in_array($locale, $this->allowedLocales)) {
            return back()->with('error', 'Bahasa tidak tersedia / Language not available.');
        }

        // Simpan ke session
        session(['locale' => $locale]);

        // Simpan ke database agar persisten setelah login ulang
        if (Auth::check()) {
            Auth::user()->update(['locale' => $locale]);
        }

        $message = $locale === 'id'
            ? 'Bahasa berhasil diubah ke Bahasa Indonesia.'
            : 'Language changed to English.';

        return back()->with('success', $message);
    }
}