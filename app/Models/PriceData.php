<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\MasterKomoditas;


class PriceData extends Model
{
    use HasFactory;

    /**
     * Nama tabel di database
     */
    protected $table = 'price_data';

    /**
     * Kolom yang boleh diisi secara mass assignment
     * (harus sesuai dengan migration baru)
     */
    protected $fillable = [
        'komoditas_id',
        'tanggal',
        'harga',
    ];

    /**
     * Cast otomatis tipe data
     */
    protected $casts = [
        'tanggal' => 'date',
        'harga'   => 'float',
    ];

    /**
     * Relasi ke master komoditas
     * price_data.komoditas_id -> master_komoditas.id
     */

    public function komoditas()
    {
        return $this->belongsTo(MasterKomoditas::class, 'komoditas_id');
    }

    /**
     * Scope khusus untuk kebutuhan forecasting (Prophet)
     * Menghasilkan format: ds, y
     */
    public function scopeForForecast($query)
    {
        return $query->selectRaw('tanggal as ds, harga as y')
                     ->orderBy('tanggal');
    }

    /**
     * Accessor opsional untuk format harga (UI)
     */
    public function getHargaFormattedAttribute(): string
    {
        return 'Rp ' . number_format($this->harga, 0, ',', '.');
    }
}
