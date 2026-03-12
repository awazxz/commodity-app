"""
run_forecast_all.py

Script batch untuk menjalankan forecast semua komoditas sekaligus.
Bisa dijalankan manual dari terminal, atau dijadwalkan via cron.

FIX: Forecast sekarang selalu dimulai dari tanggal setelah data terakhir
(bukan dari tanggal sekarang). Jadi jika data terakhir Desember 2023,
forecast akan mulai dari Januari 2024 secara kontinu.

Perbedaan dari scheduler.py:
- run_forecast_all.py → dijalankan langsung dari terminal (bukan background)
- scheduler.py        → berjalan otomatis di background saat Flask jalan

Gunakan script ini untuk:
- Inisialisasi awal semua model (pertama kali deploy)
- Refresh manual semua prediksi
- Testing batch sebelum production
- ★ Fix data forecast yang melompat tahun (jalankan dengan --force)

Usage:
    python run_forecast_all.py                    # retrain yang perlu saja
    python run_forecast_all.py --force            # force retrain + replace semua forecast
    python run_forecast_all.py --id 9,10,11       # retrain ID tertentu saja
    python run_forecast_all.py --dry-run          # cek tanpa eksekusi
    python run_forecast_all.py --direct           # langsung ke DB tanpa Flask
    python run_forecast_all.py --force --direct   # ★ untuk fix loncatan tahun
"""

import argparse
import sys
import time
from datetime import datetime

import pandas as pd
import requests

from data.database_connector import DatabaseConnector
from models.predictor import CommodityPredictor, _normalize_freq
from models.prophet_forecasting import CommodityForecastModel, MIN_DATA_POINTS

# ── Konfigurasi default ────────────────────────────────────────
FLASK_URL = "http://localhost:5000"

DEFAULT_HYPERPARAMS = {
    'changepoint_prior_scale': 0.05,
    'seasonality_prior_scale': 10.0,
    'seasonality_mode':        'multiplicative',
    'weekly_seasonality':      False,
    'yearly_seasonality':      True,
}

# FIX: Naikkan periods agar forecast mencakup dari data terakhir
# sampai jauh ke depan. Predictor akan otomatis menutup gap.
# 84 hari = 12 minggu ke depan SETELAH tanggal data terakhir.
DEFAULT_PERIODS_DAYS = 84
DELAY_BETWEEN        = 0.5  # detik jeda antar komoditas


# ═══════════════════════════════════════════════════════════════
# MODE 1: VIA FLASK API (jika server sudah jalan)
# ═══════════════════════════════════════════════════════════════

def check_flask_health() -> bool:
    try:
        r = requests.get(f"{FLASK_URL}/api/health", timeout=5)
        return r.json().get("success", False)
    except Exception:
        return False


