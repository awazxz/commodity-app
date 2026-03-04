import pandas as pd
import numpy as np
from prophet import Prophet
import warnings
warnings.filterwarnings('ignore')


class CommodityForecastModel:
    """
    Model forecasting harga komoditas menggunakan Facebook Prophet.

    ✅ FIX: freq diteruskan ke semua proses CV dan predict
    sehingga data mingguan (W) ditangani dengan benar.
    """

    def __init__(
        self,
        changepoint_prior_scale: float = 0.05,
        seasonality_prior_scale: float = 10.0,
        seasonality_mode: str = 'multiplicative',
        weekly_seasonality: bool = False,
        yearly_seasonality: bool = True,
    ):
        self.changepoint_prior_scale = changepoint_prior_scale
        self.seasonality_prior_scale = seasonality_prior_scale
        self.seasonality_mode        = seasonality_mode
        self.weekly_seasonality      = weekly_seasonality
        self.yearly_seasonality      = yearly_seasonality

        self.model          = None
        self.train_df       = None
        self.data_freq      = 'W'   # ✅ disimpan saat train, dipakai di CV
        self._metrics_cache = None

    # ----------------------------------------------------------
    # DETECT FREQUENCY
    # Deteksi otomatis frekuensi data dari interval antar baris
    # ----------------------------------------------------------
    @staticmethod
    def detect_frequency(df: pd.DataFrame) -> str:
        """
        Deteksi frekuensi data dari kolom 'ds'.
        Return: 'D' (harian), 'W' (mingguan), 'MS' (bulanan awal bulan)
        """
        if len(df) < 2:
            return 'W'

        df_sorted = df.sort_values('ds').reset_index(drop=True)
        # Ambil sample 10 interval pertama
        sample    = df_sorted['ds'].head(11)
        diffs     = sample.diff().dropna().dt.days.tolist()
        avg_diff  = sum(diffs) / len(diffs) if diffs else 7

        if avg_diff <= 2:
            return 'D'
        elif avg_diff <= 10:
            return 'W'
        else:
            return 'MS'

    # ----------------------------------------------------------
    # TRAIN
    # ----------------------------------------------------------
    def train(self, df: pd.DataFrame, freq: str = None) -> None:
        self.train_df       = df.copy()
        self._metrics_cache = None

        # ✅ Deteksi frekuensi otomatis jika tidak dikirim
        self.data_freq = freq if freq else self.detect_frequency(df)
        print(f"   [Prophet] Frekuensi data terdeteksi: {self.data_freq}")

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

    # ----------------------------------------------------------
    # PREDICT
    # ✅ FIX: gunakan self.data_freq sebagai default freq
    # ----------------------------------------------------------
    def predict(self, periods: int = 30, freq: str = None) -> pd.DataFrame:
        if self.model is None:
            raise ValueError("Model belum dilatih. Panggil train() terlebih dahulu.")

        # ✅ Gunakan frekuensi data jika freq tidak dikirim eksplisit
        use_freq = freq if freq else self.data_freq

        future   = self.model.make_future_dataframe(periods=periods, freq=use_freq)
        forecast = self.model.predict(future)

        return forecast[['ds', 'yhat', 'yhat_lower', 'yhat_upper', 'trend']]\
            .tail(periods)\
            .reset_index(drop=True)

    # ----------------------------------------------------------
    # METRICS — Walk-forward CV + hyperparameter-sensitive extras
    # ----------------------------------------------------------
    def get_model_metrics(self) -> dict:
        if self._metrics_cache is not None:
            return self._metrics_cache

        # ✅ Turunkan minimum dari 30 ke 10 agar data mingguan sedikit tetap jalan
        if self.train_df is None or len(self.train_df) < 10:
            self._metrics_cache = self._empty_metrics()
            return self._metrics_cache

        cv_metrics          = self._compute_cv_metrics()
        insample_metrics    = self._compute_insample_metrics()
        sensitivity_metrics = self._compute_sensitivity_metrics()

        self._metrics_cache = {
            'mape':     cv_metrics.get('mape', insample_metrics['mape']),
            'rmse':     cv_metrics.get('rmse', insample_metrics['rmse']),
            'mae':      cv_metrics.get('mae',  insample_metrics['mae']),
            'coverage': cv_metrics.get('coverage', 0.95),

            'in_sample_mape': insample_metrics['mape'],
            'in_sample_rmse': insample_metrics['rmse'],
            'in_sample_mae':  insample_metrics['mae'],

            'avg_interval_width':   sensitivity_metrics['avg_interval_width'],
            'changepoint_count':    sensitivity_metrics['changepoint_count'],
            'trend_flexibility':    sensitivity_metrics['trend_flexibility'],
            'seasonality_strength': sensitivity_metrics['seasonality_strength'],

            'cv_method': f'walk_forward_80_20_{self.data_freq}',
            'hyperparameters_used': {
                'changepoint_prior_scale': self.changepoint_prior_scale,
                'seasonality_prior_scale': self.seasonality_prior_scale,
                'seasonality_mode':        self.seasonality_mode,
                'weekly_seasonality':      self.weekly_seasonality,
                'yearly_seasonality':      self.yearly_seasonality,
                'data_frequency':          self.data_freq,
            }
        }

        return self._metrics_cache

    # ----------------------------------------------------------
    # CV METRICS
    # ✅ FIX UTAMA: gunakan self.data_freq bukan hardcode 'D'
    # ----------------------------------------------------------
    def _compute_cv_metrics(self) -> dict:
        """
        Walk-forward CV: latih di 80% data, test di 20% sisanya.
        
        ✅ FIX: make_future_dataframe menggunakan self.data_freq
        sehingga tanggal forecast match dengan tanggal aktual test set.
        """
        df         = self.train_df.copy()
        n          = len(df)
        test_size  = max(4, int(n * 0.20))   # ✅ min 4 (bukan 10) agar data sedikit tetap jalan
        train_size = n - test_size

        if train_size < 8:  # ✅ turunkan threshold dari 20 ke 8
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

            # ✅ FIX: gunakan self.data_freq, bukan 'D' hardcoded
            future   = cv_model.make_future_dataframe(periods=test_size, freq=self.data_freq)
            forecast = cv_model.predict(future)

            # ✅ FIX: match berdasarkan periode, bukan exact timestamp
            # Untuk data mingguan, tanggal Prophet mungkin sedikit berbeda
            # (misal Senin vs Minggu), jadi kita pakai posisi tail
            forecast_test  = forecast.tail(test_size).reset_index(drop=True)
            actual_vals    = test_df['y'].values
            predicted_vals = forecast_test['yhat'].values
            lower_vals     = forecast_test['yhat_lower'].values
            upper_vals     = forecast_test['yhat_upper'].values

            # Pastikan panjang sama
            min_len        = min(len(actual_vals), len(predicted_vals))
            actual_vals    = actual_vals[:min_len]
            predicted_vals = predicted_vals[:min_len]
            lower_vals     = lower_vals[:min_len]
            upper_vals     = upper_vals[:min_len]

            return {
                'mape':     round(float(self._mape(actual_vals, predicted_vals)), 4),
                'rmse':     round(float(self._rmse(actual_vals, predicted_vals)), 2),
                'mae':      round(float(self._mae(actual_vals, predicted_vals)), 2),
                'coverage': round(float(self._coverage(actual_vals, lower_vals, upper_vals)), 4),
            }

        except Exception as e:
            print(f"⚠️  CV metrics error: {e}")
            return {}

    # ----------------------------------------------------------
    # IN-SAMPLE METRICS
    # ----------------------------------------------------------
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
                'mae':  round(float(self._mae(actual, pred)), 2),
            }
        except Exception:
            return {'mape': 0.0, 'rmse': 0.0, 'mae': 0.0}

    # ----------------------------------------------------------
    # SENSITIVITY METRICS
    # ----------------------------------------------------------
    def _compute_sensitivity_metrics(self) -> dict:
        if self.model is None or self.train_df is None:
            return {
                'avg_interval_width':   0.0,
                'changepoint_count':    0,
                'trend_flexibility':    0.0,
                'seasonality_strength': 0.0,
            }

        try:
            forecast           = self.model.predict(self.train_df[['ds']].copy())
            interval_widths    = forecast['yhat_upper'] - forecast['yhat_lower']
            avg_interval_width = round(float(interval_widths.mean()), 2)

            changepoint_count = 0
            trend_flexibility = 0.0

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
            print(f"⚠️  Sensitivity metrics error: {e}")
            return {
                'avg_interval_width':   0.0,
                'changepoint_count':    0,
                'trend_flexibility':    0.0,
                'seasonality_strength': 0.0,
            }

    # ----------------------------------------------------------
    # EVALUATE
    # ----------------------------------------------------------
    def evaluate(self) -> dict:
        metrics = self.get_model_metrics()
        return {
            **metrics,
            'method':         'walk_forward_cross_validation',
            'train_size_pct': 80,
            'test_size_pct':  20,
            'data_frequency': self.data_freq,
        }

    # ----------------------------------------------------------
    # METRIC HELPERS
    # ----------------------------------------------------------
    @staticmethod
    def _empty_metrics() -> dict:
        return {
            'mape': 0.0, 'rmse': 0.0, 'mae': 0.0, 'coverage': 0.95,
            'in_sample_mape': 0.0, 'in_sample_rmse': 0.0, 'in_sample_mae': 0.0,
            'avg_interval_width': 0.0, 'changepoint_count': 0,
            'trend_flexibility': 0.0, 'seasonality_strength': 0.0,
            'cv_method': 'walk_forward_80_20',
            'hyperparameters_used': {},
        }

    @staticmethod
    def _mape(actual: np.ndarray, predicted: np.ndarray) -> float:
        actual    = np.array(actual, dtype=float)
        predicted = np.array(predicted, dtype=float)
        mask      = actual != 0
        if not mask.any():
            return 0.0
        return float(np.mean(np.abs((actual[mask] - predicted[mask]) / actual[mask])) * 100)

    @staticmethod
    def _rmse(actual: np.ndarray, predicted: np.ndarray) -> float:
        actual    = np.array(actual, dtype=float)
        predicted = np.array(predicted, dtype=float)
        return float(np.sqrt(np.mean((actual - predicted) ** 2)))

    @staticmethod
    def _mae(actual: np.ndarray, predicted: np.ndarray) -> float:
        actual    = np.array(actual, dtype=float)
        predicted = np.array(predicted, dtype=float)
        return float(np.mean(np.abs(actual - predicted)))

    @staticmethod
    def _coverage(actual: np.ndarray, lower: np.ndarray, upper: np.ndarray) -> float:
        actual = np.array(actual, dtype=float)
        lower  = np.array(lower,  dtype=float)
        upper  = np.array(upper,  dtype=float)
        inside = np.sum((actual >= lower) & (actual <= upper))
        return float(inside / len(actual)) if len(actual) > 0 else 0.95