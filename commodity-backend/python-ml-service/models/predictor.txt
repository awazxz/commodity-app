"""
models/predictor.py

CommodityPredictor — wrapper level tinggi yang mengintegrasikan:
- Prophet (forecasting utama via CommodityForecastModel)
- Random Forest (fallback jika data < MIN_DATA_POINTS untuk Prophet)
- Model persistence untuk kedua jenis model

FIX v2:
- _predict_prophet() meneruskan start_after=last_data_date ke
  CommodityForecastModel.predict() — nilai ini berasal dari
  historical_df yang sudah difilter oleh user (bukan train_df cache)
- Ini memastikan forecast dimulai tepat setelah data aktual terakhir
  yang dipilih user, tidak ada loncatan tahun
- Auto force_retrain jika end_date filter user berbeda >7 hari
  dari last_date yang tersimpan di cache model
"""

import os
import warnings
from datetime import datetime

import joblib
import numpy as np
import pandas as pd
from sklearn.ensemble import RandomForestRegressor
from sklearn.metrics import mean_squared_error

from models.prophet_forecasting import CommodityForecastModel, MODEL_DIR, MIN_DATA_POINTS

warnings.filterwarnings('ignore')


# ═══════════════════════════════════════════════════════════════
# HELPERS
# ═══════════════════════════════════════════════════════════════

def _normalize_freq(freq: str) -> str:
    mapping = {'D': 'D', 'W': 'W', 'M': 'MS', 'MS': 'MS', 'ME': 'MS'}
    return mapping.get(freq.upper().strip(), 'W')


