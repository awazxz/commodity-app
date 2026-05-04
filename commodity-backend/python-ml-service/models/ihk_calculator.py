"""
ihk_calculator.py
==================
Modul kalkulasi IHK Bahan Pokok menggunakan metodologi BPS chain-linked.
Tahun dasar 2022 = 100. Scope: 21 komoditas bahan pokok.

Metodologi (sesuai Form Penghitungan Inflasi BPS 2022=100):
─────────────────────────────────────────────────────────────
  LANGKAH 1 — NK per komoditas (chain-linked dari NK sebelumnya):
    NK_i_0  = NK_dasar_i                          (dari master, konstan)
    NK_i_t  = RH_i_t × NK_i_{t-1} / 100          ← chain dari NK, bukan dari IHK!

  LANGKAH 2 — IHK per komoditas (dari NK, dibulatkan 2 desimal):
    IHK_i_t = ROUND(NK_i_t / NK_i_0 × 100, 2)   ← WAJIB dibulatkan 2 desimal

  LANGKAH 3 — NK agregat UMUM:
    NK_umum_t = Σ(NK_i_t)                         (sum NK komoditas leaf)

  LANGKAH 4 — IHK agregat UMUM (dibulatkan 2 desimal):
    IHK_umum_t = ROUND(NK_umum_t / NK_umum_0 × 100, 2)

  LANGKAH 5 — Bobot dinamis per bulan:
    Bobot_i_t = NK_i_t / NK_umum_t × 100

  LANGKAH 6 — Inflasi M-to-M (menggunakan IHK yang sudah dibulatkan):
    Inflasi_MtoM_t = IHK_umum_t / IHK_umum_{t-1} × 100 − 100

  LANGKAH 7 — Inflasi Y-to-D (menggunakan IHK yang sudah dibulatkan):
    Inflasi_YtD_t  = IHK_umum_t / IHK_umum_Des{tahun−1} × 100 − 100

  LANGKAH 8 — Inflasi Y-on-Y (menggunakan IHK yang sudah dibulatkan):
    Inflasi_YoY_t  = IHK_umum_t / IHK_umum_{t−12} × 100 − 100

  LANGKAH 9 — Andil per komoditas ke inflasi M-to-M:
    Inflasi_MtoM_i_t = IHK_i_t / IHK_i_{t-1} × 100 − 100
                       (menggunakan IHK komoditas yang sudah dibulatkan)
    Andil_i_t        = (Bobot_i_{t-1} / 100) × Inflasi_MtoM_i_t

  KONDISI (threshold BPS ±0.1%):
    inflasi  : Inflasi_MtoM >  0.1%
    deflasi  : Inflasi_MtoM < −0.1%
    stabil   : |Inflasi_MtoM| ≤ 0.1%

  CATATAN PEMBULATAN (sesuai form Excel BPS):
    • IHK per komoditas   : ROUND(..., 2)
    • IHK agregat UMUM    : ROUND(..., 2)
    • Semua inflasi (MtoM/YtD/YoY) dihitung dari IHK yang SUDAH dibulatkan
    • NK intermediate     : TIDAK dibulatkan (presisi penuh)
    • Tanpa pembulatan IHK → YtD/YoY akan berbeda dari form BPS

Tabel DB:
  READ  : master_komoditas, bobot_komoditas, rh_komoditas
  WRITE : ihk_bulanan, andil_inflasi_bulanan

v4.0 — Sesuai formula Excel BPS: NK chain-linked, IHK dibulatkan 2dp
"""

import numpy as np
import pandas as pd
from typing import Optional
from sqlalchemy import text


