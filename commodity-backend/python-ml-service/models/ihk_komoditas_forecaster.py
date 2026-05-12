"""
python-ml-service/models/ihk_komoditas_forecaster.py
======================================================
Modul forecast IHK per komoditas menggunakan Prophet.

METODOLOGI:
─────────────────────────────────────────────────────
  Modul ini melengkapi ihk_forecaster.py (IHK agregat) dengan forecast
  IHK untuk masing-masing komoditas secara individual.

  IHK per komoditas sudah dihitung oleh ihk_calculator.py via chain NK:
    NK_t    = RH_t × NK_{t-1} / 100
    IHK_i_t = ROUND(NK_t / NK_dasar × 100, 2)
  Hasil tersimpan di andil_inflasi_bulanan.nilai_ihk_komoditas.

  Modul ini menggunakan nilai_ihk_komoditas sebagai input Prophet
  untuk menghasilkan forecast IHK per komoditas N bulan ke depan.

  Andil forecast dihitung menggunakan bobot dari bobot_komoditas
  (share dari IHK umum BPS, bukan bobot internal 21 komoditas):
    inflasi_MtM_i = IHK_fc_i_t / IHK_i_{t-1} × 100 − 100
    andil_i       = bobot_i / 100 × inflasi_MtM_i

  User dapat memilih periode forecast bebas: 1–12 bulan ke depan.

TABEL DB:
  READ  : andil_inflasi_bulanan, master_komoditas, bobot_komoditas
  WRITE : ihk_komoditas_forecast

DDL (jalankan sekali):
─────────────────────────────────────────────────────
  CREATE TABLE ihk_komoditas_forecast (
      id                     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      komoditas_id           INT UNSIGNED NOT NULL,
      tanggal                DATE NOT NULL,
      nilai_ihk_forecast     DECIMAL(12,6) NOT NULL,
      ihk_lower              DECIMAL(12,6),
      ihk_upper              DECIMAL(12,6),
      inflasi_mtom_forecast  DECIMAL(10,6),
      andil_forecast         DECIMAL(10,6),
      kondisi_forecast       VARCHAR(10),
      mape_insample          DECIMAL(10,4),
      n_data_historis        INT,
      periods                INT,
      dibuat_pada            DATETIME DEFAULT CURRENT_TIMESTAMP,
      updated_at             DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY uq_kmd_tgl (komoditas_id, tanggal),
      KEY idx_tanggal (tanggal),
      KEY idx_komoditas (komoditas_id)
  );

CARA RUNNING:
─────────────────────────────────────────────────────
  from models.ihk_komoditas_forecaster import IHKKomoditasForecaster

  fc = IHKKomoditasForecaster(db_connector)

  # Forecast semua komoditas, N bulan ke depan (1–12)
  result = fc.forecast_all(periods=6)

  # Forecast komoditas tertentu saja
  result = fc.forecast_all(periods=3, komoditas_ids=[1, 2, 5])

  # Ambil hasil forecast dari DB untuk bulan tertentu
  data = fc.get_forecast_bulan('2025-08')

  # Ringkasan bulan depan dari bulan referensi tertentu
  summary = fc.get_forecast_summary(bulan_referensi='2025-07')

  # Data historis + forecast per komoditas (untuk chart)
  detail = fc.get_ihk_historis_dan_forecast(komoditas_id=1, n_hist=12)

INTEGRASI DENGAN ENDPOINT LARAVEL:
─────────────────────────────────────────────────────
  Endpoint yang memanggil forecast_all() sebaiknya menerima parameter
  ?periods=N dari request, lalu teruskan ke fc.forecast_all(periods=N).
  Dengan begitu user bisa memilih rentang forecast secara bebas.

v2.0 — Koreksi: bobot andil dari bobot_komoditas (BPS), bukan bobot_dinamis (NK internal)
"""

import warnings
import numpy as np
import pandas as pd
from typing import Optional, List
from sqlalchemy import text

warnings.filterwarnings('ignore')

# ─── Prophet import dengan fallback ───────────────────────────────────────────
try:
    from models.prophet_forecasting import _build_prophet
    _PROPHET_FROM_MODULE = True
