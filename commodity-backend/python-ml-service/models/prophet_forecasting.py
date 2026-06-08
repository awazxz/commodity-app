"""
models/prophet_forecasting.py

Changelog v10.4 — Sync Fix + Metrics Accuracy:
  FIX 1: CommodityForecastModel.__init__ default:
          yearly_fourier_order 20 -> 10 (sinkron predictor.py v8.3)
          n_changepoints       25 -> 15 (sinkron predictor.py v8.3)
  FIX 2: GRID_SEARCH_SPACE n_changepoints [25,35,50] -> [10,15,25,35]
          agar grid search mencoba nilai yang cocok untuk data bulanan
  FIX 3: load_model() fallback yearly_fourier_order 20->10, n_changepoints 25->15
          agar model lama yang di-load tidak pakai nilai lama
  FIX 4: coverage dihitung dari actual yhat_lower/upper (bukan hardcode)
          — sudah benar di _compute_cv_metrics, pastikan konsisten di semua fungsi
  FIX 5: directional_acc di _compute_cv_metrics sekarang menggunakan
          data out-of-sample (CV), bukan in-sample — lebih representatif
  FIX 6: rolling CV n_folds adaptif berdasarkan panjang data bulanan:
          data < 36 bulan -> 2 fold, >= 36 bulan -> 3 fold (was: < 200 rows)
  FIX 7: _compute_rolling_cv test_n minimum disesuaikan untuk data bulanan:
          min 3 bulan (was: 13) agar data pendek tetap bisa dievaluasi

Changelog v10.3 — Monthly Data Support + Hybrid Removed
Changelog v10.2 — Forecast Accuracy Fix
Changelog v10.1 — Fitted Values Fix
Changelog v10.0 — Speed + Accuracy Overhaul
"""

import os
import traceback
import warnings
import portalocker
from datetime import datetime
from itertools import product as iterproduct

import joblib
import numpy as np
import pandas as pd
from prophet import Prophet

warnings.filterwarnings('ignore')


# =============================================================
# KONSTANTA
# =============================================================

MODEL_DIR             = os.getenv('MODEL_DIR', 'saved_models')
MODEL_MAX_AGE_H       = int(os.getenv('MODEL_MAX_AGE_HOURS', 24))
MIN_DATA_POINTS       = 10
WINDOW_WEEKS          = int(os.getenv('WINDOW_WEEKS', 200))
WINDOW_MONTHS         = int(os.getenv('WINDOW_MONTHS', 60))
MAPE_DRIFT_THRESHOLD  = float(os.getenv('MAPE_DRIFT_THRESHOLD', 15.0))
MAX_STALE_DAYS        = int(os.getenv('MAX_STALE_DAYS', 60))

# FIX 2: tambah nilai kecil [10,15] untuk data bulanan
# hapus 50 yang menyebabkan over-fitting pada data pendek
GRID_SEARCH_SPACE = {
    'changepoint_prior_scale': [0.05, 0.1, 0.2, 0.3],
    'seasonality_prior_scale': [5.0, 10.0, 15.0],
    'seasonality_mode':        ['additive', 'multiplicative'],
    'yearly_fourier_order':    [10, 15, 20],
    'n_changepoints':          [10, 15, 25, 35],  # FIX 2: was [25, 35, 50]
}


# =============================================================
# FEATURE ENGINEERING — adaptif mingguan vs bulanan
# =============================================================

def build_features(df: pd.DataFrame) -> pd.DataFrame:
    df = df.sort_values('ds').copy()

    if len(df) >= 2:
        diffs   = df['ds'].diff().dropna().dt.days
        avg_gap = diffs.median()
    else:
        avg_gap = 7

    is_monthly = avg_gap > 20

    if is_monthly:
        for lag in [1, 2, 3, 6, 12]:
            df[f'lag_{lag}m'] = df['y'].shift(lag)

        df['momentum_3m']   = df['y'] - df['y'].shift(3)
        df['momentum_6m']   = df['y'] - df['y'].shift(6)
        df['pct_change_1m'] = df['y'].pct_change(1)
        df['pct_change_3m'] = df['y'].pct_change(3)

        for w in [3, 6, 12]:
            df[f'rolling_mean_{w}m'] = df['y'].rolling(w).mean()
            df[f'rolling_std_{w}m']  = df['y'].rolling(w).std()
            df[f'rolling_min_{w}m']  = df['y'].rolling(w).min()
            df[f'rolling_max_{w}m']  = df['y'].rolling(w).max()

        df['price_position_12m'] = (
            (df['y'] - df['rolling_min_12m']) /
            (df['rolling_max_12m'] - df['rolling_min_12m'] + 1e-8)
        )

        df['month']   = df['ds'].dt.month
        df['quarter'] = df['ds'].dt.quarter

        df['is_pre_lebaran']  = df['month'].isin([3, 4]).astype(int)
        df['is_post_lebaran'] = df['month'].isin([5, 6]).astype(int)
        df['is_harvest_q1']   = df['month'].isin([2, 3, 4]).astype(int)
        df['is_harvest_q3']   = df['month'].isin([8, 9, 10]).astype(int)
        df['is_year_end']     = df['month'].isin([11, 12]).astype(int)

    else:
        for lag in [1, 2, 4, 8, 13]:
            df[f'lag_{lag}w'] = df['y'].shift(lag)

        df['momentum_4w']   = df['y'] - df['y'].shift(4)
        df['momentum_8w']   = df['y'] - df['y'].shift(8)
        df['pct_change_1w'] = df['y'].pct_change(1)
        df['pct_change_4w'] = df['y'].pct_change(4)

        for w in [4, 8, 13]:
            df[f'rolling_mean_{w}w'] = df['y'].rolling(w).mean()
            df[f'rolling_std_{w}w']  = df['y'].rolling(w).std()
            df[f'rolling_min_{w}w']  = df['y'].rolling(w).min()
            df[f'rolling_max_{w}w']  = df['y'].rolling(w).max()

        df['price_position_13w'] = (
            (df['y'] - df['rolling_min_13w']) /
            (df['rolling_max_13w'] - df['rolling_min_13w'] + 1e-8)
        )

        df['week_of_year'] = df['ds'].dt.isocalendar().week.astype(int)
        df['month']        = df['ds'].dt.month
        df['quarter']      = df['ds'].dt.quarter

        df['is_pre_lebaran']  = df['week_of_year'].between(10, 18).astype(int)
        df['is_post_lebaran'] = df['week_of_year'].between(19, 24).astype(int)
        df['is_harvest_q1']   = df['month'].isin([2, 3, 4]).astype(int)
        df['is_harvest_q3']   = df['month'].isin([8, 9, 10]).astype(int)

    return df.dropna()


# =============================================================
# DRIFT CHECKER
# =============================================================

class DriftChecker:

    @staticmethod
    def check(commodity_id, current_df, threshold=MAPE_DRIFT_THRESHOLD, n_recent=8):
        path = CommodityForecastModel._model_path(commodity_id)
        if not os.path.exists(path):
            return {'drift_detected': False, 'recent_mape': 0.0, 'reason': 'model_not_found'}
        try:
            payload = joblib.load(path)
            result  = CommodityForecastModel._check_mape_drift(
                payload=payload, current_df=current_df,
                threshold=threshold, n_recent=n_recent,
            )
            return result
        except Exception as e:
            return {'drift_detected': False, 'recent_mape': 0.0, 'reason': f'drift_check_error: {e}'}


