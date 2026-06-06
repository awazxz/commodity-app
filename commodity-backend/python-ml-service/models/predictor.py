"""
models/predictor.py

Changelog v8.3 — Monthly Support + Hybrid Removed:
  - Hapus train_hybrid / HybridCommodityModel (tidak diperlukan)
  - Patch _convert_periods(): support data bulanan (MS) dengan benar
  - DEFAULT_HYPERPARAMS: yearly_fourier_order 20→10, n_changepoints 25→15
    untuk data bulanan (lebih ringan dan stabil)

Changelog v8.2 — Hyperparams Sync Fix:
  1. DEFAULT_HYPERPARAMS sinkron dengan prophet_forecasting.py v10.2
     (cp=0.1, cpr=0.85, ws=False, sps=10.0)
  2. best_params_from_cache reuse lengkap: tambah cpr dan ws.

Changelog v8.1 — Fitted Values Fix.
Changelog v8.0 — Speed + Accuracy Overhaul.
"""

import os
import warnings
from datetime import datetime

import joblib
import numpy as np
import pandas as pd
from sklearn.ensemble import RandomForestRegressor
from sklearn.metrics import mean_squared_error

from models.prophet_forecasting import (
    CommodityForecastModel,
    MODEL_DIR,
    MIN_DATA_POINTS,
    MAX_STALE_DAYS,
    _merge_forecast_actual,
)

warnings.filterwarnings('ignore')


# ═══════════════════════════════════════════════════════════════
# KONSTANTA DEFAULT — SATU SUMBER KEBENARAN
# WAJIB sinkron dengan prophet_forecasting.py dan app.py
#
# v8.3: yearly_fourier_order dan n_changepoints diturunkan
#       agar lebih ringan untuk data bulanan
# ═══════════════════════════════════════════════════════════════

DEFAULT_HYPERPARAMS = {
    'changepoint_prior_scale': 0.1,    # sync v10.2 FIX #4
    'seasonality_prior_scale': 10.0,   # sync v10.2
    'seasonality_mode':        'additive',
    'weekly_seasonality':      False,  # sync v10.2 FIX #6
    'yearly_seasonality':      True,
    'yearly_fourier_order':    10,     # v8.3: was 20 — lebih ringan untuk data bulanan
    'monthly_seasonality':     True,
    'n_changepoints':          15,     # v8.3: was 25 — lebih ringan untuk data bulanan
    'changepoint_range':       0.85,   # sync v10.2 FIX #2
}


# ═══════════════════════════════════════════════════════════════
# HELPERS
# ═══════════════════════════════════════════════════════════════

def _mape(actual, predicted) -> float:
    actual    = np.array(actual,    dtype=float)
    predicted = np.array(predicted, dtype=float)
    mask      = actual != 0
    if not mask.any():
        return 0.0
    return float(np.mean(np.abs((actual[mask] - predicted[mask]) / actual[mask])) * 100)


def _normalize_freq(freq: str) -> str:
    mapping = {'D': 'D', 'W': 'W', 'M': 'MS', 'MS': 'MS', 'ME': 'MS'}
    return mapping.get(freq.upper().strip(), 'W')


