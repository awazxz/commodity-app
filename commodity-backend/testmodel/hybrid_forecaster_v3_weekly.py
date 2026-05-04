"""
=============================================================
  HYBRID FORECASTER v3 — Weekly (Mingguan) untuk Data Komoditas
  Database Format: id, komoditas_id, tanggal, harga, status, is_outlier

  Perubahan dari v2 (harian):
  - load_data()        : baca dari DB/CSV format baru (komoditas_id, tanggal, harga)
                         → filter is_outlier=0, status='cleaned'
                         → support multi-komoditas (loop per komoditas_id)
  - build_features()   : lag & window dalam satuan MINGGU
  - CONFIG             : forecast_periods = 26 minggu (~6 bulan)
                         lag & rolling window dalam minggu
  - ProphetModel       : freq="W" (weekly)
  - OUTPUT UTAMA       : inflasi/deflasi (YoY, MoM, kumulatif)
                         + sinyal naik/turun tetap disertakan
  - Semua model tetap  : Prophet + DirectMultiStep LGBM + Kalman + DirClassifier

  Karakteristik data:
  - Frekuensi   : Mingguan (setiap Senin)
  - Multi-symbol: komoditas_id sebagai pengganti Symbol
  - Rentang     : 2020-01-06 dst
  - Harga       : dalam Rupiah (IDR), skala ribuan
=============================================================
"""

import warnings
warnings.filterwarnings("ignore")

import numpy as np
import pandas as pd
from datetime import datetime, timedelta

from prophet import Prophet
import lightgbm as lgb
from pykalman import KalmanFilter
from sklearn.preprocessing import StandardScaler
from sklearn.metrics import mean_absolute_percentage_error

# ─────────────────────────────────────────────────────────────
#  KONFIGURASI — untuk data MINGGUAN
# ─────────────────────────────────────────────────────────────

CONFIG = {
    "forecast_periods"   : 26,           # ~6 bulan ke depan (minggu)
    "cv_folds"           : 3,
    "cv_test_size"       : 52,           # ~1 tahun (52 minggu per fold)
    "lgbm_lags"          : [1, 2, 4, 8, 13, 26, 52],  # 1w, 2w, 1m, 2m, 3m, 6m, 1yr
    "rolling_windows"    : [4, 8, 13, 26],             # 1m, 2m, 3m, 6m
    "direction_threshold": 0.005,        # 0.5% threshold naik/turun

    # Threshold inflasi/deflasi (dalam persen perubahan harga)
    "inflation_threshold"  : 0.02,       # >2% MoM = inflasi
    "deflation_threshold"  : -0.02,      # <-2% MoM = deflasi

    "prophet_hp": {
        "changepoint_prior_scale" : 0.3,
        "seasonality_prior_scale" : 10.0,
        "seasonality_mode"        : "multiplicative",
        "yearly_seasonality"      : True,
        "weekly_seasonality"      : False,  # data mingguan, tidak perlu weekly
        "daily_seasonality"       : False,
        "n_changepoints"          : 30,
        "changepoint_range"       : 0.90,
    },
    "lgbm_hp": {
        "n_estimators"    : 400,
        "learning_rate"   : 0.03,
        "max_depth"       : 5,
        "num_leaves"      : 31,
        "subsample"       : 0.8,
        "colsample_bytree": 0.8,
        "reg_alpha"       : 0.1,
        "reg_lambda"      : 0.5,
        "random_state"    : 42,
        "verbose"         : -1,
    },
    "ensemble_weights": {
        "prophet" : 0.40,
        "lgbm"    : 0.40,
        "kalman"  : 0.20,
    },
    "dir_confidence_weight": 0.15,

    "csv_path"       : "data_forecasting_transformed.csv",  # path CSV jika tidak pakai DB langsung
    "komoditas_id"   : None,                     # None = semua komoditas, atau isi integer
    "freq"           : "W",                      # "W" = weekly
}

# Nama komoditas (sesuaikan dengan data aktual kamu)
KOMODITAS_NAMES = {
    13: "Beras",
    14: "Gula Pasir",
    15: "Minyak Goreng",
    16: "Tepung Terigu",
    17: "Daging Sapi",
    18: "Daging Ayam",
    19: "Telur Ayam",
    20: "Cabai Merah",
}


# ─────────────────────────────────────────────────────────────
#  LOAD DATA — format DB baru (komoditas_id, tanggal, harga)
# ─────────────────────────────────────────────────────────────

def load_data(csv_path: str = CONFIG["csv_path"],
              komoditas_id: int = None) -> pd.DataFrame:
    """
    Baca CSV dengan format:
    id, komoditas_id, tanggal, harga, status, is_outlier, created_at, updated_at

    Filter:
    - is_outlier = 0 (hapus outlier)
    - status = 'cleaned' (hanya data bersih)
    - komoditas_id tertentu (opsional)

    Return df dengan kolom standar: ds, y, komoditas_id
    Frekuensi: mingguan (W)
    """
    df = pd.read_csv(csv_path)
    df.columns = [c.strip().lower() for c in df.columns]

    # Pastikan kolom yang dibutuhkan ada
    required = {"komoditas_id", "tanggal", "harga"}
    missing  = required - set(df.columns)
    if missing:
        raise ValueError(f"Kolom tidak ditemukan: {missing}. "
                         f"Kolom yang ada: {list(df.columns)}")

    # Filter data bersih
    if "is_outlier" in df.columns:
        df = df[df["is_outlier"] == 0].copy()
    if "status" in df.columns:
        df = df[df["status"] == "cleaned"].copy()

    # Filter komoditas
    if komoditas_id is not None:
        df = df[df["komoditas_id"] == komoditas_id].copy()

    # Konversi tipe
    df["tanggal"] = pd.to_datetime(df["tanggal"])
    df["harga"]   = pd.to_numeric(df["harga"], errors="coerce")
    df = df.dropna(subset=["tanggal", "harga"])

    # Rename ke format standar
    df = df.rename(columns={"tanggal": "ds", "harga": "y"})
    df = df.sort_values(["komoditas_id", "ds"]).reset_index(drop=True)

    print(f"  [LoadData] Weekly data: {len(df)} baris | "
          f"Komoditas: {df['komoditas_id'].nunique()} | "
          f"{df['ds'].min().date()} → {df['ds'].max().date()}")
    return df


