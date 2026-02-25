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

    // Relasi ke price_data
    public function priceData()
    {
        return $this->hasMany(PriceData::class, 'komoditas_id');
    }

    // Accessor: "Beras Premium" atau "Beras" kalau tidak ada varian
    public function getDisplayNameAttribute(): string
    {
        return $this->nama_varian
            ? $this->nama_komoditas . ' ' . $this->nama_varian
            : $this->nama_komoditas;
    }
}