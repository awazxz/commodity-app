<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPreference extends Model
{
    protected $table = 'user_preferences';

    protected $fillable = [
        'user_id',
        'start_date',
        'end_date',
        'forecast_weeks',
        'changepoint_prior_scale',
        'seasonality_prior_scale',
        'seasonality_mode',
        'weekly_seasonality',
        'yearly_seasonality',
    ];

    protected $casts = [
        'start_date'              => 'date',
        'end_date'                => 'date',
        'forecast_weeks'          => 'integer',
        'changepoint_prior_scale' => 'float',
        'seasonality_prior_scale' => 'float',
        'weekly_seasonality'      => 'boolean',
        'yearly_seasonality'      => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Ambil preferensi user, buat baru dengan default jika belum ada.
     */
    public static function getOrDefault(int $userId): self
    {
        return self::firstOrCreate(
            ['user_id' => $userId],
            [
                'start_date'              => null,
                'end_date'                => null,
                'forecast_weeks'          => 12,
                'changepoint_prior_scale' => 0.05,
                'seasonality_prior_scale' => 10.0,
                'seasonality_mode'        => 'multiplicative',
                'weekly_seasonality'      => false,
                'yearly_seasonality'      => false,
            ]
        );
    }

    /**
     * Simpan preferensi dari array input (hasil dari request).
     */
    public static function saveFromRequest(int $userId, array $data): self
    {
        return self::updateOrCreate(
            ['user_id' => $userId],
            array_filter($data, fn($v) => $v !== null)
        );
    }
}