def get_single_commodity(df: pd.DataFrame, komoditas_id: int) -> pd.DataFrame:
    """Ambil data satu komoditas, resample ke mingguan jika perlu."""
    sub = df[df["komoditas_id"] == komoditas_id][["ds", "y"]].copy()
    sub = sub.sort_values("ds").reset_index(drop=True)

    # Resample ke W jika data tidak konsisten mingguan
    sub = sub.set_index("ds")
    sub = sub.resample("W").last()   # ambil nilai terakhir per minggu
    sub = sub.dropna().reset_index()
    sub.columns = ["ds", "y"]

    min_rows = 2 * CONFIG["cv_folds"] * CONFIG["cv_test_size"] // 2
    if len(sub) < max(min_rows, 50):
        print(f"  [Warning] Komoditas {komoditas_id}: hanya {len(sub)} baris "
              f"(minimal ~{max(min_rows,50)}). CV mungkin tidak stabil.")
    return sub


# ─────────────────────────────────────────────────────────────
#  FEATURE ENGINEERING — satuan MINGGU
# ─────────────────────────────────────────────────────────────

def build_features(df: pd.DataFrame, lags: list, windows: list) -> pd.DataFrame:
    feat = df[["ds", "y"]].copy().sort_values("ds").reset_index(drop=True)

    # ── Lag features (dalam minggu) ──
    for lag in lags:
        feat[f"lag_{lag}w"] = feat["y"].shift(lag)

    # ── Rolling stats ──
    for w in windows:
        feat[f"roll_mean_{w}w"] = feat["y"].shift(1).rolling(w).mean()
        feat[f"roll_std_{w}w"]  = feat["y"].shift(1).rolling(w).std()
        feat[f"roll_min_{w}w"]  = feat["y"].shift(1).rolling(w).min()
        feat[f"roll_max_{w}w"]  = feat["y"].shift(1).rolling(w).max()

    # ── Momentum (dalam minggu) ──
    feat["momentum_4w"]  = feat["y"].shift(1) / (feat["y"].shift(5)  + 1e-9) - 1   # 1 bulan
    feat["momentum_13w"] = feat["y"].shift(1) / (feat["y"].shift(14) + 1e-9) - 1   # 3 bulan
    feat["momentum_26w"] = feat["y"].shift(1) / (feat["y"].shift(27) + 1e-9) - 1   # 6 bulan
    feat["momentum_52w"] = feat["y"].shift(1) / (feat["y"].shift(53) + 1e-9) - 1   # 1 tahun (YoY)

    # ── Return mingguan (% perubahan) ──
    feat["return_1w"]  = feat["y"].pct_change(1)
    feat["return_4w"]  = feat["y"].pct_change(4)
    feat["return_13w"] = feat["y"].pct_change(13)
    feat["return_52w"] = feat["y"].pct_change(52)   # proxy YoY

    # ── Coefficient of Variation ──
    for w in windows:
        mean_ = feat["y"].shift(1).rolling(w).mean()
        std_  = feat["y"].shift(1).rolling(w).std()
        feat[f"cov_{w}w"] = std_ / (mean_ + 1e-9)

    # ── Posisi harga dalam range historis ──
    for w in windows:
        mn = feat[f"roll_min_{w}w"]
        mx = feat[f"roll_max_{w}w"]
        feat[f"price_pos_{w}w"] = (feat["y"].shift(1) - mn) / (mx - mn + 1e-9)

    # ── Kalender (mingguan) ──
    feat["week_of_year"] = feat["ds"].dt.isocalendar().week.astype(int)
    feat["month"]        = feat["ds"].dt.month
    feat["quarter"]      = feat["ds"].dt.quarter
    feat["year"]         = feat["ds"].dt.year

    # Encoding siklikal
    feat["week_sin"]  = np.sin(2 * np.pi * feat["week_of_year"] / 52)
    feat["week_cos"]  = np.cos(2 * np.pi * feat["week_of_year"] / 52)
    feat["month_sin"] = np.sin(2 * np.pi * feat["month"] / 12)
    feat["month_cos"] = np.cos(2 * np.pi * feat["month"] / 12)
    feat["q_sin"]     = np.sin(2 * np.pi * feat["quarter"] / 4)
    feat["q_cos"]     = np.cos(2 * np.pi * feat["quarter"] / 4)

    # ── Normalisasi tahun (tren jangka panjang) ──
    yr_min = feat["year"].min()
    yr_max = feat["year"].max()
    feat["year_norm"] = (feat["year"] - yr_min) / (yr_max - yr_min + 1e-9)

    return feat


def make_direction_labels(series: pd.Series, threshold: float) -> np.ndarray:
    pct_change = series.pct_change()
    labels = np.where(pct_change >  threshold,  1,
             np.where(pct_change < -threshold, -1, 0))
    return labels


# ─────────────────────────────────────────────────────────────
#  INFLASI / DEFLASI — fungsi analisis utama
# ─────────────────────────────────────────────────────────────

