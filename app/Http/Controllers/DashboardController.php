<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        if (!$user || !$user->role) {
            abort(403, 'Role belum ditentukan.');
        }

        return match ($user->role) {
            'admin'    => redirect()->route('admin.dashboard'),
            'operator' => redirect()->route('operator.dashboard'),
            'user'     => redirect()->route('user.dashboard'),
            default    => abort(403, 'Role tidak dikenali.'),
        };
    }
}
