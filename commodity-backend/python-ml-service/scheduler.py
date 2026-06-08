"""
scheduler.py

Background scheduler untuk auto-retraining model Prophet.

Jadwal:
- Setiap hari  jam 02:00 -> retrain komoditas yang butuh update
- Setiap Senin jam 03:00 -> force retrain mingguan (termasuk drift check)
- Setiap hari  jam 04:00 -> async drift check (tidak blocking request)
- Setiap hari  jam 02:30 -> recalculate IHK 3 bulan terakhir
- Setiap Senin jam 03:30 -> forecast IHK 6 bulan ke depan

Changelog v3.2:
  FIX 1: yearly_fourier_order 20 -> 10 (sinkron predictor.py v8.3)
  FIX 2: n_changepoints 25 -> 15 (sinkron predictor.py v8.3)
  FIX 3: Hapus train_hybrid — HybridCommodityModel sudah dihapus di v8.3
  FIX 4: Tambah jadwal IHK (recalculate + forecast) agar IHK selalu fresh

Changelog v3.1 — Hyperparams Sync Fix:
  FIXED: DEFAULT_HYPERPARAMS disinkronkan dengan prophet_forecasting.py v10.2.
"""

import threading
import time
from datetime import datetime

import schedule

from data.database_connector import DatabaseConnector
from models.predictor import CommodityPredictor, _normalize_freq
from models.prophet_forecasting import (
    CommodityForecastModel,
    DriftChecker,
    MIN_DATA_POINTS,
    MAPE_DRIFT_THRESHOLD,
)

# ── Default hyperparameter — SATU SUMBER KEBENARAN ────────────
# WAJIB sinkron dengan:
# - DEFAULT_HYPERPARAMS di predictor.py v8.3
# - _DEFAULT_HP di app.py
# - __init__ default CommodityForecastModel di prophet_forecasting.py v10.3
DEFAULT_HYPERPARAMS = {
    'changepoint_prior_scale': 0.1,
    'seasonality_prior_scale': 10.0,
    'seasonality_mode':        'additive',
    'weekly_seasonality':      False,
    'yearly_seasonality':      True,
    'yearly_fourier_order':    10,   # FIX v3.2: was 20, sinkron predictor.py v8.3
    'monthly_seasonality':     True,
    'n_changepoints':          15,   # FIX v3.2: was 25, sinkron predictor.py v8.3
    'changepoint_range':       0.85,
}

DEFAULT_FORECAST_PERIODS_DAYS = 84

# ── Shared state untuk drift flags (thread-safe via lock) ──────
_drift_lock  = threading.Lock()
_drift_flags = {}


# ═══════════════════════════════════════════════════════════════
# RETRAIN KOMODITAS
# ═══════════════════════════════════════════════════════════════

