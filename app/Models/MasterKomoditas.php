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

    public function prices()
    {
        return $this->hasMany(PriceData::class, 'komoditas_id');
    }
}