def _convert_periods(periods: int, freq: str) -> int:
    """Konversi periods dari satuan hari ke unit frekuensi data."""
    if freq == 'W':
        return max(1, periods // 7)
    elif freq in ('MS', 'M', 'ME'):
        return max(1, periods // 30)
    return max(1, periods)


def _compute_gap_periods(last_date: pd.Timestamp, freq: str) -> int:
    """
    Hitung berapa banyak periode yang dibutuhkan untuk menutup gap
    antara tanggal data terakhir dan hari ini.
    """
    today = pd.Timestamp.now().normalize()
    if today <= last_date:
        return 0

    gap_days  = (today - last_date).days
    freq_days = {'D': 1, 'W': 7, 'MS': 30}
    days_per  = freq_days.get(freq, 7)

    return max(0, int(gap_days // days_per) + 2)  # +2 buffer


# ═══════════════════════════════════════════════════════════════
# MAIN CLASS
# ═══════════════════════════════════════════════════════════════

class CommodityPredictor:
    """
    High-level predictor yang mengelola lifecycle model secara otomatis.

    FIX v2 utama:
    - predict() meneruskan start_after=last_data_date ke
      CommodityForecastModel.predict(), di mana last_data_date
      diambil dari historical_df terfilter (bukan dari cache).
    - Auto force_retrain jika date range berbeda dari cache.
    - Ini memastikan tidak ada loncatan tahun meski model sudah
      di-cache berbulan-bulan atau bertahun-tahun.
    """

    RF_MODEL_DIR = os.path.join(MODEL_DIR, 'rf_fallback')

    def __init__(self):
        os.makedirs(MODEL_DIR,         exist_ok=True)
        os.makedirs(self.RF_MODEL_DIR, exist_ok=True)

    # ──────────────────────────────────────────────────────────
    # PUBLIC: PREDICT (entry point utama)
    # ──────────────────────────────────────────────────────────

    def predict(
        self,
        commodity_id:  int,
        historical_df: pd.DataFrame,
        periods:       int  = 84,
        frequency:     str  = 'W',
        hyperparams:   dict = None,
        force_retrain: bool = False,
    ) -> dict:
        """
        Lakukan prediksi harga komoditas.

        Parameters
        ----------
        commodity_id   : ID komoditas di master_komoditas
        historical_df  : DataFrame dengan kolom 'ds' (datetime) dan 'y' (float)
                         — sudah difilter berdasarkan start_date & end_date user
        periods        : jumlah hari ke depan (default 84 = 12 minggu)
        frequency      : frekuensi data dari PHP ('D', 'W', 'M')
        hyperparams    : dict hyperparameter Prophet (opsional)
        force_retrain  : paksa training ulang meski model masih fresh

        Return
        ------
        dict dengan keys: predictions, model_metrics, model_source, dll.
        """
        hyperparams = hyperparams or {}

        detected_freq    = CommodityForecastModel.detect_frequency(historical_df)
        use_freq         = _normalize_freq(detected_freq)
        forecast_periods = _convert_periods(periods, use_freq)

        print(f"\n   [Predictor] commodity_id={commodity_id} | "
              f"rows={len(historical_df)} | "
              f"freq_detected={detected_freq} → {use_freq} | "
              f"periods={periods}d → {forecast_periods} {use_freq}")

        if len(historical_df) >= MIN_DATA_POINTS:
            return self._predict_prophet(
                commodity_id, historical_df, forecast_periods,
                use_freq, hyperparams, force_retrain
            )
        else:
            print(f"   [Predictor] ⚠️  Data kurang ({len(historical_df)} < {MIN_DATA_POINTS}), "
                  f"pakai RF fallback")
            return self._predict_rf_fallback(
                commodity_id, historical_df, forecast_periods, use_freq
            )

    # ──────────────────────────────────────────────────────────
    # PROPHET ENGINE — FIXED v2
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

        # ── FIX v2: Ambil last_data_date dari df yang sudah terfilter ─
        # BUKAN dari train_df cache — ini kuncinya!
        # Ketika user memilih end_date tertentu, historical_df sudah difilter
        # di app.py sebelum masuk ke sini, jadi last_data_date = akhir data user.
        last_data_date = df['ds'].max()

        print(f"   [Predictor] last_data_date={last_data_date.date()} | "
              f"forecast_periods={forecast_periods} {use_freq}")

        # ── FIX v2: Auto force_retrain jika date range berbeda dari cache ─
        # Jika user ubah end_date dan cache model punya last_date berbeda >7 hari,
        # retrain supaya model melihat data yang sama dengan yang difilter user.
        if not force_retrain:
            loaded_check = CommodityForecastModel.load_model(commodity_id)
            if loaded_check:
                _, payload_check = loaded_check
                cached_last_date = payload_check['last_date']
                date_diff_days   = abs((last_data_date - cached_last_date).days)

                if date_diff_days > 7:
                    force_retrain = True
                    print(f"   [Predictor] 🔄 Auto force_retrain: "
                          f"cache last_date={cached_last_date.date()} vs "
                          f"current last_date={last_data_date.date()} "
                          f"(diff={date_diff_days}d > 7d)")

        # ── Cek kebutuhan retraining ───────────────────────────
        needs, reason = CommodityForecastModel.needs_retraining(
            commodity_id, df, hyperparams=hyperparams
        )

        if force_retrain:
            needs  = True
            reason = "force_retrain"

        model_source = None
        forecaster   = None

        if not needs:
            loaded = CommodityForecastModel.load_model(commodity_id)
            if loaded:
                forecaster, payload = loaded
                model_source = f"cached (trained {payload['trained_at'].strftime('%Y-%m-%d %H:%M')})"
                print(f"   [Predictor] ✅ Pakai cached model — {reason}")
            else:
                needs  = True
                reason = "load_failed"

        if needs:
            print(f"   [Predictor] 🔁 Retrain — {reason}")

            cp = float(hyperparams.get('changepoint_prior_scale', 0.05))
            ss = float(hyperparams.get('seasonality_prior_scale', 10.0))
            sm = hyperparams.get('seasonality_mode', 'multiplicative')
            ws = bool(hyperparams.get('weekly_seasonality', False))
            ys = bool(hyperparams.get('yearly_seasonality', True))

            forecaster = CommodityForecastModel(
                changepoint_prior_scale = cp,
                seasonality_prior_scale = ss,
                seasonality_mode        = sm,
                weekly_seasonality      = ws,
                yearly_seasonality      = ys,
            )
            forecaster.train(df, freq=use_freq)
            forecaster.save_model(commodity_id, metadata={
                'triggered_by': 'predictor',
                'reason':        reason,
            })
            model_source = f"newly_trained ({reason})"

        # ── FIX v2: Teruskan start_after=last_data_date ke predict() ──
        # last_data_date berasal dari df (historical_df terfilter user),
        # BUKAN dari train_df internal model yang mungkin stale.
        # Ini memastikan forecast mulai tepat setelah data aktual terakhir user.
        forecast_df = forecaster.predict(
            periods     = forecast_periods,
            freq        = use_freq,
            start_after = last_data_date,   # ← FIX UTAMA v2
        )

        metrics = forecaster.get_model_metrics()

        # ── Validasi & log tanggal hasil forecast ─────────────
        if not forecast_df.empty:
            first_forecast = forecast_df['ds'].min()
            last_forecast  = forecast_df['ds'].max()
            print(f"   [Predictor] forecast range: "
                  f"{first_forecast.date()} → {last_forecast.date()} "
                  f"({len(forecast_df)} titik)")

            # Peringatkan jika masih ada gap besar (data tidak up-to-date)
            gap_days = (first_forecast - last_data_date).days
            if gap_days > 60:
                print(f"   [Predictor] ⚠️  Gap {gap_days} hari antara data terakhir "
                      f"dan forecast pertama. Pertimbangkan untuk input data aktual "
                      f"sampai mendekati hari ini.")

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
            'trend_direction':       trend_direction,
            'future_interval_width': round(future_interval_width, 2),
            'model_metrics':         self._format_metrics(metrics, use_freq),
        }

    # ──────────────────────────────────────────────────────────
    # RANDOM FOREST FALLBACK — FIXED v2
    # ──────────────────────────────────────────────────────────

    def _predict_rf_fallback(
        self,
        commodity_id:     int,
        df:               pd.DataFrame,
        forecast_periods: int,
        use_freq:         str,
    ) -> dict:
        """
        Fallback ke Random Forest untuk data yang terlalu sedikit untuk Prophet.
        FIX v2: forecast dimulai dari tanggal setelah last_data_date df terfilter.
        """
        rf_path = os.path.join(self.RF_MODEL_DIR, f"rf_{commodity_id}.pkl")

        # FIX v2: ambil dari df terfilter, bukan dari cache
        last_data_date = df['ds'].max()

        df_feat = df.copy()
        df_feat['day_of_year']      = df_feat['ds'].dt.dayofyear
        df_feat['month']            = df_feat['ds'].dt.month
        df_feat['year']             = df_feat['ds'].dt.year
        df_feat['days_since_start'] = (
            df_feat['ds'] - df_feat['ds'].min()
        ).dt.days

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

        # FIX v2: mulai dari last_data_date df terfilter, bukan dari sekarang
        freq_map   = {'D': 1, 'W': 7, 'MS': 30}
        delta_days = freq_map.get(use_freq, 7)

        future_dates = [
            last_data_date + pd.Timedelta(days=delta_days * i)
            for i in range(1, forecast_periods + 1)
        ]

        print(f"   [RF] forecast range: "
              f"{future_dates[0].date()} → {future_dates[-1].date()} "
              f"({len(future_dates)} titik)")

        predictions = []
        for fd in future_dates:
            days_since = (fd - start_date).days
            feat       = np.array([[days_since, fd.month, fd.dayofyear]])
            pred_price = float(model.predict(feat)[0])

            margin = pred_price * 0.05
            predictions.append({
                'date':            fd.strftime('%Y-%m-%d'),
                'predicted_price': round(pred_price, 2),
                'lower_bound':     round(pred_price - margin, 2),
                'upper_bound':     round(pred_price + margin, 2),
                'trend':           round(pred_price, 2),
            })

        y_pred  = model.predict(X)
        mse     = mean_squared_error(y, y_pred)
        metrics = {
            'mape':                  round(float(self._mape(y, y_pred)), 4),
            'rmse':                  round(float(np.sqrt(mse)), 2),
            'mae':                   round(float(np.mean(np.abs(y - y_pred))), 2),
            'coverage':              0.90,
            'in_sample_mape':        round(float(self._mape(y, y_pred)), 4),
            'in_sample_rmse':        round(float(np.sqrt(mse)), 2),
            'in_sample_mae':         round(float(np.mean(np.abs(y - y_pred))), 2),
            'avg_interval_width':    0.0,
            'future_interval_width': 0.0,
            'changepoint_count':     0,
            'trend_flexibility':     0.0,
            'seasonality_strength':  0.0,
            'trend_direction':       'stable',
            'confidence_level':      0.90,
            'cv_method':             'in_sample_rf_fallback',
            'data_frequency':        use_freq,
        }

        trend_direction = self._detect_trend([p['predicted_price'] for p in predictions])

        return {
            'engine':                'random_forest_fallback',
            'model_source':          'rf_cached_or_trained',
            'data_points':           len(df),
            'last_data_date':        last_data_date.strftime('%Y-%m-%d'),
            'forecast_periods':      forecast_periods,
            'frequency':             use_freq,
            'predictions':           predictions,
            'trend_direction':       trend_direction,
            'future_interval_width': 0.0,
            'model_metrics':         metrics,
        }

    # ──────────────────────────────────────────────────────────
    # TRAIN ONLY (untuk batch / scheduler)
    # ──────────────────────────────────────────────────────────

    def train_and_save(
        self,
        commodity_id: int,
        df:           pd.DataFrame,
        hyperparams:  dict = None,
        freq:         str  = None,
    ) -> dict:
        """
        Train model dan simpan ke disk tanpa generate prediksi.
        Digunakan oleh scheduler malam.
        """
        hyperparams = hyperparams or {}

        detected_freq = CommodityForecastModel.detect_frequency(df)
        use_freq      = _normalize_freq(freq or detected_freq)

        cp = float(hyperparams.get('changepoint_prior_scale', 0.05))
        ss = float(hyperparams.get('seasonality_prior_scale', 10.0))
        sm = hyperparams.get('seasonality_mode', 'multiplicative')
        ws = bool(hyperparams.get('weekly_seasonality', False))
        ys = bool(hyperparams.get('yearly_seasonality', True))

        forecaster = CommodityForecastModel(
            changepoint_prior_scale = cp,
            seasonality_prior_scale = ss,
            seasonality_mode        = sm,
            weekly_seasonality      = ws,
            yearly_seasonality      = ys,
        )
        forecaster.train(df, freq=use_freq)
        path = forecaster.save_model(commodity_id, metadata={
            'triggered_by': 'scheduler',
        })

        return {
            'commodity_id': commodity_id,
            'trained_at':   datetime.now().isoformat(),
            'data_points':  len(df),
            'last_date':    df['ds'].max().strftime('%Y-%m-%d'),
            'freq':         use_freq,
            'model_path':   path,
        }

    # ──────────────────────────────────────────────────────────
    # CACHE INVALIDATION (dipanggil dari app.py clear-cache)
    # ──────────────────────────────────────────────────────────

    def invalidate_cache(self, commodity_id: int) -> None:
        """
        Invalidate in-memory cache untuk satu komoditas.
        Dipanggil dari endpoint clear-cache di app.py.
        """
        # CommodityPredictor tidak menyimpan in-memory cache sendiri,
        # tapi method ini disediakan agar app.py tidak error saat memanggil
        # predictor.invalidate_cache(id) di endpoint clear-cache.
        print(f"   [Predictor] invalidate_cache commodity_id={commodity_id} (no-op, stateless)")

    def clear_all_cache(self) -> None:
        """
        Clear semua in-memory cache.
        Dipanggil dari endpoint clear-cache-all di app.py.
        """
        print(f"   [Predictor] clear_all_cache (no-op, stateless)")

    # ──────────────────────────────────────────────────────────
    # HELPERS
    # ──────────────────────────────────────────────────────────

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
        return {
            'mape':                  float(metrics.get('mape', 0)),
            'rmse':                  float(metrics.get('rmse', 0)),
            'mae':                   float(metrics.get('mae',  0)),
            'coverage':              float(metrics.get('coverage', 0.95)),
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
            'cv_method':             metrics.get('cv_method', f'walk_forward_80_20_{use_freq}'),
            'data_frequency':        use_freq,
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

    @staticmethod
    def _mape(actual, predicted) -> float:
        actual    = np.array(actual,    dtype=float)
        predicted = np.array(predicted, dtype=float)
        mask      = actual != 0
        if not mask.any():
            return 0.0
        return float(np.mean(np.abs((actual[mask] - predicted[mask]) / actual[mask])) * 100)