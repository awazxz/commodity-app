"""
scheduler.py

Background scheduler untuk auto-retraining model Prophet.

Jadwal:
- Setiap hari jam 02:00 → retrain semua komoditas yang perlu update
- Setiap Senin jam 03:00 → force retrain mingguan (opsional)

Logika:
- needs_retraining() → retrain hanya jika ada data baru / model expired
- Model yang masih fresh → SKIP (tidak ada pemborosan resource)
- Hasil forecast terbaru otomatis tersimpan ke price_forecasts
"""

import threading
import time
from datetime import datetime

import schedule

from data.database_connector import DatabaseConnector
from models.predictor import CommodityPredictor, _normalize_freq
from models.prophet_forecasting import CommodityForecastModel, MIN_DATA_POINTS

# ── Default hyperparameter untuk scheduled retrain ────────────
# Bisa disesuaikan via environment variable atau config file
DEFAULT_HYPERPARAMS = {
    'changepoint_prior_scale': 0.05,
    'seasonality_prior_scale': 10.0,
    'seasonality_mode':        'multiplicative',
    'weekly_seasonality':      False,
    'yearly_seasonality':      True,
}

# Berapa minggu / periode ke depan yang disimpan ke DB
DEFAULT_FORECAST_PERIODS_DAYS = 84   # 84 hari = 12 minggu


# ═══════════════════════════════════════════════════════════════
# CORE JOB
# ═══════════════════════════════════════════════════════════════

