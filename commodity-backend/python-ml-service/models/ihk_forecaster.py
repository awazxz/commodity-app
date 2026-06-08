"""
models/ihk_forecaster.py
=========================
Modul forecast IHK dan inflasi menggunakan Prophet.

Alur:
  1. Ambil data IHK historis dari ihk_bulanan
  2. Train Prophet pada data IHK
  3. Forecast IHK N bulan ke depan
  4. Hitung prediksi inflasi MtoM, YtD, YoY dari IHK forecast
  5. Tentukan kondisi prediksi: inflasi / deflasi / stabil
  6. Simpan hasil ke ihk_forecast_bulanan

Catatan metodologi:
  - Prophet dijalankan pada nilai IHK (bukan harga mentah)
  - Inflasi MtoM forecast = (IHK_forecast_t / IHK_forecast_{t-1} - 1) × 100
  - Inflasi YtD  forecast = (IHK_forecast_t / IHK_Des_{tahun lalu} - 1) × 100
  - Inflasi YoY  forecast = (IHK_forecast_t / IHK_{t-12} - 1) × 100
    -> untuk YoY: jika t-12 ada di historis, pakai historis
    -> jika t-12 ada di forecast, pakai forecast
  - Kondisi: inflasi >+0.1%, deflasi <-0.1%, stabil lainnya

Tabel DB:
  READ  : ihk_bulanan
  WRITE : ihk_forecast_bulanan

v2.0 — fix indentasi get_inflasi_forecast_summary + filter bulan dinamis
"""

import warnings
import numpy as np
import pandas as pd
from typing import Optional
from sqlalchemy import text

from models.prophet_forecasting import (
    CommodityForecastModel,
    _build_prophet,
    _merge_forecast_actual,
)

warnings.filterwarnings('ignore')


# ═══════════════════════════════════════════════════════════════
# KONSTANTA
# ═══════════════════════════════════════════════════════════════

STABIL_THRESHOLD   = 0.1    # ±0.1% sesuai BPS
IHK_FORECAST_TABLE = 'ihk_forecast_bulanan'

# Hyperparameter default Prophet untuk IHK
# IHK lebih smooth dari harga mentah -> cp lebih kecil, seasonality lebih sederhana
IHK_PROPHET_PARAMS = {
    'changepoint_prior_scale': 0.05,
    'seasonality_prior_scale': 5.0,
    'seasonality_mode':        'additive',
    'weekly_seasonality':      False,
    'yearly_seasonality':      True,
    'yearly_fourier_order':    5,
    'monthly_seasonality':     True,
    'n_changepoints':          10,
    'changepoint_range':       0.80,
    'interval_width':          0.80,
}


# ═══════════════════════════════════════════════════════════════
# MAIN CLASS
# ═══════════════════════════════════════════════════════════════

