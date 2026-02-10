<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceForecast extends Model
{
    use HasFactory;

    // Nama tabel harus sesuai dengan yang di database
    protected $table = 'price_forecasts';

    // Izinkan kolom-kolom ini diisi secara otomatis oleh Laravel
    protected $fillable = [
        'komoditas_id',
        'tanggal',
        'yhat',
        'yhat_lower',
        'yhat_upper',
    ];

    /**
     * Relasi ke Master Komoditas
     */
    public function komoditas()
    {
        return $this->belongsTo(MasterKomoditas::class, 'komoditas_id');
    }
}