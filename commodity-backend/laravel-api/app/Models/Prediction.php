<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prediction extends Model
{
    use HasFactory;

    protected $fillable = [
        'commodity_id',
        'user_id',
        'prediction_date',
        'predicted_price',
        'confidence_score',
        'model_parameters',
    ];

    protected $casts = [
        'prediction_date' => 'date',
        'predicted_price' => 'decimal:2',
        'confidence_score' => 'decimal:2',
        'model_parameters' => 'array',
    ];

    public function commodity()
    {
        return $this->belongsTo(Commodity::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}