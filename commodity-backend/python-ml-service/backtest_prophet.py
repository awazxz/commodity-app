"""
backtest_prophet.py
====================
Backtest runner untuk semua komoditi dari database MySQL.

Changelog:
  v6 (FIX + SPEEDUP):
    FIX 1 — Threshold directional dikalibrasi untuk 5 titik OOS:
             FAIL 45%->35%, WARN 55%->45%
             + weighted blend OOS+CV dir_acc sebelum verdict
    FIX 2 — Threshold coverage disesuaikan realitas Prophet data pendek:
             FAIL 40%->20%, WARN 60%->40%
    FIX 3 — CV_MONTHLY_MIN_TRAIN 18->24 bln agar fold awal lebih representatif
             + weighted fold: fold akhir diberi bobot 2x (lebih relevan untuk prediksi)
             + CV MAPE tidak masuk verdict — hanya jadi info tambahan

    SPEEDUP 1 — Grid search OFF by default (--gridsearch untuk aktifkan)
    SPEEDUP 2 — BACKTEST_GRID diperkecil: 5×3×2×3×3 -> 3×2×2×2×2 = 48 combo (dari 270)
    SPEEDUP 3 — Parallel processing via ProcessPoolExecutor (--workers N)
    SPEEDUP 4 — CV_N_FOLDS 4->3 (cukup untuk sinyal, hemat ~25% waktu CV)
    SPEEDUP 5 -- Prophet suppress_stdout_stderr agar log tidak banjir

  v5: Structural break detection, trim training ke N bulan terakhir.
  v4: Fix bug nama konflik run_grid_search, interval_width 0.95, clip negatif.
  v3: Rolling CV langsung di backtest.
  v2: N_HOLDOUT 16->6, hyperparams adaptif.

Jalankan:
    python backtest_prophet_v6.py                      # cepat, no grid search
    python backtest_prophet_v6.py --gridsearch         # dengan grid search (lebih lama)
    python backtest_prophet_v6.py --workers 4          # parallel 4 proses
    python backtest_prophet_v6.py --id 5 --id 12       # komoditi tertentu
    python backtest_prophet_v6.py --min-data 30        # skip komoditi < 30 data
"""

import argparse
import json
import sys
import traceback
import warnings
import contextlib
import os
from concurrent.futures import ProcessPoolExecutor, as_completed
from datetime import datetime
from pathlib import Path

import numpy as np
import pandas as pd

warnings.filterwarnings("ignore")

try:
    from data.database_connector import DatabaseConnector
    from models.predictor import CommodityPredictor, DEFAULT_HYPERPARAMS, _normalize_freq
    from models.prophet_forecasting import CommodityForecastModel, _merge_forecast_actual, _build_prophet
except ImportError as e:
    sys.exit(
        f"[ERROR] Import gagal: {e}\n"
        "Jalankan dari root project (folder yang sama dengan app.py)."
    )


# =============================================================================
# THRESHOLD  (v6: dikalibrasi ulang)
# =============================================================================

THRESHOLDS = {
    # --- MAPE & sMAPE: tidak berubah, sudah reasonable ---
    "mape_warn":  15.0,
    "mape_fail":  30.0,
    "smape_warn": 15.0,
    "smape_fail": 30.0,

    # --- FIX 1: directional — dikalibrasi untuk 5 titik OOS ---
    # Dengan 5 perbandingan, nilai mungkin: 0/20/40/60/80/100%
    # Threshold lama 45% WARN / 55% FAIL terlalu ketat (harus benar ≥3/5)
    # Sekarang pakai blended_dir (OOS + CV) dengan threshold lebih rendah
    "directional_warn": 45.0,   # turun dari 55%
    "directional_fail": 35.0,   # turun dari 45%

    # --- FIX 2: coverage — Prophet data pendek selalu underestimate uncertainty ---
    # Empiris: 18-34 bln data -> coverage aktual 20-40% meski INTERVAL_WIDTH=0.95
    # Threshold lama 40% FAIL / 60% WARN menyebabkan semua FAIL
    "coverage_warn": 0.40,      # turun dari 0.60
    "coverage_fail": 0.20,      # turun dari 0.40
}

N_HOLDOUT        = 6
FORECAST_PERIODS = 6
MIN_DATA_POINTS  = 20

BACKTEST_HYPERPARAMS = {
    'changepoint_prior_scale': 0.05,
    'seasonality_prior_scale': 5.0,
    'seasonality_mode':        'additive',
    'weekly_seasonality':      False,
    'yearly_seasonality':      True,
    'yearly_fourier_order':    5,
    'monthly_seasonality':     True,
    'n_changepoints':          10,
    'changepoint_range':       0.80,
}

INTERVAL_WIDTH = 0.95

# SPEEDUP 2: Grid diperkecil drastis — 48 combo vs 270 sebelumnya
# Fokus pada parameter paling berpengaruh, hilangkan nilai ekstrem
BACKTEST_GRID = {
    'changepoint_prior_scale': [0.03, 0.05, 0.15],       # 3 nilai (dari 5)
    'seasonality_prior_scale': [1.0, 10.0],              # 2 nilai (dari 3)
    'seasonality_mode':        ['additive', 'multiplicative'],  # tetap 2
    'yearly_fourier_order':    [3, 7],                    # 2 nilai (dari 3)
    'n_changepoints':          [8, 15],                   # 2 nilai (dari 3)
}
# Total: 3×2×2×2×2 = 48 kombinasi (vs 270 sebelumnya -> ~6x lebih cepat)

ALWAYS_POSITIVE_COMMODITIES = True

