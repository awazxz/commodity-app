<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Bersihkan tabel user dulu (opsional tapi aman)
        User::truncate();

        // =========================
        // ADMIN
        // =========================
        User::create([
            'name'     => 'Admin',
            'email'    => 'admin@commodity.test',
            'password' => Hash::make('admin123'),
            'role'     => 'admin',
        ]);

        // =========================
        // OPERATOR
        // =========================
        User::create([
            'name'     => 'Operator',
            'email'    => 'operator@commodity.test',
            'password' => Hash::make('operator123'),
            'role'     => 'operator',
        ]);

        // =========================
        // USER BIASA
        // =========================
        User::create([
            'name'     => 'User',
            'email'    => 'user@commodity.test',
            'password' => Hash::make('user123'),
            'role'     => 'user',
        ]);
    }
}