def compute_inflation_metrics(history_df: pd.DataFrame,
                              forecast_df: pd.DataFrame) -> pd.DataFrame:
    """
    Hitung metrik inflasi/deflasi dari data historis + forecast.

    Output per baris forecast:
    - pct_1w   : perubahan mingguan (%)
    - pct_4w   : perubahan bulanan (%)  → proxy inflasi bulanan
    - pct_13w  : perubahan 3-bulanan (%)
    - pct_26w  : perubahan 6-bulanan (kumulatif forecast)
    - pct_52w  : perubahan tahunan YoY (%) → inflasi tahunan
    - annualized_rate : tingkat inflasi tahunan (disetahunkan dari 6-bulan forecast)
    - inflation_status: "INFLASI" / "DEFLASI" / "STABIL"
    - direction_signal: "NAIK" / "TURUN" / "FLAT"
    """
    # Gabungkan history + forecast untuk perhitungan lookback
    hist = history_df[["ds", "y"]].copy()
    hist["source"] = "actual"

    fcast = forecast_df[["ds", "ensemble_v2"]].copy()
    fcast = fcast.rename(columns={"ensemble_v2": "y"})
    fcast["source"] = "forecast"

    combined = pd.concat([hist, fcast], ignore_index=True).sort_values("ds").reset_index(drop=True)
    combined["y_1w_ago"]  = combined["y"].shift(1)
    combined["y_4w_ago"]  = combined["y"].shift(4)
    combined["y_13w_ago"] = combined["y"].shift(13)
    combined["y_52w_ago"] = combined["y"].shift(52)

    combined["pct_1w"]  = (combined["y"] / combined["y_1w_ago"]  - 1) * 100
    combined["pct_4w"]  = (combined["y"] / combined["y_4w_ago"]  - 1) * 100
    combined["pct_13w"] = (combined["y"] / combined["y_13w_ago"] - 1) * 100
    combined["pct_52w"] = (combined["y"] / combined["y_52w_ago"] - 1) * 100

    # Ambil hanya baris forecast
    result = combined[combined["source"] == "forecast"].copy()
    result = result.merge(
        forecast_df[["ds", "prophet", "lgbm_direct", "kalman",
                     "ensemble", "ensemble_v2", "lower_95", "upper_95",
                     "interval_width", "direction_signal"]],
        on="ds", how="left"
    )

    # Hitung pct_26w (kumulatif 6 bulan dari harga awal forecast)
    last_actual = float(hist["y"].iloc[-1])
    result["pct_26w"] = (result["ensemble_v2"] / last_actual - 1) * 100

    # Annualized rate dari 6-bulan forecast (compound)
    result["annualized_rate"] = ((1 + result["pct_26w"] / 100) ** 2 - 1) * 100

    # Status inflasi berdasarkan perubahan bulanan (4w)
    inf_thresh  = CONFIG["inflation_threshold"]  * 100
    def_thresh  = CONFIG["deflation_threshold"]  * 100

    def classify_inflation(pct_4w, pct_52w):
        """Klasifikasi berdasarkan MoM dan YoY."""
        if pd.isna(pct_4w):
            return "N/A"
        if pct_4w > inf_thresh:
            severity = "TINGGI" if pct_4w > inf_thresh * 2 else "MODERAT"
            return f"INFLASI {severity}"
        elif pct_4w < def_thresh:
            return "DEFLASI"
        else:
            return "STABIL"

    result["inflation_status"] = result.apply(
        lambda r: classify_inflation(r["pct_4w"], r["pct_52w"]), axis=1
    )

    # Kolom output bersih
    cols = [
        "ds", "ensemble_v2", "lower_95", "upper_95",
        "pct_1w", "pct_4w", "pct_13w", "pct_26w", "pct_52w",
        "annualized_rate", "inflation_status", "direction_signal",
        "prophet", "lgbm_direct", "kalman", "ensemble", "interval_width",
    ]
    result = result[[c for c in cols if c in result.columns]]
    result = result.rename(columns={"ensemble_v2": "harga_forecast"})

    # Round semua numeric
    for col in result.select_dtypes(include=[np.number]).columns:
        result[col] = result[col].round(2)

    return result.reset_index(drop=True)


def compute_historical_inflation(df: pd.DataFrame) -> pd.DataFrame:
    """
    Hitung inflasi historis dari data aktual.
    Berguna untuk melihat pola sebelum forecast.
    """
    h = df[["ds", "y"]].copy().sort_values("ds").reset_index(drop=True)

    h["pct_1w"]  = h["y"].pct_change(1)  * 100
    h["pct_4w"]  = h["y"].pct_change(4)  * 100
    h["pct_13w"] = h["y"].pct_change(13) * 100
    h["pct_52w"] = h["y"].pct_change(52) * 100

    inf_thresh = CONFIG["inflation_threshold"] * 100
    def_thresh = CONFIG["deflation_threshold"] * 100

    h["inflation_status"] = h["pct_4w"].apply(
        lambda x: ("INFLASI TINGGI" if x > inf_thresh * 2
                   else "INFLASI MODERAT" if x > inf_thresh
                   else "DEFLASI" if x < def_thresh
                   else "STABIL") if not pd.isna(x) else "N/A"
    )

    return h.round(2)


# ─────────────────────────────────────────────────────────────
#  MODEL 1: PROPHET (mingguan)
# ─────────────────────────────────────────────────────────────

class ProphetModel:
    def __init__(self, hp: dict):
        self.hp    = hp
        self.model = None

    def fit(self, df: pd.DataFrame):
        m = Prophet(**{k: v for k, v in self.hp.items()})
        m.add_seasonality(name="monthly",   period=4.33,  fourier_order=5)
        m.add_seasonality(name="quarterly", period=13.0,  fourier_order=3)
        m.add_seasonality(name="yearly",    period=52.18, fourier_order=5)
        m.fit(df[["ds", "y"]])
        self.model = m
        return self

    def predict(self, periods: int, freq: str = "W") -> pd.DataFrame:
        future   = self.model.make_future_dataframe(periods=periods, freq=freq)
        forecast = self.model.predict(future)
        return forecast[["ds", "yhat", "yhat_lower", "yhat_upper"]].tail(periods).reset_index(drop=True)

    def predict_in_sample(self, df: pd.DataFrame) -> np.ndarray:
        return self.model.predict(df[["ds"]])["yhat"].values

    def get_full_forecast(self, df: pd.DataFrame, periods: int, freq: str = "W") -> pd.DataFrame:
        future   = self.model.make_future_dataframe(periods=periods, freq=freq)
        forecast = self.model.predict(future)
        return forecast[["ds", "yhat", "yhat_lower", "yhat_upper", "trend"]].reset_index(drop=True)

    def get_trend_component(self, df: pd.DataFrame) -> pd.DataFrame:
        """Ekstrak trend murni dari Prophet — berguna untuk analisis inflasi struktural."""
        forecast = self.model.predict(df[["ds"]])
        return forecast[["ds", "trend", "yhat"]].copy()


# ─────────────────────────────────────────────────────────────
#  MODEL 2: DIRECT MULTI-STEP LGBM + ASYMMETRIC LOSS
# ─────────────────────────────────────────────────────────────

def directional_loss(y_true, y_pred, direction_weight: float = 2.0):
    residual  = y_pred - y_true
    mean_true = np.mean(y_true)
    sign_true = np.sign(y_true - mean_true)
    sign_pred = np.sign(y_pred - mean_true)
    wrong_dir = (sign_true != sign_pred).astype(float)
    penalty   = 1.0 + (direction_weight - 1.0) * wrong_dir
    grad      = residual * penalty
    hess      = penalty
    return grad, hess