def run_via_api(commodity_ids: list, force: bool, dry_run: bool) -> dict:
    """
    Jalankan forecast via Flask API endpoint.
    Gunakan mode ini jika Flask sudah berjalan.
    """
    print(f"\n   Mode: VIA FLASK API ({FLASK_URL})")
    success_list = []
    failed_list  = []
    skipped_list = []

    db          = DatabaseConnector()
    commodities = db.get_all_commodities()

    if commodity_ids:
        commodities = [c for c in commodities if c['id'] in commodity_ids]

    commodities = [c for c in commodities if (c.get('jumlah_data') or 0) >= MIN_DATA_POINTS]

    for idx, c in enumerate(commodities, 1):
        cid  = c['id']
        nama = c.get('full_name') or c.get('nama_komoditas', f'ID {cid}')

        print(f"\n   [{idx}/{len(commodities)}] {nama} (ID={cid})")

        if dry_run:
            needs, reason = CommodityForecastModel.needs_retraining(
                cid,
                db.get_commodity_prices(cid),
                hyperparams=DEFAULT_HYPERPARAMS,
            )
            print(f"        [DRY-RUN] needs_retrain={needs} | reason={reason}")
            skipped_list.append({'id': cid, 'name': nama, 'reason': reason})
            continue

        payload = {
            'commodity_id':  cid,
            'periods':       DEFAULT_PERIODS_DAYS,
            'frequency':     'W',
            'force_retrain': force,
            **DEFAULT_HYPERPARAMS,
        }

        try:
            r      = requests.post(
                f"{FLASK_URL}/api/forecast/predict-advanced",
                json    = payload,
                timeout = 300,
            )
            result = r.json()

            if result.get('success'):
                d            = result['data']
                metrics      = d.get('model_metrics', {})
                source       = d.get('model_source', '')
                last_data    = d.get('last_data_date', '-')
                preds        = d.get('predictions', [])
                first_pred   = preds[0]['date']  if preds else '-'
                last_pred    = preds[-1]['date'] if preds else '-'

                print(f"         OK | MAPE={metrics.get('mape', 0):.2f}% | "
                      f"titik={len(preds)} | source={source}")
                print(f"           data terakhir={last_data} | "
                      f"forecast: {first_pred} → {last_pred}")

                success_list.append({
                    'id':         cid,
                    'name':       nama,
                    'mape':       metrics.get('mape', 0),
                    'source':     source,
                    'last_data':  last_data,
                    'first_pred': first_pred,
                    'last_pred':  last_pred,
                })
            else:
                msg = result.get('message', 'Unknown error')
                print(f"         Gagal: {msg}")
                failed_list.append({'id': cid, 'name': nama, 'error': msg})

        except requests.Timeout:
            print(f"          Timeout (>300s)")
            failed_list.append({'id': cid, 'name': nama, 'error': 'timeout'})

        except Exception as e:
            print(f"         Error: {e}")
            failed_list.append({'id': cid, 'name': nama, 'error': str(e)})

        if idx < len(commodities):
            time.sleep(DELAY_BETWEEN)

    return {
        'mode':    'api',
        'success': success_list,
        'failed':  failed_list,
        'skipped': skipped_list,
    }


# ═══════════════════════════════════════════════════════════════
# MODE 2: DIRECT (tanpa Flask, langsung ke DB)
# ═══════════════════════════════════════════════════════════════

