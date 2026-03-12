"""
models/prophet_forecasting.py

Model forecasting harga komoditas menggunakan Facebook Prophet.
Dilengkapi dengan:
- Model persistence (simpan/load ke disk)
- Deteksi kebutuhan retraining otomatis
- Walk-forward cross validation
- Hyperparameter-sensitive metrics

FIX v2:
- predict() menerima parameter start_after dari luar (dari historical_df terfilter)
- extra_periods dihitung dari start_after, BUKAN dari train_df yang mungkin stale
- Ini memastikan forecast selalu dimulai setelah data aktual terakhir,
  meski model sudah di-cache berbulan-bulan atau bertahun-tahun
"""

import os
import warnings
from datetime import datetime

import joblib
import numpy as np
import pandas as pd
from prophet import Prophet

warnings.filterwarnings('ignore')


# ═══════════════════════════════════════════════════════════════
# KONSTANTA
# ═══════════════════════════════════════════════════════════════

MODEL_DIR        = os.getenv('MODEL_DIR', 'saved_models')
MODEL_MAX_AGE_H  = int(os.getenv('MODEL_MAX_AGE_HOURS', 24))
MIN_DATA_POINTS  = 10


# ═══════════════════════════════════════════════════════════════
# MAIN CLASS
# ═══════════════════════════════════════════════════════════════