def retrain_all_commodities(force: bool = False):
    """
    Job utama scheduler.

    Iterasi semua komoditas aktif:
    1. Cek apakah model perlu retrain (needs_retraining)
    2. Jika ya  → train ulang → simpan model → generate forecast → simpan ke DB
    3. Jika tidak → skip (model masih fresh)

    Parameters
    ----------
    force : bool
        True = paksa retrain semua meski model masih fresh.
        Gunakan untuk force refresh manual.
    """
    db        = DatabaseConnector()
    predictor = CommodityPredictor()

    start_time = datetime.now()
    print(f"\n{'='*60}")
    print(f"  🔄 AUTO-RETRAIN {'(FORCE) ' if force else ''}dimulai: {start_time.strftime('%Y-%m-%d %H:%M:%S')}")
    print(f"{'='*60}")

    # ── Ambil semua komoditas yang punya data ──────────────────
    commodities = db.get_all_commodities()
    commodities = [c for c in commodities if (c.get('jumlah_data') or 0) >= MIN_DATA_POINTS]

    if not commodities:
        print("   ⚠️  Tidak ada komoditas dengan data cukup untuk ditraining.")
        return

    print(f"   Komoditas aktif: {len(commodities)}\n")

    retrained = 0
    skipped   = 0
    failed    = 0
    results   = []

    for idx, c in enumerate(commodities, 1):
        cid  = c['id']
        nama = c.get('full_name') or c.get('nama_komoditas', f'ID {cid}')
        jumlah = c.get('jumlah_data', 0)

        print(f"   [{idx}/{len(commodities)}] {nama} (ID={cid}, data={jumlah} baris)")

        try:
            # ── Ambil data historis ────────────────────────────
            df = db.get_commodity_prices(cid)
            if df.empty or len(df) < MIN_DATA_POINTS:
                print(f"        ⏭️  Skip — data tidak cukup ({len(df)} baris)")
                skipped += 1
                results.append({'id': cid, 'name': nama, 'status': 'skipped', 'reason': 'insufficient_data'})
                continue

            # ── Cek kebutuhan retrain ──────────────────────────
            needs, reason = CommodityForecastModel.needs_retraining(
                cid, df, hyperparams=DEFAULT_HYPERPARAMS
            )

            if force:
                needs  = True
                reason = "force_retrain"

            if not needs:
                print(f"        ✅ Skip — {reason}")
                skipped += 1
                results.append({'id': cid, 'name': nama, 'status': 'skipped', 'reason': reason})
                continue

            print(f"        🔁 Retrain — alasan: {reason}")

            # ── Deteksi frekuensi data ─────────────────────────
            detected_freq = CommodityForecastModel.detect_frequency(df)
            use_freq      = _normalize_freq(detected_freq)
            freq_to_periode = {'D': 'daily', 'W': 'weekly', 'MS': 'monthly'}
            periode_value   = freq_to_periode.get(use_freq, 'weekly')

            # ── Train & simpan model ───────────────────────────
            train_info = predictor.train_and_save(
                commodity_id = cid,
                df           = df,
                hyperparams  = DEFAULT_HYPERPARAMS,
                freq         = use_freq,
            )

            # ── Generate forecast & simpan ke DB ──────────────
            forecast_result = predictor.predict(
                commodity_id  = cid,
                historical_df = df,
                periods       = DEFAULT_FORECAST_PERIODS_DAYS,
                frequency     = use_freq,
                hyperparams   = DEFAULT_HYPERPARAMS,
                force_retrain = False,   # Sudah retrain di atas, pakai cache
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

            mape = forecast_result['model_metrics'].get('mape', 0)
            print(f"        ✅ Selesai | MAPE={mape:.2f}% | "
                  f"{len(forecast_result['predictions'])} titik prediksi | "
                  f"freq={use_freq}")

            retrained += 1
            results.append({
                'id':     cid,
                'name':   nama,
                'status': 'retrained',
                'reason': reason,
                'mape':   round(mape, 4),
                'freq':   use_freq,
            })

        except Exception as e:
            import traceback
            print(f"        ❌ Gagal: {e}")
            print(traceback.format_exc())
            failed += 1
            results.append({'id': cid, 'name': nama, 'status': 'failed', 'error': str(e)})

    # ── Ringkasan ──────────────────────────────────────────────
    elapsed = (datetime.now() - start_time).total_seconds()
    print(f"\n{'='*60}")
    print(f"  ✅ AUTO-RETRAIN SELESAI dalam {elapsed:.1f} detik")
    print(f"     Retrained : {retrained}")
    print(f"     Skipped   : {skipped}")
    print(f"     Failed    : {failed}")
    print(f"     Total     : {len(commodities)}")
    print(f"{'='*60}\n")

    return results


# ═══════════════════════════════════════════════════════════════
# SCHEDULER RUNNER
# ═══════════════════════════════════════════════════════════════

def _run_scheduler_loop():
    """Loop scheduler yang berjalan di background thread."""
    print("   [Scheduler] ✅ Background thread aktif")
    print(f"   [Scheduler] Jadwal:")
    print(f"               - Setiap hari  jam 02:00 → auto-retrain")
    print(f"               - Setiap Senin jam 03:00 → force retrain mingguan")

    while True:
        schedule.run_pending()
        time.sleep(30)   # cek setiap 30 detik


def start_scheduler():
    """
    Daftarkan job dan jalankan scheduler di background daemon thread.
    Dipanggil dari app.py saat startup.
    """
    # ── Daftarkan jadwal ───────────────────────────────────────

    # Retrain harian — cek semua, skip yang masih fresh
    schedule.every().day.at("02:00").do(retrain_all_commodities, force=False)

    # Force retrain mingguan setiap Senin pagi
    schedule.every().monday.at("03:00").do(retrain_all_commodities, force=True)

    # ── Jalankan di daemon thread ──────────────────────────────
    thread = threading.Thread(target=_run_scheduler_loop, daemon=True)
    thread.start()

    print("\n   [Scheduler] 🚀 Scheduler dimulai")
    return thread


# ═══════════════════════════════════════════════════════════════
# MANUAL TRIGGER (untuk testing)
# ═══════════════════════════════════════════════════════════════

if __name__ == '__main__':
    """
    Jalankan retrain sekali langsung (tanpa scheduler loop).
    Berguna untuk testing atau trigger manual dari terminal.

    Usage:
        python scheduler.py            → retrain yang butuh update saja
        python scheduler.py --force    → force retrain semua komoditas
    """
    import sys

    force_flag = '--force' in sys.argv

    print("=" * 60)
    print(f"  MANUAL RETRAIN {'(FORCE) ' if force_flag else ''}")
    print("=" * 60)

    results = retrain_all_commodities(force=force_flag)

    if results:
        print("\nDetail hasil:")
        for r in results:
            status_icon = {'retrained': '✅', 'skipped': '⏭️', 'failed': '❌'}.get(r['status'], '?')
            mape_str    = f" | MAPE={r['mape']:.2f}%" if 'mape' in r else ''
            reason_str  = f" | {r.get('reason', r.get('error', ''))}"
            print(f"  {status_icon} [{r['id']}] {r['name']}{mape_str}{reason_str}")