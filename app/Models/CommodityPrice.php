<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommodityPrice extends Model
{
    use HasFactory;

    // ✅ Ganti ke tabel yang benar
    protected $table = 'price_data';

    protected $fillable = [
        'komoditas_id',
        'tanggal',
        'harga',
        'status',
        'is_outlier',
    ];

    protected $casts = [
        'tanggal'    => 'date:Y-m-d',
        'harga'      => 'float',
        'is_outlier' => 'boolean',
    ];

    public function komoditas()
    {
        return $this->belongsTo(MasterKomoditas::class, 'komoditas_id');
    }
}