<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterKomoditas extends Model
{
    protected $table = 'master_komoditas';

    protected $fillable = [
        'nama_komoditas',
        'nama_varian',
        'satuan',
        'kuantitas',
    ];

    // =========================================================
    // Relasi ke tabel harga (price_data)
    // =========================================================
    public function priceData()
    {
        return $this->hasMany(PriceData::class, 'komoditas_id');
    }

    // =========================================================
    // Accessor display_name
    // Menggabungkan nama_komoditas + nama_varian jika ada
    // Contoh: "Beras Premium", "Cabai Merah", "Jagung"
    // =========================================================
    public function getDisplayNameAttribute(): string
    {
        $nama   = $this->nama_komoditas ?? '';
        $varian = $this->nama_varian    ?? '';

        // Hanya gabungkan jika nama_varian tidak null, tidak kosong,
        // dan belum termasuk bagian dari nama_komoditas
        if ($varian && !str_contains($nama, $varian)) {
            return trim($nama . ' ' . $varian);
        }

        return $nama ?: ('Komoditas #' . $this->id);
    }
}