def run_direct(commodity_ids: list, force: bool, dry_run: bool) -> dict:
    """
    Jalankan forecast langsung ke DB tanpa Flask API.
    Gunakan mode ini untuk inisialisasi awal atau jika Flask belum jalan.

    FIX: Menggunakan predictor.predict() yang sudah diperbaiki,
    sehingga forecast selalu mulai dari setelah data terakhir.
    """
    print(f"\n   Mode: DIRECT (tanpa Flask API)")

    db          = DatabaseConnector()
    predictor   = CommodityPredictor()
    commodities = db.get_all_commodities()

    if commodity_ids:
        commodities = [c for c in commodities if c['id'] in commodity_ids]

    commodities = [c for c in commodities if (c.get('jumlah_data') or 0) >= MIN_DATA_POINTS]

    success_list = []
    failed_list  = []
    skipped_list = []

    for idx, c in enumerate(commodities, 1):
        cid    = c['id']
        nama   = c.get('full_name') or c.get('nama_komoditas', f'ID {cid}')
        jumlah = c.get('jumlah_data', 0)

        print(f"\n   [{idx}/{len(commodities)}] {nama} (ID={cid}, data={jumlah} baris)")

        try:
            df = db.get_commodity_prices(cid)

            if df.empty or len(df) < MIN_DATA_POINTS:
                print(f"          Skip — data tidak cukup ({len(df)} baris)")
                skipped_list.append({'id': cid, 'name': nama, 'reason': 'insufficient_data'})
                continue

            last_data_date = df['ds'].max()
            print(f"         Data terakhir: {last_data_date.date()}")

            # ── Cek kebutuhan retrain ──────────────────────────
            needs, reason = CommodityForecastModel.needs_retraining(
                cid, df, hyperparams=DEFAULT_HYPERPARAMS
            )

            if force:
                needs  = True
                reason = "force_flag"

            if not needs and not dry_run:
                print(f"          Skip — {reason}")
                skipped_list.append({'id': cid, 'name': nama, 'reason': reason})
                continue

            if dry_run:
                detected_freq = CommodityForecastModel.detect_frequency(df)
                use_freq      = _normalize_freq(detected_freq)
                today         = pd.Timestamp.now().normalize()
                gap_days      = (today - last_data_date).days
                freq_days     = {'D': 1, 'W': 7, 'MS': 30}
                gap_periods   = gap_days // freq_days.get(use_freq, 7)

                print(f"        [DRY-RUN] needs_retrain={needs} | reason={reason}")
                print(f"        [DRY-RUN] freq={use_freq} | "
                      f"gap={gap_days} hari ({gap_periods} {use_freq}) | "
                      f"forecast akan mulai dari {last_data_date.date()}")
                skipped_list.append({'id': cid, 'name': nama, 'reason': f'dry_run ({reason})'})
                continue

            print(f"         Retrain — {reason}")

            # ── Deteksi frekuensi ──────────────────────────────
            detected_freq   = CommodityForecastModel.detect_frequency(df)
            use_freq        = _normalize_freq(detected_freq)
            freq_to_periode = {'D': 'daily', 'W': 'weekly', 'MS': 'monthly'}
            periode_value   = freq_to_periode.get(use_freq, 'weekly')

            # ── Train & predict ────────────────────────────────
            # FIX: predictor.predict() otomatis menutup gap dan
            # memulai forecast dari last_data_date
            result = predictor.predict(
                commodity_id  = cid,
                historical_df = df,
                periods       = DEFAULT_PERIODS_DAYS,
                frequency     = use_freq,
                hyperparams   = DEFAULT_HYPERPARAMS,
                force_retrain = True,
            )

            preds      = result['predictions']
            first_pred = preds[0]['date']  if preds else '-'
            last_pred  = preds[-1]['date'] if preds else '-'

            # ── Simpan ke DB ───────────────────────────────────
            forecast_df = pd.DataFrame([{
                'ds':         pd.to_datetime(p['date']),
                'yhat':       p['predicted_price'],
                'yhat_lower': p['lower_bound'],
                'yhat_upper': p['upper_bound'],
            } for p in preds])

            db.save_forecast_results(cid, forecast_df, periode=periode_value)
            db.save_forecast_run(
                commodity_id = cid,
                metrics      = result['model_metrics'],
                params       = DEFAULT_HYPERPARAMS,
                engine_used  = result['engine'],
                reason       = reason,
            )

            mape = result['model_metrics'].get('mape', 0)
            print(f"         OK | MAPE={mape:.2f}% | "
                  f"titik={len(preds)} | freq={use_freq} | engine={result['engine']}")
            print(f"           data terakhir={result.get('last_data_date', '-')} | "
                  f"forecast: {first_pred} → {last_pred}")

            success_list.append({
                'id':         cid,
                'name':       nama,
                'mape':       round(mape, 4),
                'freq':       use_freq,
                'reason':     reason,
                'last_data':  result.get('last_data_date', '-'),
                'first_pred': first_pred,
                'last_pred':  last_pred,
            })

        except Exception as e:
            import traceback as tb
            print(f"         Gagal: {e}")
            print(tb.format_exc())
            failed_list.append({'id': cid, 'name': nama, 'error': str(e)})

        if idx < len(commodities) and not dry_run:
            time.sleep(DELAY_BETWEEN)

    return {
        'mode':    'direct',
        'success': success_list,
        'failed':  failed_list,
        'skipped': skipped_list,
    }




