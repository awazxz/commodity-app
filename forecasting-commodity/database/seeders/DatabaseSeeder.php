<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // Panggil seeder Role terlebih dahulu (karena User butuh Role)
        $this->call(RoleSeeder::class);
        
        // Panggil seeder Data Komoditas
        $this->call(PriceDataSeeder::class);

        $this->call(UserSeeder::class);
    }


}