def retrain_all_commodities(force: bool = False):
    """
    Retrain model Prophet untuk semua komoditas aktif.
    Grid search dijalankan otomatis di predictor.train_and_save()
    selama user_override=False.

    FIX v3.2: Hapus parameter train_hybrid — sudah tidak ada di v8.3.
    """
    db        = DatabaseConnector()
    predictor = CommodityPredictor()

    start_time = datetime.now()
    print(f"\n{'='*60}")
    print(f"  AUTO-RETRAIN {'(FORCE) ' if force else ''}dimulai: "
          f"{start_time.strftime('%Y-%m-%d %H:%M:%S')}")
    print(f"  Hyperparams : {DEFAULT_HYPERPARAMS}")
    print(f"{'='*60}")

    commodities = db.get_all_commodities()
    commodities = [c for c in commodities
                   if (c.get('jumlah_data') or 0) >= MIN_DATA_POINTS]

    if not commodities:
        print("   Tidak ada komoditas dengan data cukup.")
        return

    print(f"   Komoditas aktif: {len(commodities)}\n")

    retrained = 0
    skipped   = 0
    failed    = 0
    results   = []

    for idx, c in enumerate(commodities, 1):
        cid    = c['id']
        nama   = c.get('full_name') or c.get('nama_komoditas', f'ID {cid}')
        jumlah = c.get('jumlah_data', 0)

        print(f"   [{idx}/{len(commodities)}] {nama} (ID={cid}, data={jumlah} baris)")

        try:
            df = db.get_commodity_prices(cid)
            if df.empty or len(df) < MIN_DATA_POINTS:
                print(f"        Skip — data tidak cukup ({len(df)} baris)")
                skipped += 1
                results.append({'id': cid, 'name': nama,
                                 'status': 'skipped', 'reason': 'insufficient_data'})
                continue

            needs, reason = CommodityForecastModel.needs_retraining(
                cid, df, hyperparams=DEFAULT_HYPERPARAMS,
            )

            if not needs and not force:
                with _drift_lock:
                    drift_info = _drift_flags.get(cid, {})
                if drift_info.get('drift'):
                    needs  = True
                    reason = (f"mape_drift ({drift_info.get('mape', 0):.2f}% "
                               f"> threshold, detected at {drift_info.get('checked_at', '?')})")
                    print(f"        Drift terdeteksi dari async check: {reason}")

            if force:
                needs  = True
                reason = "force_retrain"

            if not needs:
                print(f"        Skip — {reason}")
                skipped += 1
                results.append({'id': cid, 'name': nama,
                                 'status': 'skipped', 'reason': reason})
                continue

            print(f"        Retrain — alasan: {reason}")

            detected_freq   = CommodityForecastModel.detect_frequency(df)
            use_freq        = _normalize_freq(detected_freq)
            freq_to_periode = {'D': 'daily', 'W': 'weekly', 'MS': 'monthly'}
            periode_value   = freq_to_periode.get(use_freq, 'weekly')

            # train_and_save menjalankan grid search otomatis (user_override=False)
            train_info = predictor.train_and_save(
                commodity_id = cid,
                df           = df,
                hyperparams  = DEFAULT_HYPERPARAMS,
                freq         = use_freq,
            )

            with _drift_lock:
                _drift_flags.pop(cid, None)

            forecast_result = predictor.predict(
                commodity_id  = cid,
                historical_df = df,
                periods       = DEFAULT_FORECAST_PERIODS_DAYS,
                frequency     = use_freq,
                hyperparams   = DEFAULT_HYPERPARAMS,
                force_retrain = False,
            )

            import pandas as pd
            forecast_df = pd.DataFrame([{
                'ds':         pd.to_datetime(p['date']),
                'yhat':       p['predicted_price'],
                'yhat_lower': p['lower_bound'],
                'yhat_upper': p['upper_bound'],
            } for p in forecast_result['predictions']])

            db.save_forecast_results(cid, forecast_df, periode=periode_value)
            db.save_forecast_run(
                commodity_id = cid,
                metrics      = forecast_result['model_metrics'],
                params       = DEFAULT_HYPERPARAMS,
                engine_used  = forecast_result['engine'],
                reason       = reason,
            )

            mape       = forecast_result['model_metrics'].get('mape', 0)
            preds      = forecast_result['predictions']
            first_pred = preds[0]['date']  if preds else '-'
            last_pred  = preds[-1]['date'] if preds else '-'

            print(f"        Selesai | MAPE={mape:.2f}% | "
                  f"{len(preds)} titik | freq={use_freq} | "
                  f"forecast: {first_pred} -> {last_pred}")

            retrained += 1
            results.append({
                'id':         cid,
                'name':       nama,
                'status':     'retrained',
                'reason':     reason,
                'mape':       round(mape, 4),
                'freq':       use_freq,
                'first_pred': first_pred,
                'last_pred':  last_pred,
            })

        except Exception as e:
            import traceback
            print(f"        Gagal: {e}")
            print(traceback.format_exc())
            failed += 1
            results.append({'id': cid, 'name': nama,
                             'status': 'failed', 'error': str(e)})

    elapsed = (datetime.now() - start_time).total_seconds()
    print(f"\n{'='*60}")
    print(f"  AUTO-RETRAIN SELESAI dalam {elapsed:.1f} detik")
    print(f"     Retrained : {retrained}")
    print(f"     Skipped   : {skipped}")
    print(f"     Failed    : {failed}")
    print(f"     Total     : {len(commodities)}")
    print(f"{'='*60}\n")

    return results


# ═══════════════════════════════════════════════════════════════
# DRIFT CHECK
# ═══════════════════════════════════════════════════════════════