class DirectMultiStepLGBM:
    def __init__(self, hp, lags, windows, forecast_periods, use_directional_loss=True):
        self.hp                   = hp
        self.lags                 = lags
        self.windows              = windows
        self.H                    = forecast_periods
        self.use_directional_loss = use_directional_loss
        self.models               = {}
        self.scaler               = StandardScaler()
        self.feature_cols         = None

    def _get_feature_cols(self, df):
        return [c for c in df.columns if c not in {"ds", "y"}]

    def _prepare_Xy(self, feat, horizon):
        f = feat.copy()
        f["target"] = f["y"].shift(-horizon)
        f = f.dropna()
        X = f[self.feature_cols].values
        y = f["target"].values
        return X, y

    def fit(self, df, prophet_residuals=None):
        feat = build_features(df, self.lags, self.windows)
        if prophet_residuals is not None:
            feat["prophet_residual"] = 0.0
            n = min(len(prophet_residuals), len(feat))
            feat.loc[feat.index[-n:], "prophet_residual"] = prophet_residuals[-n:]

        self.feature_cols = self._get_feature_cols(feat)
        feat_clean = feat.dropna()
        self.scaler.fit(feat_clean[self.feature_cols].values)

        print(f"    [DirectLGBM] Training {self.H} model...", end="", flush=True)
        for h in range(1, self.H + 1):
            X, y = self._prepare_Xy(feat, horizon=h)
            X_scaled = self.scaler.transform(X)

            if self.use_directional_loss:
                def _obj(y_true, y_pred):
                    return directional_loss(y_true, y_pred)
                model = lgb.LGBMRegressor(
                    objective        = _obj,
                    n_estimators     = self.hp.get("n_estimators", 400),
                    learning_rate    = self.hp.get("learning_rate", 0.03),
                    max_depth        = self.hp.get("max_depth", 5),
                    num_leaves       = self.hp.get("num_leaves", 31),
                    subsample        = self.hp.get("subsample", 0.8),
                    colsample_bytree = self.hp.get("colsample_bytree", 0.8),
                    reg_alpha        = self.hp.get("reg_alpha", 0.1),
                    reg_lambda       = self.hp.get("reg_lambda", 0.5),
                    random_state     = self.hp.get("random_state", 42),
                    verbose          = -1,
                )
                model.fit(X_scaled, y, callbacks=[lgb.log_evaluation(period=-1)])
            else:
                model = lgb.LGBMRegressor(**self.hp)
                model.fit(X_scaled, y)

            self.models[h] = model
            if h % 5 == 0 or h == self.H:
                print(f" {h}", end="", flush=True)
        print()
        return self

    def predict(self, history, prophet_future=None):
        feat = build_features(history, self.lags, self.windows)
        if prophet_future is not None:
            feat["prophet_residual"] = 0.0
        last_row = feat.iloc[[-1]].copy()
        for c in self.feature_cols:
            if c not in last_row.columns:
                last_row[c] = 0.0
        X        = last_row[self.feature_cols].values
        X_scaled = self.scaler.transform(X)
        preds    = [self.models[h].predict(X_scaled)[0] for h in range(1, self.H + 1)]
        return np.array(preds)

    def get_feature_importance(self):
        if not self.models:
            return pd.DataFrame()
        imp_sum = None
        count   = 0
        for h, model in self.models.items():
            fi      = model.feature_importances_
            imp_sum = fi.astype(float) if imp_sum is None else imp_sum + fi.astype(float)
            count  += 1
        return pd.DataFrame({
            "feature"   : self.feature_cols,
            "importance": np.round(imp_sum / count, 1),
        }).sort_values("importance", ascending=False)


# ─────────────────────────────────────────────────────────────
#  MODEL 3: DIRECTIONAL CLASSIFIER
# ─────────────────────────────────────────────────────────────

class DirectionalClassifier:
    def __init__(self, hp, lags, windows, forecast_periods, direction_threshold=0.005):
        self.hp           = hp
        self.lags         = lags
        self.windows      = windows
        self.H            = forecast_periods
        self.threshold    = direction_threshold
        self.models       = {}
        self.scaler       = StandardScaler()
        self.feature_cols = None

    def _encode_direction(self, y_future, y_current):
        pct    = (y_future - y_current) / (y_current + 1e-9)
        labels = np.where(pct >  self.threshold, 2,
                 np.where(pct < -self.threshold, 0, 1))
        return labels.astype(int)

    def fit(self, df, prophet_residuals=None):
        feat = build_features(df, self.lags, self.windows)
        if prophet_residuals is not None:
            feat["prophet_residual"] = 0.0
            n = min(len(prophet_residuals), len(feat))
            feat.loc[feat.index[-n:], "prophet_residual"] = prophet_residuals[-n:]

        feat_clean        = feat.dropna().copy()
        self.feature_cols = [c for c in feat_clean.columns if c not in {"ds", "y"}]
        self.scaler.fit(feat_clean[self.feature_cols].values)

        clf_hp = {
            "n_estimators"    : self.hp.get("n_estimators", 300),
            "learning_rate"   : self.hp.get("learning_rate", 0.03),
            "max_depth"       : self.hp.get("max_depth", 4),
            "num_leaves"      : 15,
            "subsample"       : self.hp.get("subsample", 0.8),
            "colsample_bytree": self.hp.get("colsample_bytree", 0.8),
            "random_state"    : self.hp.get("random_state", 42),
            "verbose"         : -1,
            "objective"       : "multiclass",
            "num_class"       : 3,
        }

        print(f"    [DirClassifier] Training {self.H} model...", end="", flush=True)
        for h in range(1, self.H + 1):
            f              = feat_clean.copy()
            f["y_future"]  = f["y"].shift(-h)
            f["y_current"] = f["y"]
            f              = f.dropna()
            if len(f) < 20:
                continue
            y_labels = self._encode_direction(f["y_future"].values, f["y_current"].values)
            if len(np.unique(y_labels)) < 2:
                continue
            X        = f[self.feature_cols].values
            X_scaled = self.scaler.transform(X)
            clf      = lgb.LGBMClassifier(**clf_hp)
            clf.fit(X_scaled, y_labels)
            self.models[h] = clf
            if h % 5 == 0 or h == self.H:
                print(f" {h}", end="", flush=True)
        print()
        return self

    def predict_proba(self, history, prophet_residuals=None):
        feat = build_features(history, self.lags, self.windows)
        if prophet_residuals is not None:
            feat["prophet_residual"] = 0.0
        for c in self.feature_cols:
            if c not in feat.columns:
                feat[c] = 0.0
        last_row = feat.iloc[[-1]][self.feature_cols].values
        X_scaled = self.scaler.transform(last_row)
        result   = {}
        for h in range(1, self.H + 1):
            if h not in self.models:
                result[h] = {"up": 0.333, "flat": 0.333, "down": 0.333}
                continue
            proba     = self.models[h].predict_proba(X_scaled)[0]
            result[h] = {"down": float(proba[0]), "flat": float(proba[1]), "up": float(proba[2])}
        return result


# ─────────────────────────────────────────────────────────────
#  MODEL 4: KALMAN FILTER
# ─────────────────────────────────────────────────────────────

