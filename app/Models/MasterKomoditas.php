<?php

namespace App\Models;
use App\Models\PriceData;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MasterKomoditas extends Model
{
    use HasFactory;

    protected $table = 'master_komoditas';

    protected $fillable = [
        'nama_komoditas',
        'nama_varian',
        'satuan',
        'kuantitas',
    ];

   // TAMBAHKAN INI
    public function priceData()
    {
        // Ganti 'komoditas_id' dengan nama kolom foreign key di tabel price_data kamu
        return $this->hasMany(PriceData::class, 'komoditas_id');
    }
}