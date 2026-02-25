<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommodityPrice extends Model
{
    use HasFactory;

    protected $table = 'commodity_prices'; // SESUAIKAN NAMA TABEL

    protected $fillable = [
        'commodity_name',
        'date',
        'price',
    ];

    protected $casts = [
        'date'  => 'date',
        'price' => 'float',
    ];
}