class CommodityForecastModel:
    """
    Wrapper Prophet dengan model persistence dan auto-retraining.

    Lifecycle:
        1. Pertama kali  → train() → save_model()
        2. Request berikutnya → load_model() → predict() langsung (< 1 detik)
        3. Scheduler malam → needs_retraining()? → train() → save_model()
    """

    def __init__(
        self,
        changepoint_prior_scale: float = 0.05,
        seasonality_prior_scale: float = 10.0,
        seasonality_mode: str          = 'multiplicative',
        weekly_seasonality: bool       = False,
        yearly_seasonality: bool       = True,
    ):
        self.changepoint_prior_scale = changepoint_prior_scale
        self.seasonality_prior_scale = seasonality_prior_scale
        self.seasonality_mode        = seasonality_mode
        self.weekly_seasonality      = weekly_seasonality
        self.yearly_seasonality      = yearly_seasonality

        self.model          = None
        self.train_df       = None
        self.data_freq      = 'W'
        self._metrics_cache = None

        os.makedirs(MODEL_DIR, exist_ok=True)

    # ──────────────────────────────────────────────────────────
    # PROPERTY: path file model
    # ──────────────────────────────────────────────────────────

    @staticmethod
    def _model_path(commodity_id: int) -> str:
        return os.path.join(MODEL_DIR, f"commodity_{commodity_id}.pkl")

    # ──────────────────────────────────────────────────────────
    # DETECT FREQUENCY
    # ──────────────────────────────────────────────────────────

    @staticmethod
    def detect_frequency(df: pd.DataFrame) -> str:
        """
        Deteksi frekuensi data dari interval antar baris kolom 'ds'.
        Return: 'D' (harian) | 'W' (mingguan) | 'MS' (bulanan)
        """
        if len(df) < 2:
            return 'W'

        df_sorted = df.sort_values('ds').reset_index(drop=True)
        sample    = df_sorted['ds'].head(11)
        diffs     = sample.diff().dropna().dt.days.tolist()
        avg_diff  = sum(diffs) / len(diffs) if diffs else 7

        if avg_diff <= 2:
            return 'D'
        elif avg_diff <= 10:
            return 'W'
        else:
            return 'MS'

    # ──────────────────────────────────────────────────────────
    # TRAIN
    # ──────────────────────────────────────────────────────────

    def train(self, df: pd.DataFrame, freq: str = None) -> None:
        """
        Latih model Prophet dengan seluruh data historis.
        Model lama akan ditimpa.
        """
        self.train_df       = df.copy()
        self._metrics_cache = None
        self.data_freq      = freq if freq else self.detect_frequency(df)

        print(f"   [Prophet] Training | freq={self.data_freq} | "
              f"rows={len(df)} | "
              f"cp={self.changepoint_prior_scale} | "
              f"ss={self.seasonality_prior_scale} | "
              f"mode={self.seasonality_mode}")

        self.model = Prophet(
            changepoint_prior_scale = self.changepoint_prior_scale,
            seasonality_prior_scale = self.seasonality_prior_scale,
            seasonality_mode        = self.seasonality_mode,
            weekly_seasonality      = self.weekly_seasonality,
            yearly_seasonality      = self.yearly_seasonality,
            daily_seasonality       = False,
            interval_width          = 0.95,
        )
        self.model.fit(df[['ds', 'y']])
        print(f"   [Prophet] ✅ Training selesai")

    # ──────────────────────────────────────────────────────────
    # PREDICT — FIXED v2
    # ──────────────────────────────────────────────────────────

    def predict(
        self,
        periods:      int            = 12,
        freq:         str            = None,
        start_after:  pd.Timestamp   = None,
    ) -> pd.DataFrame:
        """
        Generate forecast ke depan sebanyak `periods` unit frekuensi.

        FIX v2:
        - start_after diisi dari LUAR oleh predictor.py dengan nilai
          historical_df['ds'].max() — yaitu tanggal akhir data yang
          sudah difilter oleh user (bukan train_df yang mungkin stale).
        - extra_periods dihitung dari start_after ini, sehingga forecast
          selalu dimulai tepat setelah data aktual terakhir user.
        - Mencegah loncatan tahun ketika model di-cache lama dan
          end_date filter user berbeda dari saat model dilatih.

        Parameters
        ----------
        periods     : jumlah titik forecast yang diinginkan (dalam unit freq)
        freq        : frekuensi ('D', 'W', 'MS')
        start_after : WAJIB diisi dari luar — tanggal terakhir data aktual
                      yang difilter user. Fallback ke train_df jika None.

        Return
        ------
        DataFrame: ds, yhat, yhat_lower, yhat_upper, trend
        """
        if self.model is None:
            raise ValueError("Model belum dilatih. Panggil train() terlebih dahulu.")

        use_freq = freq if freq else self.data_freq

        # ── FIX v2: Prioritas start_after dari parameter (historical_df terfilter)
        # Jika tidak diisi, fallback ke train_df (perilaku lama, kurang akurat)
        if start_after is None:
            if self.train_df is not None:
                start_after = self.train_df['ds'].max()
                print(f"   [Prophet] ⚠️  start_after tidak diset dari luar, "
                      f"fallback ke train_df.max()={start_after.date()}")
            else:
                raise ValueError("start_after tidak diset dan train_df kosong.")

        # ── Hitung extra_periods untuk menutup gap dari start_after ke sekarang
        # Contoh: data terakhir 2024-06-30, freq='W', hari ini=2026-03-09
        # → gap ≈ 89 minggu → extra_periods=91 (89+2 buffer)
        # → total = 91 + target_periods
        today    = pd.Timestamp.now().normalize()
        gap_days = max(0, (today - start_after).days)

        freq_days = {'D': 1, 'W': 7, 'MS': 30}
        days_per  = freq_days.get(use_freq, 7)

        extra_periods = max(0, int(gap_days // days_per) + 2)  # +2 buffer
        total_periods = extra_periods + periods

        print(f"   [Prophet] predict | freq={use_freq} | "
              f"start_after={start_after.date()} | "
              f"gap={gap_days}d → extra={extra_periods} | "
              f"target={periods} | total={total_periods}")

        future   = self.model.make_future_dataframe(periods=total_periods, freq=use_freq)
        forecast = self.model.predict(future)

        result = forecast[['ds', 'yhat', 'yhat_lower', 'yhat_upper', 'trend']].copy()

        # ── Filter: ambil HANYA yang setelah start_after ─────
        result = result[result['ds'] > start_after].reset_index(drop=True)

        # ── Ambil tepat `periods` titik pertama ──────────────
        result = result.head(periods).reset_index(drop=True)

        if len(result) == 0:
            raise ValueError(
                f"Tidak ada forecast yang dihasilkan setelah {start_after.date()}. "
                f"start_after={start_after.date()}, total_periods_generated={total_periods}. "
                f"Coba paksa retrain dengan force_retrain=True."
            )

        return result

    # ──────────────────────────────────────────────────────────
    # SAVE MODEL KE DISK
    # ──────────────────────────────────────────────────────────

    def save_model(self, commodity_id: int, metadata: dict = None) -> str:
        """
        Simpan model Prophet beserta metadata ke disk.
        File: saved_models/commodity_{id}.pkl
        Return: path file yang disimpan
        """
        if self.model is None:
            raise ValueError("Tidak ada model untuk disimpan.")

        path = self._model_path(commodity_id)

        payload = {
            'model':       self.model,
            'train_df':    self.train_df,
            'data_freq':   self.data_freq,
            'hyperparams': {
                'changepoint_prior_scale': self.changepoint_prior_scale,
                'seasonality_prior_scale': self.seasonality_prior_scale,
                'seasonality_mode':        self.seasonality_mode,
                'weekly_seasonality':      self.weekly_seasonality,
                'yearly_seasonality':      self.yearly_seasonality,
            },
            'trained_at':  datetime.now(),
            'data_points': len(self.train_df),
            'last_date':   self.train_df['ds'].max(),
            'metadata':    metadata or {},
        }

        joblib.dump(payload, path)
        print(f"   [Model] 💾 Model disimpan → {path} "
              f"(last_date={payload['last_date'].date()}, "
              f"rows={payload['data_points']})")
        return path

    # ──────────────────────────────────────────────────────────
    # LOAD MODEL DARI DISK
    # ──────────────────────────────────────────────────────────

    @classmethod
    def load_model(cls, commodity_id: int):
        """
        Load model dari disk.
        Return: (instance, payload) | None
        """
        path = cls._model_path(commodity_id)

        if not os.path.exists(path):
            print(f"   [Model] ℹ️  Model tidak ditemukan: {path}")
            return None

        try:
            payload = joblib.load(path)

            hp       = payload['hyperparams']
            instance = cls(
                changepoint_prior_scale = hp['changepoint_prior_scale'],
                seasonality_prior_scale = hp['seasonality_prior_scale'],
                seasonality_mode        = hp['seasonality_mode'],
                weekly_seasonality      = hp['weekly_seasonality'],
                yearly_seasonality      = hp['yearly_seasonality'],
            )
            instance.model     = payload['model']
            instance.train_df  = payload['train_df']
            instance.data_freq = payload['data_freq']

            print(f"   [Model] ✅ Model loaded: komoditas_id={commodity_id} | "
                  f"trained_at={payload['trained_at'].strftime('%Y-%m-%d %H:%M')} | "
                  f"last_date={payload['last_date'].date()} | "
                  f"rows={payload['data_points']}")

            return instance, payload

        except Exception as e:
            print(f"   [Model] ⚠️  Gagal load model komoditas_id={commodity_id}: {e}")
            return None

    # ──────────────────────────────────────────────────────────
    # CEK KEBUTUHAN RETRAINING
    # ──────────────────────────────────────────────────────────

    @staticmethod
    def needs_retraining(
        commodity_id:  int,
        current_df:    pd.DataFrame,
        max_age_hours: int  = MODEL_MAX_AGE_H,
        hyperparams:   dict = None,
    ) -> tuple:
        """
        Tentukan apakah model perlu dilatih ulang.

        Kondisi retrain:
        1. File model belum ada
        2. File model corrupt
        3. Ada data baru sejak training terakhir
        4. Model sudah melebihi max_age_hours
        5. Hyperparameter berubah

        Return: (needs_retrain: bool, reason: str)
        """
        path = CommodityForecastModel._model_path(commodity_id)

        if not os.path.exists(path):
            return True, "model_not_found"

        try:
            payload = joblib.load(path)

            trained_at        = payload['trained_at']
            last_trained_date = payload['last_date']
            age_hours         = (datetime.now() - trained_at).total_seconds() / 3600

            if age_hours > max_age_hours:
                return True, f"model_expired ({age_hours:.1f}h > {max_age_hours}h)"

            current_last_date = current_df['ds'].max()
            if current_last_date > last_trained_date:
                new_rows = len(current_df[current_df['ds'] > last_trained_date])
                return True, f"new_data ({new_rows} baris baru sejak {last_trained_date.date()})"

            if hyperparams:
                saved_hp = payload.get('hyperparams', {})
                for key, val in hyperparams.items():
                    if saved_hp.get(key) != val:
                        return True, f"hyperparams_changed ({key}: {saved_hp.get(key)} → {val})"

            return False, f"model_fresh (trained {age_hours:.1f}h ago, last_date={last_trained_date.date()})"

        except Exception as e:
            return True, f"model_corrupt ({e})"

    # ──────────────────────────────────────────────────────────
    # MODEL INFO
    # ──────────────────────────────────────────────────────────

    @staticmethod
    def get_model_info(commodity_id: int) -> dict:
        path = CommodityForecastModel._model_path(commodity_id)

        if not os.path.exists(path):
            return {'exists': False, 'commodity_id': commodity_id}

        try:
            payload   = joblib.load(path)
            age_hours = (datetime.now() - payload['trained_at']).total_seconds() / 3600

            return {
                'exists':       True,
                'commodity_id': commodity_id,
                'trained_at':   payload['trained_at'].isoformat(),
                'last_date':    payload['last_date'].isoformat(),
                'data_points':  payload['data_points'],
                'data_freq':    payload['data_freq'],
                'age_hours':    round(age_hours, 2),
                'is_fresh':     age_hours <= MODEL_MAX_AGE_H,
                'hyperparams':  payload.get('hyperparams', {}),
                'metadata':     payload.get('metadata', {}),
            }
        except Exception as e:
            return {'exists': False, 'commodity_id': commodity_id, 'error': str(e)}

    # ──────────────────────────────────────────────────────────
    # METRICS
    # ──────────────────────────────────────────────────────────

    def get_model_metrics(self) -> dict:
        """Hitung semua metrics (cached setelah pertama kali)."""
        if self._metrics_cache is not None:
            return self._metrics_cache

        if self.train_df is None or len(self.train_df) < MIN_DATA_POINTS:
            self._metrics_cache = self._empty_metrics()
            return self._metrics_cache

        cv_metrics          = self._compute_cv_metrics()
        insample_metrics    = self._compute_insample_metrics()
        sensitivity_metrics = self._compute_sensitivity_metrics()

        self._metrics_cache = {
            'mape':     cv_metrics.get('mape',     insample_metrics['mape']),
            'rmse':     cv_metrics.get('rmse',     insample_metrics['rmse']),
            'mae':      cv_metrics.get('mae',      insample_metrics['mae']),
            'coverage': cv_metrics.get('coverage', 0.95),
            'in_sample_mape': insample_metrics['mape'],
            'in_sample_rmse': insample_metrics['rmse'],
            'in_sample_mae':  insample_metrics['mae'],
            'avg_interval_width':   sensitivity_metrics['avg_interval_width'],
            'changepoint_count':    sensitivity_metrics['changepoint_count'],
            'trend_flexibility':    sensitivity_metrics['trend_flexibility'],
            'seasonality_strength': sensitivity_metrics['seasonality_strength'],
            'cv_method':       f'walk_forward_80_20_{self.data_freq}',
            'data_frequency':  self.data_freq,
            'hyperparameters_used': {
                'changepoint_prior_scale': self.changepoint_prior_scale,
                'seasonality_prior_scale': self.seasonality_prior_scale,
                'seasonality_mode':        self.seasonality_mode,
                'weekly_seasonality':      self.weekly_seasonality,
                'yearly_seasonality':      self.yearly_seasonality,
            }
        }

        return self._metrics_cache

    def evaluate(self) -> dict:
        metrics = self.get_model_metrics()
        return {
            **metrics,
            'method':         'walk_forward_cross_validation',
            'train_size_pct': 80,
            'test_size_pct':  20,
            'data_frequency': self.data_freq,
        }

    # ──────────────────────────────────────────────────────────
    # INTERNAL: CV METRICS
    # ──────────────────────────────────────────────────────────

    def _compute_cv_metrics(self) -> dict:
        df        = self.train_df.copy()
        n         = len(df)
        test_size = max(4, int(n * 0.20))
        train_size = n - test_size

        if train_size < 8:
            return {}

        train_df = df.iloc[:train_size].reset_index(drop=True)
        test_df  = df.iloc[train_size:].reset_index(drop=True)

        try:
            cv_model = Prophet(
                changepoint_prior_scale = self.changepoint_prior_scale,
                seasonality_prior_scale = self.seasonality_prior_scale,
                seasonality_mode        = self.seasonality_mode,
                weekly_seasonality      = self.weekly_seasonality,
                yearly_seasonality      = self.yearly_seasonality,
                daily_seasonality       = False,
                interval_width          = 0.95,
            )
            cv_model.fit(train_df[['ds', 'y']])

            future        = cv_model.make_future_dataframe(periods=test_size, freq=self.data_freq)
            forecast      = cv_model.predict(future)
            forecast_test = forecast.tail(test_size).reset_index(drop=True)

            actual_vals    = test_df['y'].values
            predicted_vals = forecast_test['yhat'].values
            lower_vals     = forecast_test['yhat_lower'].values
            upper_vals     = forecast_test['yhat_upper'].values

            min_len        = min(len(actual_vals), len(predicted_vals))
            actual_vals    = actual_vals[:min_len]
            predicted_vals = predicted_vals[:min_len]
            lower_vals     = lower_vals[:min_len]
            upper_vals     = upper_vals[:min_len]

            return {
                'mape':     round(float(self._mape(actual_vals, predicted_vals)), 4),
                'rmse':     round(float(self._rmse(actual_vals, predicted_vals)), 2),
                'mae':      round(float(self._mae(actual_vals, predicted_vals)),  2),
                'coverage': round(float(self._coverage(actual_vals, lower_vals, upper_vals)), 4),
            }

        except Exception as e:
            print(f"   [Metrics] ⚠️  CV error: {e}")
            return {}

    def _compute_insample_metrics(self) -> dict:
        if self.model is None or self.train_df is None:
            return {'mape': 0.0, 'rmse': 0.0, 'mae': 0.0}

        try:
            forecast = self.model.predict(self.train_df[['ds']].copy())
            actual   = self.train_df['y'].values
            pred     = forecast['yhat'].values

            return {
                'mape': round(float(self._mape(actual, pred)), 4),
                'rmse': round(float(self._rmse(actual, pred)), 2),
                'mae':  round(float(self._mae(actual, pred)),  2),
            }
        except Exception:
            return {'mape': 0.0, 'rmse': 0.0, 'mae': 0.0}

    def _compute_sensitivity_metrics(self) -> dict:
        if self.model is None or self.train_df is None:
            return {
                'avg_interval_width': 0.0, 'changepoint_count': 0,
                'trend_flexibility': 0.0, 'seasonality_strength': 0.0,
            }

        try:
            forecast           = self.model.predict(self.train_df[['ds']].copy())
            interval_widths    = forecast['yhat_upper'] - forecast['yhat_lower']
            avg_interval_width = round(float(interval_widths.mean()), 2)

            changepoint_count  = 0
            trend_flexibility  = 0.0

            if hasattr(self.model, 'params') and self.model.params:
                try:
                    delta = self.model.params.get('delta', None)
                    if delta is not None:
                        delta_arr         = np.array(delta).flatten()
                        threshold         = np.std(delta_arr) * 0.1 if len(delta_arr) > 0 else 0
                        changepoint_count = int(np.sum(np.abs(delta_arr) > threshold))
                        trend_flexibility = round(float(np.std(delta_arr)), 6)
                except Exception:
                    pass

            seasonality_strength = 0.0
            try:
                if 'yearly' in forecast.columns:
                    seasonality_strength = round(float(forecast['yearly'].std()), 2)
                elif 'additive_terms' in forecast.columns:
                    seasonality_strength = round(float(forecast['additive_terms'].std()), 2)
                elif 'multiplicative_terms' in forecast.columns:
                    seasonality_strength = round(float(forecast['multiplicative_terms'].std() * 100), 4)
            except Exception:
                pass

            return {
                'avg_interval_width':   avg_interval_width,
                'changepoint_count':    changepoint_count,
                'trend_flexibility':    trend_flexibility,
                'seasonality_strength': seasonality_strength,
            }

        except Exception as e:
            print(f"   [Metrics] ⚠️  Sensitivity error: {e}")
            return {
                'avg_interval_width': 0.0, 'changepoint_count': 0,
                'trend_flexibility': 0.0, 'seasonality_strength': 0.0,
            }

    # ──────────────────────────────────────────────────────────
    # STATIC HELPERS
    # ──────────────────────────────────────────────────────────

    @staticmethod
    def _empty_metrics() -> dict:
        return {
            'mape': 0.0, 'rmse': 0.0, 'mae': 0.0, 'coverage': 0.95,
            'in_sample_mape': 0.0, 'in_sample_rmse': 0.0, 'in_sample_mae': 0.0,
            'avg_interval_width': 0.0, 'changepoint_count': 0,
            'trend_flexibility': 0.0, 'seasonality_strength': 0.0,
            'cv_method': 'walk_forward_80_20', 'hyperparameters_used': {},
        }

    @staticmethod
    def _mape(actual: np.ndarray, predicted: np.ndarray) -> float:
        actual    = np.array(actual,    dtype=float)
        predicted = np.array(predicted, dtype=float)
        mask      = actual != 0
        if not mask.any():
            return 0.0
        return float(np.mean(np.abs((actual[mask] - predicted[mask]) / actual[mask])) * 100)

    @staticmethod
    def _rmse(actual: np.ndarray, predicted: np.ndarray) -> float:
        actual    = np.array(actual,    dtype=float)
        predicted = np.array(predicted, dtype=float)
        return float(np.sqrt(np.mean((actual - predicted) ** 2)))

    @staticmethod
    def _mae(actual: np.ndarray, predicted: np.ndarray) -> float:
        actual    = np.array(actual,    dtype=float)
        predicted = np.array(predicted, dtype=float)
        return float(np.mean(np.abs(actual - predicted)))

    @staticmethod
    def _coverage(actual: np.ndarray, lower: np.ndarray, upper: np.ndarray) -> float:
        actual = np.array(actual, dtype=float)
        lower  = np.array(lower,  dtype=float)
        upper  = np.array(upper,  dtype=float)
        inside = np.sum((actual >= lower) & (actual <= upper))
        return float(inside / len(actual)) if len(actual) > 0 else 0.95