class IHKForecaster:
    """
    Forecast IHK dan inflasi menggunakan Prophet.

    Cara pakai:
        forecaster = IHKForecaster(db_connector)
        forecaster.forecast(periods=6)                   # forecast + simpan ke DB
        forecaster.get_forecast_result()                 # ambil hasil forecast dari DB
        forecaster.get_forecast_vs_aktual()              # forecast vs aktual (jika ada)
        forecaster.get_inflasi_forecast_summary('2025-03')  # ringkasan untuk bulan tertentu
    """

    def __init__(self, db_connector):
        self.db = db_connector

    # ═══════════════════════════════════════════════════════
    # PRIVATE — Helpers
    # ═══════════════════════════════════════════════════════

    def _safe_float(self, val):
        if val is None:
            return None
        try:
            f = float(val)
            return None if np.isnan(f) else f
        except (TypeError, ValueError):
            return None

    def _kondisi(self, val):
        v = self._safe_float(val)
        if v is None:
            return None
        if v > STABIL_THRESHOLD:
            return 'inflasi'
        elif v < -STABIL_THRESHOLD:
            return 'deflasi'
        return 'stabil'

    # ═══════════════════════════════════════════════════════
    # PRIVATE — Data Fetching
    # ═══════════════════════════════════════════════════════

    def _get_ihk_historis(self) -> pd.DataFrame:
        """Ambil semua data IHK historis dari ihk_bulanan."""
        query = """
            SELECT tanggal, nilai_ihk, inflasi, inflasi_ytd, inflasi_yoy, kondisi
            FROM ihk_bulanan
            ORDER BY tanggal ASC
        """
        with self.db.engine.connect() as conn:
            rows = conn.execute(text(query)).fetchall()

        if not rows:
            return pd.DataFrame()

        df = pd.DataFrame(rows, columns=[
            'tanggal', 'nilai_ihk', 'inflasi',
            'inflasi_ytd', 'inflasi_yoy', 'kondisi',
        ])
        df['tanggal']   = pd.to_datetime(df['tanggal'])
        df['nilai_ihk'] = df['nilai_ihk'].astype(float)
        return df

    # ═══════════════════════════════════════════════════════
    # PRIVATE — Kalkulasi Inflasi dari IHK Forecast
    # ═══════════════════════════════════════════════════════

    def _hitung_inflasi_forecast(
        self,
        df_forecast: pd.DataFrame,
        df_historis: pd.DataFrame,
    ) -> pd.DataFrame:
        """
        Hitung inflasi MtoM, YtD, YoY dari IHK forecast.

        Untuk t-1 dan t-12:
          - Prioritaskan data historis (lebih akurat)
          - Jika tidak ada di historis, pakai data forecast
        """
        df = df_forecast.copy().sort_values('tanggal').reset_index(drop=True)

        # Lookup IHK: historis + forecast (historis prioritas)
        ihk_hist_lookup = df_historis.set_index('tanggal')['nilai_ihk'].to_dict()
        ihk_fc_lookup   = df.set_index('tanggal')['nilai_ihk_forecast'].to_dict()

        def _get_ihk(tanggal):
            return ihk_hist_lookup.get(tanggal) or ihk_fc_lookup.get(tanggal)

        # ── M-to-M ──────────────────────────────────────────────────────────
        inflasi_mtom = []
        for _, row in df.iterrows():
            t_prev   = (row['tanggal'] - pd.DateOffset(months=1)).replace(day=1)
            ihk_prev = _get_ihk(t_prev)
            if ihk_prev is None or ihk_prev == 0:
                inflasi_mtom.append(None)
            else:
                inflasi_mtom.append(
                    (row['nilai_ihk_forecast'] / ihk_prev - 1) * 100
                )
        df['inflasi_mtom_forecast'] = inflasi_mtom

        # ── Y-to-D: vs IHK Desember tahun lalu ──────────────────────────────
        ihk_des_lookup = {}
        for _, row in df_historis[df_historis['tanggal'].dt.month == 12].iterrows():
            ihk_des_lookup[row['tanggal'].year] = float(row['nilai_ihk'])

        inflasi_ytd = []
        for _, row in df.iterrows():
            ihk_des = ihk_des_lookup.get(row['tanggal'].year - 1)
            if ihk_des is None or ihk_des == 0:
                inflasi_ytd.append(None)
            else:
                inflasi_ytd.append(
                    (row['nilai_ihk_forecast'] / ihk_des - 1) * 100
                )
        df['inflasi_ytd_forecast'] = inflasi_ytd

        # ── Y-on-Y: vs bulan sama 12 bulan lalu ─────────────────────────────
        inflasi_yoy = []
        for _, row in df.iterrows():
            t_12   = (row['tanggal'] - pd.DateOffset(months=12)).replace(day=1)
            ihk_12 = _get_ihk(t_12)
            if ihk_12 is None or ihk_12 == 0:
                inflasi_yoy.append(None)
            else:
                inflasi_yoy.append(
                    (row['nilai_ihk_forecast'] / ihk_12 - 1) * 100
                )
        df['inflasi_yoy_forecast'] = inflasi_yoy

        # ── Kondisi ─────────────────────────────────────────────────────────
        df['kondisi_forecast'] = df['inflasi_mtom_forecast'].apply(self._kondisi)

        return df

    # ═══════════════════════════════════════════════════════
    # PRIVATE — Simpan ke DB
    # ═══════════════════════════════════════════════════════

    def _save_forecast_to_db(self, df: pd.DataFrame, periods: int) -> int:
        """Simpan hasil forecast IHK ke ihk_forecast_bulanan."""
        if df.empty:
            return 0

        query = """
            INSERT INTO ihk_forecast_bulanan
                (tanggal, nilai_ihk_forecast, ihk_lower, ihk_upper,
                 inflasi_mtom_forecast, inflasi_ytd_forecast, inflasi_yoy_forecast,
                 kondisi_forecast, periods, dibuat_pada)
            VALUES
                (:tanggal, :nilai_ihk_forecast, :ihk_lower, :ihk_upper,
                 :inflasi_mtom_forecast, :inflasi_ytd_forecast, :inflasi_yoy_forecast,
                 :kondisi_forecast, :periods, NOW())
            ON DUPLICATE KEY UPDATE
                nilai_ihk_forecast     = VALUES(nilai_ihk_forecast),
                ihk_lower              = VALUES(ihk_lower),
                ihk_upper              = VALUES(ihk_upper),
                inflasi_mtom_forecast  = VALUES(inflasi_mtom_forecast),
                inflasi_ytd_forecast   = VALUES(inflasi_ytd_forecast),
                inflasi_yoy_forecast   = VALUES(inflasi_yoy_forecast),
                kondisi_forecast       = VALUES(kondisi_forecast),
                periods                = VALUES(periods),
                dibuat_pada            = NOW(),
                updated_at             = CURRENT_TIMESTAMP
        """
        sf    = self._safe_float
        count = 0
        with self.db.engine.begin() as conn:
            for _, row in df.iterrows():
                conn.execute(text(query), {
                    'tanggal':               row['tanggal'].strftime('%Y-%m-%d'),
                    'nilai_ihk_forecast':    float(row['nilai_ihk_forecast']),
                    'ihk_lower':             float(row['ihk_lower']),
                    'ihk_upper':             float(row['ihk_upper']),
                    'inflasi_mtom_forecast': sf(row.get('inflasi_mtom_forecast')),
                    'inflasi_ytd_forecast':  sf(row.get('inflasi_ytd_forecast')),
                    'inflasi_yoy_forecast':  sf(row.get('inflasi_yoy_forecast')),
                    'kondisi_forecast':      row.get('kondisi_forecast'),
                    'periods':               periods,
                })
                count += 1
        return count

    # ═══════════════════════════════════════════════════════
    # PUBLIC — Forecast
    # ═══════════════════════════════════════════════════════

    def forecast(self, periods: int = 12) -> dict:
        """
        Forecast IHK dan inflasi N bulan ke depan.

        Args:
            periods: jumlah bulan ke depan (default 6, max 24)
        """
        periods = max(1, min(periods, 24))
        print(f"[IHKForecast] Memulai forecast {periods} bulan ke depan...")

        # 1. Ambil data historis IHK
        df_historis = self._get_ihk_historis()
        if df_historis.empty:
            return {
                'success': False,
                'message': 'Tidak ada data IHK historis. Jalankan kalkulasi IHK dulu.',
            }

        n_data = len(df_historis)
        print(f"[IHKForecast] Data historis: {n_data} bulan "
              f"({df_historis['tanggal'].min().strftime('%Y-%m')} s/d "
              f"{df_historis['tanggal'].max().strftime('%Y-%m')})")

        if n_data < 12:
            return {
                'success': False,
                'message': f'Data IHK terlalu sedikit ({n_data} bulan). '
                           f'Minimal 12 bulan untuk forecast yang reliable.',
            }

        # 2. Siapkan data Prophet (ds, y)
        df_prophet = df_historis[['tanggal', 'nilai_ihk']].rename(columns={
            'tanggal':   'ds',
            'nilai_ihk': 'y',
        }).copy()

        last_date = df_prophet['ds'].max()

        # 3. Build dan train Prophet
        print(f"[IHKForecast] Training Prophet (cp={IHK_PROPHET_PARAMS['changepoint_prior_scale']}, "
              f"mode={IHK_PROPHET_PARAMS['seasonality_mode']})...")
        try:
            m = _build_prophet(**IHK_PROPHET_PARAMS)
            m.fit(df_prophet[['ds', 'y']])
        except Exception as e:
            return {'success': False, 'message': f'Gagal training Prophet: {str(e)}'}

        # 4. Generate forecast
        print(f"[IHKForecast] Generating forecast {periods} bulan...")
        try:
            future   = m.make_future_dataframe(periods=periods + 2, freq='MS')
            forecast = m.predict(future)
        except Exception as e:
            return {'success': False, 'message': f'Gagal generate forecast: {str(e)}'}

        # 5. Filter hanya bulan forecast (setelah last_date)
        fc_future = forecast[forecast['ds'] > last_date].head(periods).copy()
        fc_future = fc_future.reset_index(drop=True)

        if fc_future.empty:
            return {'success': False, 'message': f'Tidak ada forecast setelah {last_date.date()}.'}

        # Clip negatif (IHK tidak mungkin negatif)
        fc_future['yhat']       = fc_future['yhat'].clip(lower=0)
        fc_future['yhat_lower'] = fc_future['yhat_lower'].clip(lower=0)
        fc_future['yhat_upper'] = fc_future['yhat_upper'].clip(lower=0)

        # 6. Bentuk DataFrame forecast
        df_forecast = pd.DataFrame({
            'tanggal':            fc_future['ds'],
            'nilai_ihk_forecast': fc_future['yhat'],
            'ihk_lower':          fc_future['yhat_lower'],
            'ihk_upper':          fc_future['yhat_upper'],
        })

        # 7. Hitung inflasi dari IHK forecast
        print("[IHKForecast] Menghitung inflasi forecast (MtoM, YtD, YoY)...")
        df_forecast = self._hitung_inflasi_forecast(df_forecast, df_historis)

        # 8. Simpan ke DB
        print("[IHKForecast] Menyimpan ke database...")
        saved = self._save_forecast_to_db(df_forecast, periods)
        print(f"[IHKForecast] Tersimpan: {saved} baris ke ihk_forecast_bulanan")

        # 9. Hitung akurasi model pada data historis (in-sample sederhana)
        fc_history  = forecast[forecast['ds'] <= last_date].copy()
        merged_hist = _merge_forecast_actual(
            fc_history[['ds', 'yhat', 'yhat_lower', 'yhat_upper']],
            df_prophet[['ds', 'y']],
        )
        mape_insample = 0.0
        if len(merged_hist) > 0:
            mape_insample = CommodityForecastModel._mape(
                merged_hist['y'].values,
                merged_hist['yhat'].values,
            )

        sf = self._safe_float
        hasil_forecast = []
        for _, row in df_forecast.iterrows():
            hasil_forecast.append({
                'periode':               row['tanggal'].strftime('%Y-%m'),
                'tanggal':               row['tanggal'].strftime('%Y-%m-%d'),
                'nilai_ihk_forecast':    round(float(row['nilai_ihk_forecast']), 6),
                'ihk_lower':             round(float(row['ihk_lower']), 6),
                'ihk_upper':             round(float(row['ihk_upper']), 6),
                'inflasi_mtom_forecast': sf(row.get('inflasi_mtom_forecast')),
                'inflasi_ytd_forecast':  sf(row.get('inflasi_ytd_forecast')),
                'inflasi_yoy_forecast':  sf(row.get('inflasi_yoy_forecast')),
                'kondisi_forecast':      row.get('kondisi_forecast'),
            })

        return {
            'success': True,
            'periods': periods,
            'last_data_historis': last_date.strftime('%Y-%m'),
            'forecast_mulai':     df_forecast['tanggal'].min().strftime('%Y-%m'),
            'forecast_sampai':    df_forecast['tanggal'].max().strftime('%Y-%m'),
            'model_info': {
                'engine':          'prophet',
                'n_data_historis': n_data,
                'mape_insample':   round(mape_insample, 4),
                'hyperparams':     IHK_PROPHET_PARAMS,
            },
            'forecast': hasil_forecast,
            'saved':    saved,
        }

    # ═══════════════════════════════════════════════════════
    # PUBLIC — Query hasil forecast dari DB
    # ═══════════════════════════════════════════════════════

    def get_forecast_result(self, limit: int = 24) -> dict:
        """Ambil hasil forecast IHK terbaru dari DB."""
        query = """
            SELECT
                tanggal, nilai_ihk_forecast, ihk_lower, ihk_upper,
                inflasi_mtom_forecast, inflasi_ytd_forecast, inflasi_yoy_forecast,
                kondisi_forecast, dibuat_pada
            FROM ihk_forecast_bulanan
            ORDER BY tanggal ASC
            LIMIT :limit
        """
        with self.db.engine.connect() as conn:
            rows = conn.execute(text(query), {'limit': limit}).fetchall()

        if not rows:
            return {
                'success': False,
                'message': 'Belum ada forecast IHK. Jalankan POST /api/ihk/forecast dulu.',
            }

        sf = self._safe_float
        return {
            'success': True,
            'data': {
                'total':       len(rows),
                'dibuat_pada': str(rows[0][8])[:16] if rows else None,
                'forecast': [
                    {
                        'periode':               str(r[0])[:7],
                        'tanggal':               str(r[0]),
                        'nilai_ihk_forecast':    round(float(r[1]), 6),
                        'ihk_lower':             round(float(r[2]), 6),
                        'ihk_upper':             round(float(r[3]), 6),
                        'inflasi_mtom_forecast': sf(r[4]),
                        'inflasi_ytd_forecast':  sf(r[5]),
                        'inflasi_yoy_forecast':  sf(r[6]),
                        'kondisi_forecast':      r[7],
                    }
                    for r in rows
                ],
            },
        }

    def get_forecast_vs_aktual(self) -> dict:
        """
        Bandingkan hasil forecast dengan IHK aktual (jika sudah tersedia).
        Berguna untuk evaluasi akurasi forecast bulan sebelumnya.
        """
        query = """
            SELECT
                f.tanggal,
                f.nilai_ihk_forecast,
                f.ihk_lower,
                f.ihk_upper,
                f.inflasi_mtom_forecast,
                f.kondisi_forecast,
                a.nilai_ihk   AS nilai_ihk_aktual,
                a.inflasi     AS inflasi_mtom_aktual,
                a.kondisi     AS kondisi_aktual
            FROM ihk_forecast_bulanan f
            LEFT JOIN ihk_bulanan a
                ON DATE_FORMAT(f.tanggal, '%Y-%m') = DATE_FORMAT(a.tanggal, '%Y-%m')
            ORDER BY f.tanggal ASC
        """
        with self.db.engine.connect() as conn:
            rows = conn.execute(text(query)).fetchall()

        if not rows:
            return {'success': False, 'message': 'Belum ada data forecast.'}

        sf        = self._safe_float
        hasil     = []
        mape_vals = []

        for r in rows:
            ihk_fc  = float(r[1])
            ihk_act = sf(r[6])

            error_pct   = None
            in_interval = None
            if ihk_act is not None:
                error_pct   = abs(ihk_fc - ihk_act) / ihk_act * 100 if ihk_act != 0 else None
                in_interval = float(r[2]) <= ihk_act <= float(r[3])
                if error_pct is not None:
                    mape_vals.append(error_pct)

            hasil.append({
                'periode':               str(r[0])[:7],
                'nilai_ihk_forecast':    round(ihk_fc, 6),
                'ihk_lower':             round(float(r[2]), 6),
                'ihk_upper':             round(float(r[3]), 6),
                'inflasi_mtom_forecast': sf(r[4]),
                'kondisi_forecast':      r[5],
                'nilai_ihk_aktual':      round(ihk_act, 6) if ihk_act else None,
                'inflasi_mtom_aktual':   sf(r[7]),
                'kondisi_aktual':        r[8],
                'error_pct':             round(error_pct, 4) if error_pct is not None else None,
                'in_interval':           in_interval,
                'sudah_terealisasi':     ihk_act is not None,
            })

        mape_realized = round(float(np.mean(mape_vals)), 4) if mape_vals else None

        return {
            'success': True,
            'data': {
                'total':         len(hasil),
                'n_terealisasi': len(mape_vals),
                'mape_realized': mape_realized,
                'hasil':         hasil,
            },
        }

    def get_inflasi_forecast_summary(self, bulan: str = None) -> dict:
        """
        Ringkasan forecast inflasi — cocok untuk widget dashboard.

        Args:
            bulan: format 'YYYY-MM' — bulan yang sedang dilihat user (bukan bulan forecast).
                   Forecast yang ditampilkan adalah bulan SETELAH bulan ini.
                   Default: bulan data historis terakhir di ihk_bulanan.

        Returns:
            dict dengan bulan_depan (forecast +1) dan trend 3 bulan ke depan.
        """
        # ── 1. Tentukan tanggal referensi ────────────────────────────────────
        if bulan:
            try:
                ref_date = pd.to_datetime(bulan + '-01')
            except Exception:
                return {
                    'success': False,
                    'message': f'Format bulan tidak valid: {bulan}. Gunakan format YYYY-MM.',
                }
        else:
            # Default: bulan terakhir dari data historis (bukan dari tabel forecast)
            with self.db.engine.connect() as conn:
                result = conn.execute(text(
                    "SELECT MAX(tanggal) FROM ihk_bulanan"
                )).fetchone()
            if not result or not result[0]:
                return {'success': False, 'message': 'Tidak ada data IHK historis.'}
            ref_date = pd.to_datetime(result[0]).replace(day=1)

        # ── 2. Forecast dimulai dari bulan setelah ref_date ──────────────────
        next_month = (ref_date + pd.DateOffset(months=1)).replace(day=1)

        # ← TAMBAHKAN DI SINI: cek apakah next_month persis ada di tabel
        check_query = """
            SELECT COUNT(*) FROM ihk_forecast_bulanan
            WHERE DATE_FORMAT(tanggal, '%Y-%m') = :target
        """
        with self.db.engine.connect() as conn:
            count = conn.execute(text(check_query), {
                'target': next_month.strftime('%Y-%m')
            }).scalar()

        if not count:
            return {
                'success': False,
                'message': (
                    f'Belum ada forecast untuk periode setelah '
                    f'{ref_date.strftime("%Y-%m")}.'
                ),
            }
        query = """
            SELECT
                tanggal,
                nilai_ihk_forecast,
                ihk_lower,
                ihk_upper,
                inflasi_mtom_forecast,
                inflasi_ytd_forecast,
                inflasi_yoy_forecast,
                kondisi_forecast
            FROM ihk_forecast_bulanan
            WHERE tanggal >= :next_month
            ORDER BY tanggal ASC
            LIMIT 3
        """
        with self.db.engine.connect() as conn:
            rows = conn.execute(text(query), {
                'next_month': next_month.strftime('%Y-%m-%d'),
            }).fetchall()

        # ── 3. Validasi hasil ────────────────────────────────────────────────
        if not rows:
            return {
                'success': False,
                'message': (
                    f'Belum ada forecast untuk periode setelah {ref_date.strftime("%Y-%m")}. '
                    f'Jalankan POST /api/ihk/forecast dulu.'
                ),
            }

        # ── 4. Hitung trend 3 bulan ──────────────────────────────────────────
        sf        = self._safe_float
        r0        = rows[0]
        mtom_vals = [sf(r[4]) for r in rows if sf(r[4]) is not None]

        if len(mtom_vals) >= 2:
            trend_inflasi = (
                'meningkat' if mtom_vals[-1] > mtom_vals[0]
                else 'menurun' if mtom_vals[-1] < mtom_vals[0]
                else 'stabil'
            )
        else:
            trend_inflasi = 'stabil'

        # ── 5. Return ────────────────────────────────────────────────────────
        return {
            'success': True,
            'data': {
                'ref_bulan':   ref_date.strftime('%Y-%m'),   # bulan yang difilter user
                'bulan_depan': {
                    'periode':               str(r0[0])[:7],
                    'nilai_ihk_forecast':    round(float(r0[1]), 6),
                    'ihk_lower':             round(float(r0[2]), 6),
                    'ihk_upper':             round(float(r0[3]), 6),
                    'inflasi_mtom_forecast': sf(r0[4]),
                    'inflasi_ytd_forecast':  sf(r0[5]),
                    'inflasi_yoy_forecast':  sf(r0[6]),
                    'kondisi_forecast':      r0[7],
                },
                'trend_3_bulan': trend_inflasi,
                'forecast_3_bulan': [
                    {
                        'periode':               str(r[0])[:7],
                        'nilai_ihk_forecast':    round(float(r[1]), 6),
                        'inflasi_mtom_forecast': sf(r[4]),
                        'kondisi_forecast':      r[7],
                    }
                    for r in rows
                ],
            },
        }