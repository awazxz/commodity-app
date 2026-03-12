<?php

namespace App\Http\Traits;

use App\Models\UserPreference;
use Illuminate\Http\Request;
use Carbon\Carbon;

trait SavesUserPreferences
{
    /**
     * Baca preferensi terakhir user dari DB.
     * Dipakai sebagai fallback saat request tidak membawa parameter.
     */
    protected function loadUserPreferences(int $userId): UserPreference
    {
        return UserPreference::getOrDefault($userId);
    }

    /**
     * Simpan preferensi saat user melakukan submit form prediksi.
     * Hanya dipanggil jika request adalah POST (user sengaja mengubah parameter).
     */
    protected function persistUserPreferences(int $userId, array $data): void
    {
        UserPreference::saveFromRequest($userId, [
            'start_date'              => $data['start_date']              ?? null,
            'end_date'                => $data['end_date']                ?? null,
            'forecast_weeks'          => isset($data['forecast_weeks'])
                                            ? (int) $data['forecast_weeks'] : null,
            'changepoint_prior_scale' => isset($data['changepoint_prior_scale'])
                                            ? (float) $data['changepoint_prior_scale'] : null,
            'seasonality_prior_scale' => isset($data['seasonality_prior_scale'])
                                            ? (float) $data['seasonality_prior_scale'] : null,
            'seasonality_mode'        => $data['seasonality_mode']        ?? null,
            'weekly_seasonality'      => isset($data['weekly_seasonality'])
                                            ? filter_var($data['weekly_seasonality'], FILTER_VALIDATE_BOOLEAN) : null,
            'yearly_seasonality'      => isset($data['yearly_seasonality'])
                                            ? filter_var($data['yearly_seasonality'], FILTER_VALIDATE_BOOLEAN) : null,
        ]);
    }

    /**
     * Resolve parameter akhir: prioritas → request POST > preferences DB > default hardcode.
     * Mengembalikan array parameter yang siap dipakai controller.
     */
    protected function resolveParameters(Request $request, UserPreference $prefs): array
    {
        $isPost = $request->isMethod('POST');

        // Helper: ambil dari request jika POST dan ada, fallback ke pref, lalu ke default
        $get = fn(string $key, $prefVal, $default) =>
            ($isPost && $request->has($key))
                ? $request->input($key)
                : ($prefVal ?? $default);

        $startDate = $get('start_date',
            $prefs->start_date ? $prefs->start_date->format('Y-m-d') : null,
            null   // null → akan di-resolve dari dbMinDate di controller
        );

        $endDate = $get('end_date',
            $prefs->end_date ? $prefs->end_date->format('Y-m-d') : null,
            null   // null → akan di-resolve dari dbMaxDate di controller
        );

        $forecastWeeks = (int) $get('forecast_weeks', $prefs->forecast_weeks, 12);
        $forecastWeeks = max(1, min(52, $forecastWeeks));

        $cpScale = (float) $get('changepoint_prior_scale', $prefs->changepoint_prior_scale, 0.05);
        $cpScale = max(0.001, min(0.5, $cpScale));

        $seasonScale = (float) $get('seasonality_prior_scale', $prefs->seasonality_prior_scale, 10.0);
        $seasonScale = max(0.01, min(50.0, $seasonScale));

        $seasonMode = $get('seasonality_mode', $prefs->seasonality_mode, 'multiplicative');
        if (!in_array($seasonMode, ['additive', 'multiplicative'])) {
            $seasonMode = 'multiplicative';
        }

        $weeklySeason = filter_var(
            $get('weekly_seasonality',
                $prefs->weekly_seasonality,
                false),
            FILTER_VALIDATE_BOOLEAN
        );

        $yearlySeason = filter_var(
            $get('yearly_seasonality',
                $prefs->yearly_seasonality,
                false),
            FILTER_VALIDATE_BOOLEAN
        );

        return compact(
            'startDate', 'endDate',
            'forecastWeeks',
            'cpScale', 'seasonScale', 'seasonMode',
            'weeklySeason', 'yearlySeason'
        );
    }
}