class IHKCalculator:
    """
    Kalkulasi IHK Bahan Pokok bulanan, metodologi BPS chain-linked.

    Cara pakai:
        calc = IHKCalculator(db_connector)
        calc.calculate_and_save_all()          # hitung + simpan semua bulan
        calc.recalculate_latest(n_bulan=3)     # recalc N bulan terakhir (aman)
        calc.get_ihk_summary()                 # ringkasan IHK terbaru
        calc.get_ihk_history()                 # riwayat IHK semua bulan
        calc.get_commodity_detail(bulan)       # detail per komoditas
        calc.get_inflasi_comparison(bulan)     # perbandingan bulan x vs x-1
        calc.get_inflasi_forecast_context()    # data untuk Prophet forecast
    """

    STABIL_THRESHOLD = 0.1    # ±0.1% dianggap stabil (sesuai BPS)
    IHK_BASE         = 100.0  # tahun dasar 2022 = 100
    IHK_ROUND_DP     = 2      # pembulatan IHK sesuai form BPS (2 desimal)

    def __init__(self, db_connector):
        self.db = db_connector

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
        if v > self.STABIL_THRESHOLD:
            return 'inflasi'
        elif v < -self.STABIL_THRESHOLD:
            return 'deflasi'
        return 'stabil'

    def _round_ihk(self, val: float) -> float:
        """
        Bulatkan IHK ke 2 desimal sesuai formula Excel BPS:
          IHK = ROUND(NK_t / NK_dasar * 100, 2)
        Pembulatan ini WAJIB agar YtD dan YoY cocok dengan form BPS.
        """
        return round(val, self.IHK_ROUND_DP)

    def _get_master_komoditas(self) -> pd.DataFrame:
        query = """
            SELECT
                mk.id           AS komoditas_id,
                mk.nama_komoditas,
                mk.nama_varian,
                mk.satuan,
                mk.harga_dasar,
                bk.nilai_bobot  AS bobot_dasar
            FROM master_komoditas mk
            JOIN bobot_komoditas bk ON bk.komoditas_id = mk.id
            WHERE bk.tanggal = (
                SELECT MIN(b2.tanggal)
                FROM bobot_komoditas b2
                WHERE b2.komoditas_id = mk.id
            )
            ORDER BY mk.id
        """
        with self.db.engine.connect() as conn:
            rows = conn.execute(text(query)).fetchall()

        if not rows:
            raise ValueError(
                "Tidak ada data master_komoditas atau bobot_komoditas."
            )

        df = pd.DataFrame(rows, columns=[
            'komoditas_id', 'nama_komoditas', 'nama_varian',
            'satuan', 'harga_dasar', 'bobot_dasar',
        ])

        missing = df[df['bobot_dasar'].isna() | (df['bobot_dasar'] == 0)]
        if not missing.empty:
            raise ValueError(
                f"{len(missing)} komoditas tidak punya bobot_dasar: "
                f"{missing['nama_komoditas'].tolist()}"
            )

        df['harga_dasar'] = df['harga_dasar'].astype(float)
        df['bobot_dasar'] = df['bobot_dasar'].astype(float)
        # NK_dasar = harga_dasar × bobot_dasar (konstan, tidak pernah berubah)
        df['nk_dasar']    = df['harga_dasar'] * df['bobot_dasar']
        return df

    def _get_rh_bulanan(self, start_date=None, end_date=None) -> pd.DataFrame:
        clauses = ["1=1"]
        params  = {}
        if start_date:
            clauses.append("tanggal >= :start_date")
            params['start_date'] = start_date
        if end_date:
            clauses.append("tanggal <= :end_date")
            params['end_date'] = end_date

        query = f"""
            SELECT komoditas_id, tanggal, nilai_rh
            FROM rh_komoditas
            WHERE {" AND ".join(clauses)}
            ORDER BY komoditas_id, tanggal
        """
        with self.db.engine.connect() as conn:
            rows = conn.execute(text(query), params).fetchall()

        if not rows:
            return pd.DataFrame(columns=['komoditas_id', 'tanggal', 'nilai_rh'])

        df = pd.DataFrame(rows, columns=['komoditas_id', 'tanggal', 'nilai_rh'])
        df['tanggal']  = pd.to_datetime(df['tanggal']).dt.to_period('M').dt.to_timestamp()
        df['nilai_rh'] = df['nilai_rh'].astype(float)
        return df

    def _get_nk_seed(self, komoditas_ids: list, before_date: str) -> dict:
        """
        Ambil NK seed (NK bulan sebelumnya) untuk melanjutkan chain NK.

        PENTING: NK di-chain dari NK, bukan dari IHK.
        Formula: NK_t = RH_t * NK_{t-1} / 100
        Sehingga seed yang dibutuhkan adalah NK bulan terakhir sebelum
        periode perhitungan.

        Urutan prioritas seed:
          1. andil_inflasi_bulanan (nilai_nk_komoditas bulan terakhir)
          2. ihk_seed (NK bulan Desember tahun dasar, default 2022-12)
          3. Fallback: nk_dasar komoditas (NK tahun dasar 2022 = IHK 100)
        """
        if not komoditas_ids:
            return {}

        # ── 1. Coba ambil NK terakhir dari andil_inflasi_bulanan ──────────
        try:
            placeholders = ', '.join([f':id_{i}' for i in range(len(komoditas_ids))])
            params = {f'id_{i}': kid for i, kid in enumerate(komoditas_ids)}
            params['before_date'] = before_date

            query = f"""
                SELECT komoditas_id, nilai_nk_komoditas
                FROM (
                    SELECT
                        komoditas_id,
                        nilai_nk_komoditas,
                        ROW_NUMBER() OVER (
                            PARTITION BY komoditas_id
                            ORDER BY tanggal DESC
                        ) AS rn
                    FROM andil_inflasi_bulanan
                    WHERE tanggal < :before_date
                      AND komoditas_id IN ({placeholders})
                      AND nilai_nk_komoditas IS NOT NULL
                ) t
                WHERE rn = 1
            """
            with self.db.engine.connect() as conn:
                rows = conn.execute(text(query), params).fetchall()

            if rows:
                seed = {int(r[0]): float(r[1]) for r in rows}
                missing = set(komoditas_ids) - set(seed.keys())
                if not missing:
                    print(f"  [SEED] {len(seed)} NK komoditas dari andil_inflasi_bulanan")
                    return seed
            else:
                seed    = {}
                missing = set(komoditas_ids)

        except Exception:
            # Kolom nilai_nk_komoditas mungkin belum ada di tabel lama
            seed    = {}
            missing = set(komoditas_ids)

        # ── 2. Fallback: gunakan IHK seed lalu konversi ke NK ─────────────
        # NK_seed = IHK_seed / 100 * NK_dasar
        # Jika ihk_seed = 100 (Des 2022), maka NK_seed = NK_dasar ✓
        if missing:
            try:
                placeholders2 = ', '.join([f':sid_{i}' for i in range(len(missing))])
                params2 = {f'sid_{i}': kid for i, kid in enumerate(missing)}

                query2 = f"""
                    SELECT s.komoditas_id, s.ihk_seed, mk.harga_dasar, bk.nilai_bobot
                    FROM ihk_seed s
                    JOIN master_komoditas mk ON mk.id = s.komoditas_id
                    JOIN bobot_komoditas bk ON bk.komoditas_id = mk.id
                    WHERE s.komoditas_id IN ({placeholders2})
                      AND bk.tanggal = (
                          SELECT MIN(b2.tanggal) FROM bobot_komoditas b2
                          WHERE b2.komoditas_id = mk.id
                      )
                """
                with self.db.engine.connect() as conn:
                    rows2 = conn.execute(text(query2), params2).fetchall()

                for r in rows2:
                    kid        = int(r[0])
                    ihk_seed   = float(r[1])
                    nk_dasar   = float(r[2]) * float(r[3])
                    # NK_seed = NK_dasar * ihk_seed / 100
                    seed[kid]  = nk_dasar * ihk_seed / 100.0

                still_missing = missing - set(int(r[0]) for r in rows2)
                print(f"  [SEED] {len(rows2)} NK komoditas dari ihk_seed (konversi IHK→NK)")

                if still_missing:
                    print(f"  [SEED] WARNING: {len(still_missing)} komoditas tidak ada di "
                          f"ihk_seed, fallback ke NK_dasar (IHK=100): {still_missing}")
                    # Jika IHK=100, NK_seed = NK_dasar
                    # Perlu lookup nk_dasar per komoditas
                    for kid in still_missing:
                        seed[kid] = None   # akan di-resolve di _hitung_nk_chain

            except Exception as e:
                print(f"  [SEED] WARNING: Gagal baca ihk_seed: {e}, "
                      f"fallback ke NK_dasar (IHK=100)")
                for kid in missing:
                    seed[kid] = None       # akan di-resolve di _hitung_nk_chain

        return seed

    def _get_ihk_historis_dari_db(self) -> pd.DataFrame:
        query = """
            SELECT tanggal, nilai_ihk
            FROM ihk_bulanan
            ORDER BY tanggal ASC
        """
        with self.db.engine.connect() as conn:
            rows = conn.execute(text(query)).fetchall()
        if not rows:
            return pd.DataFrame(columns=['tanggal', 'nilai_ihk'])
        df = pd.DataFrame(rows, columns=['tanggal', 'nilai_ihk'])
        df['tanggal']   = pd.to_datetime(df['tanggal'])
        df['nilai_ihk'] = df['nilai_ihk'].astype(float)
        return df

    # ───────────────────────────────────────────────────────────────────────
    # LANGKAH 1-2: Hitung NK chain dan IHK per komoditas
    # ───────────────────────────────────────────────────────────────────────

    def _hitung_nk_chain_dan_ihk_komoditas(
        self,
        df_rh: pd.DataFrame,
        df_master: pd.DataFrame,
        nk_seed: dict,
    ) -> pd.DataFrame:
        """
        Hitung NK dan IHK per komoditas secara chain-linked.

        Formula (sesuai Excel BPS):
          NK_t   = RH_t × NK_{t−1} / 100         ← chain dari NK, bukan dari IHK
          IHK_t  = ROUND(NK_t / NK_dasar × 100, 2) ← dibulatkan 2 desimal

        Parameter nk_seed:
          - Nilai None → gunakan nk_dasar komoditas (setara IHK_seed = 100)
        """
        # Buat lookup nk_dasar per komoditas_id
        nk_dasar_map = dict(zip(
            df_master['komoditas_id'].tolist(),
            df_master['nk_dasar'].tolist(),
        ))

        results = []
        for kid in sorted(df_rh['komoditas_id'].unique()):
            df_k    = df_rh[df_rh['komoditas_id'] == kid].sort_values('tanggal').copy()
            nk_dasar = nk_dasar_map.get(int(kid))
            if nk_dasar is None or nk_dasar == 0:
                print(f"  [WARNING] Komoditas {kid}: nk_dasar = 0/None, dilewati.")
                continue

            # NK seed: ambil dari dict, fallback ke nk_dasar (= IHK_seed 100)
            nk_prev = nk_seed.get(int(kid))
            if nk_prev is None:
                nk_prev = nk_dasar   # IHK_seed = 100 → NK_seed = NK_dasar

            nk_vals  = []
            ihk_vals = []
            for rh in df_k['nilai_rh']:
                # NK chain: NK_t = RH_t * NK_{t-1} / 100
                nk_t = float(rh) * nk_prev / 100.0
                # IHK: ROUND(NK_t / NK_dasar * 100, 2) — sesuai formula Excel BPS
                ihk_t = self._round_ihk(nk_t / nk_dasar * 100.0)
                nk_vals.append(nk_t)
                ihk_vals.append(ihk_t)
                nk_prev = nk_t       # lanjut chain dari NK (TIDAK dari IHK yang sudah rounded)

            df_k['nilai_nk_komoditas']  = nk_vals
            df_k['nilai_ihk_komoditas'] = ihk_vals   # sudah dibulatkan 2 desimal
            results.append(df_k)

        if not results:
            return pd.DataFrame()
        return pd.concat(results, ignore_index=True)

    # ───────────────────────────────────────────────────────────────────────
    # LANGKAH 3-5: Hitung NK agregat, IHK UMUM, dan bobot dinamis
    # ───────────────────────────────────────────────────────────────────────

    def _hitung_nk_agregat_dan_bobot(
        self,
        df_nk_ihk: pd.DataFrame,
        df_master: pd.DataFrame,
    ) -> pd.DataFrame:
        """
        Gabungkan NK komoditas dengan master, hitung NK agregat dan bobot.

        Returns DataFrame dengan kolom tambahan:
          - nk_dasar        : NK dasar komoditas (konstan)
          - total_nk_bulan  : Σ NK_i_t per bulan (= NK UMUM)
          - bobot_dinamis   : NK_i_t / Σ NK_i_t × 100
        """
        df = df_nk_ihk.merge(
            df_master[['komoditas_id', 'nk_dasar', 'nama_komoditas',
                        'nama_varian', 'satuan', 'bobot_dasar']],
            on='komoditas_id', how='inner',
        )

        missing = (
            set(df_nk_ihk['komoditas_id'].unique())
            - set(df['komoditas_id'].unique())
        )
        if missing:
            print(f"  [WARNING] {len(missing)} komoditas tidak punya data master: {missing}")

        # NK_i_t sudah ada di kolom nilai_nk_komoditas
        total_nk = (
            df.groupby('tanggal')['nilai_nk_komoditas']
            .sum()
            .reset_index()
            .rename(columns={'nilai_nk_komoditas': 'total_nk_bulan'})
        )
        df = df.merge(total_nk, on='tanggal')
        df['bobot_dinamis'] = df['nilai_nk_komoditas'] / df['total_nk_bulan'] * 100.0
        return df

    def _hitung_ihk_agregat(self, df_nk: pd.DataFrame, df_master: pd.DataFrame) -> pd.DataFrame:
        """
        Hitung IHK agregat UMUM dari NK.

        Formula (sesuai Excel BPS):
          IHK_umum_t = ROUND(NK_umum_t / NK_umum_0 × 100, 2)

        NK_umum_0 = Σ nk_dasar (konstan).
        NK_umum_t = Σ NK_i_t (sudah ada di total_nk_bulan).
        """
        total_nk_dasar = float(df_master['nk_dasar'].sum())
        ihk = (
            df_nk.groupby('tanggal')
            .agg(
                total_nk         = ('nilai_nk_komoditas', 'sum'),
                jumlah_komoditas = ('komoditas_id',       'count'),
            )
            .reset_index()
        )
        # ROUND ke 2 desimal — WAJIB sesuai form BPS agar YtD/YoY cocok
        ihk['nilai_ihk'] = ihk['total_nk'].apply(
            lambda nk: self._round_ihk(nk / total_nk_dasar * 100.0)
        )
        return ihk.sort_values('tanggal').reset_index(drop=True)

    # ───────────────────────────────────────────────────────────────────────
    # LANGKAH 6-8: Hitung inflasi MtoM, YtD, YoY dari IHK yang sudah rounded
    # ───────────────────────────────────────────────────────────────────────

    def _hitung_inflasi_agregat(self, df_ihk: pd.DataFrame) -> pd.DataFrame:
        """
        Hitung inflasi MtoM, YtD, YoY dari IHK agregat.

        PENTING: df_ihk['nilai_ihk'] harus sudah dibulatkan 2 desimal
        sebelum masuk fungsi ini. Inflasi dihitung dari IHK yang rounded
        agar hasilnya sama dengan form Excel BPS.

        Formula:
          MtoM  = IHK_t / IHK_{t−1} × 100 − 100
          YtD   = IHK_t / IHK_Des{tahun−1} × 100 − 100
          YoY   = IHK_t / IHK_{t−12} × 100 − 100
        """
        df = df_ihk.copy().sort_values('tanggal').reset_index(drop=True)
        df['tanggal'] = pd.to_datetime(df['tanggal'])

        # ── MtoM ────────────────────────────────────────────────────────
        df['ihk_prev']     = df['nilai_ihk'].shift(1)
        df['inflasi_mtom'] = None
        mask = df['ihk_prev'].notna() & (df['ihk_prev'] != 0)
        df.loc[mask, 'inflasi_mtom'] = (
            df.loc[mask, 'nilai_ihk'] / df.loc[mask, 'ihk_prev'] * 100 - 100
        )

        # ── YtD ─────────────────────────────────────────────────────────
        # Lookup IHK Desember per tahun (hanya dari data yang tersedia di df)
        ihk_des = (
            df[df['tanggal'].dt.month == 12]
            .set_index(df[df['tanggal'].dt.month == 12]['tanggal'].dt.year)['nilai_ihk']
            .to_dict()
        )

        def _ytd(row):
            ihk_d = ihk_des.get(row['tanggal'].year - 1)
            if ihk_d is None or ihk_d == 0:
                return None
            return row['nilai_ihk'] / ihk_d * 100 - 100

        df['inflasi_ytd'] = df.apply(_ytd, axis=1)

        # ── YoY ─────────────────────────────────────────────────────────
        ihk_lookup = df.set_index('tanggal')['nilai_ihk'].to_dict()

        def _yoy(row):
            t12    = (row['tanggal'] - pd.DateOffset(months=12)).replace(day=1)
            ihk_12 = ihk_lookup.get(t12)
            if ihk_12 is None or ihk_12 == 0:
                return None
            return row['nilai_ihk'] / ihk_12 * 100 - 100

        df['inflasi_yoy'] = df.apply(_yoy, axis=1)
        df['kondisi']     = df['inflasi_mtom'].apply(self._kondisi)

        cols = ['tanggal', 'nilai_ihk', 'inflasi_mtom', 'inflasi_ytd', 'inflasi_yoy', 'kondisi']
        if 'total_nk' in df.columns:
            cols = ['tanggal', 'nilai_ihk', 'total_nk', 'jumlah_komoditas',
                    'inflasi_mtom', 'inflasi_ytd', 'inflasi_yoy', 'kondisi']
        return df[cols]

    # ───────────────────────────────────────────────────────────────────────
    # LANGKAH 9: Hitung andil per komoditas
    # ───────────────────────────────────────────────────────────────────────

    def _hitung_andil(self, df_nk: pd.DataFrame) -> pd.DataFrame:
        """
        Hitung andil per komoditas terhadap inflasi MtoM.

        Formula:
          Inflasi_MtoM_i = IHK_i_t / IHK_i_{t-1} × 100 − 100
                           (menggunakan IHK komoditas yang sudah dibulatkan)
          Andil_i        = (Bobot_i_{t-1} / 100) × Inflasi_MtoM_i
        """
        df = df_nk.sort_values(['komoditas_id', 'tanggal']).copy()
        # nilai_ihk_komoditas sudah dibulatkan 2 desimal dari _hitung_nk_chain_dan_ihk_komoditas
        df['ihk_prev']   = df.groupby('komoditas_id')['nilai_ihk_komoditas'].shift(1)
        df['bobot_prev'] = df.groupby('komoditas_id')['bobot_dinamis'].shift(1)

        df['inflasi_mtom_komoditas'] = None
        mask = df['ihk_prev'].notna() & (df['ihk_prev'] != 0)
        df.loc[mask, 'inflasi_mtom_komoditas'] = (
            df.loc[mask, 'nilai_ihk_komoditas'] / df.loc[mask, 'ihk_prev'] * 100 - 100
        )

        df['andil_mtom'] = None
        mask2 = df['bobot_prev'].notna() & df['inflasi_mtom_komoditas'].notna()
        df.loc[mask2, 'andil_mtom'] = (
            df.loc[mask2, 'bobot_prev'] / 100.0
            * df.loc[mask2, 'inflasi_mtom_komoditas']
        )

        return df[[
            'komoditas_id', 'tanggal', 'nilai_rh',
            'nilai_nk_komoditas', 'nilai_ihk_komoditas',
            'bobot_dinamis', 'inflasi_mtom_komoditas', 'andil_mtom',
        ]]

    # ───────────────────────────────────────────────────────────────────────
    # DB Save
    # ───────────────────────────────────────────────────────────────────────

    def _save_ihk_to_db(self, df: pd.DataFrame) -> int:
        if df.empty:
            return 0
        query = """
            INSERT INTO ihk_bulanan
                (tanggal, nilai_ihk, inflasi, inflasi_ytd, inflasi_yoy, kondisi)
            VALUES
                (:tanggal, :nilai_ihk, :inflasi, :inflasi_ytd, :inflasi_yoy, :kondisi)
            ON DUPLICATE KEY UPDATE
                nilai_ihk   = VALUES(nilai_ihk),
                inflasi     = VALUES(inflasi),
                inflasi_ytd = VALUES(inflasi_ytd),
                inflasi_yoy = VALUES(inflasi_yoy),
                kondisi     = VALUES(kondisi),
                updated_at  = CURRENT_TIMESTAMP
        """
        sf    = self._safe_float
        count = 0
        with self.db.engine.begin() as conn:
            for _, row in df.iterrows():
                conn.execute(text(query), {
                    'tanggal':     row['tanggal'].strftime('%Y-%m-%d'),
                    'nilai_ihk':   float(row['nilai_ihk']),
                    'inflasi':     sf(row.get('inflasi_mtom')),
                    'inflasi_ytd': sf(row.get('inflasi_ytd')),
                    'inflasi_yoy': sf(row.get('inflasi_yoy')),
                    'kondisi':     row.get('kondisi'),
                })
                count += 1
        return count

    def _save_andil_to_db(self, df: pd.DataFrame) -> int:
        if df.empty:
            return 0
        # Catatan: pastikan tabel andil_inflasi_bulanan memiliki kolom
        # nilai_nk_komoditas (tambahkan via migration jika belum ada)
        query = """
            INSERT INTO andil_inflasi_bulanan
                (komoditas_id, tanggal, nilai_rh,
                 nilai_nk_komoditas, nilai_ihk_komoditas,
                 bobot_dinamis, inflasi_mtom_komoditas, andil_mtom)
            VALUES
                (:komoditas_id, :tanggal, :nilai_rh,
                 :nilai_nk_komoditas, :nilai_ihk_komoditas,
                 :bobot_dinamis, :inflasi_mtom_komoditas, :andil_mtom)
            ON DUPLICATE KEY UPDATE
                nilai_rh                = VALUES(nilai_rh),
                nilai_nk_komoditas      = VALUES(nilai_nk_komoditas),
                nilai_ihk_komoditas     = VALUES(nilai_ihk_komoditas),
                bobot_dinamis           = VALUES(bobot_dinamis),
                inflasi_mtom_komoditas  = VALUES(inflasi_mtom_komoditas),
                andil_mtom              = VALUES(andil_mtom),
                updated_at              = CURRENT_TIMESTAMP
        """
        sf    = self._safe_float
        count = 0
        with self.db.engine.begin() as conn:
            for _, row in df.iterrows():
                conn.execute(text(query), {
                    'komoditas_id':           int(row['komoditas_id']),
                    'tanggal':                row['tanggal'].strftime('%Y-%m-%d'),
                    'nilai_rh':               float(row['nilai_rh']),
                    'nilai_nk_komoditas':     float(row['nilai_nk_komoditas']),
                    'nilai_ihk_komoditas':    float(row['nilai_ihk_komoditas']),
                    'bobot_dinamis':          float(row['bobot_dinamis']),
                    'inflasi_mtom_komoditas': sf(row.get('inflasi_mtom_komoditas')),
                    'andil_mtom':             sf(row.get('andil_mtom')),
                })
                count += 1
        return count

    # ───────────────────────────────────────────────────────────────────────
    # PUBLIC — Kalkulasi utama
    # ───────────────────────────────────────────────────────────────────────

    def calculate_and_save_all(self, start_date=None, end_date=None) -> dict:
        print("[IHK] Memulai kalkulasi IHK Bahan Pokok (BPS chain-linked 2022=100)...")

        df_master      = self._get_master_komoditas()
        total_nk_dasar = float(df_master['nk_dasar'].sum())
        print(f"[IHK] Komoditas : {len(df_master)} | Total NK₀ : {total_nk_dasar:,.2f}")

        df_rh = self._get_rh_bulanan(start_date, end_date)
        if df_rh.empty:
            raise ValueError("Tidak ada data RH di tabel rh_komoditas.")
        print(f"[IHK] RH        : {len(df_rh)} baris | "
              f"{df_rh['tanggal'].min().strftime('%Y-%m')} s/d "
              f"{df_rh['tanggal'].max().strftime('%Y-%m')}")

        kid_list   = df_rh['komoditas_id'].unique().tolist()
        first_date = df_rh['tanggal'].min().strftime('%Y-%m-%d')

        # Ambil NK seed (bukan IHK seed) untuk melanjutkan chain NK
        nk_seed = self._get_nk_seed(kid_list, before_date=first_date)
        print(f"[IHK] NK Seed   : {len(nk_seed)} komoditas")

        print("[IHK] Menghitung NK chain dan IHK per komoditas (ROUND 2dp)...")
        df_nk_ihk = self._hitung_nk_chain_dan_ihk_komoditas(df_rh, df_master, nk_seed)

        print("[IHK] Menghitung NK agregat dan bobot dinamis...")
        df_nk = self._hitung_nk_agregat_dan_bobot(df_nk_ihk, df_master)

        print("[IHK] Menghitung IHK agregat UMUM (ROUND 2dp)...")
        df_ihk_agregat = self._hitung_ihk_agregat(df_nk, df_master)

        print("[IHK] Menghitung inflasi (MtoM, YtD, YoY) dari IHK rounded...")
        if start_date:
            df_hist = self._get_ihk_historis_dari_db()
            if not df_hist.empty:
                # Gabungkan historis (sudah rounded saat disimpan) + baru
                df_ihk_all = pd.concat([
                    df_hist[~df_hist['tanggal'].isin(df_ihk_agregat['tanggal'])][['tanggal', 'nilai_ihk']],
                    df_ihk_agregat[['tanggal', 'nilai_ihk']],
                ], ignore_index=True).sort_values('tanggal').reset_index(drop=True)
                df_ihk_all['total_nk']         = None
                df_ihk_all['jumlah_komoditas'] = None
                for _, row in df_ihk_agregat.iterrows():
                    mask = df_ihk_all['tanggal'] == row['tanggal']
                    df_ihk_all.loc[mask, 'total_nk']         = row['total_nk']
                    df_ihk_all.loc[mask, 'jumlah_komoditas'] = row['jumlah_komoditas']
            else:
                df_ihk_all = df_ihk_agregat
        else:
            df_ihk_all = df_ihk_agregat

        df_inflasi_all = self._hitung_inflasi_agregat(df_ihk_all)

        bulan_baru = set(df_ihk_agregat['tanggal'].tolist())
        df_inflasi = df_inflasi_all[
            df_inflasi_all['tanggal'].isin(bulan_baru)
        ].copy().reset_index(drop=True)

        print("[IHK] Menghitung andil per komoditas...")
        df_andil = self._hitung_andil(df_nk)

        print("[IHK] Menyimpan ke database...")
        saved_ihk   = self._save_ihk_to_db(df_inflasi)
        saved_andil = self._save_andil_to_db(df_andil)
        print(f"[IHK] Tersimpan : {saved_ihk} ihk_bulanan | {saved_andil} andil_inflasi_bulanan")

        last = df_inflasi.iloc[-1]
        sf   = self._safe_float
        return {
            'success':        True,
            'bulan_dihitung': len(df_inflasi),
            'periode': {
                'start': str(df_inflasi['tanggal'].min().date()),
                'end':   str(df_inflasi['tanggal'].max().date()),
            },
            'ihk_terakhir':          round(float(last['nilai_ihk']), 2),
            'inflasi_mtom_terakhir': sf(last.get('inflasi_mtom')),
            'inflasi_ytd_terakhir':  sf(last.get('inflasi_ytd')),
            'inflasi_yoy_terakhir':  sf(last.get('inflasi_yoy')),
            'kondisi_terakhir':      last.get('kondisi'),
            'saved': {
                'ihk_bulanan':   saved_ihk,
                'andil_inflasi': saved_andil,
            },
        }

    def recalculate_latest(self, n_bulan: int = 3) -> dict:
        with self.db.engine.connect() as conn:
            result = conn.execute(text(
                "SELECT MAX(tanggal) FROM rh_komoditas"
            )).fetchone()

        if not result or not result[0]:
            return {'success': False, 'message': 'Tidak ada data RH'}

        latest     = pd.to_datetime(result[0]).replace(day=1)
        start_calc = (latest - pd.DateOffset(months=n_bulan - 1)).replace(day=1)

        print(f"[IHK] Recalculate: {start_calc.strftime('%Y-%m')} "
              f"s/d {latest.strftime('%Y-%m')} ({n_bulan} bulan)")

        return self.calculate_and_save_all(
            start_date=start_calc.strftime('%Y-%m-%d'),
            end_date=latest.strftime('%Y-%m-%d'),
        )

    # ───────────────────────────────────────────────────────────────────────
    # PUBLIC — Query hasil
    # ───────────────────────────────────────────────────────────────────────

    def get_ihk_summary(self) -> dict:
        query = """
            SELECT tanggal, nilai_ihk, inflasi, inflasi_ytd, inflasi_yoy, kondisi
            FROM ihk_bulanan
            ORDER BY tanggal DESC
            LIMIT 2
        """
        with self.db.engine.connect() as conn:
            rows = conn.execute(text(query)).fetchall()

        if not rows:
            return {
                'success': False,
                'message': 'Data IHK belum tersedia. Jalankan POST /api/ihk/calculate dulu.',
            }

        sf = self._safe_float
        r1 = rows[0]
        r0 = rows[1] if len(rows) > 1 else None

        data = {
            'periode':      str(r1[0])[:7],
            'nilai_ihk':    round(float(r1[1]), 2),
            'inflasi_mtom': sf(r1[2]),
            'inflasi_ytd':  sf(r1[3]),
            'inflasi_yoy':  sf(r1[4]),
            'kondisi':      r1[5],
        }

        if r0:
            ihk_ini  = float(r1[1])
            ihk_lalu = float(r0[1])
            selisih  = ihk_ini - ihk_lalu
            data['perbandingan_bulan_lalu'] = {
                'periode':       str(r0[0])[:7],
                'nilai_ihk':     round(ihk_lalu, 2),
                'kondisi':       r0[5],
                'selisih_ihk':   round(selisih, 2),
                'perubahan_pct': sf(r1[2]),
                'naik_turun':    'naik' if selisih > 0 else ('turun' if selisih < 0 else 'sama'),
            }

        return {'success': True, 'data': data}

    def get_ihk_history(self, start_date=None, end_date=None, limit: int = 60) -> dict:
        clauses = ["1=1"]
        params  = {'limit': limit}
        if start_date:
            clauses.append("tanggal >= :start_date")
            params['start_date'] = start_date
        if end_date:
            clauses.append("tanggal <= :end_date")
            params['end_date'] = end_date

        query = f"""
            SELECT tanggal, nilai_ihk, inflasi, inflasi_ytd, inflasi_yoy, kondisi
            FROM ihk_bulanan
            WHERE {" AND ".join(clauses)}
            ORDER BY tanggal ASC
            LIMIT :limit
        """
        with self.db.engine.connect() as conn:
            rows = conn.execute(text(query), params).fetchall()

        if not rows:
            return {'success': False, 'message': 'Belum ada data IHK.'}

        sf = self._safe_float
        return {
            'success': True,
            'data': {
                'total': len(rows),
                'history': [
                    {
                        'periode':      str(r[0])[:7],
                        'tanggal':      str(r[0]),
                        'nilai_ihk':    round(float(r[1]), 2),
                        'inflasi_mtom': sf(r[2]),
                        'inflasi_ytd':  sf(r[3]),
                        'inflasi_yoy':  sf(r[4]),
                        'kondisi':      r[5],
                    }
                    for r in rows
                ],
            },
        }

    def get_commodity_detail(self, bulan=None) -> dict:
        if bulan:
            try:
                target_date = pd.to_datetime(bulan + '-01')
            except Exception:
                return {'success': False, 'message': f'Format bulan tidak valid: {bulan}. Gunakan YYYY-MM'}
        else:
            with self.db.engine.connect() as conn:
                result = conn.execute(text(
                    "SELECT MAX(tanggal) FROM andil_inflasi_bulanan"
                )).fetchone()
            if not result or not result[0]:
                return {'success': False, 'message': 'Belum ada data. Jalankan kalkulasi dulu.'}
            target_date = pd.to_datetime(result[0]).replace(day=1)

        target_str = target_date.strftime('%Y-%m-%d')

        query = """
            SELECT
                aib.komoditas_id,
                mk.nama_komoditas,
                mk.nama_varian,
                mk.satuan,
                aib.nilai_rh,
                aib.nilai_nk_komoditas,
                aib.nilai_ihk_komoditas,
                aib.bobot_dinamis,
                aib.inflasi_mtom_komoditas,
                aib.andil_mtom
            FROM andil_inflasi_bulanan aib
            JOIN master_komoditas mk ON mk.id = aib.komoditas_id
            WHERE DATE_FORMAT(aib.tanggal, '%Y-%m') = DATE_FORMAT(:tanggal, '%Y-%m')
            ORDER BY ABS(COALESCE(aib.andil_mtom, 0)) DESC
        """
        query_ihk = """
            SELECT nilai_ihk, inflasi, inflasi_ytd, inflasi_yoy, kondisi
            FROM ihk_bulanan
            WHERE DATE_FORMAT(tanggal, '%Y-%m') = DATE_FORMAT(:tanggal, '%Y-%m')
            LIMIT 1
        """
        with self.db.engine.connect() as conn:
            rows    = conn.execute(text(query),     {'tanggal': target_str}).fetchall()
            ihk_row = conn.execute(text(query_ihk), {'tanggal': target_str}).fetchone()

        if not rows:
            return {
                'success': False,
                'message': f'Tidak ada data detail untuk bulan {target_date.strftime("%Y-%m")}',
            }

        sf = self._safe_float
        return {
            'success': True,
            'data': {
                'periode':          target_date.strftime('%Y-%m'),
                'nilai_ihk':        sf(ihk_row[0]) if ihk_row else None,
                'inflasi_mtom':     sf(ihk_row[1]) if ihk_row else None,
                'inflasi_ytd':      sf(ihk_row[2]) if ihk_row else None,
                'inflasi_yoy':      sf(ihk_row[3]) if ihk_row else None,
                'kondisi':          ihk_row[4]      if ihk_row else None,
                'jumlah_komoditas': len(rows),
                'komoditas': [
                    {
                        'komoditas_id':           int(r[0]),
                        'nama':                   r[1] + (f' ({r[2]})' if r[2] else ''),
                        'satuan':                 r[3],
                        'nilai_rh':               sf(r[4]),
                        'nilai_nk_komoditas':     sf(r[5]),
                        'nilai_ihk_komoditas':    sf(r[6]),
                        'bobot_dinamis':          sf(r[7]),
                        'inflasi_mtom_komoditas': sf(r[8]),
                        'andil_mtom':             sf(r[9]),
                    }
                    for r in rows
                ],
            },
        }

    def get_inflasi_comparison(self, bulan=None) -> dict:
        if bulan:
            try:
                target_date = pd.to_datetime(bulan + '-01')
            except Exception:
                return {'success': False, 'message': f'Format bulan tidak valid: {bulan}'}
        else:
            with self.db.engine.connect() as conn:
                result = conn.execute(text(
                    "SELECT MAX(tanggal) FROM ihk_bulanan"
                )).fetchone()
            if not result or not result[0]:
                return {'success': False, 'message': 'Belum ada data IHK.'}
            target_date = pd.to_datetime(result[0]).replace(day=1)

        prev_date = (target_date - pd.DateOffset(months=1)).replace(day=1)

        with self.db.engine.connect() as conn:
            rows = conn.execute(text("""
                SELECT tanggal, nilai_ihk, inflasi, inflasi_ytd, inflasi_yoy, kondisi
                FROM ihk_bulanan
                WHERE tanggal IN (:t1, :t2)
                ORDER BY tanggal ASC
            """), {
                't1': prev_date.strftime('%Y-%m-%d'),
                't2': target_date.strftime('%Y-%m-%d'),
            }).fetchall()

        if len(rows) < 2:
            return {
                'success': False,
                'message': (
                    f'Data tidak lengkap untuk perbandingan '
                    f'{prev_date.strftime("%Y-%m")} vs {target_date.strftime("%Y-%m")}'
                ),
            }

        sf = self._safe_float
        r0, r1   = rows[0], rows[1]
        ihk_lalu = float(r0[1])
        ihk_ini  = float(r1[1])
        selisih  = ihk_ini - ihk_lalu

        with self.db.engine.connect() as conn:
            andil_rows = conn.execute(text("""
                SELECT
                    aib.komoditas_id,
                    mk.nama_komoditas,
                    aib.nilai_rh,
                    aib.bobot_dinamis,
                    aib.inflasi_mtom_komoditas,
                    aib.andil_mtom
                FROM andil_inflasi_bulanan aib
                JOIN master_komoditas mk ON mk.id = aib.komoditas_id
                WHERE DATE_FORMAT(aib.tanggal, '%Y-%m') = DATE_FORMAT(:tanggal, '%Y-%m')
                ORDER BY ABS(COALESCE(aib.andil_mtom, 0)) DESC
            """), {'tanggal': target_date.strftime('%Y-%m-%d')}).fetchall()

        return {
            'success': True,
            'data': {
                'bulan_ini': {
                    'periode':      str(r1[0])[:7],
                    'nilai_ihk':    round(ihk_ini, 2),
                    'inflasi_mtom': sf(r1[2]),
                    'inflasi_ytd':  sf(r1[3]),
                    'inflasi_yoy':  sf(r1[4]),
                    'kondisi':      r1[5],
                },
                'bulan_lalu': {
                    'periode':      str(r0[0])[:7],
                    'nilai_ihk':    round(ihk_lalu, 2),
                    'inflasi_mtom': sf(r0[2]),
                    'inflasi_ytd':  sf(r0[3]),
                    'inflasi_yoy':  sf(r0[4]),
                    'kondisi':      r0[5],
                },
                'perbandingan': {
                    'selisih_ihk':     round(selisih, 2),
                    'perubahan_pct':   sf(r1[2]),
                    'naik_turun':      'naik' if selisih > 0 else ('turun' if selisih < 0 else 'sama'),
                    'kondisi_berubah': r0[5] != r1[5],
                    'kondisi_dari':    r0[5],
                    'kondisi_ke':      r1[5],
                },
                'andil_komoditas': [
                    {
                        'komoditas_id':           int(r[0]),
                        'nama_komoditas':         r[1],
                        'nilai_rh':               sf(r[2]),
                        'bobot_dinamis':          sf(r[3]),
                        'inflasi_mtom_komoditas': sf(r[4]),
                        'andil_mtom':             sf(r[5]),
                    }
                    for r in andil_rows
                ],
            },
        }

    def get_inflasi_forecast_context(self) -> dict:
        with self.db.engine.connect() as conn:
            rows = conn.execute(text("""
                SELECT tanggal, nilai_ihk
                FROM ihk_bulanan
                ORDER BY tanggal ASC
            """)).fetchall()

        if not rows:
            return {'success': False, 'message': 'Belum ada data IHK untuk forecast.'}

        return {
            'success': True,
            'data': [{'ds': str(r[0]), 'y': float(r[1])} for r in rows],
        }