class KalmanModel:
    def __init__(self):
        self.kf          = None
        self.state_means = None
        self.state_covs  = None

    def fit(self, y):
        T_mat    = [[1, 1], [0, 1]]
        H_mat    = [[1, 0]]
        self.kf  = KalmanFilter(
            transition_matrices      = T_mat,
            observation_matrices     = H_mat,
            initial_state_mean       = [y[0], 0],
            initial_state_covariance = np.eye(2) * 1000,
            em_vars=["transition_covariance", "observation_covariance"],
            n_dim_state=2, n_dim_obs=1,
        )
        self.kf = self.kf.em(y.reshape(-1, 1), n_iter=10)
        self.state_means, self.state_covs = self.kf.filter(y.reshape(-1, 1))
        return self

    def predict(self, periods):
        T        = np.array([[1, 1], [0, 1]])
        Q        = self.kf.transition_covariance
        H        = np.array([[1, 0]])
        R        = self.kf.observation_covariance
        cur_mean = self.state_means[-1]
        cur_cov  = self.state_covs[-1]
        means, lowers, uppers = [], [], []
        for _ in range(periods):
            pred_mean = T @ cur_mean
            pred_cov  = T @ cur_cov @ T.T + Q
            obs_var   = (H @ pred_cov @ H.T + R)[0, 0]
            std_dev   = np.sqrt(max(obs_var, 0))
            obs_mean  = (H @ pred_mean)[0]
            means.append(obs_mean)
            lowers.append(obs_mean - 1.96 * std_dev)
            uppers.append(obs_mean + 1.96 * std_dev)
            cur_mean = pred_mean
            cur_cov  = pred_cov
        return np.array(means), np.array(lowers), np.array(uppers)


# ─────────────────────────────────────────────────────────────
#  DYNAMIC ENSEMBLE
# ─────────────────────────────────────────────────────────────

class DynamicEnsemble:
    def __init__(self, config):
        self.config     = config
        self.weights    = config["ensemble_weights"].copy()
        self.cv_results = {}

    def compute_directional_accuracy(self, actual, pred):
        if len(actual) < 2:
            return 0.5
        return float(np.mean(np.sign(np.diff(actual)) == np.sign(np.diff(pred))))

    def compute_coverage(self, actual, lower, upper):
        return float(np.mean((actual >= lower) & (actual <= upper)))

    def compute_winkler(self, actual, lower, upper, alpha=0.05):
        width   = upper - lower
        pen_low = (2 / alpha) * np.maximum(lower - actual, 0)
        pen_hi  = (2 / alpha) * np.maximum(actual - upper, 0)
        return float(np.mean(width + pen_low + pen_hi))

    def apply_directional_modifier(self, ens_pred, dir_proba, prev_price, dir_weight=0.15):
        modified      = ens_pred.copy()
        current_price = prev_price
        for i, pred in enumerate(ens_pred):
            h = i + 1
            if h not in dir_proba:
                continue
            p            = dir_proba[h]
            ensemble_dir = np.sign(pred - current_price)
            max_p        = max(p["up"], p["flat"], p["down"])
            if max_p < 0.50:
                current_price = pred
                continue
            if max_p == p["up"]:
                clf_dir = +1
            elif max_p == p["down"]:
                clf_dir = -1
            else:
                clf_dir = 0
            if clf_dir == 0:
                modified[i] = current_price + 0.3 * (pred - current_price)
            elif clf_dir == ensemble_dir:
                confidence_boost = (max_p - 0.5) * dir_weight
                modified[i] = current_price + (1 + confidence_boost) * (pred - current_price)
            else:
                confidence_pull = (max_p - 0.5) * dir_weight
                modified[i] = current_price + (1 - confidence_pull * 2) * (pred - current_price)
            current_price = modified[i]
        return modified

    def rolling_cv(self, df, prophet_model, lgbm_model, kalman_model, dir_clf=None):
        n, folds, test_size = len(df), self.config["cv_folds"], self.config["cv_test_size"]

        results = {
            name: {"mape": [], "dacc": []}
            for name in ["prophet", "lgbm", "kalman", "ensemble", "ensemble_v2"]
        }
        results["ensemble"]["coverage"]    = []
        results["ensemble"]["winkler"]     = []
        results["ensemble_v2"]["coverage"] = []
        results["ensemble_v2"]["winkler"]  = []

        print("\n  [CV] Rolling Cross-Validation (data mingguan)...")

        for fold in range(folds):
            test_end   = n - (folds - fold - 1) * test_size
            test_start = test_end - test_size
            train_end  = test_start

            if train_end < 60:   # minimal ~1 tahun data mingguan untuk training
                print(f"  [Fold {fold+1}] Skip — data training < 60 minggu")
                continue

            train  = df.iloc[:train_end].copy()
            test   = df.iloc[test_start:test_end].copy()
            y_true = test["y"].values

            try:
                pm = ProphetModel(self.config["prophet_hp"])
                pm.fit(train)
                p_df   = pm.predict(test_size, freq=self.config.get("freq", "W"))
                p_pred = p_df["yhat"].values
                p_lo   = p_df["yhat_lower"].values
                p_hi   = p_df["yhat_upper"].values
                p_in   = pm.predict_in_sample(train)
                p_res  = train["y"].values - p_in

                lm = DirectMultiStepLGBM(
                    self.config["lgbm_hp"],
                    self.config["lgbm_lags"],
                    self.config["rolling_windows"],
                    test_size, use_directional_loss=True,
                )
                lm.fit(train, p_res)
                l_pred = lm.predict(train)
                l_lo   = l_pred * 0.92
                l_hi   = l_pred * 1.08

                km = KalmanModel()
                km.fit(train["y"].values)
                k_pred, k_lo, k_hi = km.predict(test_size)

                w      = self.weights
                ens_v1 = w["prophet"]*p_pred + w["lgbm"]*l_pred + w["kalman"]*k_pred
                ens_lo = w["prophet"]*p_lo   + w["lgbm"]*l_lo   + w["kalman"]*k_lo
                ens_hi = w["prophet"]*p_hi   + w["lgbm"]*l_hi   + w["kalman"]*k_hi

                if dir_clf is not None:
                    clf_cv = DirectionalClassifier(
                        self.config["lgbm_hp"],
                        self.config["lgbm_lags"],
                        self.config["rolling_windows"],
                        test_size,
                        self.config["direction_threshold"],
                    )
                    clf_cv.fit(train, p_res)
                    dir_proba  = clf_cv.predict_proba(train)
                    prev_price = float(train["y"].iloc[-1])
                    ens_v2     = self.apply_directional_modifier(
                        ens_v1, dir_proba, prev_price,
                        self.config["dir_confidence_weight"]
                    )
                else:
                    ens_v2 = ens_v1.copy()

                for name, pred in [("prophet", p_pred), ("lgbm", l_pred), ("kalman", k_pred)]:
                    results[name]["mape"].append(
                        mean_absolute_percentage_error(y_true, pred) * 100)
                    results[name]["dacc"].append(
                        self.compute_directional_accuracy(y_true, pred) * 100)

                for name, pred in [("ensemble", ens_v1), ("ensemble_v2", ens_v2)]:
                    results[name]["mape"].append(
                        mean_absolute_percentage_error(y_true, pred) * 100)
                    results[name]["dacc"].append(
                        self.compute_directional_accuracy(y_true, pred) * 100)
                    results[name]["coverage"].append(
                        self.compute_coverage(y_true, ens_lo, ens_hi) * 100)
                    results[name]["winkler"].append(
                        self.compute_winkler(y_true, ens_lo, ens_hi))

                print(f"  [Fold {fold+1}/{folds}] "
                      f"Prophet={results['prophet']['mape'][-1]:.1f}% "
                      f"(DA:{results['prophet']['dacc'][-1]:.0f}%) | "
                      f"LGBM={results['lgbm']['mape'][-1]:.1f}% "
                      f"(DA:{results['lgbm']['dacc'][-1]:.0f}%) | "
                      f"Ens.v2={results['ensemble_v2']['mape'][-1]:.1f}% "
                      f"(DA:{results['ensemble_v2']['dacc'][-1]:.0f}%)")

            except Exception as e:
                import traceback
                print(f"  [Fold {fold+1}] Error: {e}")
                traceback.print_exc()
                continue

        self.cv_results = results

        # Hitung bobot dinamis dari inverse MAPE
        avg_mapes = {
            name: np.mean(results[name]["mape"]) if results[name]["mape"] else 99.0
            for name in ["prophet", "lgbm", "kalman"]
        }
        inv_mapes    = {k: 1.0 / (v + 1e-9) for k, v in avg_mapes.items()}
        total        = sum(inv_mapes.values())
        self.weights = {k: v / total for k, v in inv_mapes.items()}

        print(f"\n  [Ensemble] Bobot dinamis:")
        for k, v in self.weights.items():
            print(f"    {k:12s}: {v:.3f} (avg MAPE = {avg_mapes[k]:.2f}%)")

        return results