def check_drift_all_commodities():
    db = DatabaseConnector()

    start_time = datetime.now()
    print(f"\n{'='*60}")
    print(f"  ASYNC DRIFT CHECK dimulai: {start_time.strftime('%Y-%m-%d %H:%M:%S')}")
    print(f"{'='*60}")

    commodities = db.get_all_commodities()
    commodities = [c for c in commodities
                   if (c.get('jumlah_data') or 0) >= MIN_DATA_POINTS]

    if not commodities:
        print("   Tidak ada komoditas untuk dicek.")
        return

    drifted = 0
    clean   = 0
    failed  = 0

    for idx, c in enumerate(commodities, 1):
        cid  = c['id']
        nama = c.get('full_name') or c.get('nama_komoditas', f'ID {cid}')

        try:
            df = db.get_commodity_prices(cid)
            if df.empty or len(df) < MIN_DATA_POINTS:
                continue

            result = DriftChecker.check(
                commodity_id = cid,
                current_df   = df,
                threshold    = MAPE_DRIFT_THRESHOLD,
            )

            with _drift_lock:
                _drift_flags[cid] = {
                    'drift':      result['drift_detected'],
                    'mape':       result.get('recent_mape', 0.0),
                    'checked_at': datetime.now().strftime('%Y-%m-%d %H:%M'),
                }

            if result['drift_detected']:
                drifted += 1
                print(f"   [{idx}] {nama}: DRIFT terdeteksi "
                      f"(MAPE={result.get('recent_mape', 0):.2f}% > {MAPE_DRIFT_THRESHOLD}%)")
            else:
                clean += 1

        except Exception as e:
            failed += 1
            print(f"   [{idx}] {nama}: Error drift check: {e}")

    elapsed = (datetime.now() - start_time).total_seconds()
    print(f"\n  DRIFT CHECK SELESAI dalam {elapsed:.1f} detik")
    print(f"     Drifted : {drifted}")
    print(f"     Clean   : {clean}")
    print(f"     Failed  : {failed}")
    print(f"{'='*60}\n")


# ═══════════════════════════════════════════════════════════════
# IHK — RECALCULATE & FORECAST (FIX v3.2: tambahan baru)
# ═══════════════════════════════════════════════════════════════

def recalculate_ihk(n_bulan: int = 3):
    """
    Recalculate IHK N bulan terakhir menggunakan IHKCalculator.
    Aman dijalankan tiap malam — hanya update bulan yang berubah.
    """
    try:
        from models.ihk_calculator import IHKCalculator
        db   = DatabaseConnector()
        calc = IHKCalculator(db)

        start_time = datetime.now()
        print(f"\n{'='*60}")
        print(f"  IHK RECALCULATE dimulai: {start_time.strftime('%Y-%m-%d %H:%M:%S')}")
        print(f"  n_bulan: {n_bulan}")
        print(f"{'='*60}")

        result = calc.recalculate_latest(n_bulan=n_bulan)

        elapsed = (datetime.now() - start_time).total_seconds()
        print(f"  IHK RECALCULATE SELESAI dalam {elapsed:.1f} detik")
        print(f"  Hasil: {result}")
        print(f"{'='*60}\n")

        return result

    except Exception as e:
        import traceback
        print(f"  IHK RECALCULATE GAGAL: {e}")
        print(traceback.format_exc())


def forecast_ihk(periods: int = 6):
    """
    Forecast IHK N bulan ke depan menggunakan IHKForecaster + Prophet.
    Dijalankan setelah recalculate selesai agar data historis selalu fresh.
    """
    try:
        from models.ihk_forecaster import IHKForecaster
        db         = DatabaseConnector()
        forecaster = IHKForecaster(db)

        start_time = datetime.now()
        print(f"\n{'='*60}")
        print(f"  IHK FORECAST dimulai: {start_time.strftime('%Y-%m-%d %H:%M:%S')}")
        print(f"  periods: {periods} bulan")
        print(f"{'='*60}")

        result = forecaster.forecast(periods=periods)

        elapsed = (datetime.now() - start_time).total_seconds()
        print(f"  IHK FORECAST SELESAI dalam {elapsed:.1f} detik")
        if result.get('success'):
            print(f"  Forecast: {result.get('forecast_mulai')} -> {result.get('forecast_sampai')}")
            print(f"  MAPE in-sample: {result.get('model_info', {}).get('mape_insample', 0):.4f}%")
        else:
            print(f"  Gagal: {result.get('message')}")
        print(f"{'='*60}\n")

        return result

    except Exception as e:
        import traceback
        print(f"  IHK FORECAST GAGAL: {e}")
        print(traceback.format_exc())


