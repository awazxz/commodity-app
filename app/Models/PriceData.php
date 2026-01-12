<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceData extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang terkait dengan model.
     * Secara default Laravel akan menganggap nama tabelnya 'price_data'
     */
    protected $table = 'price_data';

    /**
     * Atribut yang dapat diisi secara massal (Mass Assignable).
     * Sesuaikan dengan kolom yang kita buat di file migration.
     */
    protected $fillable = [
        'nama_varian',
        'kuantitas',
        'satuan',
        'harga',
        'tahun',
        'bulan',
        'minggu',
    ];

    /**
     * Jika Anda ingin memformat harga secara otomatis saat ditampilkan
     * Anda bisa menambahkan accessor (opsional)
     */
    public function getHargaFormattedAttribute()
    {
        return 'Rp ' . number_format($this->harga, 0, ',', '.');
    }
}