# ─────────────────────────────────────────────────────────────
#  HYBRID FORECASTER V3 — KELAS UTAMA (MINGGUAN)
# ─────────────────────────────────────────────────────────────

class HybridForecasterV3Weekly:
    """
    Wrapper utama untuk data komoditas mingguan (format DB).
    Fokus output: analisis inflasi/deflasi + sinyal naik/turun.
    """

    def __init__(self, config=CONFIG):
        self.config      = config
        self.df          = None           # data satu komoditas
        self.komoditas_id = None
        self.prophet     = ProphetModel(config["prophet_hp"])
        self.lgbm        = DirectMultiStepLGBM(
            config["lgbm_hp"], config["lgbm_lags"],
            config["rolling_windows"], config["forecast_periods"],
            use_directional_loss=True,
        )
        self.kalman      = KalmanModel()
        self.dir_clf     = DirectionalClassifier(
            config["lgbm_hp"], config["lgbm_lags"],
            config["rolling_windows"], config["forecast_periods"],
            config["direction_threshold"],
        )
        self.ensemble        = DynamicEnsemble(config)
        self.forecast_df     = None
        self.inflation_df    = None
        self.hist_inflation  = None
        self.results         = {}

    def load(self, csv_path=None, komoditas_id=None):
        path = csv_path or self.config["csv_path"]
        kid  = komoditas_id or self.config.get("komoditas_id")

        all_df = load_data(path, kid)

        if kid is not None:
            self.komoditas_id = kid
            self.df = get_single_commodity(all_df, kid)
        else:
            # Jika tidak ada filter, ambil komoditas pertama sebagai default
            first_kid = all_df["komoditas_id"].iloc[0]
            self.komoditas_id = first_kid
            self.df = get_single_commodity(all_df, first_kid)
            print(f"  [Info] Tidak ada filter komoditas. "
                  f"Menggunakan komoditas_id={first_kid}. "
                  f"Gunakan run_all() untuk semua komoditas.")

        self.results["komoditas_id"] = self.komoditas_id
        self.results["komoditas_name"] = KOMODITAS_NAMES.get(
            self.komoditas_id, f"Komoditas-{self.komoditas_id}"
        )
        self.results["train_df"] = self.df

        # Hitung inflasi historis
        self.hist_inflation = compute_historical_inflation(self.df)
        self.results["hist_inflation"] = self.hist_inflation
        return self

    def run_cv(self):
        self.ensemble.rolling_cv(
            self.df, self.prophet, self.lgbm, self.kalman, self.dir_clf
        )
        self.results["cv_results"] = self.ensemble.cv_results
        self.results["weights"]    = self.ensemble.weights
        return self

    def fit_all(self):
        name = self.results.get("komoditas_name", "Komoditas")
        print(f"\n  [HybridV3] Training semua model — {name} (data mingguan)...")
        self.prophet.fit(self.df)
        p_in_sample = self.prophet.predict_in_sample(self.df)
        p_residuals = self.df["y"].values - p_in_sample

        self.lgbm.fit(self.df, p_residuals)
        self.kalman.fit(self.df["y"].values)
        self.dir_clf.fit(self.df, p_residuals)

        self.results["prophet_insample"]  = p_in_sample
        self.results["prophet_residuals"] = p_residuals
        print(f"  [HybridV3] Semua model selesai dilatih.")
        return self

    def forecast(self):
        periods = self.config["forecast_periods"]
        freq    = self.config.get("freq", "W")
        w       = self.ensemble.weights

        p_df   = self.prophet.predict(periods, freq=freq)
        p_pred = p_df["yhat"].values
        p_lo   = p_df["yhat_lower"].values
        p_hi   = p_df["yhat_upper"].values

        full_prophet = self.prophet.get_full_forecast(self.df, periods, freq=freq)
        self.results["prophet_full_forecast"] = full_prophet

        l_pred = self.lgbm.predict(self.df)
        l_lo   = l_pred * 0.92
        l_hi   = l_pred * 1.08

        k_pred, k_lo, k_hi = self.kalman.predict(periods)

        ens_pred = w["prophet"]*p_pred + w["lgbm"]*l_pred + w["kalman"]*k_pred
        ens_lo   = w["prophet"]*p_lo   + w["lgbm"]*l_lo   + w["kalman"]*k_lo
        ens_hi   = w["prophet"]*p_hi   + w["lgbm"]*l_hi   + w["kalman"]*k_hi

        dir_proba  = self.dir_clf.predict_proba(self.df)
        prev_price = float(self.df["y"].iloc[-1])
        ens_mod    = self.ensemble.apply_directional_modifier(
            ens_pred, dir_proba, prev_price, self.config["dir_confidence_weight"]
        )

        # Sinyal arah naik/turun/flat
        dir_signals = []
        for i in range(periods):
            h     = i + 1
            p     = dir_proba.get(h, {"up": 0.333, "flat": 0.333, "down": 0.333})
            max_p = max(p["up"], p["flat"], p["down"])
            if max_p == p["up"]:
                dir_signals.append(f"NAIK ({p['up']:.0%})")
            elif max_p == p["down"]:
                dir_signals.append(f"TURUN ({p['down']:.0%})")
            else:
                dir_signals.append(f"FLAT ({p['flat']:.0%})")

        self.forecast_df = pd.DataFrame({
            "ds"              : p_df["ds"],
            "prophet"         : np.round(p_pred, 2),
            "lgbm_direct"     : np.round(l_pred, 2),
            "kalman"          : np.round(k_pred, 2),
            "ensemble"        : np.round(ens_pred, 2),
            "ensemble_v2"     : np.round(ens_mod, 2),
            "lower_95"        : np.round(ens_lo, 2),
            "upper_95"        : np.round(ens_hi, 2),
            "interval_width"  : np.round(ens_hi - ens_lo, 2),
            "direction_signal": dir_signals,
        })

        # Hitung metrik inflasi/deflasi
        self.inflation_df = compute_inflation_metrics(self.df, self.forecast_df)

        self.results["forecast_df"]   = self.forecast_df
        self.results["inflation_df"]  = self.inflation_df
        self.results["dir_proba"]     = dir_proba
        self.results["component_forecasts"] = {
            "prophet": p_pred, "lgbm": l_pred, "kalman": k_pred,
            "p_lo": p_lo, "p_hi": p_hi,
        }
        return self.forecast_df

    def print_summary(self):
        if self.forecast_df is None:
            print("Jalankan .forecast() dulu.")
            return

        name = self.results.get("komoditas_name", f"Komoditas-{self.komoditas_id}")
        cv   = self.ensemble.cv_results
        v1   = cv.get("ensemble",    {})
        v2   = cv.get("ensemble_v2", {})

        print("\n" + "=" * 70)
        print(f"  HYBRID FORECASTER v3 MINGGUAN — {name} (ID: {self.komoditas_id})")
        print("=" * 70)
        print(f"  Data     : {self.df['ds'].min().date()} → {self.df['ds'].max().date()}")
        print(f"  N data   : {len(self.df)} minggu")
        print(f"  Harga terakhir : Rp {self.df['y'].iloc[-1]:,.2f}")
        print(f"  Forecast : {self.forecast_df['ds'].iloc[0].date()} "
              f"→ {self.forecast_df['ds'].iloc[-1].date()} ({len(self.forecast_df)} minggu)")

        # Ringkasan inflasi historis
        if self.hist_inflation is not None:
            h = self.hist_inflation.dropna(subset=["pct_52w"])
            if len(h) > 0:
                avg_yoy = h["pct_52w"].mean()
                last_yoy = h["pct_52w"].iloc[-1]
                print(f"\n  ┌─ Inflasi Historis ─────────────────────────────────────────")
                print(f"  │  Rata-rata YoY (historis) : {avg_yoy:+.2f}%")
                print(f"  │  YoY terakhir             : {last_yoy:+.2f}%")
                print(f"  └────────────────────────────────────────────────────────────")

        print(f"\n  Bobot Ensemble (dinamis dari CV):")
        for k, v in self.ensemble.weights.items():
            print(f"    {k:12s}: {v:.3f}")

        print("\n  ┌─ CV Metrics (Rolling 3-fold, ~52 minggu/fold) ─────────────────")
        for model in ["prophet", "lgbm", "kalman", "ensemble", "ensemble_v2"]:
            m = cv.get(model, {})
            if not m.get("mape"):
                continue
            mape_avg = np.mean(m["mape"])
            mape_std = np.std(m["mape"])
            dacc_avg = np.mean(m["dacc"])
            marker   = " ✓" if dacc_avg >= 60 else (" !" if dacc_avg >= 55 else "  ")
            label    = "ensemble+Dir" if model == "ensemble_v2" else model
            line     = (f"  │  {label:<14} MAPE={mape_avg:>6.2f}%±{mape_std:.2f}%  "
                        f"DA={dacc_avg:>5.1f}%{marker}")
            if model in ("ensemble", "ensemble_v2") and m.get("coverage"):
                line += f"  | Cov={np.mean(m['coverage']):.1f}%"
            print(line)
        print("  └──────────────────────────────────────────────────────────────────")

        if v1.get("dacc") and v2.get("dacc"):
            d_v1 = np.mean(v1["dacc"])
            d_v2 = np.mean(v2["dacc"])
            m_v1 = np.mean(v1["mape"])
            m_v2 = np.mean(v2["mape"])
            print(f"\n  Improvement vs Ensemble v1:")
            print(f"    DirAcc : {d_v1:.1f}% → {d_v2:.1f}% ({d_v2-d_v1:+.1f}%)")
            print(f"    MAPE   : {m_v1:.2f}% → {m_v2:.2f}% ({m_v2-m_v1:+.2f}%)")

        # Tabel utama — inflasi/deflasi + sinyal
        print(f"\n  Forecast Inflasi/Deflasi — 26 Minggu ke Depan:")
        print(f"  {'Tanggal':<12} {'Harga':>12} {'MoM%':>7} {'YoY%':>7} "
              f"{'Kumulatif%':>11} {'Status Inflasi':<22} {'Sinyal'}")
        print("  " + "-" * 95)

        if self.inflation_df is not None:
            for _, row in self.inflation_df.iterrows():
                print(f"  {str(row['ds'].date()):<12} "
                      f"Rp{row['harga_forecast']:>12,.0f} "
                      f"{row['pct_4w']:>+7.2f}% "
                      f"{row['pct_52w']:>+7.2f}% "
                      f"{row['pct_26w']:>+11.2f}% "
                      f"{str(row['inflation_status']):<22} "
                      f"{row['direction_signal']}")

        # Ringkasan akhir 6 bulan
        if self.inflation_df is not None and len(self.inflation_df) > 0:
            last_row  = self.inflation_df.iloc[-1]
            ann_rate  = last_row["annualized_rate"]
            cum_6m    = last_row["pct_26w"]
            print(f"\n  ┌─ Ringkasan 6 Bulan ke Depan ───────────────────────────────")
            print(f"  │  Harga awal forecast : Rp {self.df['y'].iloc[-1]:>12,.2f}")
            print(f"  │  Harga akhir forecast: Rp {last_row['harga_forecast']:>12,.2f}")
            print(f"  │  Perubahan kumulatif : {cum_6m:+.2f}%")
            print(f"  │  Implied annual rate : {ann_rate:+.2f}% per tahun")
            status_final = last_row["inflation_status"]
            print(f"  │  Kesimpulan          : {status_final}")
            print(f"  └────────────────────────────────────────────────────────────")
        print("=" * 70)

    def get_feature_importance(self):
        fi = self.lgbm.get_feature_importance()
        self.results["feature_importance"] = fi
        return fi

    def save_outputs(self, prefix: str = "output"):
        """Simpan semua output ke CSV."""
        if self.forecast_df is not None:
            path_fc = f"{prefix}_forecast.csv"
            self.forecast_df.to_csv(path_fc, index=False)
            print(f"  [Saved] {path_fc}")

        if self.inflation_df is not None:
            path_inf = f"{prefix}_inflation.csv"
            self.inflation_df.to_csv(path_inf, index=False)
            print(f"  [Saved] {path_inf}")

        if self.hist_inflation is not None:
            path_hist = f"{prefix}_hist_inflation.csv"
            self.hist_inflation.to_csv(path_hist, index=False)
            print(f"  [Saved] {path_hist}")