def print_summary(results: dict, elapsed: float):
    success = results['success']
    failed  = results['failed']
    skipped = results['skipped']
    total   = len(success) + len(failed) + len(skipped)

    print(f"\n{'='*60}")
    print(f"  RINGKASAN — mode={results['mode']} | waktu={elapsed:.1f}s")
    print(f"{'='*60}")
    print(f"   Berhasil : {len(success)}")
    print(f"   Gagal    : {len(failed)}")
    print(f"   Skipped  : {len(skipped)}")
    print(f"  Total      : {total}")

    if success:
        mapes = [r['mape'] for r in success if 'mape' in r]
        if mapes:
            avg_mape = sum(mapes) / len(mapes)
            print(f"\n  Avg MAPE   : {avg_mape:.2f}%")
            best  = min(success, key=lambda x: x.get('mape', 999))
            worst = max(success, key=lambda x: x.get('mape', 0))
            print(f"  Best MAPE  : {best['name']} ({best.get('mape', 0):.2f}%)")
            print(f"  Worst MAPE : {worst['name']} ({worst.get('mape', 0):.2f}%)")

        print(f"\n  Detail forecast per komoditas:")
        for r in success:
            print(f"     [{r['id']}] {r['name']}")
            print(f"       data terakhir : {r.get('last_data', '-')}")
            print(f"       forecast      : {r.get('first_pred', '-')} → {r.get('last_pred', '-')}")

    if failed:
        print(f"\n  Komoditas gagal:")
        for f in failed:
            print(f"     [{f['id']}] {f['name']}: {f.get('error', '')}")

    print(f"\n   Prediksi tersimpan di tabel price_forecasts")
    print(f"     Refresh halaman laporan untuk melihat hasilnya.")
    print(f"{'='*60}\n")




def main():
    parser = argparse.ArgumentParser(
        description='Batch forecast semua komoditas',
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Contoh penggunaan:

  # Fix loncatan tahun (jalankan ini dulu):
  python run_forecast_all.py --force --direct

  # Retrain hanya yang perlu saja:
  python run_forecast_all.py

  # Retrain komoditas tertentu:
  python run_forecast_all.py --force --id 1,2,3

  # Cek status tanpa eksekusi:
  python run_forecast_all.py --dry-run
        """
    )
    parser.add_argument(
        '--force', action='store_true',
        help='Force retrain semua meski model masih fresh. '
             'Gunakan ini untuk memperbaiki forecast yang melompat tahun.'
    )
    parser.add_argument(
        '--id', type=str, default='',
        help='Comma-separated ID komoditas (contoh: --id 9,10,11). '
             'Kosong = semua komoditas.'
    )
    parser.add_argument(
        '--dry-run', action='store_true',
        help='Cek status tanpa melakukan training / prediksi. '
             'Tampilkan gap antara data terakhir dan hari ini.'
    )
    parser.add_argument(
        '--direct', action='store_true',
        help='Langsung ke DB tanpa Flask API (untuk inisialisasi awal / fix data).'
    )
    args = parser.parse_args()

    commodity_ids = []
    if args.id:
        try:
            commodity_ids = [int(x.strip()) for x in args.id.split(',')]
        except ValueError:
            print(" Format --id tidak valid. Gunakan: --id 9,10,11")
            sys.exit(1)

    print("=" * 60)
    print("  BATCH FORECAST - Semua Komoditas")
    print("=" * 60)
    print(f"  Waktu       : {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print(f"  Force       : {args.force}")
    print(f"  Dry-run     : {args.dry_run}")
    print(f"  Direct      : {args.direct}")
    print(f"  Target IDs  : {commodity_ids if commodity_ids else 'semua'}")
    print(f"  Periods     : {DEFAULT_PERIODS_DAYS} hari ke depan dari data terakhir")
    print("=" * 60)

    if args.force and not args.dry_run:
        print("\n  MODE --force: Semua model akan di-retrain ulang.")
        print("   Forecast lama di price_forecasts akan dihapus dan diganti.")
        print("   Forecast baru akan dimulai dari SETELAH tanggal data terakhir.\n")

    start = datetime.now()

    if args.direct:
        results = run_direct(
            commodity_ids = commodity_ids,
            force         = args.force,
            dry_run       = args.dry_run,
        )
    else:
        print("\n Cek koneksi Flask API...")
        if check_flask_health():
            print(" Flask API online — gunakan mode API\n")
            results = run_via_api(
                commodity_ids = commodity_ids,
                force         = args.force,
                dry_run       = args.dry_run,
            )
        else:
            print("⚠️  Flask API tidak berjalan — fallback ke mode DIRECT\n")
            results = run_direct(
                commodity_ids = commodity_ids,
                force         = args.force,
                dry_run       = args.dry_run,
            )

    elapsed = (datetime.now() - start).total_seconds()
    print_summary(results, elapsed)


if __name__ == '__main__':
    main()