# =============================================================
# HELPER: Build Prophet instance
# =============================================================

def _build_prophet(
    changepoint_prior_scale : float = 0.1,
    seasonality_prior_scale : float = 10.0,
    seasonality_mode        : str   = 'additive',
    weekly_seasonality      : bool  = False,
    yearly_seasonality      : bool  = True,
    daily_seasonality       : bool  = False,
    interval_width          : float = 0.80,
    yearly_fourier_order    : int   = 10,   # FIX 1: was 15
    monthly_seasonality     : bool  = True,
    n_changepoints          : int   = 15,   # FIX 1: was 25
    mcmc_samples            : int   = 0,
    changepoint_range       : float = 0.85,
) -> Prophet:
    m = Prophet(
        changepoint_prior_scale = changepoint_prior_scale,
        seasonality_prior_scale = seasonality_prior_scale,
        seasonality_mode        = seasonality_mode,
        weekly_seasonality      = weekly_seasonality,
        yearly_seasonality      = False,
        daily_seasonality       = daily_seasonality,
        interval_width          = interval_width,
        n_changepoints          = n_changepoints,
        changepoint_range       = changepoint_range,
        mcmc_samples            = mcmc_samples,
    )
    if yearly_seasonality:
        m.add_seasonality(name='yearly', period=365.25, fourier_order=yearly_fourier_order)
    if monthly_seasonality:
        m.add_seasonality(name='monthly', period=30.5, fourier_order=5)
    return m


# =============================================================
# HELPER: Merge forecast dengan actual — auto-detect tolerance
# =============================================================

def _merge_forecast_actual(
    forecast_df    : pd.DataFrame,
    actual_df      : pd.DataFrame,
    tolerance_days : int = None,
) -> pd.DataFrame:
    forecast_s = forecast_df.sort_values('ds').reset_index(drop=True)
    actual_s   = actual_df.sort_values('ds').reset_index(drop=True)

    forecast_s['ds'] = pd.to_datetime(forecast_s['ds'])
    actual_s['ds']   = pd.to_datetime(actual_s['ds'])

    if tolerance_days is None:
        if len(actual_s) >= 2:
            avg_gap = actual_s['ds'].diff().dropna().dt.days.median()
            if avg_gap > 20:
                tolerance_days = 15
            elif avg_gap > 3:
                tolerance_days = 3
            else:
                tolerance_days = 1
        else:
            tolerance_days = 3

    cols_needed = [c for c in ['ds', 'yhat', 'yhat_lower', 'yhat_upper']
                   if c in forecast_s.columns]

    try:
        merged = pd.merge_asof(
            actual_s, forecast_s[cols_needed],
            on='ds', direction='nearest',
            tolerance=pd.Timedelta(f'{tolerance_days} days'),
        ).dropna(subset=['yhat'])
        if len(merged) > 0:
            return merged
    except Exception as e:
        print(f"   [merge_asof] Error: {e} — fallback ke date join.")

    forecast_s = forecast_s.copy()
    actual_s   = actual_s.copy()
    forecast_s['_date'] = forecast_s['ds'].dt.date
    actual_s['_date']   = actual_s['ds'].dt.date

    merged2 = actual_s.merge(
        forecast_s[['_date'] + [c for c in cols_needed if c != 'ds']],
        on='_date', how='inner',
    ).drop(columns=['_date'])
    return merged2


# =============================================================
# MAIN CLASS
# =============================================================