# ─────────────────────────────────────────────────────────────
#  RUN SEMUA KOMODITAS — loop per komoditas_id
# ─────────────────────────────────────────────────────────────

def run_all_commodities(csv_path: str = CONFIG["csv_path"],
                        komoditas_ids: list = None,
                        run_cv: bool = True,
                        save_output: bool = True) -> dict:
    """
    Jalankan forecaster untuk semua komoditas dan kembalikan dict hasil.

    Parameters:
        csv_path      : path ke file CSV
        komoditas_ids : list ID komoditas yang dijalankan (None = semua)
        run_cv        : apakah jalankan cross-validation
        save_output   : apakah simpan output ke CSV

    Returns:
        dict {komoditas_id: HybridForecasterV3Weekly instance}
    """
    all_df = load_data(csv_path, komoditas_id=None)
    all_ids = sorted(all_df["komoditas_id"].unique())

    if komoditas_ids is not None:
        all_ids = [kid for kid in komoditas_ids if kid in all_ids]

    print(f"\n{'='*70}")
    print(f"  Menjalankan forecaster untuk {len(all_ids)} komoditas: {all_ids}")
    print(f"{'='*70}")

    results = {}

    for kid in all_ids:
        name = KOMODITAS_NAMES.get(kid, f"Komoditas-{kid}")
        print(f"\n{'─'*70}")
        print(f"  >> {name} (ID: {kid})")
        print(f"{'─'*70}")

        try:
            fc = HybridForecasterV3Weekly(CONFIG)
            fc.komoditas_id = kid
            sub = get_single_commodity(all_df[all_df["komoditas_id"] == kid].copy(), kid)

            if len(sub) < 30:
                print(f"  [Skip] Data < 30 minggu, tidak cukup untuk training.")
                continue

            fc.df = sub
            fc.results["komoditas_id"]   = kid
            fc.results["komoditas_name"] = name
            fc.results["train_df"]       = sub
            fc.hist_inflation = compute_historical_inflation(sub)

            if run_cv:
                fc.run_cv()
            fc.fit_all()
            fc.forecast()
            fc.print_summary()

            if save_output:
                prefix = f"output_{kid}_{name.replace(' ', '_')}"
                fc.save_outputs(prefix)

            results[kid] = fc

        except Exception as e:
            import traceback
            print(f"  [Error] Komoditas {kid}: {e}")
            traceback.print_exc()
            continue

    # Ringkasan semua komoditas
    print(f"\n\n{'='*70}")
    print(f"  RINGKASAN INFLASI/DEFLASI SEMUA KOMODITAS (6 Bulan ke Depan)")
    print(f"{'='*70}")
    print(f"  {'Komoditas':<20} {'Harga Terakhir':>15} {'Perubahan 6M':>14} "
          f"{'Annualized':>12} {'Status'}")
    print("  " + "-" * 75)

    for kid, fc in results.items():
        if fc.inflation_df is not None and len(fc.inflation_df) > 0:
            last  = fc.inflation_df.iloc[-1]
            harga = fc.df["y"].iloc[-1]
            name  = KOMODITAS_NAMES.get(kid, f"Komoditas-{kid}")
            print(f"  {name:<20} Rp{harga:>12,.0f} "
                  f"  {last['pct_26w']:>+10.2f}% "
                  f"  {last['annualized_rate']:>+8.2f}%/th "
                  f"  {last['inflation_status']}")

    print("=" * 70)
    return results