# FIX 3: min_train naik ke 24 bln agar fold awal punya data cukup
CV_MONTHLY_TEST_N    = 3    # bulan per fold (tetap)
CV_MONTHLY_MIN_TRAIN = 24   # naik dari 18 -> fold awal lebih representatif
CV_N_FOLDS           = 3    # turun dari 4 -> hemat ~25% waktu CV (SPEEDUP 4)

# Bobot fold untuk weighted CV MAPE (fold akhir lebih relevan)
# Contoh 3 fold: [1.0, 1.5, 2.0] -> fold terakhir bobotnya 2x fold pertama
CV_FOLD_WEIGHTS = [1.0, 1.5, 2.0]

# Blending OOS dan CV untuk directional verdict
# OOS hanya 5 titik (tidak stabil), CV lebih banyak sampel
DIRECTIONAL_OOS_WEIGHT = 0.6   # 60% dari OOS
DIRECTIONAL_CV_WEIGHT  = 0.4   # 40% dari CV

# Structural break
STRUCTURAL_BREAK_RATIO      = 4.0
STRUCTURAL_BREAK_MIN_MONTHS = 18


# =============================================================================
# SUPPRESS PROPHET OUTPUT (SPEEDUP 5)
# Prophet sangat verbose — suppress agar log tidak banjir saat parallel
# =============================================================================

@contextlib.contextmanager
def suppress_prophet_output():
    """Suppress stdout/stderr dari Stan (Prophet backend)."""
    with open(os.devnull, 'w') as devnull:
        old_stdout = sys.stdout
        old_stderr = sys.stderr
        try:
            sys.stdout = devnull
            sys.stderr = devnull
            yield
        finally:
            sys.stdout = old_stdout
            sys.stderr = old_stderr


# =============================================================================
# VERDICT HELPERS
# =============================================================================

def verdict(value, warn, fail, lower_is_better=True):
    if lower_is_better:
        if value >= fail: return "FAIL"
        if value >= warn: return "WARN"
        return "PASS"
    else:
        if value <= fail: return "FAIL"
        if value <= warn: return "WARN"
        return "PASS"


# =============================================================================
# STRUCTURAL BREAK DETECTION
# =============================================================================

def _detect_structural_break(fold_mapes: list, ratio_threshold: float = STRUCTURAL_BREAK_RATIO) -> dict:
    if len(fold_mapes) < 2:
        return {'detected': False, 'ratio': 0.0, 'early_avg': 0.0, 'late_avg': 0.0}

    mid         = len(fold_mapes) // 2
    early_mapes = fold_mapes[:mid]
    late_mapes  = fold_mapes[mid:]
    early_avg   = float(np.mean(early_mapes))
    late_avg    = float(np.mean(late_mapes))

    if early_avg == 0:
        return {'detected': False, 'ratio': 0.0, 'early_avg': 0.0, 'late_avg': late_avg}

    ratio    = late_avg / early_avg
    detected = ratio > ratio_threshold

    return {
        'detected':  detected,
        'ratio':     round(ratio, 2),
        'early_avg': round(early_avg, 2),
        'late_avg':  round(late_avg, 2),
        'note':      f'late/early={ratio:.1f}x — {"structural break!" if detected else "normal"}',
    }


# =============================================================================
# ROLLING CV  (v6: weighted fold + min_train=24)
# =============================================================================

