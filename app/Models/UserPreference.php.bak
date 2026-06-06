<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

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

    // =========================================================
    // DEFAULT — satu sumber kebenaran, sinkron dengan
    // AdminController::DEFAULT_* dan DEFAULT_HYPERPARAMS Flask
    // =========================================================
    private const DEFAULTS = [
        'start_date'              => null,
        'end_date'                => null,
        'forecast_weeks'          => 12,
        'changepoint_prior_scale' => 0.05,
        'seasonality_prior_scale' => 1.0,
        'seasonality_mode'        => 'multiplicative',
        'weekly_seasonality'      => false,
        'yearly_seasonality'      => true,
    ];

    // =========================================================
    // RELATIONS
    // =========================================================
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // =========================================================
    // STATIC HELPERS
    // =========================================================

    /**
     * Ambil preferensi user dari DB.
     * Jika belum ada, buat baru dengan nilai default.
     * Tidak pernah return null — selalu return instance yang valid.
     */
    public static function getOrDefault(int $userId): self
    {
        return self::firstOrCreate(
            ['user_id' => $userId],
            self::DEFAULTS
        );
    }

    /**
     * Simpan preferensi dari array input request.
     * Hanya field yang tidak null yang di-update (tidak menimpa field lain).
     */
    public static function saveFromRequest(int $userId, array $data): self
    {
        $filtered = array_filter($data, fn($v) => $v !== null);

        // Validasi seasonality_mode sebelum simpan
        if (isset($filtered['seasonality_mode'])
            && !in_array($filtered['seasonality_mode'], ['additive', 'multiplicative'], true)
        ) {
            $filtered['seasonality_mode'] = self::DEFAULTS['seasonality_mode'];
        }

        // Clamp forecast_weeks agar tidak di luar batas wajar
        if (isset($filtered['forecast_weeks'])) {
            $filtered['forecast_weeks'] = max(1, min(52, (int) $filtered['forecast_weeks']));
        }

        // Clamp changepoint_prior_scale
        if (isset($filtered['changepoint_prior_scale'])) {
            $filtered['changepoint_prior_scale'] = max(0.001, min(0.5, (float) $filtered['changepoint_prior_scale']));
        }

        // Clamp seasonality_prior_scale
        if (isset($filtered['seasonality_prior_scale'])) {
            $filtered['seasonality_prior_scale'] = max(0.01, min(50.0, (float) $filtered['seasonality_prior_scale']));
        }

        return self::updateOrCreate(
            ['user_id' => $userId],
            $filtered
        );
    }

    // =========================================================
    // INSTANCE HELPERS
    // =========================================================

    /**
     * Return start_date sebagai string Y-m-d, atau null jika belum diset.
     * Aman dipanggil tanpa cek instanceof Carbon di controller.
     */
    public function getStartDateString(): ?string
    {
        return $this->start_date instanceof Carbon
            ? $this->start_date->format('Y-m-d')
            : null;
    }

    /**
     * Return end_date sebagai string Y-m-d, atau null jika belum diset.
     */
    public function getEndDateString(): ?string
    {
        return $this->end_date instanceof Carbon
            ? $this->end_date->format('Y-m-d')
            : null;
    }

    /**
     * Return semua nilai preferensi sebagai array plain PHP.
     * Dipakai controller untuk resolve parameter tanpa perlu akses field satu-satu.
     */
    public function toParamArray(): array
    {
        return [
            'forecastWeeks' => $this->forecast_weeks              ?? self::DEFAULTS['forecast_weeks'],
            'cpScale'       => $this->changepoint_prior_scale     ?? self::DEFAULTS['changepoint_prior_scale'],
            'seasonScale'   => $this->seasonality_prior_scale     ?? self::DEFAULTS['seasonality_prior_scale'],
            'seasonMode'    => $this->seasonality_mode            ?? self::DEFAULTS['seasonality_mode'],
            'weeklySeason'  => $this->weekly_seasonality          ?? self::DEFAULTS['weekly_seasonality'],
            'yearlySeason'  => $this->yearly_seasonality          ?? self::DEFAULTS['yearly_seasonality'],
            'startDate'     => $this->getStartDateString()        ?? '',
            'endDate'       => $this->getEndDateString()          ?? '',
        ];
    }

    /**
     * Return array default tanpa perlu instance.
     * Dipakai sebagai fallback saat tidak ada admin di DB.
     */
    public static function defaultParamArray(): array
    {
        return [
            'forecastWeeks' => self::DEFAULTS['forecast_weeks'],
            'cpScale'       => self::DEFAULTS['changepoint_prior_scale'],
            'seasonScale'   => self::DEFAULTS['seasonality_prior_scale'],
            'seasonMode'    => self::DEFAULTS['seasonality_mode'],
            'weeklySeason'  => self::DEFAULTS['weekly_seasonality'],
            'yearlySeason'  => self::DEFAULTS['yearly_seasonality'],
            'startDate'     => '',
            'endDate'       => '',
        ];
    }
}