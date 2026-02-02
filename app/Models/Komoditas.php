<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Komoditas extends Model
{
    use HasFactory;

    // Paksa Laravel menggunakan nama tabel yang ada di database Anda
    protected $table = 'master_komoditas'; 

    /**
     * Jika di tabel master_komoditas tidak ada kolom 'varian',
     * atau Anda ingin menghubungkan ke tabel lain (misal: price_data),
     * definisikan relasinya di sini.
     */
    public function varian()
    {
        // Contoh jika berelasi ke tabel price_data
        return $this->hasMany(PriceData::class, 'komoditas_id');
    }
}