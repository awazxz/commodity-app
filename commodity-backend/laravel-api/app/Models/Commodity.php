<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Commodity extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'current_price',
        'price_date',
        'description',
    ];

    protected $casts = [
        'price_date' => 'date',
        'current_price' => 'decimal:2',
    ];

    public function predictions()
    {
        return $this->hasMany(Prediction::class);
    }
}