<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class PriceData extends Model
{
    protected $table = 'price_data';

    protected $fillable = [
        'komoditas_id',
        'tanggal',
        'harga',
        'status',
        'is_outlier',
    ];

    protected $casts = [
        'tanggal'    => 'date',
        'is_outlier' => 'boolean',
        'harga'      => 'float',
    ];

    // Relasi ke master_komoditas
    public function komoditas()
    {
        return $this->belongsTo(MasterKomoditas::class, 'komoditas_id');
    }

    // Accessor: format tanggal "23/02/2026"
    public function getFormattedDateAttribute(): string
    {
        return Carbon::parse($this->tanggal)->format('d/m/Y');
    }

    // Accessor: format harga "Rp 14.750"
    public function getFormattedPriceAttribute(): string
    {
        return 'Rp ' . number_format($this->harga, 0, ',', '.');
    }
}