class CommodityForecastModel:

    def __init__(
        self,
        changepoint_prior_scale : float = 0.1,
        seasonality_prior_scale : float = 10.0,
        seasonality_mode        : str   = 'additive',
        weekly_seasonality      : bool  = False,
        yearly_seasonality      : bool  = True,
        yearly_fourier_order    : int   = 10,   # FIX 1: was 20
        monthly_seasonality     : bool  = True,
        n_changepoints          : int   = 15,   # FIX 1: was 25
        changepoint_range       : float = 0.85,
        user_override           : bool  = False,
    ):
        self.changepoint_prior_scale = changepoint_prior_scale
        self.seasonality_prior_scale = seasonality_prior_scale
        self.seasonality_mode        = seasonality_mode
        self.weekly_seasonality      = weekly_seasonality
        self.yearly_seasonality      = yearly_seasonality
        self.yearly_fourier_order    = yearly_fourier_order
        self.monthly_seasonality     = monthly_seasonality
        self.n_changepoints          = n_changepoints
        self.changepoint_range       = changepoint_range
        self.user_override           = user_override

        self.model               = None
        self.train_df            = None
        self.data_freq           = 'W'
        self._metrics_cache      = None
        self.best_params         = None
        self.grid_search_results = []

        os.makedirs(MODEL_DIR, exist_ok=True)

        print(f"   [Prophet] __init__ | user_override={user_override} | "
              f"cp={changepoint_prior_scale} | ss={seasonality_prior_scale} | "
              f"mode={seasonality_mode} | n_changepoints={n_changepoints} | "
              f"changepoint_range={changepoint_range} | "
              f"weekly={weekly_seasonality} | monthly={monthly_seasonality} | "
              f"yearly_fourier={yearly_fourier_order}")

    # ----------------------------------------------------------
    # STATIC HELPERS
    # ----------------------------------------------------------

    @staticmethod
    def _model_path(commodity_id):
        return os.path.join(MODEL_DIR, f"commodity_{commodity_id}.pkl")

    @staticmethod
    def _lock_path(commodity_id):
        return os.path.join(MODEL_DIR, f"commodity_{commodity_id}.lock")

    @staticmethod
    def detect_frequency(df):
        if len(df) < 2:
            return 'W'
        df_sorted = df.sort_values('ds').reset_index(drop=True)
        mid       = len(df_sorted) // 2
        sample    = df_sorted['ds'].iloc[max(0, mid - 5): mid + 6]
        diffs     = sample.diff().dropna().dt.days.tolist()
        avg_diff  = sum(diffs) / len(diffs) if diffs else 7
        if avg_diff <= 2:
            return 'D'
        elif avg_diff <= 10:
            return 'W'
        else:
            return 'MS'

    # ----------------------------------------------------------
    # SLIDING WINDOW
    # ----------------------------------------------------------

    def _apply_sliding_window(self, df):
        df_sorted = df.sort_values('ds').reset_index(drop=True)
        n_total   = len(df_sorted)
        is_monthly = self.data_freq in ('MS', 'M', 'ME')

        if is_monthly:
            MIN_KEEP = 24
            WINDOW   = WINDOW_MONTHS
            if n_total > WINDOW:
                keep      = max(WINDOW, MIN_KEEP)
                df_sorted = df_sorted.tail(keep).reset_index(drop=True)
                print(f"   [Prophet] Sliding window bulanan: {keep}/{n_total} bulan")
            else:
                print(f"   [Prophet] Pakai semua {n_total} bulan data bulanan")
        else:
            MIN_KEEP = 52
            if n_total > WINDOW_WEEKS * 2:
                keep      = max(WINDOW_WEEKS, MIN_KEEP)
                df_sorted = df_sorted.tail(keep).reset_index(drop=True)
                print(f"   [Prophet] Sliding window: {keep} dari {n_total} baris")
            elif n_total > WINDOW_WEEKS:
                keep = max(WINDOW_WEEKS, int(n_total * 0.90), MIN_KEEP)
                df_sorted = df_sorted.tail(keep).reset_index(drop=True)
                print(f"   [Prophet] Adaptive window: {keep}/{n_total} baris")
            else:
                print(f"   [Prophet] Pakai semua {n_total} baris")

        return df_sorted

    # ----------------------------------------------------------
    # GRID SEARCH
    # ----------------------------------------------------------

    def auto_grid_search(self, df, freq=None, param_grid=None, verbose=True):
        if self.user_override:
            self.best_params = {
                'changepoint_prior_scale': self.changepoint_prior_scale,
                'seasonality_prior_scale': self.seasonality_prior_scale,
                'seasonality_mode':        self.seasonality_mode,
                'yearly_fourier_order':    self.yearly_fourier_order,
                'n_changepoints':          self.n_changepoints,
                'changepoint_range':       self.changepoint_range,
                'mape': None, 'source': 'user_override',
            }
            print(f"   [GridSearch] SKIP — user_override=True.")
            return self.best_params

        grid     = param_grid or GRID_SEARCH_SPACE
        use_freq = freq or self.detect_frequency(df)

        df_gs = df.sort_values('ds').reset_index(drop=True)
        if len(df_gs) > WINDOW_WEEKS:
            df_gs = df_gs.tail(WINDOW_WEEKS).reset_index(drop=True)

        n_total   = len(df_gs)
        n_holdout = max(4, int(n_total * 0.10))
        df_gs     = df_gs.iloc[:-n_holdout].reset_index(drop=True)

        n       = len(df_gs)
        test_n  = max(4, int(n * 0.20))
        train_n = n - test_n

        if train_n < 8:
            print(f"   [GridSearch] Data terlalu sedikit. Pakai default params.")
            self.best_params = {
                'changepoint_prior_scale': self.changepoint_prior_scale,
                'seasonality_prior_scale': self.seasonality_prior_scale,
                'seasonality_mode':        self.seasonality_mode,
                'yearly_fourier_order':    self.yearly_fourier_order,
                'n_changepoints':          self.n_changepoints,
                'changepoint_range':       self.changepoint_range,
                'mape': None,
            }
            return self.best_params

        train_gs = df_gs.iloc[:train_n].reset_index(drop=True)
        test_gs  = df_gs.iloc[train_n:].reset_index(drop=True)

        keys   = list(grid.keys())
        values = list(grid.values())
        combos = list(iterproduct(*values))
        total  = len(combos)

        if verbose:
            print(f"   [GridSearch] {total} kombinasi | freq={use_freq} | train={train_n} | test={test_n}")

        results = []
        for i, combo in enumerate(combos):
            params = dict(zip(keys, combo))
            try:
                m = _build_prophet(
                    changepoint_prior_scale = params['changepoint_prior_scale'],
                    seasonality_prior_scale = params['seasonality_prior_scale'],
                    seasonality_mode        = params['seasonality_mode'],
                    weekly_seasonality      = self.weekly_seasonality,
                    yearly_seasonality      = self.yearly_seasonality,
                    yearly_fourier_order    = params.get('yearly_fourier_order', self.yearly_fourier_order),
                    monthly_seasonality     = self.monthly_seasonality,
                    n_changepoints          = params.get('n_changepoints', self.n_changepoints),
                    changepoint_range       = self.changepoint_range,
                    interval_width          = 0.80,
                )
                m.fit(train_gs[['ds', 'y']])
                future   = m.make_future_dataframe(periods=test_n + 8, freq=use_freq)
                forecast = m.predict(future)
                forecast = forecast[forecast['ds'] > train_gs['ds'].max()].reset_index(drop=True)
                merged   = _merge_forecast_actual(forecast, test_gs[['ds', 'y']])
                if len(merged) == 0:
                    continue

                mape    = self._mape(merged['y'].values, merged['yhat'].values)
                dir_acc = self._directional_accuracy(merged['y'].values, merged['yhat'].values)

                results.append({**params, 'mape': round(mape, 4), 'dir_acc': round(dir_acc, 2)})

                if verbose and (i + 1) % 20 == 0:
                    print(f"   [GridSearch] {i+1}/{total} selesai...")
            except Exception as e:
                if verbose:
                    print(f"   [GridSearch] {params} error: {e}")
                continue

        if not results:
            print(f"   [GridSearch] Semua kombinasi gagal. Pakai default params.")
            self.best_params = {
                'changepoint_prior_scale': self.changepoint_prior_scale,
                'seasonality_prior_scale': self.seasonality_prior_scale,
                'seasonality_mode':        self.seasonality_mode,
                'yearly_fourier_order':    self.yearly_fourier_order,
                'n_changepoints':          self.n_changepoints,
                'changepoint_range':       self.changepoint_range,
                'mape': None,
            }
            return self.best_params

        mapes    = [r['mape']    for r in results]
        dir_accs = [r['dir_acc'] for r in results]
        mape_range = (max(mapes) - min(mapes))   or 1.0
        dir_range  = (max(dir_accs) - min(dir_accs)) or 1.0

        for r in results:
            norm_mape    = (r['mape']    - min(mapes))    / mape_range
            norm_dir_acc = (r['dir_acc'] - min(dir_accs)) / dir_range
            r['composite_score'] = round(0.7 * norm_mape + 0.3 * (1.0 - norm_dir_acc), 6)

        results.sort(key=lambda x: x['composite_score'])
        self.grid_search_results = results
        self.best_params         = results[0]

        if verbose:
            print(f"   [GridSearch] Best composite={self.best_params['composite_score']:.4f} | "
                  f"MAPE={self.best_params['mape']:.4f}% | "
                  f"DirAcc={self.best_params['dir_acc']:.1f}% | "
                  f"cps={self.best_params['changepoint_prior_scale']} | "
                  f"sps={self.best_params['seasonality_prior_scale']} | "
                  f"mode={self.best_params['seasonality_mode']}")

        if not self.user_override:
            self.changepoint_prior_scale = self.best_params['changepoint_prior_scale']
            self.seasonality_prior_scale = self.best_params['seasonality_prior_scale']
            self.seasonality_mode        = self.best_params['seasonality_mode']
            self.yearly_fourier_order    = self.best_params.get('yearly_fourier_order', self.yearly_fourier_order)
            self.n_changepoints          = self.best_params.get('n_changepoints', self.n_changepoints)

        return self.best_params

    # ----------------------------------------------------------
    # TRAIN
    # ----------------------------------------------------------

    def train(self, df, freq=None):
        self._metrics_cache = None
        self.data_freq      = freq if freq else self.detect_frequency(df)

        df_train      = self._apply_sliding_window(df)
        self.train_df = df_train.copy()

        if self.user_override:
            active_cps  = self.changepoint_prior_scale
            active_sps  = self.seasonality_prior_scale
            active_mode = self.seasonality_mode
            active_fou  = self.yearly_fourier_order
            active_ncp  = self.n_changepoints
            active_cpr  = self.changepoint_range
            source      = "user_override"
        elif self.best_params:
            active_cps  = self.best_params['changepoint_prior_scale']
            active_sps  = self.best_params['seasonality_prior_scale']
            active_mode = self.best_params['seasonality_mode']
            active_fou  = self.best_params.get('yearly_fourier_order', self.yearly_fourier_order)
            active_ncp  = self.best_params.get('n_changepoints', self.n_changepoints)
            active_cpr  = self.changepoint_range
            self.changepoint_prior_scale = active_cps
            self.seasonality_prior_scale = active_sps
            self.seasonality_mode        = active_mode
            self.yearly_fourier_order    = active_fou
            self.n_changepoints          = active_ncp
            source = "grid_search_best"
        else:
            active_cps  = self.changepoint_prior_scale
            active_sps  = self.seasonality_prior_scale
            active_mode = self.seasonality_mode
            active_fou  = self.yearly_fourier_order
            active_ncp  = self.n_changepoints
            active_cpr  = self.changepoint_range
            source      = "default"

        print(f"   [Prophet] Training | source={source} | freq={self.data_freq} | "
              f"rows={len(df_train)} | cp={active_cps} | ss={active_sps} | "
              f"mode={active_mode} | n_cp={active_ncp} | cpr={active_cpr}")

        self.model = _build_prophet(
            changepoint_prior_scale = active_cps,
            seasonality_prior_scale = active_sps,
            seasonality_mode        = active_mode,
            weekly_seasonality      = self.weekly_seasonality,
            yearly_seasonality      = self.yearly_seasonality,
            yearly_fourier_order    = active_fou,
            monthly_seasonality     = self.monthly_seasonality,
            n_changepoints          = active_ncp,
            changepoint_range       = active_cpr,
            interval_width          = 0.80,
        )
        self.model.fit(df_train[['ds', 'y']])
        print(f"   [Prophet] Training selesai (source={source})")

    # ----------------------------------------------------------
    # PREDICT
    # ----------------------------------------------------------

    def predict(
        self,
        periods         : int          = 12,
        freq            : str          = None,
        start_after     : pd.Timestamp = None,
        include_history : bool         = False,
    ) -> pd.DataFrame:
        if self.model is None:
            raise ValueError("Model belum dilatih. Panggil train() terlebih dahulu.")

        use_freq = freq if freq else self.data_freq

        if freq and freq != self.data_freq:
            print(f"   [Prophet] freq mismatch: request={freq}, model={self.data_freq}. "
                  f"Pakai model freq: {self.data_freq}")
            use_freq = self.data_freq

        if start_after is None:
            if self.train_df is not None:
                start_after = self.train_df['ds'].max()
            else:
                raise ValueError("start_after tidak diset dan train_df kosong.")

        today    = pd.Timestamp.now().normalize()
        gap_days = max(0, (today - start_after).days)

        if gap_days > MAX_STALE_DAYS:
            print(f"   [Prophet] WARNING Model stale: gap={gap_days}d > {MAX_STALE_DAYS}d. "
                  f"Tetap dijalankan dari last_date={start_after.date()}")

        freq_days = {'D': 1, 'W': 7, 'MS': 30, 'M': 30, 'ME': 30}
        days_per  = freq_days.get(use_freq, 7)

        MAX_EXTRA     = 52
        extra_periods = min(max(0, int(gap_days // days_per) + 2), MAX_EXTRA)

        BUFFER        = 8
        total_periods = extra_periods + periods + BUFFER

        print(f"   [Prophet] predict | freq={use_freq} | include_history={include_history} | "
              f"start_after={start_after.date()} | gap={gap_days}d | extra={extra_periods} | "
              f"target={periods} | total={total_periods}")

        future   = self.model.make_future_dataframe(periods=total_periods, freq=use_freq)
        forecast = self.model.predict(future)

        result = forecast[['ds', 'yhat', 'yhat_lower', 'yhat_upper', 'trend']].copy()

        if include_history:
            result = result.reset_index(drop=True)
            print(f"   [Prophet] include_history=True -> {len(result)} baris total")
        else:
            result = result[result['ds'] > start_after].reset_index(drop=True)
            result = result.head(periods).reset_index(drop=True)

            if len(result) == 0:
                raise ValueError(
                    f"Tidak ada forecast setelah {start_after.date()}. "
                    f"Coba force_retrain=True."
                )
            print(f"   [Prophet] include_history=False -> {len(result)} titik future")

        return result

    # ----------------------------------------------------------
    # SAVE / LOAD MODEL
    # ----------------------------------------------------------

    def save_model(self, commodity_id, metadata=None):
        if self.model is None:
            raise ValueError("Tidak ada model untuk disimpan.")

        path      = self._model_path(commodity_id)
        lock_path = self._lock_path(commodity_id)

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
                'yearly_fourier_order':    self.yearly_fourier_order,
                'monthly_seasonality':     self.monthly_seasonality,
                'n_changepoints':          self.n_changepoints,
                'changepoint_range':       self.changepoint_range,
            },
            'user_override':       self.user_override,
            'best_params':         self.best_params,
            'grid_search_results': self.grid_search_results,
            'trained_at':          datetime.now(),
            'data_points':         len(self.train_df),
            'last_date':           self.train_df['ds'].max(),
            'metadata':            metadata or {},
            'cached_metrics':      self._metrics_cache,
        }

        lock_file = open(lock_path, 'w')
        try:
            portalocker.lock(lock_file, portalocker.LOCK_EX)
            joblib.dump(payload, path)
        finally:
            portalocker.unlock(lock_file)
            lock_file.close()

        print(f"   [Model] Disimpan -> {path} "
              f"(last_date={payload['last_date'].date()}, rows={payload['data_points']}, "
              f"user_override={self.user_override}, "
              f"has_cached_metrics={self._metrics_cache is not None})")
        return path

    def save_model_with_metrics(self, commodity_id, metadata=None):
        print(f"   [Model] Menghitung metrics untuk payload...")
        self._metrics_cache = None
        self.get_model_metrics()
        return self.save_model(commodity_id, metadata=metadata)

    @classmethod
    def load_model(cls, commodity_id):
        path = cls._model_path(commodity_id)
        if not os.path.exists(path):
            return None

        try:
            payload  = joblib.load(path)
            hp       = payload['hyperparams']
            instance = cls(
                changepoint_prior_scale = hp['changepoint_prior_scale'],
                seasonality_prior_scale = hp['seasonality_prior_scale'],
                seasonality_mode        = hp['seasonality_mode'],
                weekly_seasonality      = hp['weekly_seasonality'],
                yearly_seasonality      = hp['yearly_seasonality'],
                # FIX 3: fallback ke nilai baru (10/15) bukan nilai lama (20/25)
                yearly_fourier_order    = hp.get('yearly_fourier_order', 10),
                monthly_seasonality     = hp.get('monthly_seasonality',  True),
                n_changepoints          = hp.get('n_changepoints', 15),
                changepoint_range       = hp.get('changepoint_range', 0.85),
                user_override           = payload.get('user_override', False),
            )
            instance.model               = payload['model']
            instance.train_df            = payload['train_df']
            instance.data_freq           = payload['data_freq']
            instance.best_params         = payload.get('best_params')
            instance.grid_search_results = payload.get('grid_search_results', [])
            instance._metrics_cache      = payload.get('cached_metrics')

            print(f"   [Model] Loaded: id={commodity_id} | "
                  f"trained_at={payload['trained_at'].strftime('%Y-%m-%d %H:%M')} | "
                  f"last_date={payload['last_date'].date()} | "
                  f"rows={payload['data_points']} | "
                  f"user_override={instance.user_override} | "
                  f"has_cached_metrics={instance._metrics_cache is not None}")

            return instance, payload

        except Exception as e:
            print(f"   [Model] Gagal load id={commodity_id}: {e}")
            traceback.print_exc()
            return None

    # ----------------------------------------------------------
    # NEEDS RETRAINING
    # ----------------------------------------------------------

    @staticmethod
    def needs_retraining(commodity_id, current_df, max_age_hours=MODEL_MAX_AGE_H, hyperparams=None):
        path = CommodityForecastModel._model_path(commodity_id)
        if not os.path.exists(path):
            return True, "model_not_found"

        try:
            payload           = joblib.load(path)
            trained_at        = payload['trained_at']
            last_trained_date = payload['last_date']
            age_hours         = (datetime.now() - trained_at).total_seconds() / 3600

            if age_hours > max_age_hours:
                return True, f"model_expired ({age_hours:.1f}h > {max_age_hours}h)"

            current_last_date = current_df['ds'].max()
            if current_last_date > last_trained_date:
                new_rows = len(current_df[current_df['ds'] > last_trained_date])
                return True, f"new_data ({new_rows} baris baru sejak {last_trained_date.date()})"

            if hyperparams and hyperparams.get('user_override', False):
                saved_hp = payload.get('hyperparams', {})
                for key, val in hyperparams.items():
                    if key == 'user_override':
                        continue
                    if saved_hp.get(key) != val:
                        return True, f"hyperparams_changed ({key}: {saved_hp.get(key)} -> {val})"

            return False, f"model_fresh (trained {age_hours:.1f}h ago, last_date={last_trained_date.date()})"

        except Exception as e:
            return True, f"model_corrupt ({e})"

    # ----------------------------------------------------------
    # DRIFT CHECK
    # ----------------------------------------------------------

    @staticmethod
    def _check_mape_drift(payload, current_df, threshold=MAPE_DRIFT_THRESHOLD, n_recent=8):
        df_sorted = current_df.sort_values('ds').reset_index(drop=True)
        if len(df_sorted) < n_recent + 4:
            return {'drift_detected': False, 'recent_mape': 0.0}

        hp   = payload['hyperparams']
        freq = payload['data_freq']

        m = _build_prophet(
            changepoint_prior_scale = hp['changepoint_prior_scale'],
            seasonality_prior_scale = hp['seasonality_prior_scale'],
            seasonality_mode        = hp['seasonality_mode'],
            weekly_seasonality      = hp['weekly_seasonality'],
            yearly_seasonality      = hp['yearly_seasonality'],
            yearly_fourier_order    = hp.get('yearly_fourier_order', 10),  # FIX 3
            monthly_seasonality     = hp.get('monthly_seasonality',  True),
            n_changepoints          = hp.get('n_changepoints', 15),         # FIX 3
            changepoint_range       = hp.get('changepoint_range', 0.85),
        )

        train_for_drift = df_sorted.iloc[:-n_recent].reset_index(drop=True)
        if len(train_for_drift) < 8:
            return {'drift_detected': False, 'recent_mape': 0.0}

        recent_df = df_sorted.tail(n_recent).reset_index(drop=True)
        m.fit(train_for_drift[['ds', 'y']])
        future   = m.make_future_dataframe(periods=n_recent + 4, freq=freq)
        forecast = m.predict(future)
        merged   = _merge_forecast_actual(
            forecast[['ds', 'yhat', 'yhat_lower', 'yhat_upper']],
            recent_df[['ds', 'y']],
        )
        if len(merged) == 0:
            return {'drift_detected': False, 'recent_mape': 0.0}

        recent_mape = CommodityForecastModel._mape(merged['y'].values, merged['yhat'].values)
        return {'drift_detected': recent_mape > threshold, 'recent_mape': round(recent_mape, 4)}

    # ----------------------------------------------------------
    # MODEL INFO
    # ----------------------------------------------------------

    @staticmethod
    def get_model_info(commodity_id):
        path = CommodityForecastModel._model_path(commodity_id)
        if not os.path.exists(path):
            return {'exists': False, 'commodity_id': commodity_id}
        try:
            payload   = joblib.load(path)
            age_hours = (datetime.now() - payload['trained_at']).total_seconds() / 3600
            return {
                'exists':              True,
                'commodity_id':        commodity_id,
                'trained_at':          payload['trained_at'].isoformat(),
                'last_date':           payload['last_date'].isoformat(),
                'data_points':         payload['data_points'],
                'data_freq':           payload['data_freq'],
                'age_hours':           round(age_hours, 2),
                'is_fresh':            age_hours <= MODEL_MAX_AGE_H,
                'hyperparams':         payload.get('hyperparams', {}),
                'user_override':       payload.get('user_override', False),
                'best_params':         payload.get('best_params'),
                'grid_search_results': payload.get('grid_search_results', []),
                'metadata':            payload.get('metadata', {}),
                'has_cached_metrics':  payload.get('cached_metrics') is not None,
            }
        except Exception as e:
            return {'exists': False, 'commodity_id': commodity_id, 'error': str(e)}

    # ----------------------------------------------------------
    # METRICS — STATIC HELPERS
    # ----------------------------------------------------------

    @staticmethod
    def _mape(actual, predicted):
        actual    = np.array(actual,    dtype=float)
        predicted = np.array(predicted, dtype=float)
        mask      = actual != 0
        if not mask.any():
            return 0.0
        return float(np.mean(np.abs((actual[mask] - predicted[mask]) / actual[mask])) * 100)

    @staticmethod
    def _r_squared(actual, predicted):
        actual    = np.array(actual,    dtype=float)
        predicted = np.array(predicted, dtype=float)
        ss_res = np.sum((actual - predicted) ** 2)
        ss_tot = np.sum((actual - np.mean(actual)) ** 2)
        if ss_tot == 0:
            return 0.0
        return float(max(0.0, min(1.0, 1 - (ss_res / ss_tot))))

    @staticmethod
    def _smape(actual, predicted):
        actual    = np.array(actual,    dtype=float)
        predicted = np.array(predicted, dtype=float)
        denom = (np.abs(actual) + np.abs(predicted)) / 2.0
        mask  = denom > 0
        if not mask.any():
            return 0.0
        return float(np.mean(np.abs(actual[mask] - predicted[mask]) / denom[mask]) * 100)

    @staticmethod
    def _directional_accuracy(actual, predicted):
        """
        FIX 5: Directional accuracy dihitung dari perubahan arah forecast
        vs perubahan arah aktual pada data out-of-sample (CV test set).
        Minimal 2 titik diperlukan untuk menghitung arah.
        """
        actual    = np.array(actual,    dtype=float)
        predicted = np.array(predicted, dtype=float)
        if len(actual) < 2:
            return 0.0
        actual_dir    = np.sign(np.diff(actual))
        predicted_dir = np.sign(np.diff(predicted))
        # Hanya evaluasi titik yang actual-nya bergerak (bukan flat)
        moving_mask = actual_dir != 0
        if not moving_mask.any():
            # Semua flat — cek apakah forecast juga flat
            return float(np.mean(predicted_dir == 0) * 100)
        return float(np.mean(actual_dir[moving_mask] == predicted_dir[moving_mask]) * 100)

    @staticmethod
    def _winkler_score(actual, lower, upper, alpha=0.05):
        actual = np.array(actual, dtype=float)
        lower  = np.array(lower,  dtype=float)
        upper  = np.array(upper,  dtype=float)
        width  = upper - lower
        scores = width.copy().astype(float)
        below  = actual < lower
        above  = actual > upper
        scores[below] += (2.0 / alpha) * (lower[below] - actual[below])
        scores[above] += (2.0 / alpha) * (actual[above] - upper[above])
        return float(np.mean(scores))

    @staticmethod
    def _pinball_loss(actual, quantile_pred, tau):
        actual        = np.array(actual,        dtype=float)
        quantile_pred = np.array(quantile_pred, dtype=float)
        error         = actual - quantile_pred
        return float(np.mean(np.maximum(tau * error, (tau - 1) * error)))

    @staticmethod
    def _interval_sharpness(lower, upper):
        return float(np.mean(np.array(upper, dtype=float) - np.array(lower, dtype=float)))

    @staticmethod
    def _coverage(actual, lower, upper):
        """
        FIX 4: Coverage dihitung langsung dari actual vs yhat_lower/upper.
        interval_width=0.80 artinya target coverage ~80%, bukan 95%.
        """
        actual = np.array(actual, dtype=float)
        lower  = np.array(lower,  dtype=float)
        upper  = np.array(upper,  dtype=float)
        return float(np.mean((actual >= lower) & (actual <= upper)))

    # ----------------------------------------------------------
    # METRICS — COMPUTE
    # ----------------------------------------------------------

    def _compute_cv_metrics(self):
        """
        FIX 4 & 5: CV menggunakan out-of-sample 20% terakhir.
        Coverage dan directional_acc dihitung dari data test (bukan in-sample).
        """
        if self.model is None or self.train_df is None:
            return {}
        n      = len(self.train_df)
        test_n = max(4, int(n * 0.20))
        split  = n - test_n
        if split < 8 or test_n < 4:
            return {}
        try:
            train_cv = self.train_df.iloc[:split].copy()
            test_cv  = self.train_df.iloc[split:].reset_index(drop=True).copy()
            m = _build_prophet(
                changepoint_prior_scale = self.changepoint_prior_scale,
                seasonality_prior_scale = self.seasonality_prior_scale,
                seasonality_mode        = self.seasonality_mode,
                weekly_seasonality      = self.weekly_seasonality,
                yearly_seasonality      = self.yearly_seasonality,
                yearly_fourier_order    = self.yearly_fourier_order,
                monthly_seasonality     = self.monthly_seasonality,
                n_changepoints          = self.n_changepoints,
                changepoint_range       = self.changepoint_range,
                interval_width          = 0.80,
            )
            m.fit(train_cv[['ds', 'y']])
            future   = m.make_future_dataframe(periods=test_n + 8, freq=self.data_freq)
            forecast = m.predict(future)
            forecast = forecast[forecast['ds'] > train_cv['ds'].max()].reset_index(drop=True)
            merged   = _merge_forecast_actual(
                forecast[['ds', 'yhat', 'yhat_lower', 'yhat_upper']], test_cv[['ds', 'y']]
            )
            if len(merged) == 0:
                return {}

            actual    = merged['y'].values
            predicted = merged['yhat'].values
            lower     = merged['yhat_lower'].values
            upper     = merged['yhat_upper'].values

            # FIX 4: coverage dari actual data, bukan hardcode
            coverage = self._coverage(actual, lower, upper)

            print(f"   [CV] test_n={len(merged)} | "
                  f"MAPE={self._mape(actual, predicted):.2f}% | "
                  f"DirAcc={self._directional_accuracy(actual, predicted):.1f}% | "
                  f"Coverage={coverage:.2f} (target=0.80)")

            return {
                'mape':               round(self._mape(actual, predicted),                  4),
                'rmse':               round(float(np.sqrt(np.mean((actual-predicted)**2))), 2),
                'mae':                round(float(np.mean(np.abs(actual-predicted))),        2),
                'smape':              round(self._smape(actual, predicted),                  4),
                'directional_acc':    round(self._directional_accuracy(actual, predicted),   2),
                'winkler_score':      round(self._winkler_score(actual, lower, upper),       2),
                'pinball_lower':      round(self._pinball_loss(actual, lower, 0.025),        4),
                'pinball_upper':      round(self._pinball_loss(actual, upper, 0.975),        4),
                'interval_sharpness': round(self._interval_sharpness(lower, upper),          2),
                'coverage':           round(coverage,                                         4),
                'r_squared':          round(self._r_squared(actual, predicted),             4),
            }
        except Exception as e:
            print(f"   [Metrics] CV error: {e}")
            traceback.print_exc()
            return {}

    def _compute_rolling_cv(self, n_folds=3):
        """
        FIX 6 & 7: n_folds adaptif berdasarkan panjang data dan frekuensi.
        test_n minimum disesuaikan: 3 bulan untuk bulanan, 13 untuk mingguan.
        """
        if self.model is None or self.train_df is None:
            return {}

        n          = len(self.train_df)
        is_monthly = self.data_freq in ('MS', 'M', 'ME')

        # FIX 7: minimum test size per fold adaptif
        min_test_n = 3 if is_monthly else 13
        test_n     = max(min_test_n, int(n * 0.15))

        min_train_for_cv = test_n * 2
        max_possible     = max(1, (n - min_train_for_cv) // test_n)

        # FIX 6: n_folds adaptif berdasarkan panjang data (bukan hardcode rows)
        if is_monthly:
            recommended_folds = 2 if n < 36 else 3
        else:
            recommended_folds = 2 if n < 200 else 3

        actual_folds = min(n_folds, max_possible, recommended_folds)
        if actual_folds < 1:
            return {}

        df_sorted          = self.train_df.sort_values('ds').reset_index(drop=True)
        fold_mapes         = []
        fold_smapes        = []
        fold_dir_accs      = []
        fold_winklers      = []
        fold_pinball_lower = []
        fold_pinball_upper = []
        fold_coverages     = []

        for fold_i in range(actual_folds):
            test_end   = n - fold_i * test_n
            test_start = test_end - test_n
            train_end  = test_start
            if train_end < min_train_for_cv:
                break
            train_fold = df_sorted.iloc[:train_end].reset_index(drop=True)
            test_fold  = df_sorted.iloc[test_start:test_end].reset_index(drop=True)
            try:
                m = _build_prophet(
                    changepoint_prior_scale = self.changepoint_prior_scale,
                    seasonality_prior_scale = self.seasonality_prior_scale,
                    seasonality_mode        = self.seasonality_mode,
                    weekly_seasonality      = self.weekly_seasonality,
                    yearly_seasonality      = self.yearly_seasonality,
                    yearly_fourier_order    = self.yearly_fourier_order,
                    monthly_seasonality     = self.monthly_seasonality,
                    n_changepoints          = self.n_changepoints,
                    changepoint_range       = self.changepoint_range,
                    interval_width          = 0.80,
                )
                m.fit(train_fold[['ds', 'y']])
                future   = m.make_future_dataframe(periods=test_n + 8, freq=self.data_freq)
                forecast = m.predict(future)
                forecast = forecast[forecast['ds'] > train_fold['ds'].max()].reset_index(drop=True)
                merged   = _merge_forecast_actual(
                    forecast[['ds', 'yhat', 'yhat_lower', 'yhat_upper']], test_fold[['ds', 'y']]
                )
                if len(merged) == 0:
                    continue
                actual    = merged['y'].values
                predicted = merged['yhat'].values
                lower     = merged['yhat_lower'].values
                upper     = merged['yhat_upper'].values

                cov = self._coverage(actual, lower, upper)
                fold_mapes.append(self._mape(actual, predicted))
                fold_smapes.append(self._smape(actual, predicted))
                fold_dir_accs.append(self._directional_accuracy(actual, predicted))
                fold_winklers.append(self._winkler_score(actual, lower, upper))
                fold_pinball_lower.append(self._pinball_loss(actual, lower, tau=0.025))
                fold_pinball_upper.append(self._pinball_loss(actual, upper, tau=0.975))
                fold_coverages.append(cov)

                print(f"   [RollingCV] Fold {fold_i+1}/{actual_folds}: "
                      f"MAPE={fold_mapes[-1]:.2f}% | "
                      f"DirAcc={fold_dir_accs[-1]:.1f}% | "
                      f"Coverage={cov:.2f}")
            except Exception as e:
                print(f"   [RollingCV] Fold {fold_i+1} error: {e}")
                continue

        if not fold_mapes:
            return {}

        result = {
            'fold_mapes':          [round(v, 4) for v in fold_mapes],
            'fold_smapes':         [round(v, 4) for v in fold_smapes],
            'fold_dir_accs':       [round(v, 2)  for v in fold_dir_accs],
            'fold_winklers':       [round(v, 2)  for v in fold_winklers],
            'fold_coverages':      [round(v, 4) for v in fold_coverages],
            'n_folds_completed':   len(fold_mapes),
            'rolling_cv_mape':     round(float(np.mean(fold_mapes)),    4),
            'rolling_cv_mape_std': round(float(np.std(fold_mapes)),     4),
            'rolling_cv_smape':    round(float(np.mean(fold_smapes)),   4),
            'rolling_cv_dir_acc':  round(float(np.mean(fold_dir_accs)), 2),
            'rolling_cv_winkler':  round(float(np.mean(fold_winklers)), 2),
            'rolling_cv_coverage': round(float(np.mean(fold_coverages)), 4),
        }
        if fold_pinball_lower:
            result['rolling_cv_pinball_lower'] = round(float(np.mean(fold_pinball_lower)), 4)
        if fold_pinball_upper:
            result['rolling_cv_pinball_upper'] = round(float(np.mean(fold_pinball_upper)), 4)

        print(f"   [RollingCV] Selesai {len(fold_mapes)} fold | "
              f"avg MAPE={result['rolling_cv_mape']:.2f}% | "
              f"DirAcc={result['rolling_cv_dir_acc']:.1f}% | "
              f"Coverage={result['rolling_cv_coverage']:.2f}")
        return result

    def _compute_insample_metrics(self):
        if self.model is None or self.train_df is None:
            return {'mape': 0.0, 'rmse': 0.0, 'mae': 0.0}
        try:
            future   = self.model.make_future_dataframe(periods=1, freq=self.data_freq)
            forecast = self.model.predict(future)
            forecast = forecast[forecast['ds'] <= self.train_df['ds'].max()].reset_index(drop=True)
            if len(forecast) == 0:
                return {'mape': 0.0, 'rmse': 0.0, 'mae': 0.0}
            merged = _merge_forecast_actual(
                forecast[['ds', 'yhat', 'yhat_lower', 'yhat_upper']],
                self.train_df[['ds', 'y']],
            )
            if len(merged) == 0:
                return {'mape': 0.0, 'rmse': 0.0, 'mae': 0.0}
            actual    = merged['y'].values
            predicted = merged['yhat'].values
            lower     = merged['yhat_lower'].values
            upper     = merged['yhat_upper'].values
            return {
                'mape':               round(self._mape(actual, predicted),                  4),
                'rmse':               round(float(np.sqrt(np.mean((actual-predicted)**2))), 2),
                'mae':                round(float(np.mean(np.abs(actual-predicted))),        2),
                'smape':              round(self._smape(actual, predicted),                  4),
                'directional_acc':    round(self._directional_accuracy(actual, predicted),   2),
                'interval_sharpness': round(self._interval_sharpness(lower, upper),          2),
                'r_squared':          round(self._r_squared(actual, predicted),             4),
            }
        except Exception as e:
            print(f"   [Metrics] In-sample error: {e}")
            return {'mape': 0.0, 'rmse': 0.0, 'mae': 0.0}

    def _compute_sensitivity_metrics(self):
        if self.model is None:
            return {
                'avg_interval_width': 0.0, 'changepoint_count': 0,
                'trend_flexibility':  float(self.changepoint_prior_scale),
                'seasonality_strength': float(self.seasonality_prior_scale),
            }
        try:
            future   = self.model.make_future_dataframe(periods=52, freq=self.data_freq)
            forecast = self.model.predict(future)
            avg_interval      = float((forecast['yhat_upper'] - forecast['yhat_lower']).mean())
            changepoints      = getattr(self.model, 'changepoints', pd.Series([]))
            changepoint_count = len(changepoints) if changepoints is not None else 0
            if hasattr(self.model, 'params') and 'beta' in self.model.params:
                beta = self.model.params['beta']
                seasonality_strength = float(np.abs(beta).mean()) if len(beta) > 0 else 0.0
            else:
                seasonality_strength = float(self.seasonality_prior_scale)
            return {
                'avg_interval_width':   round(avg_interval,                        2),
                'changepoint_count':    changepoint_count,
                'trend_flexibility':    round(float(self.changepoint_prior_scale),  4),
                'seasonality_strength': round(seasonality_strength,                 4),
            }
        except Exception as e:
            print(f"   [Metrics] Sensitivity error: {e}")
            return {
                'avg_interval_width': 0.0, 'changepoint_count': 0,
                'trend_flexibility':  float(self.changepoint_prior_scale),
                'seasonality_strength': float(self.seasonality_prior_scale),
            }

    def get_model_metrics(self):
        print(f"   [Metrics] cache={'ADA' if self._metrics_cache else 'None'} | "
              f"model={'ADA' if self.model else 'None'}")

        if self._metrics_cache is not None:
            return self._metrics_cache

        if self.model is None or self.train_df is None or len(self.train_df) < MIN_DATA_POINTS:
            self._metrics_cache = self._empty_metrics()
            return self._metrics_cache

        cv_metrics       = {}
        insample_metrics = {'mape': 0.0, 'rmse': 0.0, 'mae': 0.0}
        sensitivity      = {
            'avg_interval_width': 0.0, 'changepoint_count': 0,
            'trend_flexibility':  float(self.changepoint_prior_scale),
            'seasonality_strength': float(self.seasonality_prior_scale),
        }
        rolling_cv = {}

        try:
            cv_metrics = self._compute_cv_metrics()
        except Exception as e:
            print(f"   [Metrics] CV gagal: {e}")
        try:
            insample_metrics = self._compute_insample_metrics()
        except Exception as e:
            print(f"   [Metrics] In-sample gagal: {e}")
        try:
            sensitivity = self._compute_sensitivity_metrics()
        except Exception as e:
            print(f"   [Metrics] Sensitivity gagal: {e}")
        try:
            n_folds    = 2 if len(self.train_df) < 36 else 3  # FIX 6
            rolling_cv = self._compute_rolling_cv(n_folds=n_folds)
        except Exception as e:
            print(f"   [Metrics] Rolling CV gagal: {e}")

        # FIX 4: coverage dari CV (out-of-sample), bukan hardcode 0.80
        cv_coverage = cv_metrics.get('coverage')
        if cv_coverage is None:
            cv_coverage = rolling_cv.get('rolling_cv_coverage', 0.80)

        main_metrics = {
            'mape':     cv_metrics.get('mape',     insample_metrics.get('mape', 0)),
            'rmse':     cv_metrics.get('rmse',     insample_metrics.get('rmse', 0)),
            'mae':      cv_metrics.get('mae',      insample_metrics.get('mae',  0)),
            'coverage': round(cv_coverage, 4),
            'in_sample_mape': insample_metrics.get('mape', 0),
            'in_sample_rmse': insample_metrics.get('rmse', 0),
            'in_sample_mae':  insample_metrics.get('mae',  0),
            'avg_interval_width':   sensitivity['avg_interval_width'],
            'changepoint_count':    sensitivity['changepoint_count'],
            'trend_flexibility':    sensitivity['trend_flexibility'],
            'seasonality_strength': sensitivity['seasonality_strength'],
            'cv_method':      f'walk_forward_80_20_{self.data_freq}',
            'data_frequency': self.data_freq,
            'hyperparameters_used': {
                'changepoint_prior_scale': self.changepoint_prior_scale,
                'seasonality_prior_scale': self.seasonality_prior_scale,
                'seasonality_mode':        self.seasonality_mode,
                'weekly_seasonality':      self.weekly_seasonality,
                'yearly_seasonality':      self.yearly_seasonality,
                'yearly_fourier_order':    self.yearly_fourier_order,
                'monthly_seasonality':     self.monthly_seasonality,
                'n_changepoints':          self.n_changepoints,
                'changepoint_range':       self.changepoint_range,
            },
            'best_params_from_grid_search': self.best_params,
            'user_override': self.user_override,
            'r_squared':    cv_metrics.get('r_squared', insample_metrics.get('r_squared', 0.0)),
        }

        extended_metrics = {
            'smape':               cv_metrics.get('smape',              0.0),
            'directional_acc':     cv_metrics.get('directional_acc',    0.0),
            'winkler_score':       cv_metrics.get('winkler_score',      0.0),
            'pinball_lower':       cv_metrics.get('pinball_lower',      0.0),
            'pinball_upper':       cv_metrics.get('pinball_upper',      0.0),
            'interval_sharpness':  cv_metrics.get('interval_sharpness', 0.0),
            'in_sample_smape':     insample_metrics.get('smape',              0.0),
            'in_sample_dir_acc':   insample_metrics.get('directional_acc',    0.0),
            'in_sample_sharpness': insample_metrics.get('interval_sharpness', 0.0),
            **rolling_cv,
        }

        self._log_extended_metrics(extended_metrics)
        self._metrics_cache = {**main_metrics, 'extended_metrics': extended_metrics}
        return self._metrics_cache

    def _log_extended_metrics(self, ext):
        print(f"\n   +-- Extended Metrics -------------------------------------------")
        print(f"   |  SMAPE     : {ext.get('smape', 0):.4f}%")
        print(f"   |  DirAcc    : {ext.get('directional_acc', 0):.2f}%")
        print(f"   |  Coverage  : {self._metrics_cache['coverage'] if self._metrics_cache else '?'}"
              f" (target=0.80)")
        if 'rolling_cv_mape' in ext:
            print(f"   |  Rolling CV: MAPE={ext.get('rolling_cv_mape', 0):.4f}% "
                  f"(+/-{ext.get('rolling_cv_mape_std', 0):.4f}%) | "
                  f"DirAcc={ext.get('rolling_cv_dir_acc', 0):.2f}% | "
                  f"Coverage={ext.get('rolling_cv_coverage', 0):.2f}")
        print(f"   +---------------------------------------------------------------\n")

    def evaluate(self):
        metrics  = self.get_model_metrics()
        extended = metrics.get('extended_metrics', {})
        return {
            **{k: v for k, v in metrics.items() if k != 'extended_metrics'},
            'smape':              extended.get('smape',              0.0),
            'directional_acc':    extended.get('directional_acc',    0.0),
            'winkler_score':      extended.get('winkler_score',      0.0),
            'pinball_lower':      extended.get('pinball_lower',      0.0),
            'pinball_upper':      extended.get('pinball_upper',      0.0),
            'interval_sharpness': extended.get('interval_sharpness', 0.0),
            'in_sample_smape':    extended.get('in_sample_smape',    0.0),
            'in_sample_dir_acc':  extended.get('in_sample_dir_acc',  0.0),
            'hyperparameters_used': metrics.get('hyperparameters_used', {}),
            'rolling_cv': {
                'mape':          extended.get('rolling_cv_mape',          0.0),
                'mape_std':      extended.get('rolling_cv_mape_std',      0.0),
                'smape':         extended.get('rolling_cv_smape',         0.0),
                'dir_acc':       extended.get('rolling_cv_dir_acc',       0.0),
                'winkler':       extended.get('rolling_cv_winkler',       0.0),
                'coverage':      extended.get('rolling_cv_coverage',      0.0),
                'pinball_lower': extended.get('rolling_cv_pinball_lower', 0.0),
                'pinball_upper': extended.get('rolling_cv_pinball_upper', 0.0),
                'fold_mapes':    extended.get('fold_mapes',               []),
                'fold_smapes':   extended.get('fold_smapes',              []),
                'fold_dir_accs': extended.get('fold_dir_accs',            []),
                'fold_coverages':extended.get('fold_coverages',           []),
                'n_folds':       extended.get('n_folds_completed',         0),
            },
            'method': 'walk_forward_cv_80_20 + rolling_fold',
            'train_size_pct': 80, 'test_size_pct': 20,
            'data_frequency': self.data_freq,
        }

    def _empty_metrics(self):
        return {
            'mape': 0.0, 'rmse': 0.0, 'mae': 0.0, 'coverage': 0.80,
            'in_sample_mape': 0.0, 'in_sample_rmse': 0.0, 'in_sample_mae': 0.0,
            'avg_interval_width': 0.0, 'changepoint_count': 0,
            'trend_flexibility':    float(self.changepoint_prior_scale),
            'seasonality_strength': float(self.seasonality_prior_scale),
            'cv_method': 'insufficient_data', 'data_frequency': self.data_freq,
            'hyperparameters_used': {
                'changepoint_prior_scale': self.changepoint_prior_scale,
                'seasonality_prior_scale': self.seasonality_prior_scale,
                'seasonality_mode':        self.seasonality_mode,
                'weekly_seasonality':      self.weekly_seasonality,
                'yearly_seasonality':      self.yearly_seasonality,
                'yearly_fourier_order':    self.yearly_fourier_order,
                'monthly_seasonality':     self.monthly_seasonality,
                'n_changepoints':          self.n_changepoints,
                'changepoint_range':       self.changepoint_range,
            },
            'best_params_from_grid_search': self.best_params,
            'user_override': self.user_override,
            'extended_metrics': {
                'smape': 0.0, 'directional_acc': 0.0, 'winkler_score': 0.0,
                'pinball_lower': 0.0, 'pinball_upper': 0.0, 'interval_sharpness': 0.0,
                'in_sample_smape': 0.0, 'in_sample_dir_acc': 0.0, 'in_sample_sharpness': 0.0,
            },
        }