# ─────────────────────────────────────────────────────────────
#  MAIN
# ─────────────────────────────────────────────────────────────

if __name__ == "__main__":
    print("=" * 70)
    print("  HYBRID FORECASTER v3 MINGGUAN — Inflasi/Deflasi Komoditas")
    print("=" * 70)

    # ── OPSI 1: Satu komoditas ──
    # Ganti komoditas_id sesuai kebutuhan (13=Beras, 14=Gula, dst)
    TARGET_KOMODITAS = 13

    forecaster = HybridForecasterV3Weekly(CONFIG)
    forecaster.load(
        csv_path     = CONFIG["csv_path"],
        komoditas_id = TARGET_KOMODITAS,
    )
    forecaster.run_cv()
    forecaster.fit_all()
    forecaster.forecast()
    forecaster.print_summary()

    fi = forecaster.get_feature_importance()
    if not fi.empty:
        print(f"\n  Top 10 Fitur LGBM Direct:")
        print(fi.head(10).to_string(index=False))

    forecaster.save_outputs(prefix=f"output_{TARGET_KOMODITAS}")

    # ── OPSI 2: Semua komoditas sekaligus ──
    # Uncomment baris di bawah untuk jalankan semua
    # all_results = run_all_commodities(
    #     csv_path      = CONFIG["csv_path"],
    #     komoditas_ids = None,    # None = semua, atau [13, 14, 15]
    #     run_cv        = True,
    #     save_output   = True,
    # )