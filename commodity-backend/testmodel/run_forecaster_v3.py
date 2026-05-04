"""
=============================================================
  RUN INI DARI TERMINAL:

    # Semua komoditas:
    python run_forecaster_v3.py --all

    # Satu komoditas by nama (substring, case-insensitive):
    python run_forecaster_v3.py --nama jagung

    # Satu komoditas by nomor urut id (1–12):
    python run_forecaster_v3.py --kid 1

    # Skip CV (lebih cepat untuk testing):
    python run_forecaster_v3.py --all --no-cv

    # Ganti path CSV dan forecast 52 minggu:
    python run_forecaster_v3.py --csv data.csv --all --periods 52

    # Simpan output CSV:
    python run_forecaster_v3.py --all --save-csv

    # Mode headless (tanpa popup chart):
    python run_forecaster_v3.py --all --no-show
=============================================================
"""

import argparse
import sys
import os
import warnings
warnings.filterwarnings("ignore")

import numpy as np
import pandas as pd
from datetime import datetime, timedelta

# ── Parse argumen ───────────────────────────────────────────
parser = argparse.ArgumentParser(
    description="Hybrid Forecaster v3 — Komoditas Mingguan (Inflasi/Deflasi)"
)
parser.add_argument("--csv",     type=str, default="data_komoditas.csv",
                    help="Path ke file CSV (default: data_komoditas.csv)")
parser.add_argument("--kid",     type=int, default=None,
                    help="id komoditas (kolom 'id' di CSV, nilai 1-12)")
parser.add_argument("--nama",    type=str, default=None,
                    help="Nama komoditas (substring, e.g. 'jagung', 'cabe merah')")
parser.add_argument("--all",     action="store_true",
                    help="Jalankan semua komoditas sekaligus")
parser.add_argument("--no-cv",   action="store_true",
                    help="Skip cross-validation (lebih cepat)")
parser.add_argument("--periods", type=int, default=26,
                    help="Jumlah minggu forecast ke depan (default: 26 ~6 bulan)")
parser.add_argument("--no-show", action="store_true",
                    help="Jangan tampilkan popup chart (simpan file saja)")
parser.add_argument("--save-csv", action="store_true",
                    help="Simpan output forecast + inflasi ke CSV")
args = parser.parse_args()

# ── Matplotlib backend ──────────────────────────────────────
import matplotlib
if args.no_show:
    matplotlib.use("Agg")
else:
    try:
        matplotlib.use("TkAgg")
    except Exception:
        try:
            matplotlib.use("Qt5Agg")
        except Exception:
            matplotlib.use("Agg")
            args.no_show = True
            print("  [Warning] Tidak ada display backend. Beralih ke Agg (simpan file saja).")

import matplotlib.pyplot as plt

# ── Validasi argumen ────────────────────────────────────────
if not args.all and args.kid is None and args.nama is None:
    print("\n  [Error] Tentukan salah satu:")
    print("    --all              → semua komoditas")
    print("    --kid <id>         → by id (1-12)")
    print("    --nama <substring> → by nama (e.g. --nama jagung)")
    print("\n  Contoh: python run_forecaster_v3.py --all")
    sys.exit(1)

if not os.path.exists(args.csv):
    print(f"\n  [Error] File CSV tidak ditemukan: {args.csv}")
    print(f"  Pastikan file CSV ada di folder yang sama dengan script ini.")
    sys.exit(1)


# ══════════════════════════════════════════════════════════════
#  ADAPTER: Baca & transformasi format CSV lama ke format baru
#  Format lama: id, tanggal, nama_komoditas, harga
#  Format baru: komoditas_id, tanggal, harga  (+ is_outlier, status)
# ══════════════════════════════════════════════════════════════

