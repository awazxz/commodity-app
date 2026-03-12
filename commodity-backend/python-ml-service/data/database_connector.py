"""
data/database_connector.py

Database Connector untuk aplikasi komoditas.
Menangani semua operasi DB: price data, forecast results, model metadata.
"""

import os
import pandas as pd
from datetime import datetime
from sqlalchemy import create_engine, text
from dotenv import load_dotenv

load_dotenv()


class DatabaseConnector:

    def __init__(self):
        self.engine               = self._create_engine()
        self._forecast_cols_cache = None

    # ═══════════════════════════════════════════════════════════
    # ENGINE
    # ═══════════════════════════════════════════════════════════

    def _create_engine(self):
        DB_USER = os.getenv('DB_USERNAME') or os.getenv('DB_USER', 'root')
        DB_PASS = os.getenv('DB_PASSWORD', '')
        DB_HOST = os.getenv('DB_HOST', '127.0.0.1')
        DB_PORT = os.getenv('DB_PORT', '3306')
        DB_NAME = os.getenv('DB_DATABASE', 'commodityapp')

        conn_str = (
            f'mysql+pymysql://{DB_USER}:{DB_PASS}@{DB_HOST}:{DB_PORT}/{DB_NAME}'
            f'?charset=utf8mb4'
        )
        print(f"   [DB] Connecting → {DB_HOST}:{DB_PORT}/{DB_NAME} as '{DB_USER}'")

        return create_engine(
            conn_str,
            pool_pre_ping  = True,
            pool_recycle   = 3600,
            pool_size      = 5,
            max_overflow   = 10,
        )

    def test_connection(self) -> bool:
        with self.engine.connect() as conn:
            conn.execute(text("SELECT 1"))
        return True

    # ═══════════════════════════════════════════════════════════
    # COMMODITIES
    # ═══════════════════════════════════════════════════════════

    def get_all_commodities(self) -> list:
        query = """
            SELECT
                mk.id,
                mk.nama_komoditas,
                mk.nama_varian,
                mk.satuan,
                COUNT(pd.id)        AS jumlah_data,
                MAX(pd.tanggal)     AS tanggal_terakhir,
                MAX(pd.harga)       AS harga_terakhir
            FROM master_komoditas mk
            LEFT JOIN price_data pd
                ON mk.id = pd.komoditas_id
               AND pd.harga IS NOT NULL
               AND pd.harga > 0
            GROUP BY mk.id, mk.nama_komoditas, mk.nama_varian, mk.satuan
            ORDER BY mk.nama_komoditas, mk.nama_varian
        """
        with self.engine.connect() as conn:
            rows = conn.execute(text(query)).fetchall()

        return [
            {
                'id':               r[0],
                'nama_komoditas':   r[1],
                'nama_varian':      r[2] or '',
                'satuan':           r[3] or 'Kg',
                'jumlah_data':      r[4],
                'tanggal_terakhir': str(r[5]) if r[5] else None,
                'harga_terakhir':   float(r[6]) if r[6] else None,
                'full_name':        f"{r[1]} {r[2] or ''}".strip(),
            }
            for r in rows
        ]

    def get_commodity_info(self, commodity_id: int) -> dict:
        query = """
            SELECT id, nama_komoditas, nama_varian, satuan
            FROM master_komoditas
            WHERE id = :id
        """
        with self.engine.connect() as conn:
            row = conn.execute(text(query), {'id': commodity_id}).fetchone()

        if not row:
            return None

        return {
            'id':             row[0],
            'nama_komoditas': row[1],
            'nama_varian':    row[2] or '',
            'satuan':         row[3] or 'Kg',
            'full_name':      f"{row[1]} {row[2] or ''}".strip(),
        }

    # ═══════════════════════════════════════════════════════════
    # PRICE DATA
    # ═══════════════════════════════════════════════════════════

    def get_commodity_prices(
        self,
        commodity_id: int,
        start_date:   str = None,
        end_date:     str = None,
    ) -> pd.DataFrame:
        """
        Ambil data harga historis untuk satu komoditas.
        Return DataFrame: ds (datetime), y (float)
        """
        query  = """
            SELECT tanggal AS ds, harga AS y
            FROM price_data
            WHERE komoditas_id = :id
              AND harga IS NOT NULL
              AND harga > 0
        """
        params = {'id': commodity_id}

        if start_date:
            query += " AND tanggal >= :start_date"
            params['start_date'] = start_date
        if end_date:
            query += " AND tanggal <= :end_date"
            params['end_date'] = end_date

        query += " ORDER BY tanggal ASC"

        with self.engine.connect() as conn:
            df = pd.read_sql(text(query), conn, params=params)

        if not df.empty:
            df['ds'] = pd.to_datetime(df['ds'])
            df['y']  = df['y'].astype(float)

            # Deduplikasi tanggal
            if df['ds'].duplicated().any():
                dup_count = df['ds'].duplicated().sum()
                print(f"   [DB] ⚠️  {dup_count} duplikat tanggal untuk "
                      f"komoditas_id={commodity_id}, di-aggregate...")
                df = (
                    df.groupby('ds', as_index=False)['y']
                    .mean()
                    .sort_values('ds')
                    .reset_index(drop=True)
                )

        print(f"   [DB] get_commodity_prices: id={commodity_id} → {len(df)} baris valid")
        return df

    def get_latest_price_date(self, commodity_id: int):
        """
        Ambil tanggal data harga terbaru untuk komoditas.
        Digunakan oleh scheduler untuk deteksi data baru.
        Return: datetime | None
        """
        query = """
            SELECT MAX(tanggal)
            FROM price_data
            WHERE komoditas_id = :id
              AND harga IS NOT NULL
              AND harga > 0
        """
        with self.engine.connect() as conn:
            result = conn.execute(text(query), {'id': commodity_id}).fetchone()

        if result and result[0]:
            return pd.to_datetime(result[0])
        return None

    def get_price_statistics(
        self,
        commodity_id: int,
        start_date:   str = None,
        end_date:     str = None,
    ) -> dict:
        terkini_where = "komoditas_id = :id AND harga IS NOT NULL AND harga > 0"
        if start_date:
            terkini_where += " AND tanggal >= :start"
        if end_date:
            terkini_where += " AND tanggal <= :end"

        query = f"""
            SELECT
                COUNT(*)        AS total_data,
                AVG(harga)      AS rata_rata,
                MIN(harga)      AS harga_min,
                MAX(harga)      AS harga_max,
                MIN(tanggal)    AS tanggal_awal,
                MAX(tanggal)    AS tanggal_akhir,
                STDDEV(harga)   AS std_deviasi,
                (
                    SELECT harga FROM price_data
                    WHERE {terkini_where}
                    ORDER BY tanggal DESC LIMIT 1
                ) AS harga_terkini
            FROM price_data
            WHERE komoditas_id = :id
              AND harga IS NOT NULL
              AND harga > 0
        """
        params = {'id': commodity_id}
        if start_date:
            query  += " AND tanggal >= :start"
            params['start'] = start_date
        if end_date:
            query  += " AND tanggal <= :end"
            params['end'] = end_date

        with self.engine.connect() as conn:
            row = conn.execute(text(query), params).fetchone()

        if not row or row[0] == 0:
            return None

        return {
            'total_data':    row[0],
            'rata_rata':     round(float(row[1]), 2) if row[1] else 0,
            'harga_min':     round(float(row[2]), 2) if row[2] else 0,
            'harga_max':     round(float(row[3]), 2) if row[3] else 0,
            'tanggal_awal':  str(row[4]) if row[4] else None,
            'tanggal_akhir': str(row[5]) if row[5] else None,
            'std_deviasi':   round(float(row[6]), 2) if row[6] else 0,
            'harga_terkini': round(float(row[7]), 2) if row[7] else 0,
        }

    # ═══════════════════════════════════════════════════════════
    # FORECAST RESULTS
    # ═══════════════════════════════════════════════════════════

    def save_forecast_results(
        self,
        commodity_id: int,
        forecast_df:  pd.DataFrame,
        periode:      str = None,
    ) -> int:
        """
        Simpan hasil forecast ke tabel price_forecasts.

        Pola: DELETE lama → INSERT baru (dalam satu transaksi).
        `periode` disesuaikan dengan frekuensi data aktual.

        Return: jumlah baris yang berhasil diinsert
        """
        # Map frekuensi ke nilai kolom periode
        freq_to_periode = {
            'D':  'daily',
            'W':  'weekly',
            'MS': 'monthly',
            'M':  'monthly',
        }
        if periode is None:
            periode = 'weekly'

        try:
            with self.engine.begin() as conn:

                # Hapus forecast lama
                deleted = conn.execute(
                    text("DELETE FROM price_forecasts WHERE komoditas_id = :id"),
                    {'id': commodity_id}
                ).rowcount
                print(f"   [DB] 🗑️  Hapus {deleted} forecast lama untuk id={commodity_id}")

                inserted = 0
                for _, row in forecast_df.iterrows():
                    conn.execute(text("""
                        INSERT INTO price_forecasts
                            (komoditas_id, tanggal, harga_prediksi,
                             harga_lower, harga_upper, periode,
                             created_at, updated_at)
                        VALUES
                            (:komoditas_id, :tanggal, :harga_prediksi,
                             :harga_lower, :harga_upper, :periode,
                             NOW(), NOW())
                        ON DUPLICATE KEY UPDATE
                            harga_prediksi = VALUES(harga_prediksi),
                            harga_lower    = VALUES(harga_lower),
                            harga_upper    = VALUES(harga_upper),
                            updated_at     = NOW()
                    """), {
                        'komoditas_id':   commodity_id,
                        'tanggal':        row['ds'].strftime('%Y-%m-%d'),
                        'harga_prediksi': round(float(row['yhat']),       2),
                        'harga_lower':    round(float(row['yhat_lower']), 2),
                        'harga_upper':    round(float(row['yhat_upper']), 2),
                        'periode':        periode,
                    })
                    inserted += 1

                print(f"   [DB] ✅ {inserted} forecast baru tersimpan untuk id={commodity_id}")
                return inserted

        except Exception as e:
            print(f"   [DB] ❌ save_forecast_results error: {e}")
            raise

    def save_forecast_run(
        self,
        commodity_id: int,
        metrics:      dict,
        params:       dict,
        engine_used:  str = 'prophet',
        reason:       str = '',
    ) -> None:
        """
        Simpan metadata run forecast ke tabel forecast_results.
        Berisi ringkasan metrics + info retraining.
        """
        try:
            with self.engine.begin() as conn:
                conn.execute(text("""
                    INSERT INTO forecast_results
                        (komoditas_id, run_at, mape, rmse,
                         changepoint_prior_scale, seasonality_prior_scale,
                         seasonality_mode, weekly_seasonality, yearly_seasonality,
                         engine_used, retrain_reason,
                         status, created_at, updated_at)
                    VALUES
                        (:komoditas_id, NOW(), :mape, :rmse,
                         :cp_scale, :ss_scale,
                         :s_mode, :weekly, :yearly,
                         :engine_used, :reason,
                         'success', NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                        run_at         = NOW(),
                        mape           = VALUES(mape),
                        rmse           = VALUES(rmse),
                        engine_used    = VALUES(engine_used),
                        retrain_reason = VALUES(retrain_reason),
                        status         = 'success',
                        updated_at     = NOW()
                """), {
                    'komoditas_id': commodity_id,
                    'mape':         round(float(metrics.get('mape', 0)), 4),
                    'rmse':         round(float(metrics.get('rmse', 0)), 4),
                    'cp_scale':     params.get('changepoint_prior_scale', 0.05),
                    'ss_scale':     params.get('seasonality_prior_scale', 10.0),
                    's_mode':       params.get('seasonality_mode', 'multiplicative'),
                    'weekly':       1 if params.get('weekly_seasonality') else 0,
                    'yearly':       1 if params.get('yearly_seasonality') else 0,
                    'engine_used':  engine_used,
                    'reason':       reason,
                })
                print(f"   [DB] ✅ Metadata forecast tersimpan untuk id={commodity_id} "
                      f"(engine={engine_used}, reason={reason})")

        except Exception as e:
            # Non-blocking: gagal simpan metadata tidak menghentikan forecast
            print(f"   [DB] ⚠️  Gagal simpan metadata forecast_results: {e}")

    def get_last_forecast_run(self, commodity_id: int) -> dict:
        """
        Ambil info run forecast terakhir untuk satu komoditas.
        Digunakan di dashboard untuk menampilkan kapan terakhir diperbarui.
        """
        query = """
            SELECT run_at, mape, rmse, engine_used, retrain_reason, status
            FROM forecast_results
            WHERE komoditas_id = :id
            ORDER BY run_at DESC
            LIMIT 1
        """
        try:
            with self.engine.connect() as conn:
                row = conn.execute(text(query), {'id': commodity_id}).fetchone()

            if not row:
                return None

            return {
                'run_at':         str(row[0]) if row[0] else None,
                'mape':           float(row[1]) if row[1] else 0,
                'rmse':           float(row[2]) if row[2] else 0,
                'engine_used':    row[3] or 'prophet',
                'retrain_reason': row[4] or '',
                'status':         row[5] or 'unknown',
            }
        except Exception:
            return None

    # ═══════════════════════════════════════════════════════════
    # UPLOAD CSV HELPERS
    # ═══════════════════════════════════════════════════════════

    def find_commodity_id(self, nama_komoditas: str) -> int:
        """Cari komoditas_id dari nama (fuzzy match)."""
        parts  = nama_komoditas.split(' ', 1)
        nama   = parts[0]
        varian = parts[1] if len(parts) > 1 else ''

        query  = "SELECT id FROM master_komoditas WHERE nama_komoditas LIKE :nama"
        params = {'nama': f'%{nama}%'}

        if varian:
            query  += " AND (nama_varian LIKE :varian OR nama_varian IS NULL OR nama_varian = '')"
            params['varian'] = f'%{varian}%'

        query += " LIMIT 1"

        try:
            with self.engine.connect() as conn:
                result = conn.execute(text(query), params).fetchone()
                return result[0] if result else None
        except Exception:
            return None

    def upsert_price(self, commodity_id: int, date_str: str, price: float) -> str:
        """
        Insert atau update satu baris harga.
        Return: 'inserted' | 'updated'
        """
        with self.engine.begin() as conn:
            result = conn.execute(text("""
                INSERT INTO price_data
                    (komoditas_id, tanggal, harga, created_at, updated_at)
                VALUES
                    (:cid, :date, :price, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    harga      = VALUES(harga),
                    updated_at = NOW()
            """), {'cid': commodity_id, 'date': date_str, 'price': price})

        return 'inserted' if result.rowcount == 1 else 'updated'