def _convert_periods(periods: int, freq: str) -> int:
    """
    Konversi periods ke jumlah periode sesuai frekuensi data.

    Frontend kirim periods:
    - Data bulanan (MS) : periods = jumlah bulan langsung (dari target_month)
                          Minimal 3, maksimal 24 bulan.
    - Data mingguan (W) : periods dalam hari → bagi 7, minimal 4 minggu.
    - Data harian (D)   : periods dalam hari, minimal 30.
    """
    if freq in ('MS', 'M', 'ME'):
        # Untuk bulanan: periods sudah dalam satuan bulan
        return max(3, min(periods, 24))
    elif freq == 'W':
        converted = max(1, periods // 7)
        return max(converted, 4)   # minimal 4 minggu
    elif freq == 'D':
        return max(periods, 30)    # minimal 30 hari
    return max(1, periods)


def _detect_user_override(hyperparams: dict) -> bool:
    if not hyperparams:
        return False

    if 'user_override' in hyperparams:
        result = bool(hyperparams['user_override'])
        print(f"   [Predictor] _detect_user_override: flag eksplisit = {result}")
        return result

    for key, default_val in DEFAULT_HYPERPARAMS.items():
        if key not in hyperparams:
            continue
        user_val = hyperparams[key]

        if isinstance(default_val, float):
            try:
                if abs(float(user_val) - default_val) > 1e-9:
                    print(f"   [Predictor] _detect_user_override: {key} berbeda "
                          f"({float(user_val)} vs default {default_val})")
                    return True
            except (TypeError, ValueError):
                pass
        elif isinstance(default_val, bool):
            if bool(user_val) != default_val:
                return True
        elif isinstance(default_val, int):
            try:
                if int(user_val) != default_val:
                    return True
            except (TypeError, ValueError):
                pass
        else:
            if user_val != default_val:
                return True

    print(f"   [Predictor] _detect_user_override: semua param sama → False")
    return False


# ═══════════════════════════════════════════════════════════════
# MAIN CLASS
# ═══════════════════════════════════════════════════════════════

class CommodityPredictor:

    RF_MODEL_DIR = os.path.join(MODEL_DIR, 'rf_fallback')

    def __init__(self):
        os.makedirs(MODEL_DIR,         exist_ok=True)
        os.makedirs(self.RF_MODEL_DIR, exist_ok=True)

    # ──────────────────────────────────────────────────────────
    # PUBLIC: PREDICT (serving path — harus cepat)
    # ──────────────────────────────────────────────────────────

    def predict(
        self,
        commodity_id:  int,
        historical_df: pd.DataFrame,
        periods:       int  = 12,
        frequency:     str  = 'MS',
        hyperparams:   dict = None,
        force_retrain: bool = False,
    ) -> dict:
        hyperparams = hyperparams or {}

        detected_freq    = CommodityForecastModel.detect_frequency(historical_df)
        use_freq         = _normalize_freq(detected_freq)
        forecast_periods = _convert_periods(periods, use_freq)

        print(f"\n   [Predictor] commodity_id={commodity_id} | "
              f"rows={len(historical_df)} | "
              f"freq_detected={detected_freq} → {use_freq} | "
              f"periods={periods} → {forecast_periods} {use_freq}")

        if len(historical_df) >= MIN_DATA_POINTS:
            return self._predict_prophet(
                commodity_id, historical_df, forecast_periods,
                use_freq, hyperparams, force_retrain,
            )
        else:
            print(f"   [Predictor] ⚠️  Data kurang ({len(historical_df)} < {MIN_DATA_POINTS}), "
                  f"pakai RF fallback")
            return self._predict_rf_fallback(
                commodity_id, historical_df, forecast_periods, use_freq,
            )

    # ──────────────────────────────────────────────────────────
    # PROPHET ENGINE (serving path)
    # ──────────────────────────────────────────────────────────

    def _predict_prophet(
        self,
        commodity_id:     int,
        df:               pd.DataFrame,
        forecast_periods: int,
        use_freq:         str,
        hyperparams:      dict,
        force_retrain:    bool,
    ) -> dict:

        last_data_date   = df['ds'].max()
        is_user_override = _detect_user_override(hyperparams)

        print(f"   [Predictor] last_data_date={last_data_date.date()} | "
              f"forecast_periods={forecast_periods} {use_freq} | "
              f"is_user_override={is_user_override}")

        loaded_once = CommodityForecastModel.load_model(commodity_id)

        if not force_retrain and loaded_once:
            _, payload_check = loaded_once
            cached_last_date = payload_check['last_date']
            gap_days         = (last_data_date - cached_last_date).days

            if gap_days > MAX_STALE_DAYS:
                print(f"   [Predictor] ⚠️  Data historis lama (gap={gap_days}d). "
                        f"Forecast akan dimulai dari last_date={cached_last_date.date()}")
            elif gap_days > 7:
                force_retrain = True
                print(f"   [Predictor] 🔄 Auto force_retrain: "
                      f"gap={gap_days}d > 7d")

        if force_retrain:
            needs  = True
            reason = "force_retrain"
        elif is_user_override:
            needs  = True
            reason = "user_override_params"
        else:
            hp_for_check  = self._build_hp(hyperparams)
            needs, reason = CommodityForecastModel.needs_retraining(
                commodity_id, df, hyperparams=hp_for_check,
            )

        model_source = None
        forecaster   = None

        if not needs:
            if loaded_once:
                forecaster, payload = loaded_once
                model_source = (
                    f"cached (trained {payload['trained_at'].strftime('%Y-%m-%d %H:%M')})"
                )
                print(f"   [Predictor] ✅ Pakai cached model — {reason}")
            else:
                needs  = True
                reason = "load_failed"

        if needs:
            print(f"   [Predictor] 🔁 Retrain (serving path, no grid search) — {reason}")

            # Ambil dari request hyperparams, fallback ke DEFAULT_HYPERPARAMS
            cp  = float(hyperparams.get('changepoint_prior_scale',
                                        DEFAULT_HYPERPARAMS['changepoint_prior_scale']))
            ss  = float(hyperparams.get('seasonality_prior_scale',
                                        DEFAULT_HYPERPARAMS['seasonality_prior_scale']))
            sm  = hyperparams.get('seasonality_mode',
                                  DEFAULT_HYPERPARAMS['seasonality_mode'])
            ws  = bool(hyperparams.get('weekly_seasonality',
                                       DEFAULT_HYPERPARAMS['weekly_seasonality']))
            ys  = bool(hyperparams.get('yearly_seasonality',
                                       DEFAULT_HYPERPARAMS['yearly_seasonality']))
            yfo = int(hyperparams.get('yearly_fourier_order',
                                      DEFAULT_HYPERPARAMS['yearly_fourier_order']))
            ms  = bool(hyperparams.get('monthly_seasonality',
                                       DEFAULT_HYPERPARAMS['monthly_seasonality']))
            ncp = int(hyperparams.get('n_changepoints',
                                      DEFAULT_HYPERPARAMS['n_changepoints']))
            cpr = float(hyperparams.get('changepoint_range',
                                        DEFAULT_HYPERPARAMS['changepoint_range']))

            best_params_from_cache = None
            if loaded_once:
                _, old_payload = loaded_once
                best_params_from_cache = old_payload.get('best_params')
                if best_params_from_cache and not is_user_override:
                    print(f"   [Predictor] Reuse best_params dari model sebelumnya: "
                          f"cps={best_params_from_cache.get('changepoint_prior_scale')} | "
                          f"sps={best_params_from_cache.get('seasonality_prior_scale')} | "
                          f"cpr={best_params_from_cache.get('changepoint_range')} | "
                          f"ws={best_params_from_cache.get('weekly_seasonality')}")
                    cp  = best_params_from_cache.get('changepoint_prior_scale', cp)
                    ss  = best_params_from_cache.get('seasonality_prior_scale', ss)
                    sm  = best_params_from_cache.get('seasonality_mode', sm)
                    yfo = best_params_from_cache.get('yearly_fourier_order', yfo)
                    ncp = best_params_from_cache.get('n_changepoints', ncp)
                    # FIX v8.2: changepoint_range dan weekly_seasonality ikut diambil
                    cpr = best_params_from_cache.get('changepoint_range', cpr)
                    ws  = best_params_from_cache.get('weekly_seasonality', ws)

            forecaster = CommodityForecastModel(
                changepoint_prior_scale = cp,
                seasonality_prior_scale = ss,
                seasonality_mode        = sm,
                weekly_seasonality      = ws,
                yearly_seasonality      = ys,
                yearly_fourier_order    = yfo,
                monthly_seasonality     = ms,
                n_changepoints          = ncp,
                changepoint_range       = cpr,
                user_override           = is_user_override,
            )
            if best_params_from_cache and not is_user_override:
                forecaster.best_params = best_params_from_cache

            forecaster.train(df, freq=use_freq)

            forecaster.save_model(commodity_id, metadata={
                'triggered_by':    'predictor_serving',
                'reason':           reason,
                'is_user_override': is_user_override,
                'note':             'no_grid_search_serving_path',
            })
            model_source = f"newly_trained_serving ({reason})"

        # ──────────────────────────────────────────────────────
        # Panggil predict() dengan include_history=True
        # Pisah: ds <= last_data_date → fitted_values
        #        ds >  last_data_date → forecast_df
        # ──────────────────────────────────────────────────────
        try:
            full_df = forecaster.predict(
                periods         = forecast_periods,
                freq            = use_freq,
                start_after     = last_data_date,
                include_history = True,
            )
        except ValueError as e:
            raise

        forecast_df = full_df[full_df['ds'] > last_data_date].head(forecast_periods).reset_index(drop=True)
        fitted_df   = full_df[full_df['ds'] <= last_data_date].reset_index(drop=True)

        if len(forecast_df) == 0:
            raise ValueError(
                f"Tidak ada forecast setelah {last_data_date.date()}. "
                f"Coba force_retrain=True."
            )

        fitted_values = []
        try:
            if not fitted_df.empty:
                merged_fitted = _merge_forecast_actual(
                    fitted_df[['ds', 'yhat', 'yhat_lower', 'yhat_upper']],
                    df[['ds', 'y']],
                )
                fitted_values = [
                    {
                        'date':         row['ds'].strftime('%Y-%m-%d'),
                        'fitted_price': round(float(row['yhat']),       2),
                        'lower_bound':  round(float(row['yhat_lower']), 2),
                        'upper_bound':  round(float(row['yhat_upper']), 2),
                    }
                    for _, row in merged_fitted.iterrows()
                ]
                print(f"   [Predictor] ✅ fitted_values: {len(fitted_values)} titik "
                      f"| forecast_df: {len(forecast_df)} titik")
        except Exception as e:
            print(f"   [Predictor] ⚠️  Gagal format fitted_values: {e}")
            fitted_values = []

        metrics = forecaster.get_model_metrics()

        if not forecast_df.empty:
            first_forecast = forecast_df['ds'].min()
            last_forecast  = forecast_df['ds'].max()
            print(f"   [Predictor] forecast range: "
                  f"{first_forecast.date()} → {last_forecast.date()} "
                  f"({len(forecast_df)} titik)")

        predictions           = self._format_predictions(forecast_df)
        trend_direction       = self._detect_trend(forecast_df['yhat'].values)
        future_interval_width = float(
            forecast_df['yhat_upper'].mean() - forecast_df['yhat_lower'].mean()
        )

        return {
            'engine':                'prophet',
            'model_source':          model_source,
            'data_points':           len(df),
            'last_data_date':        last_data_date.strftime('%Y-%m-%d'),
            'forecast_periods':      forecast_periods,
            'frequency':             use_freq,
            'predictions':           predictions,
            'fitted_values':         fitted_values,
            'trend_direction':       trend_direction,
            'future_interval_width': round(future_interval_width, 2),
            'model_metrics':         self._format_metrics(metrics, use_freq),
            'is_user_override':      is_user_override,
            'best_params':           forecaster.best_params,
        }

    # ──────────────────────────────────────────────────────────
    # RANDOM FOREST FALLBACK
    # ──────────────────────────────────────────────────────────

    def _predict_rf_fallback(
        self,
        commodity_id:     int,
        df:               pd.DataFrame,
        forecast_periods: int,
        use_freq:         str,
    ) -> dict:
        rf_path = os.path.join(self.RF_MODEL_DIR, f"rf_{commodity_id}.pkl")

        last_data_date = df['ds'].max()

        df_feat = df.copy()
        df_feat['day_of_year']      = df_feat['ds'].dt.dayofyear
        df_feat['month']            = df_feat['ds'].dt.month
        df_feat['year']             = df_feat['ds'].dt.year
        df_feat['days_since_start'] = (df_feat['ds'] - df_feat['ds'].min()).dt.days

        feature_cols = ['days_since_start', 'month', 'day_of_year']
        X = df_feat[feature_cols].values
        y = df_feat['y'].values

        if os.path.exists(rf_path):
            rf_payload = joblib.load(rf_path)
            model      = rf_payload['model']
            start_date = rf_payload['start_date']
            print(f"   [RF] ✅ Load cached RF model")
        else:
            model = RandomForestRegressor(n_estimators=100, random_state=42)
            model.fit(X, y)
            start_date = df_feat['ds'].min()
            joblib.dump({'model': model, 'start_date': start_date}, rf_path)
            print(f"   [RF] 💾 RF model trained & saved")

        freq_map   = {'D': 1, 'W': 7, 'MS': 30}
        delta_days = freq_map.get(use_freq, 7)

        future_dates = [
            last_data_date + pd.Timedelta(days=delta_days * i)
            for i in range(1, forecast_periods + 1)
        ]

        predictions = []
        for fd in future_dates:
            days_since = (fd - start_date).days
            feat       = np.array([[days_since, fd.month, fd.dayofyear]])
            pred_price = float(model.predict(feat)[0])
            margin     = pred_price * 0.05
            predictions.append({
                'date':            fd.strftime('%Y-%m-%d'),
                'predicted_price': round(pred_price, 2),
                'lower_bound':     round(pred_price - margin, 2),
                'upper_bound':     round(pred_price + margin, 2),
                'trend':           round(pred_price, 2),
            })

        y_pred   = model.predict(X)
        mape_val = round(_mape(y, y_pred), 4)
        rmse_val = round(float(np.sqrt(mean_squared_error(y, y_pred))), 2)
        mae_val  = round(float(np.mean(np.abs(y - y_pred))), 2)

        metrics = {
            'mape': mape_val, 'rmse': rmse_val, 'mae': mae_val,
            'coverage': 0.90, 'in_sample_mape': mape_val,
            'in_sample_rmse': rmse_val, 'in_sample_mae': mae_val,
            'avg_interval_width': 0.0, 'future_interval_width': 0.0,
            'changepoint_count': 0, 'trend_flexibility': 0.0,
            'seasonality_strength': 0.0, 'trend_direction': 'stable',
            'confidence_level': 0.90,
            'cv_method': 'in_sample_rf_fallback',
            'data_frequency': use_freq,
        }

        return {
            'engine':                'random_forest_fallback',
            'model_source':          'rf_cached_or_trained',
            'data_points':           len(df),
            'last_data_date':        last_data_date.strftime('%Y-%m-%d'),
            'forecast_periods':      forecast_periods,
            'frequency':             use_freq,
            'predictions':           predictions,
            'fitted_values':         [],
            'trend_direction':       self._detect_trend(
                [p['predicted_price'] for p in predictions]
            ),
            'future_interval_width': 0.0,
            'model_metrics':         metrics,
            'is_user_override':      False,
            'best_params':           None,
        }

    # ──────────────────────────────────────────────────────────
    # TRAIN ONLY (scheduler path) — tanpa hybrid
    # ──────────────────────────────────────────────────────────

    def train_and_save(
        self,
        commodity_id:  int,
        df:            pd.DataFrame,
        hyperparams:   dict = None,
        freq:          str  = None,
    ) -> dict:
        hyperparams      = hyperparams or {}
        is_user_override = _detect_user_override(hyperparams)

        detected_freq = CommodityForecastModel.detect_frequency(df)
        use_freq      = _normalize_freq(freq or detected_freq)

        cp  = float(hyperparams.get('changepoint_prior_scale',
                                    DEFAULT_HYPERPARAMS['changepoint_prior_scale']))
        ss  = float(hyperparams.get('seasonality_prior_scale',
                                    DEFAULT_HYPERPARAMS['seasonality_prior_scale']))
        sm  = hyperparams.get('seasonality_mode',
                              DEFAULT_HYPERPARAMS['seasonality_mode'])
        ws  = bool(hyperparams.get('weekly_seasonality',
                                   DEFAULT_HYPERPARAMS['weekly_seasonality']))
        ys  = bool(hyperparams.get('yearly_seasonality',
                                   DEFAULT_HYPERPARAMS['yearly_seasonality']))
        yfo = int(hyperparams.get('yearly_fourier_order',
                                  DEFAULT_HYPERPARAMS['yearly_fourier_order']))
        ms  = bool(hyperparams.get('monthly_seasonality',
                                   DEFAULT_HYPERPARAMS['monthly_seasonality']))
        ncp = int(hyperparams.get('n_changepoints',
                                  DEFAULT_HYPERPARAMS['n_changepoints']))
        cpr = float(hyperparams.get('changepoint_range',
                                    DEFAULT_HYPERPARAMS['changepoint_range']))

        forecaster = CommodityForecastModel(
            changepoint_prior_scale = cp,
            seasonality_prior_scale = ss,
            seasonality_mode        = sm,
            weekly_seasonality      = ws,
            yearly_seasonality      = ys,
            yearly_fourier_order    = yfo,
            monthly_seasonality     = ms,
            n_changepoints          = ncp,
            changepoint_range       = cpr,
            user_override           = is_user_override,
        )

        if not is_user_override:
            print(f"   [Scheduler] Grid search commodity_id={commodity_id}...")
            best = forecaster.auto_grid_search(df, freq=use_freq)
            print(f"   [Scheduler] Grid search selesai: best_mape={best.get('mape')}")
        else:
            print(f"   [Scheduler] Grid search dilewati — user_override=True")

        forecaster.train(df, freq=use_freq)

        path = forecaster.save_model_with_metrics(
            commodity_id,
            metadata={
                'triggered_by':    'scheduler',
                'is_user_override': is_user_override,
            },
        )

        return {
            'commodity_id':    commodity_id,
            'trained_at':      datetime.now().isoformat(),
            'data_points':     len(df),
            'last_date':       df['ds'].max().strftime('%Y-%m-%d'),
            'freq':            use_freq,
            'model_path':      path,
            'is_user_override': is_user_override,
            'best_params':     forecaster.best_params,
        }

    # ──────────────────────────────────────────────────────────
    # CACHE INVALIDATION
    # ──────────────────────────────────────────────────────────

    def invalidate_cache(self, commodity_id: int) -> None:
        print(f"   [Predictor] invalidate_cache commodity_id={commodity_id} (no-op, stateless)")

    def clear_all_cache(self) -> None:
        print(f"   [Predictor] clear_all_cache (no-op, stateless)")

    # ──────────────────────────────────────────────────────────
    # HELPERS PRIVATE
    # ──────────────────────────────────────────────────────────

    @staticmethod
    def _build_hp(hyperparams: dict) -> dict:
        return {k: hyperparams.get(k, v) for k, v in DEFAULT_HYPERPARAMS.items()}

    @staticmethod
    def _format_predictions(forecast_df: pd.DataFrame) -> list:
        result = []
        for _, row in forecast_df.iterrows():
            result.append({
                'date':            row['ds'].strftime('%Y-%m-%d'),
                'predicted_price': round(float(row['yhat']),       2),
                'lower_bound':     round(float(row['yhat_lower']), 2),
                'upper_bound':     round(float(row['yhat_upper']), 2),
                'trend':           round(float(row.get('trend', row['yhat'])), 2),
            })
        return result

    @staticmethod
    def _format_metrics(metrics: dict, use_freq: str) -> dict:
        ext = metrics.get('extended_metrics', {})
        return {
            'mape':                  float(metrics.get('mape', 0)),
            'rmse':                  float(metrics.get('rmse', 0)),
            'mae':                   float(metrics.get('mae',  0)),
            'coverage':              float(metrics.get('coverage', 0.95)),
            'r_squared':             float(metrics.get('r_squared', 0.0)),
            'in_sample_mape':        float(metrics.get('in_sample_mape', 0)),
            'in_sample_rmse':        float(metrics.get('in_sample_rmse', 0)),
            'in_sample_mae':         float(metrics.get('in_sample_mae',  0)),
            'avg_interval_width':    float(metrics.get('avg_interval_width', 0)),
            'future_interval_width': 0.0,
            'changepoint_count':     int(metrics.get('changepoint_count', 0)),
            'trend_flexibility':     float(metrics.get('trend_flexibility', 0)),
            'seasonality_strength':  float(metrics.get('seasonality_strength', 0)),
            'trend_direction':       'stable',
            'confidence_level':      0.95,
            'cv_method':             metrics.get('cv_method',
                                                 f'walk_forward_80_20_{use_freq}'),
            'data_frequency':        use_freq,
            'best_params_from_grid_search': metrics.get('best_params_from_grid_search'),
            'user_override':         metrics.get('user_override', False),
            'smape':              float(ext.get('smape', 0)),
            'directional_acc':    float(ext.get('directional_acc', 0)),
            'winkler_score':      float(ext.get('winkler_score', 0)),
            'pinball_lower':      float(ext.get('pinball_lower', 0)),
            'pinball_upper':      float(ext.get('pinball_upper', 0)),
            'interval_sharpness': float(ext.get('interval_sharpness', 0)),
            'in_sample_smape':    float(ext.get('in_sample_smape', 0)),
            'in_sample_dir_acc':  float(ext.get('in_sample_dir_acc', 0)),
            'rolling_cv_mape':    float(ext.get('rolling_cv_mape', 0)),
            'rolling_cv_dir_acc': float(ext.get('rolling_cv_dir_acc', 0)),
            'rolling_cv_winkler': float(ext.get('rolling_cv_winkler', 0)),
            'rolling_cv_coverage': float(ext.get('rolling_cv_coverage', 0)),
        }

    @staticmethod
    def _detect_trend(values) -> str:
        values = list(values)
        if len(values) < 2:
            return 'stable'
        first_val = float(values[0])
        last_val  = float(values[-1])
        if first_val == 0:
            return 'stable'
        threshold = first_val * 0.01
        if last_val > first_val + threshold:
            return 'increasing'
        elif last_val < first_val - threshold:
            return 'decreasing'
        return 'stable'