def run_ihk_nightly():
    """
    Job malam untuk IHK: recalculate dulu, lalu forecast.
    Dipisah dari retrain komoditas agar tidak saling blocking.
    """
    print(f"\n[Scheduler] IHK nightly job dimulai...")
    recalculate_ihk(n_bulan=3)
    forecast_ihk(periods=6)
    print(f"[Scheduler] IHK nightly job selesai.")


def run_ihk_weekly():
    """
    Job mingguan untuk IHK: recalculate lebih banyak bulan + forecast lebih jauh.
    Dijalankan Senin pagi setelah force retrain komoditas.
    """
    print(f"\n[Scheduler] IHK weekly job dimulai...")
    recalculate_ihk(n_bulan=6)   # recalculate 6 bulan untuk validasi lebih luas
    forecast_ihk(periods=12)     # forecast 1 tahun ke depan tiap Senin
    print(f"[Scheduler] IHK weekly job selesai.")


# ═══════════════════════════════════════════════════════════════
# SCHEDULER LOOP
# ═══════════════════════════════════════════════════════════════

def _run_scheduler_loop():
    print("   [Scheduler] Background thread aktif")
    print(f"   [Scheduler] Jadwal:")
    print(f"               - Setiap hari  jam 02:00 -> auto-retrain komoditas")
    print(f"               - Setiap Senin jam 03:00 -> force retrain komoditas")
    print(f"               - Setiap hari  jam 04:00 -> async drift check")
    print(f"               - Setiap hari  jam 02:30 -> recalculate IHK 3 bulan")
    print(f"               - Setiap Senin jam 03:30 -> forecast IHK 12 bulan")

    while True:
        schedule.run_pending()
        time.sleep(30)


def start_scheduler():
    # Retrain komoditas
    schedule.every().day.at("02:00").do(
        retrain_all_commodities, force=False,
    )
    schedule.every().monday.at("03:00").do(
        retrain_all_commodities, force=True,
    )

    # Drift check
    schedule.every().day.at("04:00").do(check_drift_all_commodities)

    # IHK — setelah retrain komoditas selesai (estimasi 02:30)
    schedule.every().day.at("02:30").do(run_ihk_nightly)
    schedule.every().monday.at("03:30").do(run_ihk_weekly)

    thread = threading.Thread(target=_run_scheduler_loop, daemon=True)
    thread.start()

    print("\n   [Scheduler] Scheduler dimulai (5 jobs terdaftar)")
    return thread


# ═══════════════════════════════════════════════════════════════
# CLI — jalankan manual dari terminal
# ═══════════════════════════════════════════════════════════════

if __name__ == '__main__':
    import sys

    force_flag = '--force' in sys.argv
    drift_flag = '--drift' in sys.argv
    ihk_flag   = '--ihk'   in sys.argv

    print("=" * 60)

    if drift_flag:
        print("  MANUAL DRIFT CHECK")
        print("=" * 60)
        check_drift_all_commodities()

    elif ihk_flag:
        print("  MANUAL IHK RECALCULATE + FORECAST")
        print("=" * 60)
        run_ihk_nightly()

    else:
        print(f"  MANUAL RETRAIN {'(FORCE) ' if force_flag else ''}")
        print("=" * 60)

        results = retrain_all_commodities(force=force_flag)

        if results:
            print("\nDetail hasil:")
            for r in results:
                icon     = {'retrained': 'OK', 'skipped': 'SKIP',
                             'failed':    'FAIL'}.get(r['status'], '?')
                mape_str = f" | MAPE={r['mape']:.2f}%" if 'mape' in r else ''
                reason   = r.get('reason', r.get('error', ''))
                print(f"  {icon} [{r['id']}] {r['name']}{mape_str} | {reason}")