def load_and_transform_csv(csv_path: str) -> pd.DataFrame:
    """
    Baca CSV format lama (id, tanggal, nama_komoditas, harga)
    dan ubah ke format yang dibutuhkan forecaster:
    (komoditas_id, tanggal, harga, status, is_outlier)
    """
    df = pd.read_csv(csv_path)
    df.columns = [c.strip().lower() for c in df.columns]

    print(f"  [CSV] Kolom ditemukan: {list(df.columns)}")
    print(f"  [CSV] Total baris: {len(df)}")

    # ── Deteksi format CSV ──────────────────────────────────
    # Format lama: id, tanggal, nama_komoditas, harga
    if "nama_komoditas" in df.columns and "id" in df.columns:
        print("  [CSV] Format terdeteksi: id + nama_komoditas (format lama)")

        # Buat mapping id → nama_komoditas
        id_nama_map = (
            df[["id", "nama_komoditas"]]
            .drop_duplicates("id")
            .set_index("id")["nama_komoditas"]
            .to_dict()
        )
        print(f"  [CSV] Komoditas ditemukan ({len(id_nama_map)}):")
        for kid, nama in sorted(id_nama_map.items()):
            count = len(df[df["id"] == kid])
            print(f"         id={kid:2d} | {nama:<35} | {count} minggu")

        # Rename kolom ke format standar
        df = df.rename(columns={
            "id"             : "komoditas_id",
            "tanggal"        : "tanggal",
            "harga"          : "harga",
        })

        # Tambah kolom yang dibutuhkan load_data()
        df["status"]     = "cleaned"
        df["is_outlier"] = 0

        # Simpan mapping nama untuk dipakai nanti
        df["_nama"] = df["komoditas_id"].map(id_nama_map)

        return df, id_nama_map

    # Format baru: komoditas_id, tanggal, harga
    elif "komoditas_id" in df.columns:
        print("  [CSV] Format terdeteksi: komoditas_id (format baru)")
        if "status" not in df.columns:
            df["status"] = "cleaned"
        if "is_outlier" not in df.columns:
            df["is_outlier"] = 0
        id_nama_map = {kid: f"Komoditas-{kid}" for kid in df["komoditas_id"].unique()}
        df["_nama"] = df["komoditas_id"].map(id_nama_map)
        return df, id_nama_map

    else:
        raise ValueError(
            f"Format CSV tidak dikenali. "
            f"Kolom yang ada: {list(df.columns)}\n"
            f"Kolom yang dibutuhkan: (id + nama_komoditas + tanggal + harga) "
            f"atau (komoditas_id + tanggal + harga)"
        )


# ══════════════════════════════════════════════════════════════
#  CONFIG & MODEL IMPORT
# ══════════════════════════════════════════════════════════════

# Override CONFIG sebelum import (agar forecast_periods ikut args)
_CONFIG_OVERRIDE = {
    "forecast_periods": args.periods,
    "csv_path"        : args.csv,
    "freq"            : "W",
}

# Import modul utama
try:
    from hybrid_forecaster_v3_weekly import (
        HybridForecasterV3Weekly,
        CONFIG,
        get_single_commodity,
        compute_historical_inflation,
        compute_inflation_metrics,
    )
    CONFIG.update(_CONFIG_OVERRIDE)
except ImportError as e:
    print(f"\n  [Error] Tidak bisa import hybrid_forecaster_v3_weekly: {e}")
    print("  Pastikan file 'hybrid_forecaster_v3_weekly.py' ada di folder yang sama.")
    sys.exit(1)

try:
    from visualisasi_v3_weekly import run_all_charts
    HAS_VIZ = True
except ImportError:
    print("  [Warning] visualisasi_v3_weekly.py tidak ditemukan. Chart dilewati.")
    HAS_VIZ = False


# ══════════════════════════════════════════════════════════════
#  FUNGSI UTAMA: jalankan satu komoditas
# ══════════════════════════════════════════════════════════════

def run_single(df_all: pd.DataFrame, komoditas_id: int, nama: str,
               run_cv: bool = True, save_csv: bool = False) -> "HybridForecasterV3Weekly | None":
    """Jalankan full pipeline untuk satu komoditas."""

    print(f"\n{'─'*65}")
    print(f"  >> {nama} (id={komoditas_id})")
    print(f"{'─'*65}")

    # Filter data satu komoditas
    sub_df = df_all[df_all["komoditas_id"] == komoditas_id].copy()
    sub_df = sub_df.rename(columns={"tanggal": "ds", "harga": "y"})
    sub_df["ds"] = pd.to_datetime(sub_df["ds"])
    sub_df["y"]  = pd.to_numeric(sub_df["y"], errors="coerce")
    sub_df = sub_df.dropna(subset=["ds", "y"]).sort_values("ds").reset_index(drop=True)

    # Resample mingguan
    sub_df = sub_df.set_index("ds")["y"].resample("W").last().dropna().reset_index()
    sub_df.columns = ["ds", "y"]

    n = len(sub_df)
    print(f"  Data tersedia : {n} minggu "
          f"({sub_df['ds'].min().date()} → {sub_df['ds'].max().date()})")

    if n < 30:
        print(f"  [Skip] Data terlalu sedikit ({n} minggu, minimal 30).")
        return None

    # Buat instance forecaster
    fc = HybridForecasterV3Weekly(CONFIG)
    fc.komoditas_id = komoditas_id
    fc.df           = sub_df
    fc.results["komoditas_id"]   = komoditas_id
    fc.results["komoditas_name"] = nama
    fc.results["train_df"]       = sub_df
    fc.hist_inflation = compute_historical_inflation(sub_df)

    try:
        if run_cv:
            fc.run_cv()
        else:
            print("  [CV] Dilewati (--no-cv). Menggunakan bobot default.")

        fc.fit_all()
        fc.forecast()
        fc.print_summary()

        if save_csv:
            prefix = f"output_{komoditas_id}_{nama.replace(' ', '_')}"
            fc.save_outputs(prefix=prefix)

        return fc

    except Exception as e:
        import traceback
        print(f"  [Error] {nama}: {e}")
        traceback.print_exc()
        return None