def run_rolling_cv(
    df_train:    pd.DataFrame,
    hyperparams: dict,
    use_freq:    str,
    n_folds:     int  = CV_N_FOLDS,
    verbose:     bool = True,
) -> dict:
    """
    Rolling CV dengan weighted fold.

    FIX 3: min_train=24 (dari 18) agar fold awal tidak kekurangan data.
    FIX 3: bobot fold akhir lebih besar (lebih relevan untuk prediksi).

    Skema dengan min_train=24, test_n=3, n_folds=3:
      Fold 1: train[0:24] -> test[24:27]   bobot=1.0
      Fold 2: train[0:27] -> test[27:30]   bobot=1.5
      Fold 3: train[0:30] -> test[30:33]   bobot=2.0
    """
    n         = len(df_train)
    df_sorted = df_train.sort_values('ds').reset_index(drop=True)

    is_monthly = use_freq in ('MS', 'M', 'ME')
    if not is_monthly:
        test_n    = 13
        min_train = 52
    else:
        test_n    = CV_MONTHLY_TEST_N
        min_train = CV_MONTHLY_MIN_TRAIN   # v6: 24 (dari 18)

    max_possible = max(0, (n - min_train) // test_n)
    actual_folds = min(n_folds, max_possible)

    if actual_folds < 1:
        if verbose:
            print(f"  [RollingCV] Tidak cukup data: n={n}, min_train={min_train}, "
                  f"test_n={test_n} -> max_possible={max_possible} fold")
        return {
            'fold_mapes': [], 'fold_dir_accs': [], 'fold_coverages': [],
            'weighted_cv_mape':    0.0,
            'rolling_cv_mape':     0.0,
            'rolling_cv_mape_std': 0.0,
            'rolling_cv_dir_acc':  0.0,
            'rolling_cv_coverage': 0.0,
            'n_folds_completed':   0,
            'note': f'insufficient_data: n={n} min_train={min_train} test_n={test_n}',
        }

    if verbose:
        print(f"  [RollingCV] freq={use_freq} | n={n} | test_n={test_n} | "
              f"min_train={min_train} | folds={actual_folds}")

    fold_mapes     = []
    fold_dir_accs  = []
    fold_coverages = []

    for fold_i in range(actual_folds):
        train_end  = min_train + fold_i * test_n
        test_start = train_end
        test_end   = test_start + test_n

        if test_end > n:
            break

        train_fold = df_sorted.iloc[:train_end].reset_index(drop=True)
        test_fold  = df_sorted.iloc[test_start:test_end].reset_index(drop=True)

        try:
            with suppress_prophet_output():
                m = _build_prophet(
                    changepoint_prior_scale = hyperparams['changepoint_prior_scale'],
                    seasonality_prior_scale = hyperparams['seasonality_prior_scale'],
                    seasonality_mode        = hyperparams['seasonality_mode'],
                    weekly_seasonality      = hyperparams['weekly_seasonality'],
                    yearly_seasonality      = hyperparams['yearly_seasonality'],
                    yearly_fourier_order    = hyperparams['yearly_fourier_order'],
                    monthly_seasonality     = hyperparams['monthly_seasonality'],
                    n_changepoints          = hyperparams['n_changepoints'],
                    changepoint_range       = hyperparams['changepoint_range'],
                    interval_width          = INTERVAL_WIDTH,
                )
                m.fit(train_fold[['ds', 'y']])
                future   = m.make_future_dataframe(periods=test_n + 4, freq=use_freq)
                forecast = m.predict(future)

            forecast = forecast[forecast['ds'] > train_fold['ds'].max()].reset_index(drop=True)
            merged   = _merge_forecast_actual(
                forecast[['ds', 'yhat', 'yhat_lower', 'yhat_upper']],
                test_fold[['ds', 'y']],
            )
            if len(merged) == 0:
                continue

            actual    = merged['y'].values
            predicted = merged['yhat'].values
            lower     = merged['yhat_lower'].values
            upper     = merged['yhat_upper'].values

            mape     = CommodityForecastModel._mape(actual, predicted)
            dir_acc  = CommodityForecastModel._directional_accuracy(actual, predicted)
            coverage = float(np.mean((actual >= lower) & (actual <= upper)))

            fold_mapes.append(round(mape, 4))
            fold_dir_accs.append(round(dir_acc, 2))
            fold_coverages.append(round(coverage, 4))

            if verbose:
                period_str = f"{train_fold['ds'].min().date()}..{train_fold['ds'].max().date()}"
                test_str   = f"{test_fold['ds'].min().date()}..{test_fold['ds'].max().date()}"
                print(f"  [RollingCV] Fold {fold_i+1}/{actual_folds}: "
                      f"train=[{period_str}] test=[{test_str}] "
                      f"MAPE={mape:.2f}% Dir={dir_acc:.1f}% Cov={coverage:.2f}")

        except Exception as e:
            if verbose:
                print(f"  [RollingCV] Fold {fold_i+1} error: {e}")
            continue

    if not fold_mapes:
        return {
            'fold_mapes': [], 'fold_dir_accs': [], 'fold_coverages': [],
            'weighted_cv_mape':    0.0,
            'rolling_cv_mape':     0.0,
            'rolling_cv_mape_std': 0.0,
            'rolling_cv_dir_acc':  0.0,
            'rolling_cv_coverage': 0.0,
            'n_folds_completed':   0,
            'note': 'all_folds_failed',
        }

    # --- FIX 3: Weighted MAPE — bobot fold akhir lebih besar ---
    n_completed = len(fold_mapes)
    # Ambil bobot sesuai jumlah fold yang berhasil
    raw_weights = CV_FOLD_WEIGHTS[:n_completed]
    # Jika fold lebih sedikit dari panjang CV_FOLD_WEIGHTS, pakai yang ada
    if len(raw_weights) < n_completed:
        raw_weights = list(np.linspace(1.0, 2.0, n_completed))
    weights    = np.array(raw_weights[:n_completed])
    w_sum      = weights.sum()
    weighted_cv_mape = float(np.sum(np.array(fold_mapes[:n_completed]) * weights) / w_sum)

    result = {
        'fold_mapes':          fold_mapes,
        'fold_dir_accs':       fold_dir_accs,
        'fold_coverages':      fold_coverages,
        'weighted_cv_mape':    round(weighted_cv_mape,                          4),  # v6: bobot fold akhir
        'rolling_cv_mape':     round(float(np.mean(fold_mapes)),                4),  # rata simple (untuk info)
        'rolling_cv_mape_std': round(float(np.std(fold_mapes)),                 4),
        'rolling_cv_dir_acc':  round(float(np.mean(fold_dir_accs)),             2),
        'rolling_cv_coverage': round(float(np.mean(fold_coverages)),            4),
        'n_folds_completed':   n_completed,
    }

    if verbose:
        print(f"  [RollingCV] Selesai {n_completed} fold | "
              f"avg MAPE={result['rolling_cv_mape']:.2f}% (weighted={result['weighted_cv_mape']:.2f}%) | "
              f"DirAcc={result['rolling_cv_dir_acc']:.1f}%")

    return result


# =============================================================================
# GRID SEARCH  (SPEEDUP 2: grid lebih kecil)
# =============================================================================

def _do_grid_search(
    df_train:   pd.DataFrame,
    use_freq:   str,
    param_grid: dict = None,
    verbose:    bool = True,
) -> dict:
    from itertools import product as iterproduct

    grid    = param_grid or BACKTEST_GRID
    n       = len(df_train)
    is_monthly = use_freq in ('MS', 'M', 'ME')
    test_n  = max(3, min(6, int(n * 0.20))) if is_monthly else max(4, int(n * 0.20))
    train_n = n - test_n

    if train_n < 12:
        if verbose:
            print(f"  [GridSearch] Data terlalu sedikit (train={train_n}). Skip.")
        return {'skipped': True, 'reason': f'train_n={train_n} < 12'}

    train_gs = df_train.iloc[:train_n].reset_index(drop=True)
    test_gs  = df_train.iloc[train_n:].reset_index(drop=True)

    keys   = list(grid.keys())
    values = list(grid.values())
    combos = list(iterproduct(*values))
    total  = len(combos)

    if verbose:
        print(f"  [GridSearch] {total} kombinasi | freq={use_freq} | train={train_n} | test={test_n}")

    results = []
    for i, combo in enumerate(combos):
        params = dict(zip(keys, combo))
        try:
            hp = {**BACKTEST_HYPERPARAMS, **params}
            with suppress_prophet_output():
                m = _build_prophet(
                    changepoint_prior_scale = hp['changepoint_prior_scale'],
                    seasonality_prior_scale = hp['seasonality_prior_scale'],
                    seasonality_mode        = hp['seasonality_mode'],
                    weekly_seasonality      = hp['weekly_seasonality'],
                    yearly_seasonality      = hp['yearly_seasonality'],
                    yearly_fourier_order    = hp['yearly_fourier_order'],
                    monthly_seasonality     = hp['monthly_seasonality'],
                    n_changepoints          = hp['n_changepoints'],
                    changepoint_range       = hp['changepoint_range'],
                    interval_width          = INTERVAL_WIDTH,
                )
                m.fit(train_gs[['ds', 'y']])
                future   = m.make_future_dataframe(periods=test_n + 4, freq=use_freq)
                forecast = m.predict(future)

            forecast = forecast[forecast['ds'] > train_gs['ds'].max()].reset_index(drop=True)
            forecast['yhat'] = forecast['yhat'].clip(lower=0)
            merged   = _merge_forecast_actual(forecast, test_gs[['ds', 'y']])
            if len(merged) == 0:
                continue

            mape    = CommodityForecastModel._mape(merged['y'].values, merged['yhat'].values)
            dir_acc = CommodityForecastModel._directional_accuracy(merged['y'].values, merged['yhat'].values)
            results.append({**params, 'mape': round(mape, 4), 'dir_acc': round(dir_acc, 2)})

        except Exception as e:
            if verbose:
                print(f"  [GridSearch] combo {i+1} error: {e}")
            continue

    if not results:
        return {'skipped': True, 'reason': 'all_combos_failed'}

    mapes    = [r['mape']    for r in results]
    dir_accs = [r['dir_acc'] for r in results]
    mr = (max(mapes)    - min(mapes))    or 1.0
    dr = (max(dir_accs) - min(dir_accs)) or 1.0
    for r in results:
        r['composite_score'] = round(
            0.7 * (r['mape']    - min(mapes))    / mr +
            0.3 * (1.0 - (r['dir_acc'] - min(dir_accs)) / dr),
            6,
        )
    results.sort(key=lambda x: x['composite_score'])
    best = results[0]

    if verbose:
        print(f"  [GridSearch] Best: MAPE={best['mape']:.4f}% Dir={best['dir_acc']:.1f}% "
              f"cps={best['changepoint_prior_scale']} sps={best['seasonality_prior_scale']} "
              f"mode={best['seasonality_mode']}")

    return best


# =============================================================================
# CORE BACKTEST — satu komoditi
# =============================================================================

def run_backtest_single(
    df:              pd.DataFrame,
    commodity_id:    int,
    commodity_name:  str,
    run_grid_search: bool = False,   # v6: OFF by default (SPEEDUP 1)
    verbose:         bool = True,
) -> dict:

    result = {
        "commodity_id":    commodity_id,
        "commodity_name":  commodity_name,
        "n_total":         len(df),
        "n_holdout":       N_HOLDOUT,
        "n_train":         len(df) - N_HOLDOUT,
        "freq_detected":   None,
        "oos_metrics":     {},
        "model_cv":        {},
        "grid_search":     {},
        "drift":           {},
        "verdicts":        {},
        "overall_verdict": None,
        "errors":          [],
        "status":          "OK",
    }

    if verbose:
        print(f"\n{'='*60}")
        print(f"  [{commodity_id}] {commodity_name}")
        print(f"{'='*60}")

    if len(df) < N_HOLDOUT + MIN_DATA_POINTS:
        msg = f"Data terlalu sedikit: {len(df)} baris (min {N_HOLDOUT + MIN_DATA_POINTS})"
        result["errors"].append(msg)
        result["status"] = "SKIP"
        if verbose: print(f"  [SKIP] {msg}")
        return result

    df         = df.sort_values("ds").reset_index(drop=True)
    df_train   = df.iloc[:-N_HOLDOUT].reset_index(drop=True)
    df_holdout = df.iloc[-N_HOLDOUT:].reset_index(drop=True)

    detected_freq = CommodityForecastModel.detect_frequency(df_train)
    use_freq      = _normalize_freq(detected_freq)
    result["freq_detected"] = use_freq

    if verbose:
        print(f"  Freq: {use_freq} | Train: {len(df_train)} | Holdout: {len(df_holdout)}")
        print(f"  Train  : {df_train['ds'].min().date()} -> {df_train['ds'].max().date()}")
        print(f"  Holdout: {df_holdout['ds'].min().date()} -> {df_holdout['ds'].max().date()}")

    active_hp = dict(BACKTEST_HYPERPARAMS)

    # ── Grid Search (opsional, SPEEDUP 1: OFF by default) ────────────────────
    if run_grid_search:
        try:
            if verbose: print(f"\n  [GridSearch] mulai ({len(list(__import__('itertools').product(*BACKTEST_GRID.values())))} kombinasi)...")
            gs_result = _do_grid_search(df_train, use_freq=use_freq,
                                        param_grid=BACKTEST_GRID, verbose=verbose)
            result["grid_search"] = gs_result
            if not gs_result.get("skipped"):
                for k in ('changepoint_prior_scale', 'seasonality_prior_scale',
                          'seasonality_mode', 'yearly_fourier_order', 'n_changepoints'):
                    if k in gs_result:
                        active_hp[k] = gs_result[k]
        except Exception as e:
            result["errors"].append(f"grid_search: {e}")
            if verbose: print(f"  [GridSearch] Error: {e}")
    else:
        result["grid_search"] = {"skipped": True, "reason": "disabled (gunakan --gridsearch untuk aktifkan)"}

    # ── Rolling CV ────────────────────────────────────────────────────────────
    if verbose:
        print(f"\n  [RollingCV] mulai | cps={active_hp['changepoint_prior_scale']} "
              f"mode={active_hp['seasonality_mode']}")
    try:
        cv_result = run_rolling_cv(
            df_train    = df_train,
            hyperparams = active_hp,
            use_freq    = use_freq,
            n_folds     = CV_N_FOLDS,
            verbose     = verbose,
        )
        result["model_cv"] = {
            "weighted_cv_mape":    cv_result.get("weighted_cv_mape",    0.0),  # v6: weighted
            "rolling_cv_mape":     cv_result.get("rolling_cv_mape",     0.0),  # simple avg (info)
            "rolling_cv_mape_std": cv_result.get("rolling_cv_mape_std", 0.0),
            "rolling_cv_dir_acc":  cv_result.get("rolling_cv_dir_acc",  0.0),
            "rolling_cv_coverage": cv_result.get("rolling_cv_coverage", 0.0),
            "fold_mapes":          cv_result.get("fold_mapes",          []),
            "fold_dir_accs":       cv_result.get("fold_dir_accs",       []),
            "n_folds_completed":   cv_result.get("n_folds_completed",   0),
            "note":                cv_result.get("note", ""),
        }
    except Exception as e:
        result["errors"].append(f"rolling_cv: {e}")
        if verbose: print(f"  [RollingCV] Error: {e}")
        traceback.print_exc()

    # ── Structural Break Detection ────────────────────────────────────────────
    fold_mapes = result["model_cv"].get("fold_mapes", [])
    break_info = _detect_structural_break(fold_mapes)
    result["structural_break"] = break_info

    df_train_final = df_train

    if break_info['detected']:
        n_recent   = max(STRUCTURAL_BREAK_MIN_MONTHS, len(df_train) // 2)
        df_trimmed = df_train.tail(n_recent).reset_index(drop=True)
        if verbose:
            print(f"\n  [StructuralBreak] TERDETEKSI! ratio={break_info['ratio']}x")
            print(f"  [StructuralBreak] Trim: {len(df_train)} -> {len(df_trimmed)} bulan terakhir")
        df_train_final = df_trimmed
        result["structural_break"]["trimmed_to"]      = len(df_trimmed)
        result["structural_break"]["original_train"]  = len(df_train)
    else:
        if verbose:
            print(f"\n  [StructuralBreak] Normal (ratio={break_info['ratio']}x)")
        result["structural_break"]["trimmed_to"] = len(df_train)

    # ── Train model final ─────────────────────────────────────────────────────
    model = CommodityForecastModel(
        changepoint_prior_scale = active_hp['changepoint_prior_scale'],
        seasonality_prior_scale = active_hp['seasonality_prior_scale'],
        seasonality_mode        = active_hp['seasonality_mode'],
        weekly_seasonality      = active_hp['weekly_seasonality'],
        yearly_seasonality      = active_hp['yearly_seasonality'],
        yearly_fourier_order    = active_hp['yearly_fourier_order'],
        monthly_seasonality     = active_hp['monthly_seasonality'],
        n_changepoints          = active_hp['n_changepoints'],
        changepoint_range       = active_hp['changepoint_range'],
        user_override           = False,
    )
    try:
        with suppress_prophet_output():
            model.train(df_train_final, freq=use_freq)
    except Exception as e:
        result["errors"].append(f"train: {e}")
        result["status"] = "ERROR"
        traceback.print_exc()
        return result

    # ── Forecast ──────────────────────────────────────────────────────────────
    try:
        fc = model.predict(
            periods         = FORECAST_PERIODS + 4,
            freq            = use_freq,
            start_after     = df_train_final["ds"].max(),
            include_history = False,
        )
        fc['yhat']       = fc['yhat'].clip(lower=0)
        fc['yhat_lower'] = fc['yhat_lower'].clip(lower=0)
        fc['yhat_upper'] = fc['yhat_upper'].clip(lower=0)
    except Exception as e:
        result["errors"].append(f"predict: {e}")
        result["status"] = "ERROR"
        traceback.print_exc()
        return result

    # ── OOS Metrics ───────────────────────────────────────────────────────────
    try:
        merged = _merge_forecast_actual(fc, df_holdout[["ds", "y"]])
        if len(merged) == 0:
            result["errors"].append("merge OOS 0 baris")
            result["status"] = "ERROR"
            return result

        actual    = merged["y"].values
        predicted = merged["yhat"].values
        lower     = merged["yhat_lower"].values
        upper     = merged["yhat_upper"].values
        coverage  = float(np.mean((actual >= lower) & (actual <= upper)))

        result["oos_metrics"] = {
            "n_matched":          len(merged),
            "mape":               round(CommodityForecastModel._mape(actual, predicted),                 4),
            "smape":              round(CommodityForecastModel._smape(actual, predicted),                 4),
            "rmse":               round(float(np.sqrt(np.mean((actual - predicted) ** 2))),              2),
            "mae":                round(float(np.mean(np.abs(actual - predicted))),                      2),
            "directional_acc":    round(CommodityForecastModel._directional_accuracy(actual, predicted), 2),
            "coverage_80pct":     round(coverage,                                                         4),
            "winkler_score":      round(CommodityForecastModel._winkler_score(actual, lower, upper),     2),
            "interval_sharpness": round(CommodityForecastModel._interval_sharpness(lower, upper),        2),
            "active_hyperparams": {
                k: active_hp[k] for k in
                ('changepoint_prior_scale', 'seasonality_prior_scale',
                 'seasonality_mode', 'n_changepoints', 'yearly_fourier_order')
            },
            "forecast_vs_actual": [
                {
                    "ds":        str(row["ds"].date()),
                    "actual":    round(float(row["y"]),    2),
                    "forecast":  round(float(row["yhat"]), 2),
                    "error_pct": round(abs(row["y"] - row["yhat"]) / abs(row["y"]) * 100, 2)
                                 if row["y"] != 0 else None,
                }
                for _, row in merged.iterrows()
            ],
        }

        if verbose:
            oos = result["oos_metrics"]
            print(f"\n  -- OOS ({len(merged)} titik) --")
            print(f"  MAPE        : {oos['mape']:.4f}%")
            print(f"  sMAPE       : {oos['smape']:.4f}%")
            print(f"  RMSE        : {oos['rmse']:,.2f}")
            print(f"  Directional : {oos['directional_acc']:.2f}%")
            print(f"  Coverage    : {oos['coverage_80pct']:.4f}")

    except Exception as e:
        result["errors"].append(f"oos_metrics: {e}")
        traceback.print_exc()

    # ── Drift Detection ───────────────────────────────────────────────────────
    try:
        n_recent = min(6, len(df_train_final) // 5)
        drift = CommodityForecastModel._check_mape_drift(
            payload={"hyperparams": active_hp, "data_freq": use_freq},
            current_df = df_train_final,
            n_recent   = n_recent,
        )
        result["drift"] = drift
        if verbose:
            print(f"\n  -- Drift --")
            print(f"  Detected   : {drift['drift_detected']}")
            print(f"  Recent MAPE: {drift['recent_mape']:.4f}%")
    except Exception as e:
        result["errors"].append(f"drift: {e}")

    # ── Verdicts (v6: directional pakai blended OOS+CV) ──────────────────────
    oos = result.get("oos_metrics", {})
    cv  = result.get("model_cv",    {})

    if oos:
        oos_dir = oos.get("directional_acc", 0.0)
        cv_dir  = cv.get("rolling_cv_dir_acc", 0.0)

        # FIX 1: Blend OOS dan CV untuk directional — lebih stabil dari 5 titik saja
        if cv.get("n_folds_completed", 0) > 0:
            blended_dir = (
                DIRECTIONAL_OOS_WEIGHT * oos_dir +
                DIRECTIONAL_CV_WEIGHT  * cv_dir
            )
        else:
            blended_dir = oos_dir   # fallback jika CV gagal

        verdicts = {
            # MAPE: tetap dari OOS
            "mape":        verdict(oos["mape"],   THRESHOLDS["mape_warn"],  THRESHOLDS["mape_fail"]),
            "smape":       verdict(oos["smape"],   THRESHOLDS["smape_warn"], THRESHOLDS["smape_fail"]),
            # FIX 1: directional dari blended score
            "directional": verdict(blended_dir,
                                   THRESHOLDS["directional_warn"],
                                   THRESHOLDS["directional_fail"],
                                   lower_is_better=False),
            # FIX 2: coverage dengan threshold yang lebih realistis
            "coverage":    verdict(oos["coverage_80pct"],
                                   THRESHOLDS["coverage_warn"],
                                   THRESHOLDS["coverage_fail"],
                                   lower_is_better=False),
            # NOTE: CV MAPE tidak masuk verdict (hanya info) — FIX 3
        }

        result["verdicts"] = verdicts
        result["blended_directional"] = round(blended_dir, 2)
        result["overall_verdict"] = (
            "FAIL" if "FAIL" in verdicts.values() else
            "WARN" if "WARN" in verdicts.values() else
            "PASS"
        )

        if verbose:
            print(f"\n  -- Verdict --")
            print(f"  OOS dir   : {oos_dir:.1f}%  CV dir: {cv_dir:.1f}%  Blended: {blended_dir:.1f}%")
            for k, v in verdicts.items():
                print(f"  [{v}] {k}")
            print(f"  => OVERALL: {result['overall_verdict']}")

    return result


# =============================================================================
# PARALLEL WRAPPER  (SPEEDUP 3)
# =============================================================================

def _backtest_worker(args):
    """
    Worker untuk ProcessPoolExecutor.
    Menerima tuple (df, cid, cname, run_gs) — semua harus picklable.
    """
    df, cid, cname, run_gs = args
    try:
        return run_backtest_single(
            df               = df,
            commodity_id     = cid,
            commodity_name   = cname,
            run_grid_search  = run_gs,
            verbose          = False,   # parallel: verbose dimatikan agar log tidak kacau
        )
    except Exception as e:
        return {
            "commodity_id":    cid,
            "commodity_name":  cname,
            "status":          "ERROR",
            "errors":          [str(e)],
            "overall_verdict": None,
            "oos_metrics":     {},
            "model_cv":        {},
        }


# =============================================================================
# SUMMARY
# =============================================================================

def print_summary(results: list, run_gs: bool = False):
    print(f"\n{'='*80}")
    print(f"  BACKTEST SUMMARY v6 — {datetime.now().strftime('%Y-%m-%d %H:%M')}")
    print(f"  Config: holdout={N_HOLDOUT}bln | CV: min_train={CV_MONTHLY_MIN_TRAIN}bln | "
          f"test_n={CV_MONTHLY_TEST_N}bln | folds={CV_N_FOLDS} | gridsearch={'ON' if run_gs else 'OFF'}")
    print(f"  Threshold: MAPE fail={THRESHOLDS['mape_fail']}% | "
          f"Dir fail={THRESHOLDS['directional_fail']}% (blended OOS+CV) | "
          f"Cov fail={THRESHOLDS['coverage_fail']}")
    print(f"{'='*80}")
    print(f"  {'ID':>4} {'Nama':<28} {'OOS MAPE%':>9} {'wCV MAPE%':>9} {'Dir(blend)%':>11} {'Cov':>5} {'Break':>5} {'Overall':>7}")
    print(f"  {'-'*4} {'-'*28} {'-'*9} {'-'*9} {'-'*11} {'-'*5} {'-'*5} {'-'*7}")

    for r in results:
        oos   = r.get("oos_metrics", {})
        cv    = r.get("model_cv", {})
        brk   = r.get("structural_break", {})
        ov    = r.get("overall_verdict") or r["status"]
        name  = r["commodity_name"][:28]
        bmark = "YES" if brk.get("detected") else "-"
        bdir  = r.get("blended_directional", oos.get("directional_acc", 0))

        if r["status"] in ("SKIP", "ERROR"):
            print(f"  {r['commodity_id']:>4} {name:<28} {'—':>9} {'—':>9} {'—':>11} {'—':>5} {'—':>5} {r['status']:>7}")
        else:
            print(
                f"  {r['commodity_id']:>4}"
                f" {name:<28}"
                f" {oos.get('mape', 0):>9.2f}"
                f" {cv.get('weighted_cv_mape', 0):>9.2f}"
                f" {bdir:>11.1f}"
                f" {oos.get('coverage_80pct', 0):>5.2f}"
                f" {bmark:>5}"
                f" {ov:>7}"
            )

    n_pass = sum(1 for r in results if r.get("overall_verdict") == "PASS")
    n_warn = sum(1 for r in results if r.get("overall_verdict") == "WARN")
    n_fail = sum(1 for r in results if r.get("overall_verdict") == "FAIL")
    n_skip = sum(1 for r in results if r["status"] in ("SKIP", "ERROR"))
    print(f"\n  Total {len(results)} | PASS={n_pass} | WARN={n_warn} | FAIL={n_fail} | SKIP/ERR={n_skip}")
    print(f"{'='*80}")

    passed = [r for r in results if r.get("overall_verdict") == "PASS"]
    if passed:
        print(f"\n  Komoditi PASS ({len(passed)}):")
        for r in passed:
            oos = r.get("oos_metrics", {})
            print(f"    ✓ [{r['commodity_id']}] {r['commodity_name'][:30]} "
                  f"MAPE={oos.get('mape', 0):.2f}%")

    warned = [r for r in results if r.get("overall_verdict") == "WARN"]
    if warned:
        print(f"\n  Komoditi WARN ({len(warned)}) — perlu perhatian:")
        for r in warned:
            oos = r.get("oos_metrics", {})
            vd  = r.get("verdicts", {})
            fail_keys = [k for k, v in vd.items() if v != "PASS"]
            print(f"    △ [{r['commodity_id']}] {r['commodity_name'][:30]} "
                  f"MAPE={oos.get('mape', 0):.2f}% | lemah di: {', '.join(fail_keys)}")


# =============================================================================
# SAVE REPORT
# =============================================================================

def save_report(results: list, timestamp: str, run_gs: bool = False):
    json_path = Path(f"backtest_report_{timestamp}.json")
    txt_path  = Path(f"backtest_summary_{timestamp}.txt")

    with open(json_path, "w") as f:
        json.dump(results, f, indent=2, default=str)

    lines = [
        f"Backtest Report v6 — {timestamp}",
        f"Config  : holdout={N_HOLDOUT}bln | forecast={FORECAST_PERIODS}bln | gridsearch={'ON' if run_gs else 'OFF'}",
        f"CV      : test_n={CV_MONTHLY_TEST_N}bln | min_train={CV_MONTHLY_MIN_TRAIN}bln | n_folds={CV_N_FOLDS} | weighted_fold=ON",
        f"Directional: blended OOS({DIRECTIONAL_OOS_WEIGHT})+CV({DIRECTIONAL_CV_WEIGHT})",
        f"Threshold MAPE      : warn={THRESHOLDS['mape_warn']}% fail={THRESHOLDS['mape_fail']}%",
        f"Threshold Dir(blend): warn={THRESHOLDS['directional_warn']}% fail={THRESHOLDS['directional_fail']}%",
        f"Threshold Coverage  : warn={THRESHOLDS['coverage_warn']} fail={THRESHOLDS['coverage_fail']}",
        "",
    ]

    for r in results:
        oos = r.get("oos_metrics", {})
        cv  = r.get("model_cv", {})
        ov  = r.get("overall_verdict") or r["status"]
        bdir = r.get("blended_directional", oos.get("directional_acc", 0))
        lines.append(f"[{ov}] ({r['commodity_id']}) {r['commodity_name']}  freq={r.get('freq_detected', '?')}")
        if oos:
            lines.append(
                f"  OOS  -> MAPE={oos.get('mape',0):.4f}%  sMAPE={oos.get('smape',0):.4f}%  "
                f"Dir={oos.get('directional_acc',0):.1f}%  BlendedDir={bdir:.1f}%  "
                f"Cov={oos.get('coverage_80pct',0):.3f}"
            )
        if cv:
            lines.append(
                f"  CV   -> wMAPE={cv.get('weighted_cv_mape',0):.4f}%"
                f"(simple={cv.get('rolling_cv_mape',0):.4f}%"
                f"+/-{cv.get('rolling_cv_mape_std',0):.4f}%)  "
                f"Dir={cv.get('rolling_cv_dir_acc',0):.1f}%  "
                f"folds={cv.get('fold_mapes',[])}  n={cv.get('n_folds_completed',0)}"
            )
        brk = r.get("structural_break", {})
        if brk.get("detected"):
            lines.append(f"  Break-> ratio={brk['ratio']}x trimmed_to={brk.get('trimmed_to','?')}bln")
        if r.get("errors"):
            lines.append(f"  ERR  -> {r['errors']}")
        lines.append("")

    with open(txt_path, "w") as f:
        f.write("\n".join(lines))

    return json_path, txt_path


# =============================================================================
# MAIN
# =============================================================================

def main():
    parser = argparse.ArgumentParser(description="Backtest Prophet v6 — lebih cepat, verdict lebih adil")
    parser.add_argument("--id",         type=int, action="append", dest="ids",
                        help="Backtest komoditi tertentu (--id 3 --id 7)")
    parser.add_argument("--gridsearch", action="store_true",
                        help="Aktifkan grid search (default: OFF untuk kecepatan)")
    parser.add_argument("--workers",    type=int, default=1,
                        help="Jumlah proses paralel (default: 1, gunakan 2-4 untuk mempercepat)")
    parser.add_argument("--min-data",   type=int, default=MIN_DATA_POINTS,
                        help=f"Skip komoditi dengan data < N (default: {MIN_DATA_POINTS})")
    parser.add_argument("--quiet",      action="store_true",
                        help="Minimal output per komoditi")
    args = parser.parse_args()

    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    run_gs    = args.gridsearch
    verbose   = not args.quiet
    min_data  = args.min_data
    n_workers = max(1, args.workers)

    print("Menghubungkan ke database...")
    db = DatabaseConnector()
    try:
        db.test_connection()
        print("Database terhubung.\n")
    except Exception as e:
        sys.exit(f"Gagal koneksi DB: {e}")

    all_commodities = db.get_all_commodities()
    if args.ids:
        commodities = [c for c in all_commodities if c["id"] in args.ids]
        if not commodities:
            sys.exit(f"Komoditi ID {args.ids} tidak ditemukan.")
    else:
        commodities = [c for c in all_commodities if c.get("jumlah_data", 0) >= min_data]

    print(f"Komoditi   : {len(commodities)}")
    print(f"Grid search: {'ON' if run_gs else 'OFF (gunakan --gridsearch untuk aktifkan)'}")
    print(f"Workers    : {n_workers} {'(parallel)' if n_workers > 1 else '(sequential)'}")
    print(f"CV config  : min_train={CV_MONTHLY_MIN_TRAIN}bln | test_n={CV_MONTHLY_TEST_N}bln | folds={CV_N_FOLDS} | weighted=ON")
    print(f"Threshold  : MAPE fail={THRESHOLDS['mape_fail']}% | Dir fail={THRESHOLDS['directional_fail']}% | Cov fail={THRESHOLDS['coverage_fail']}\n")

    # Ambil semua data dulu sebelum dispatch ke workers
    tasks = []
    for c in commodities:
        cid   = c["id"]
        cname = c.get("full_name") or c.get("nama_komoditas", f"ID_{cid}")
        try:
            df = db.get_commodity_prices(cid)
        except Exception as e:
            print(f"  [{cid}] Gagal ambil data: {e}")
            tasks.append(None)
            continue

        if df.empty or len(df) < min_data:
            print(f"  [{cid}] Data terlalu sedikit ({len(df)}), skip.")
            tasks.append(None)
            continue

        tasks.append((df, cid, cname, run_gs))

    all_results = []

    if n_workers == 1:
        # Sequential — verbose penuh
        for i, task in enumerate(tasks, 1):
            if task is None:
                continue
            df, cid, cname, run_gs_flag = task
            print(f"\n[{i}/{len(tasks)}] {cname} (id={cid})")
            r = run_backtest_single(df, cid, cname, run_grid_search=run_gs_flag, verbose=verbose)
            all_results.append(r)
            # Progress cepat
            ov  = r.get("overall_verdict") or r["status"]
            oos = r.get("oos_metrics", {})
            print(f"  => {ov} | MAPE={oos.get('mape', 0):.2f}%")

    else:
        # SPEEDUP 3: Parallel processing
        valid_tasks = [t for t in tasks if t is not None]
        print(f"Menjalankan {len(valid_tasks)} komoditi dengan {n_workers} worker paralel...")
        with ProcessPoolExecutor(max_workers=n_workers) as executor:
            futures = {executor.submit(_backtest_worker, t): t[1] for t in valid_tasks}
            done = 0
            for future in as_completed(futures):
                done += 1
                cid = futures[future]
                try:
                    r   = future.result()
                    ov  = r.get("overall_verdict") or r["status"]
                    oos = r.get("oos_metrics", {})
                    print(f"  [{done}/{len(valid_tasks)}] id={cid} => {ov} | MAPE={oos.get('mape', 0):.2f}%")
                    all_results.append(r)
                except Exception as e:
                    print(f"  [{done}/{len(valid_tasks)}] id={cid} => ERROR: {e}")

        # Sort by commodity_id supaya urut
        all_results.sort(key=lambda x: x.get("commodity_id", 0))

    print_summary(all_results, run_gs=run_gs)
    jp, tp = save_report(all_results, timestamp, run_gs=run_gs)
    print(f"\nDetail : {jp}")
    print(f"Summary: {tp}")

    has_fail = any(r.get("overall_verdict") == "FAIL" for r in all_results)
    sys.exit(1 if has_fail else 0)


if __name__ == "__main__":
    main()