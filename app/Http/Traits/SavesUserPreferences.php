<?php

namespace App\Http\Traits;

use App\Models\UserPreference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

trait SavesUserPreferences
{
    // =========================================================
    // LOAD
    // =========================================================

    /**
     * Baca preferensi user dari DB.
     * Jika belum ada, buat baru dengan nilai default.
     * Tidak pernah return null.
     */
    protected function loadUserPreferences(int $userId, int $komoditasId = 0): UserPreference
    {
        return UserPreference::getOrDefault($userId, $komoditasId);
    }

    // =========================================================
    // PERSIST — hanya dipanggil dari AdminController
    // UserController tidak boleh memanggil method ini
    // =========================================================

    /**
     * Simpan preferensi saat admin submit form prediksi (POST).
     * Hanya dipanggil jika request adalah POST dan tab = insight.
     */
    protected function persistUserPreferences(int $userId, array $data, int $komoditasId = 0): void
    {
        UserPreference::saveFromRequest($userId, [
            'start_date'              => $data['start_date']               ?? null,
            'end_date'                => $data['end_date']                 ?? null,
            'forecast_months'          => isset($data['forecast_months'])
                                            ? (int)   $data['forecast_months']   : null,
            'changepoint_prior_scale' => isset($data['changepoint_prior_scale'])
                                            ? (float) $data['changepoint_prior_scale'] : null,
            'seasonality_prior_scale' => isset($data['seasonality_prior_scale'])
                                            ? (float) $data['seasonality_prior_scale'] : null,
            'seasonality_mode'        => $data['seasonality_mode']         ?? null,
            'weekly_seasonality'      => isset($data['weekly_seasonality'])
                                            ? filter_var($data['weekly_seasonality'], FILTER_VALIDATE_BOOLEAN)
                                            : null,
            'yearly_seasonality'      => isset($data['yearly_seasonality'])
                                            ? filter_var($data['yearly_seasonality'], FILTER_VALIDATE_BOOLEAN)
                                            : null,
        ], $komoditasId);

        Log::info('[TRAIT persistUserPreferences] Preferensi disimpan untuk user_id=' . $userId, [
            'start_date'              => $data['start_date']               ?? null,
            'end_date'                => $data['end_date']                 ?? null,
            'forecast_months'          => $data['forecast_months']           ?? null,
            'changepoint_prior_scale' => $data['changepoint_prior_scale']  ?? null,
            'seasonality_prior_scale' => $data['seasonality_prior_scale']  ?? null,
            'seasonality_mode'        => $data['seasonality_mode']         ?? null,
            'weekly_seasonality'      => $data['weekly_seasonality']       ?? null,
            'yearly_seasonality'      => $data['yearly_seasonality']       ?? null,
        ]);
    }

    // =========================================================
    // RESOLVE — dipakai AdminController saja
    // UserController menggunakan resolveAdminParams() sendiri
    // yang membaca prefs admin tanpa melihat $request sama sekali
    // =========================================================

    /**
     * Resolve parameter akhir untuk AdminController.
     * Prioritas: POST request > preferensi DB > default konstanta.
     *
     * JANGAN dipanggil dari UserController — user tidak boleh
     * override parameter apapun via request.
     */
    protected function resolveParameters(Request $request, UserPreference $prefs): array
    {
        $isPost = $request->isMethod('POST');

        Log::info('[TRAIT resolveParameters]', [
            'isPost'                  => $isPost,
            'method'                  => $request->method(),
            'has_forecast_months'      => $request->has('forecast_months'),
            'forecast_months_raw'      => $request->input('forecast_months',          'NOT_IN_REQUEST'),
            'cp_raw'                  => $request->input('changepoint_prior_scale', 'NOT_IN_REQUEST'),
            'ss_raw'                  => $request->input('seasonality_prior_scale', 'NOT_IN_REQUEST'),
            'mode_raw'                => $request->input('seasonality_mode',        'NOT_IN_REQUEST'),
            'weekly_raw'              => $request->input('weekly_seasonality',      'NOT_IN_REQUEST'),
            'yearly_raw'              => $request->input('yearly_seasonality',      'NOT_IN_REQUEST'),
            'all_keys_sent'           => array_keys($request->all()),
        ]);

        // Helper: ambil dari POST jika ada, fallback ke DB, lalu ke default
        $get = fn(string $key, $prefVal, $default) =>
            ($isPost && $request->has($key))
                ? $request->input($key)
                : ($prefVal ?? $default);

        // ── Tanggal ───────────────────────────────────────────
        $startDate = $get(
            'start_date',
            $prefs->getStartDateString(),
            null
        );

        $endDate = $get(
            'end_date',
            $prefs->getEndDateString(),
            null
        );

        // Jika end_date lebih dari 30 hari yang lalu, anggap stale → reset ke null
        // supaya controller fallback ke dbMaxDate (tanggal data terbaru di DB)
        if ($endDate && Carbon::parse($endDate)->lt(Carbon::now()->subDays(30))) {
            Log::info('[TRAIT resolveParameters] end_date stale, direset ke null: ' . $endDate);
            $endDate = null;
        }

        // ── Forecast weeks ─────────────────────────────────────
        // $forecastWeeks = (int) $get('forecast_months', $prefs->forecast_months, 12);
        // $forecastWeeks = max(1, min(52, $forecastWeeks));

        $forecastMonths = (int) $get('forecast_months', $prefs->forecast_months, 12);
        $forecastMonths = max(1, min(52, $forecastMonths));
        // ── Changepoint prior scale ────────────────────────────
        $cpScale = (float) $get('changepoint_prior_scale', $prefs->changepoint_prior_scale, 0.1);
        $cpScale = max(0.001, min(0.5, $cpScale));

        // ── Seasonality prior scale ────────────────────────────
        $seasonScale = (float) $get('seasonality_prior_scale', $prefs->seasonality_prior_scale, 10.0);
        $seasonScale = max(0.01, min(50.0, $seasonScale));

        // ── Seasonality mode ───────────────────────────────────
        $seasonMode = $get('seasonality_mode', $prefs->seasonality_mode, 'additive');
        if (!in_array($seasonMode, ['additive', 'multiplicative'], true)) {
            $seasonMode = 'additive';
        }

        // ── Weekly seasonality ─────────────────────────────────
        $weeklySeason = filter_var(
            $get('weekly_seasonality', $prefs->weekly_seasonality, false),
            FILTER_VALIDATE_BOOLEAN
        );

        // ── Yearly seasonality ─────────────────────────────────
        $yearlySeason = filter_var(
            $get('yearly_seasonality', $prefs->yearly_seasonality, true),
            FILTER_VALIDATE_BOOLEAN
        );

        Log::info('[TRAIT resolveParameters] Hasil resolve:', [
            'startDate'     => $startDate,
            'endDate'       => $endDate,
            // 'forecastWeeks' => $forecastWeeks,
            'forecastMonths' => $forecastMonths,
            'cpScale'       => $cpScale,
            'seasonScale'   => $seasonScale,
            'seasonMode'    => $seasonMode,
            'weeklySeason'  => $weeklySeason,
            'yearlySeason'  => $yearlySeason,
        ]);

        // return compact(
        //     'startDate', 'endDate',
        //     'forecastWeeks',
        //     'cpScale', 'seasonScale', 'seasonMode',
        //     'weeklySeason', 'yearlySeason'
        // );
        return compact(
            'startDate', 'endDate',
            'forecastMonths',       // ← ganti variable juga di atasnya
            'cpScale', 'seasonScale', 'seasonMode',
            'weeklySeason', 'yearlySeason'
        );
    }
}