# ══════════════════════════════════════════════════════════════
#  LOAD & TRANSFORM DATA
# ══════════════════════════════════════════════════════════════

print(f"\n{'='*65}")
print(f"  Hybrid Forecaster v3 MINGGUAN — Inflasi/Deflasi Komoditas")
print(f"  CSV     : {args.csv}")
print(f"  Forecast: {args.periods} minggu ke depan")
print(f"  CV      : {'Dilewati (--no-cv)' if args.no_cv else 'Aktif (3-fold rolling)'}")
print(f"{'='*65}")

df_all, id_nama_map = load_and_transform_csv(args.csv)

# ── Tentukan daftar komoditas yang akan dijalankan ──────────
if args.all:
    target_ids = sorted(id_nama_map.keys())

elif args.nama:
    # Cari by nama substring (case-insensitive)
    keyword = args.nama.lower()
    target_ids = [
        kid for kid, nama in id_nama_map.items()
        if keyword in nama.lower()
    ]
    if not target_ids:
        print(f"\n  [Error] Tidak ada komoditas dengan nama mengandung '{args.nama}'")
        print(f"  Komoditas tersedia: {list(id_nama_map.values())}")
        sys.exit(1)
    print(f"\n  Komoditas ditemukan untuk '{args.nama}':")
    for kid in target_ids:
        print(f"    id={kid} → {id_nama_map[kid]}")

else:
    # by --kid
    if args.kid not in id_nama_map:
        print(f"\n  [Error] id={args.kid} tidak ditemukan di data.")
        print(f"  ID tersedia: {sorted(id_nama_map.keys())}")
        sys.exit(1)
    target_ids = [args.kid]


# ══════════════════════════════════════════════════════════════
#  JALANKAN FORECAST
# ══════════════════════════════════════════════════════════════

all_results = {}

for kid in target_ids:
    nama = id_nama_map[kid]
    fc   = run_single(
        df_all      = df_all,
        komoditas_id = kid,
        nama        = nama,
        run_cv      = not args.no_cv,
        save_csv    = args.save_csv,
    )
    if fc is not None:
        all_results[kid] = fc


# ══════════════════════════════════════════════════════════════
#  VISUALISASI
# ══════════════════════════════════════════════════════════════

if HAS_VIZ and all_results:
    print(f"\n  [Visualisasi] Membuat chart untuk {len(all_results)} komoditas...")
    for kid, fc in all_results.items():
        nama   = id_nama_map[kid]
        prefix = f"{kid}_{nama.replace(' ', '_')}"
        try:
            run_all_charts(fc, output_prefix=prefix)
        except Exception as e:
            print(f"  [Chart Error] {nama}: {e}")


# ══════════════════════════════════════════════════════════════
#  RINGKASAN AKHIR SEMUA KOMODITAS
# ══════════════════════════════════════════════════════════════

if len(all_results) > 1:
    print(f"\n\n{'='*70}")
    print(f"  RINGKASAN INFLASI/DEFLASI — SEMUA KOMODITAS (6 Bulan ke Depan)")
    print(f"{'='*70}")
    print(f"  {'Komoditas':<32} {'Harga Terakhir':>15} {'Δ6Bulan':>9} "
          f"{'Annual':>9} {'Status'}")
    print("  " + "─" * 80)

    for kid, fc in all_results.items():
        if fc.inflation_df is not None and len(fc.inflation_df) > 0:
            last  = fc.inflation_df.iloc[-1]
            harga = fc.df["y"].iloc[-1]
            nama  = id_nama_map[kid]
            print(f"  {nama:<32} Rp{harga:>10,.0f} "
                  f"  {last['pct_26w']:>+7.2f}% "
                  f"  {last['annualized_rate']:>+7.2f}%/th "
                  f"  {last['inflation_status']}")

    print("=" * 70)


# ── Tampilkan atau simpan chart ──────────────────────────────
if not args.no_show and HAS_VIZ and all_results:
    print("\n  Tutup window chart untuk keluar dari program.")
    plt.show(block=True)
elif HAS_VIZ and all_results:
    print("\n  Mode --no-show: chart disimpan sebagai file PNG.")

print("\n  [Done] Selesai.\n")