except ImportError:
    _PROPHET_FROM_MODULE = False
    try:
        from prophet import Prophet

        def _build_prophet(**kwargs):
            m = Prophet(
                changepoint_prior_scale=kwargs.get('changepoint_prior_scale', 0.05),
                seasonality_prior_scale=kwargs.get('seasonality_prior_scale', 5.0),
                seasonality_mode=kwargs.get('seasonality_mode', 'additive'),
                yearly_seasonality=kwargs.get('yearly_seasonality', True),
                weekly_seasonality=kwargs.get('weekly_seasonality', False),
                n_changepoints=kwargs.get('n_changepoints', 10),
                changepoint_range=kwargs.get('changepoint_range', 0.80),
                interval_width=kwargs.get('interval_width', 0.80),
            )
            if kwargs.get('monthly_seasonality', True):
                m.add_seasonality(
                    name='monthly',
                    period=30.5,
                    fourier_order=kwargs.get('yearly_fourier_order', 3),
                )
            return m

    except ImportError:
        raise ImportError("Prophet tidak ditemukan. Install: pip install prophet")


# ══════════════════════════════════════════════════════════════════════════════
# KONSTANTA
# ══════════════════════════════════════════════════════════════════════════════

STABIL_THRESHOLD = 0.1   # ±0.1% sesuai threshold BPS
MIN_DATA_BULAN   = 12    # minimal historis agar forecast reliable
MAX_PERIODS      = 12    # batas atas periode forecast (1 tahun)

# Hyperparameter Prophet untuk IHK per komoditas.
# IHK komoditas lebih volatile dari IHK agregat sehingga
# changepoint_prior_scale sedikit lebih besar.
KOMODITAS_PROPHET_PARAMS = {
    'changepoint_prior_scale': 0.10,
    'seasonality_prior_scale': 3.0,
    'seasonality_mode':        'additive',
    'weekly_seasonality':      False,
    'yearly_seasonality':      True,
    'yearly_fourier_order':    3,
    'monthly_seasonality':     True,
    'n_changepoints':          8,
    'changepoint_range':       0.80,
    'interval_width':          0.80,
}


# ══════════════════════════════════════════════════════════════════════════════
# MAIN CLASS
# ══════════════════════════════════════════════════════════════════════════════

class IHKKomoditasForecaster:
    """
    Forecast IHK per komoditas menggunakan Prophet.

    Perbedaan dengan IHKForecaster (agregat):
      - IHKForecaster          : 1 model Prophet → 1 IHK agregat berbobot
      - IHKKomoditasForecaster : N model Prophet → N IHK per komoditas
    """

    def __init__(self, db_connector):
        self.db = db_connector

    # ══════════════════════════════════════════════════════════════
    # HELPERS
    # ══════════════════════════════════════════════════════════════

    def _safe_float(self, val):
        if val is None:
            return None
        try:
            f = float(val)
            return None if np.isnan(f) else f
        except (TypeError, ValueError):
            return None

    def _kondisi(self, val: Optional[float]) -> Optional[str]:
        v = self._safe_float(val)
        if v is None:
            return None
        if v > STABIL_THRESHOLD:
            return 'inflasi'
        elif v < -STABIL_THRESHOLD:
            return 'deflasi'
        return 'stabil'

    @staticmethod
    def _mape(actual: np.ndarray, predicted: np.ndarray) -> float:
        mask = actual != 0
        if mask.sum() == 0:
            return 0.0
        return float(
            np.mean(np.abs((actual[mask] - predicted[mask]) / actual[mask])) * 100
        )

    # ══════════════════════════════════════════════════════════════
    # DATA FETCHING
    # ══════════════════════════════════════════════════════════════

    def _get_daftar_komoditas(
        self,
        komoditas_ids: Optional[List[int]] = None,
    ) -> pd.DataFrame:
        """Ambil daftar komoditas yang punya data IHK di andil_inflasi_bulanan."""
        extra  = ""
        params = {}
        if komoditas_ids:
            placeholders = ', '.join([f':id_{i}' for i in range(len(komoditas_ids))])
            params       = {f'id_{i}': kid for i, kid in enumerate(komoditas_ids)}
            extra        = f"AND mk.id IN ({placeholders})"

        query = f"""
            SELECT DISTINCT
                mk.id            AS komoditas_id,
                mk.nama_komoditas,
                mk.nama_varian
            FROM master_komoditas mk
            JOIN andil_inflasi_bulanan aib ON aib.komoditas_id = mk.id
            WHERE aib.nilai_ihk_komoditas IS NOT NULL
              AND aib.nilai_ihk_komoditas > 0
              {extra}
            ORDER BY mk.id
        """
        with self.db.engine.connect() as conn:
            rows = conn.execute(text(query), params).fetchall()

        if not rows:
            return pd.DataFrame(columns=['komoditas_id', 'nama_komoditas', 'nama_varian'])

        return pd.DataFrame(rows, columns=['komoditas_id', 'nama_komoditas', 'nama_varian'])

    def _get_bobot_komoditas(self, komoditas_id: int) -> Optional[float]:
        """
        Ambil bobot terbaru dari bobot_komoditas untuk satu komoditas.

        Bobot ini adalah share dari IHK umum BPS (total ~13% untuk 21 komoditas).
        Digunakan untuk menghitung andil forecast, bukan bobot_dinamis dari NK internal.

        Returns:
            nilai_bobot terbaru, atau None jika tidak ada.
        """
        query = """
            SELECT nilai_bobot
            FROM bobot_komoditas
            WHERE komoditas_id = :kid
            ORDER BY tanggal DESC
            LIMIT 1
        """
        with self.db.engine.connect() as conn:
            row = conn.execute(text(query), {'kid': komoditas_id}).fetchone()

        return float(row[0]) if row else None

    def _get_ihk_historis_komoditas(self, komoditas_id: int) -> pd.DataFrame:
        """
        Ambil riwayat IHK per komoditas dari andil_inflasi_bulanan.

        Kolom:
          - tanggal
          - nilai_ihk_komoditas  → input Prophet (y)
        """
        query = """
            SELECT
                tanggal,
                nilai_ihk_komoditas
            FROM andil_inflasi_bulanan
            WHERE komoditas_id = :kid
              AND nilai_ihk_komoditas IS NOT NULL
              AND nilai_ihk_komoditas > 0
            ORDER BY tanggal ASC
        """
        with self.db.engine.connect() as conn:
            rows = conn.execute(text(query), {'kid': komoditas_id}).fetchall()

        if not rows:
            return pd.DataFrame()

        df = pd.DataFrame(rows, columns=['tanggal', 'nilai_ihk_komoditas'])
        df['tanggal']             = pd.to_datetime(df['tanggal'])
        df['nilai_ihk_komoditas'] = df['nilai_ihk_komoditas'].astype(float)
        return df.sort_values('tanggal').reset_index(drop=True)

    # ══════════════════════════════════════════════════════════════
    # FORECAST SATU KOMODITAS
    # ══════════════════════════════════════════════════════════════

    def _forecast_satu_komoditas(
        self,
        komoditas_id:   int,
        nama_komoditas: str,
        periods:        int,
    ) -> dict:
        """
        Jalankan Prophet untuk satu komoditas.

        Returns dict dengan keys:
          success, df, mape, n_data, message
        """
        # 1. Historis IHK komoditas
        df_hist = self._get_ihk_historis_komoditas(komoditas_id)
        if df_hist.empty:
            return {
                'success': False,
                'message': f'Tidak ada data historis IHK untuk komoditas {komoditas_id}',
            }

        n_data = len(df_hist)
        if n_data < MIN_DATA_BULAN:
            return {
                'success': False,
                'message': (
                    f'{nama_komoditas}: data historis hanya {n_data} bulan '
                    f'(minimal {MIN_DATA_BULAN})'
                ),
            }

        # 2. Bobot dari bobot_komoditas (share IHK umum BPS)
        #    Ini yang dipakai untuk andil forecast, bukan bobot_dinamis NK internal.
        bobot = self._get_bobot_komoditas(komoditas_id)
        if bobot is None:
            return {
                'success': False,
                'message': f'{nama_komoditas}: tidak ada data bobot di bobot_komoditas',
            }

        # 3. Siapkan input Prophet
        df_prophet = df_hist.rename(columns={
            'tanggal':             'ds',
            'nilai_ihk_komoditas': 'y',
        }).copy()

        last_date = df_prophet['ds'].max()
        last_ihk  = float(df_hist['nilai_ihk_komoditas'].iloc[-1])

        # 4. Train Prophet
        try:
            m = _build_prophet(**KOMODITAS_PROPHET_PARAMS)
            m.fit(df_prophet)
        except Exception as e:
            return {'success': False, 'message': f'{nama_komoditas}: training gagal — {e}'}

        # 5. Generate forecast
        try:
            future   = m.make_future_dataframe(periods=periods + 2, freq='MS')
            forecast = m.predict(future)
        except Exception as e:
            return {'success': False, 'message': f'{nama_komoditas}: predict gagal — {e}'}

        # 6. Filter bulan forecast saja (setelah last_date)
        fc = (
            forecast[forecast['ds'] > last_date]
            .head(periods)
            .copy()
            .reset_index(drop=True)
        )
        if fc.empty:
            return {'success': False, 'message': f'{nama_komoditas}: tidak ada hasil forecast'}

        # Clip nilai negatif — IHK tidak mungkin negatif
        fc['yhat']       = fc['yhat'].clip(lower=0)
        fc['yhat_lower'] = fc['yhat_lower'].clip(lower=0)
        fc['yhat_upper'] = fc['yhat_upper'].clip(lower=0)

        # 7. Hitung inflasi MtM dan andil per bulan forecast
        #
        #    IHK prev untuk t=0  : IHK aktual bulan terakhir historis
        #    IHK prev untuk t>0  : IHK forecast t-1 (chain)
        #
        #    Formula BPS:
        #      inflasi_MtM_i = ROUND(IHK_fc_i, 2) / ROUND(IHK_prev_i, 2) × 100 − 100
        #      andil_i       = (bobot_i / 100) × inflasi_MtM_i
        #
        #    bobot_i = nilai_bobot dari bobot_komoditas (share IHK umum BPS)

        ihk_hist_lookup = (
            df_hist.set_index('tanggal')['nilai_ihk_komoditas'].to_dict()
        )
        ihk_fc_lookup = {}
        rows_result   = []

        for _, row in fc.iterrows():
            tgl    = pd.Timestamp(row['ds']).replace(day=1)
            t_prev = (tgl - pd.DateOffset(months=1)).replace(day=1)

            # Prioritas: historis aktual → forecast sebelumnya
            ihk_prev = ihk_hist_lookup.get(t_prev) or ihk_fc_lookup.get(t_prev)

            ihk_fc_val = float(row['yhat'])
            ihk_lower  = float(row['yhat_lower'])
            ihk_upper  = float(row['yhat_upper'])

            # Bulatkan 2 desimal sesuai BPS sebelum hitung inflasi
            ihk_fc_rounded   = round(ihk_fc_val, 2)
            ihk_prev_rounded = round(float(ihk_prev), 2) if ihk_prev is not None else None

            inflasi_mtom = None
            andil        = None

            if ihk_prev_rounded and ihk_prev_rounded > 0:
                inflasi_mtom = ihk_fc_rounded / ihk_prev_rounded * 100 - 100
                # Gunakan bobot dari bobot_komoditas (BPS), bukan bobot_dinamis NK
                andil = (bobot / 100.0) * inflasi_mtom

            rows_result.append({
                'komoditas_id':          komoditas_id,
                'tanggal':               tgl,
                'nilai_ihk_forecast':    ihk_fc_val,
                'ihk_lower':             ihk_lower,
                'ihk_upper':             ihk_upper,
                'inflasi_mtom_forecast': inflasi_mtom,
                'andil_forecast':        andil,
                'kondisi_forecast':      self._kondisi(inflasi_mtom),
            })

            ihk_fc_lookup[tgl] = ihk_fc_val

        df_result = pd.DataFrame(rows_result)

        # 8. Hitung MAPE in-sample
        fc_hist = forecast[forecast['ds'] <= last_date].copy()
        merged  = fc_hist.merge(
            df_prophet.rename(columns={'y': 'y_actual'}),
            on='ds', how='inner',
        )
        mape = self._mape(merged['y_actual'].values, merged['yhat'].values) \
               if len(merged) > 0 else 0.0

        return {
            'success': True,
            'df':      df_result,
            'mape':    round(mape, 4),
            'n_data':  n_data,
        }

    # ══════════════════════════════════════════════════════════════
    # SIMPAN KE DB
    # ══════════════════════════════════════════════════════════════

    def _save_to_db(
        self,
        df:      pd.DataFrame,
        periods: int,
        mape:    float,
        n_data:  int,
    ) -> int:
        """Simpan hasil forecast per komoditas ke ihk_komoditas_forecast."""
        if df.empty:
            return 0

        query = """
            INSERT INTO ihk_komoditas_forecast
                (komoditas_id, tanggal,
                 nilai_ihk_forecast, ihk_lower, ihk_upper,
                 inflasi_mtom_forecast, andil_forecast, kondisi_forecast,
                 mape_insample, n_data_historis, periods, dibuat_pada)
            VALUES
                (:komoditas_id, :tanggal,
                 :nilai_ihk_forecast, :ihk_lower, :ihk_upper,
                 :inflasi_mtom_forecast, :andil_forecast, :kondisi_forecast,
                 :mape_insample, :n_data_historis, :periods, NOW())
            ON DUPLICATE KEY UPDATE
                nilai_ihk_forecast     = VALUES(nilai_ihk_forecast),
                ihk_lower              = VALUES(ihk_lower),
                ihk_upper              = VALUES(ihk_upper),
                inflasi_mtom_forecast  = VALUES(inflasi_mtom_forecast),
                andil_forecast         = VALUES(andil_forecast),
                kondisi_forecast       = VALUES(kondisi_forecast),
                mape_insample          = VALUES(mape_insample),
                n_data_historis        = VALUES(n_data_historis),
                periods                = VALUES(periods),
                dibuat_pada            = NOW(),
                updated_at             = CURRENT_TIMESTAMP
        """
        sf    = self._safe_float
        count = 0
        with self.db.engine.begin() as conn:
            for _, row in df.iterrows():
                conn.execute(text(query), {
                    'komoditas_id':          int(row['komoditas_id']),
                    'tanggal':               row['tanggal'].strftime('%Y-%m-%d'),
                    'nilai_ihk_forecast':    float(row['nilai_ihk_forecast']),
                    'ihk_lower':             float(row['ihk_lower']),
                    'ihk_upper':             float(row['ihk_upper']),
                    'inflasi_mtom_forecast': sf(row.get('inflasi_mtom_forecast')),
                    'andil_forecast':        sf(row.get('andil_forecast')),
                    'kondisi_forecast':      row.get('kondisi_forecast'),
                    'mape_insample':         mape,
                    'n_data_historis':       n_data,
                    'periods':               periods,
                })
                count += 1
        return count

    # ══════════════════════════════════════════════════════════════
    # PUBLIC — Forecast Semua Komoditas
    # ══════════════════════════════════════════════════════════════

    def forecast_all(
        self,
        periods:       int                  = 3,
        komoditas_ids: Optional[List[int]]  = None,
    ) -> dict:
        """
        Forecast IHK untuk semua komoditas N bulan ke depan.

        User dapat memilih periods secara bebas (1–12 bulan).
        Dari endpoint Laravel, teruskan parameter ?periods=N ke sini.

        Args:
            periods       : jumlah bulan ke depan (1–12, default 3)
            komoditas_ids : filter komoditas tertentu saja (None = semua)

        Returns:
            dict: success, total_komoditas, berhasil, gagal, periods, detail
        """
        periods = max(1, min(periods, MAX_PERIODS))
        print(f"[IHKKmdFc] Forecast {periods} bulan ke depan...")

        df_kmd = self._get_daftar_komoditas(komoditas_ids)
        if df_kmd.empty:
            return {
                'success': False,
                'message': 'Tidak ada komoditas dengan data IHK di andil_inflasi_bulanan.',
            }

        total    = len(df_kmd)
        berhasil = 0
        gagal    = 0
        detail   = []

        print(f"[IHKKmdFc] {total} komoditas | {periods} bulan ke depan")

        for _, kmd in df_kmd.iterrows():
            kid  = int(kmd['komoditas_id'])
            nama = kmd['nama_komoditas']
            print(f"  [{kid:02d}] {nama:<35}", end=' ')

            result = self._forecast_satu_komoditas(kid, nama, periods)

            if not result['success']:
                gagal += 1
                detail.append({
                    'komoditas_id': kid,
                    'nama':         nama,
                    'success':      False,
                    'message':      result['message'],
                })
                print(f"GAGAL — {result['message']}")
                continue

            saved = self._save_to_db(
                result['df'], periods, result['mape'], result['n_data']
            )
            berhasil += 1

            sf   = self._safe_float
            row0 = result['df'].iloc[0]   # bulan depan (pertama dari forecast)

            detail.append({
                'komoditas_id':  kid,
                'nama':          nama,
                'success':       True,
                'n_data':        result['n_data'],
                'mape_insample': result['mape'],
                'saved':         saved,
                'bulan_depan': {
                    'tanggal':               row0['tanggal'].strftime('%Y-%m-%d'),
                    'nilai_ihk_forecast':    round(float(row0['nilai_ihk_forecast']), 2),
                    'ihk_lower':             round(float(row0['ihk_lower']), 2),
                    'ihk_upper':             round(float(row0['ihk_upper']), 2),
                    'inflasi_mtom_forecast': sf(row0.get('inflasi_mtom_forecast')),
                    'andil_forecast':        sf(row0.get('andil_forecast')),
                    'kondisi_forecast':      row0.get('kondisi_forecast'),
                },
            })
            print(f"OK (MAPE={result['mape']:.2f}%, n={result['n_data']})")

        print(f"[IHKKmdFc] Selesai: {berhasil} berhasil, {gagal} gagal")

        return {
            'success':         True,
            'total_komoditas': total,
            'berhasil':        berhasil,
            'gagal':           gagal,
            'periods':         periods,
            'detail':          detail,
        }

    # ══════════════════════════════════════════════════════════════
    # PUBLIC — Query Hasil dari DB
    # ══════════════════════════════════════════════════════════════

    def get_forecast_bulan(self, bulan: str) -> dict:
        """
        Ambil forecast IHK semua komoditas untuk bulan tertentu dari DB.

        Args:
            bulan: format 'YYYY-MM'  (misal '2025-08')

        Returns:
            dict dengan list forecast per komoditas, diurutkan |andil| DESC
        """
        try:
            target = pd.to_datetime(bulan + '-01')
        except Exception:
            return {'success': False, 'message': f'Format bulan tidak valid: {bulan}'}

        query = """
            SELECT
                ikf.komoditas_id,
                mk.nama_komoditas,
                mk.nama_varian,
                ikf.tanggal,
                ikf.nilai_ihk_forecast,
                ikf.ihk_lower,
                ikf.ihk_upper,
                ikf.inflasi_mtom_forecast,
                ikf.andil_forecast,
                ikf.kondisi_forecast,
                ikf.mape_insample,
                ikf.n_data_historis
            FROM ihk_komoditas_forecast ikf
            JOIN master_komoditas mk ON mk.id = ikf.komoditas_id
            WHERE DATE_FORMAT(ikf.tanggal, '%Y-%m') = :bulan
            ORDER BY ABS(COALESCE(ikf.andil_forecast, 0)) DESC
        """
        with self.db.engine.connect() as conn:
            rows = conn.execute(
                text(query), {'bulan': target.strftime('%Y-%m')}
            ).fetchall()

        if not rows:
            return {
                'success': False,
                'message': (
                    f'Belum ada forecast untuk {bulan}. '
                    f'Jalankan forecast_all() terlebih dahulu.'
                ),
            }

        sf = self._safe_float
        komoditas_list      = []
        total_andil_inflasi = 0.0
        total_andil_deflasi = 0.0

        for r in rows:
            andil   = sf(r[8])
            kondisi = r[9]
            if andil:
                if kondisi == 'inflasi':
                    total_andil_inflasi += andil
                elif kondisi == 'deflasi':
                    total_andil_deflasi += andil

            komoditas_list.append({
                'komoditas_id':          int(r[0]),
                'nama':                  r[1] + (f' ({r[2]})' if r[2] else ''),
                'tanggal':               str(r[3])[:7],
                'nilai_ihk_forecast':    round(float(r[4]), 2),
                'ihk_lower':             round(float(r[5]), 2),
                'ihk_upper':             round(float(r[6]), 2),
                'inflasi_mtom_forecast': sf(r[7]),
                'andil_forecast':        sf(r[8]),
                'kondisi_forecast':      r[9],
                'mape_insample':         sf(r[10]),
                'n_data':                r[11],
            })

        n_inflasi = sum(1 for k in komoditas_list if k['kondisi_forecast'] == 'inflasi')
        n_deflasi = sum(1 for k in komoditas_list if k['kondisi_forecast'] == 'deflasi')
        n_stabil  = sum(1 for k in komoditas_list if k['kondisi_forecast'] == 'stabil')

        return {
            'success': True,
            'data': {
                'bulan':               bulan,
                'total_komoditas':     len(komoditas_list),
                'n_inflasi':           n_inflasi,
                'n_deflasi':           n_deflasi,
                'n_stabil':            n_stabil,
                'total_andil_inflasi': round(total_andil_inflasi, 6),
                'total_andil_deflasi': round(total_andil_deflasi, 6),
                'komoditas':           komoditas_list,
            },
        }

    def get_forecast_summary(self, bulan_referensi: str = None) -> dict:
        """
        Ringkasan forecast IHK per komoditas untuk bulan depan.
        Cocok untuk widget di dashboard / tabel laporan.

        Args:
            bulan_referensi: 'YYYY-MM' bulan aktif yang dilihat user.
                             Forecast yang dikembalikan = bulan berikutnya.
                             Default: bulan terakhir di andil_inflasi_bulanan.
        """
        if bulan_referensi:
            try:
                ref = pd.to_datetime(bulan_referensi + '-01')
            except Exception:
                return {'success': False, 'message': f'Format tidak valid: {bulan_referensi}'}
        else:
            with self.db.engine.connect() as conn:
                result = conn.execute(
                    text("SELECT MAX(tanggal) FROM andil_inflasi_bulanan")
                ).fetchone()
            if not result or not result[0]:
                return {'success': False, 'message': 'Tidak ada data di andil_inflasi_bulanan.'}
            ref = pd.to_datetime(result[0]).replace(day=1)

        bulan_fc = (ref + pd.DateOffset(months=1)).replace(day=1)
        return self.get_forecast_bulan(bulan_fc.strftime('%Y-%m'))

    def get_ihk_historis_dan_forecast(
        self,
        komoditas_id: int,
        n_hist:       int = 12,
    ) -> dict:
        """
        Ambil IHK historis + forecast untuk satu komoditas.
        Berguna untuk chart tren IHK per komoditas di frontend.

        Args:
            komoditas_id : ID komoditas
            n_hist       : jumlah bulan historis (default 12)
        """
        hist_query = """
            SELECT
                aib.tanggal,
                aib.nilai_ihk_komoditas,
                aib.bobot_dinamis,
                aib.inflasi_mtom_komoditas,
                aib.andil_mtom
            FROM andil_inflasi_bulanan aib
            WHERE aib.komoditas_id = :kid
              AND aib.nilai_ihk_komoditas IS NOT NULL
            ORDER BY aib.tanggal DESC
            LIMIT :n_hist
        """
        fc_query = """
            SELECT
                tanggal,
                nilai_ihk_forecast,
                ihk_lower,
                ihk_upper,
                inflasi_mtom_forecast,
                andil_forecast,
                kondisi_forecast
            FROM ihk_komoditas_forecast
            WHERE komoditas_id = :kid
            ORDER BY tanggal ASC
        """
        nama_query = """
            SELECT nama_komoditas, nama_varian
            FROM master_komoditas WHERE id = :kid
        """

        sf = self._safe_float
        with self.db.engine.connect() as conn:
            hist_rows = conn.execute(
                text(hist_query), {'kid': komoditas_id, 'n_hist': n_hist}
            ).fetchall()
            fc_rows  = conn.execute(
                text(fc_query), {'kid': komoditas_id}
            ).fetchall()
            nama_row = conn.execute(
                text(nama_query), {'kid': komoditas_id}
            ).fetchone()

        if not hist_rows and not fc_rows:
            return {
                'success': False,
                'message': f'Tidak ada data untuk komoditas {komoditas_id}',
            }

        nama = (
            nama_row[0] + (f' ({nama_row[1]})' if nama_row and nama_row[1] else '')
            if nama_row else str(komoditas_id)
        )

        historis = [
            {
                'tanggal':        str(r[0])[:7],
                'nilai_ihk':      sf(r[1]),
                'bobot_dinamis':  sf(r[2]),
                'inflasi_mtom':   sf(r[3]),
                'andil_mtom':     sf(r[4]),
                'tipe':           'aktual',
            }
            for r in reversed(hist_rows)
        ]

        forecast_list = [
            {
                'tanggal':               str(r[0])[:7],
                'nilai_ihk_forecast':    round(float(r[1]), 2),
                'ihk_lower':             round(float(r[2]), 2),
                'ihk_upper':             round(float(r[3]), 2),
                'inflasi_mtom_forecast': sf(r[4]),
                'andil_forecast':        sf(r[5]),
                'kondisi_forecast':      r[6],
                'tipe':                  'forecast',
            }
            for r in fc_rows
        ]

        return {
            'success': True,
            'data': {
                'komoditas_id': komoditas_id,
                'nama':         nama,
                'historis':     historis,
                'forecast':     forecast_list,
            },
        }