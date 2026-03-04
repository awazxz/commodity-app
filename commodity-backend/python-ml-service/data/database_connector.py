"""
Database Connector for Commodity Application
Handles all database operations for price data and forecasting
"""

# data/database_connector.py
import os
import pandas as pd
from sqlalchemy import create_engine, text
from dotenv import load_dotenv

load_dotenv()


class DatabaseConnector:
    def __init__(self):
        self.engine               = self._create_engine()
        self._forecast_cols_cache = None

    # =========================================================
    # ENGINE
    # =========================================================

    def _create_engine(self):
        DB_USER = os.getenv('DB_USERNAME') or os.getenv('DB_USER', 'root')
        DB_PASS = os.getenv('DB_PASSWORD', '')
        DB_HOST = os.getenv('DB_HOST', '127.0.0.1')
        DB_PORT = os.getenv('DB_PORT', '3306')
        DB_NAME = os.getenv('DB_DATABASE', 'commodityapp')

        connection_string = (
            f'mysql+pymysql://{DB_USER}:{DB_PASS}@{DB_HOST}:{DB_PORT}/{DB_NAME}'
            f'?charset=utf8mb4'
        )

        print(f"   [DB] Connecting to {DB_HOST}:{DB_PORT}/{DB_NAME} as '{DB_USER}'")

        return create_engine(
            connection_string,
            pool_pre_ping=True,
            pool_recycle=3600,
            pool_size=5,
            max_overflow=10,
        )

    # =========================================================
    # HEALTH CHECK
    # =========================================================

    def test_connection(self):
        with self.engine.connect() as conn:
            conn.execute(text("SELECT 1"))
        return True

    # =========================================================
    # COMMODITIES
    # =========================================================

    def get_all_commodities(self):
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
            result = conn.execute(text(query)).fetchall()

        commodities = []
        for row in result:
            commodities.append({
                'id':               row[0],
                'nama_komoditas':   row[1],
                'nama_varian':      row[2] or '',
                'satuan':           row[3] or 'Kg',
                'jumlah_data':      row[4],
                'tanggal_terakhir': str(row[5]) if row[5] else None,
                'harga_terakhir':   float(row[6]) if row[6] else None,
                'full_name':        f"{row[1]} {row[2] or ''}".strip(),
            })
        return commodities

    def get_commodity_info(self, commodity_id):
        query = """
            SELECT id, nama_komoditas, nama_varian, satuan
            FROM master_komoditas
            WHERE id = :id
        """
        with self.engine.connect() as conn:
            result = conn.execute(text(query), {'id': commodity_id}).fetchone()

        if not result:
            return None

        return {
            'id':             result[0],
            'nama_komoditas': result[1],
            'nama_varian':    result[2] or '',
            'satuan':         result[3] or 'Kg',
            'full_name':      f"{result[1]} {result[2] or ''}".strip(),
        }

    # =========================================================
    # PRICE DATA
    # =========================================================

    def get_commodity_prices(self, commodity_id, start_date=None, end_date=None):
        query = """
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

            if df['ds'].duplicated().any():
                dup_count = df['ds'].duplicated().sum()
                print(f"   [DB] ⚠️  Ditemukan {dup_count} duplikat tanggal untuk komoditas_id={commodity_id}, di-aggregate...")
                df = df.groupby('ds', as_index=False)['y'].mean()
                df = df.sort_values('ds').reset_index(drop=True)

        print(f"   [DB] get_commodity_prices: komoditas_id={commodity_id} → {len(df)} baris valid")
        return df

    def get_price_statistics(self, commodity_id, start_date=None, end_date=None):
        terkini_where = "komoditas_id = :id AND harga IS NOT NULL AND harga > 0"
        if start_date:
            terkini_where += " AND tanggal >= :start"
        if end_date:
            terkini_where += " AND tanggal <= :end"

        query = f"""
            SELECT
                COUNT(*)            AS total_data,
                AVG(harga)          AS rata_rata,
                MIN(harga)          AS harga_min,
                MAX(harga)          AS harga_max,
                MIN(tanggal)        AS tanggal_awal,
                MAX(tanggal)        AS tanggal_akhir,
                STDDEV(harga)       AS std_deviasi,
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
            query += " AND tanggal >= :start"
            params['start'] = start_date
        if end_date:
            query += " AND tanggal <= :end"
            params['end'] = end_date

        with self.engine.connect() as conn:
            result = conn.execute(text(query), params).fetchone()

        if not result or result[0] == 0:
            return None

        return {
            'total_data':    result[0],
            'rata_rata':     round(float(result[1]), 2) if result[1] else 0,
            'harga_min':     round(float(result[2]), 2) if result[2] else 0,
            'harga_max':     round(float(result[3]), 2) if result[3] else 0,
            'tanggal_awal':  str(result[4]) if result[4] else None,
            'tanggal_akhir': str(result[5]) if result[5] else None,
            'std_deviasi':   round(float(result[6]), 2) if result[6] else 0,
            'harga_terkini': round(float(result[7]), 2) if result[7] else 0,
        }

    # =========================================================
    # FORECAST RESULTS
    # =========================================================

    def save_forecast_results(self, commodity_id: int, forecast_df: pd.DataFrame) -> None:
        """
        Simpan hasil forecast ke tabel price_forecasts.

        Struktur tabel price_forecasts yang digunakan:
            id              BIGINT AUTO_INCREMENT
            komoditas_id    BIGINT
            tanggal         DATE
            harga_aktual    DECIMAL(15,2) NULL
            harga_prediksi  DECIMAL(15,2) NOT NULL
            harga_lower     DECIMAL(15,2) NULL
            harga_upper     DECIMAL(15,2) NULL
            periode         ENUM('weekly','monthly','yearly') DEFAULT 'weekly'
            created_at      TIMESTAMP NULL
            updated_at      TIMESTAMP NULL
        """
        try:
            with self.engine.begin() as conn:

                # ✅ Hapus forecast lama untuk komoditas ini di price_forecasts
                conn.execute(
                    text("DELETE FROM price_forecasts WHERE komoditas_id = :id"),
                    {'id': commodity_id}
                )

                inserted = 0
                for _, row in forecast_df.iterrows():
                    conn.execute(text("""
                        INSERT INTO price_forecasts
                            (komoditas_id, tanggal, harga_prediksi, harga_lower, harga_upper, periode, created_at, updated_at)
                        VALUES
                            (:komoditas_id, :tanggal, :harga_prediksi, :harga_lower, :harga_upper, 'weekly', NOW(), NOW())
                        ON DUPLICATE KEY UPDATE
                            harga_prediksi = VALUES(harga_prediksi),
                            harga_lower    = VALUES(harga_lower),
                            harga_upper    = VALUES(harga_upper),
                            updated_at     = NOW()
                    """), {
                        'komoditas_id':  commodity_id,
                        'tanggal':       row['ds'].strftime('%Y-%m-%d'),
                        'harga_prediksi': round(float(row['yhat']),       2),
                        'harga_lower':    round(float(row['yhat_lower']), 2),
                        'harga_upper':    round(float(row['yhat_upper']), 2),
                    })
                    inserted += 1

                print(f"   [DB] ✅ Berhasil simpan {inserted} baris forecast ke price_forecasts untuk komoditas_id={commodity_id}")

        except Exception as e:
            print(f"   [DB] ❌ save_forecast_results error: {e}")
            raise

    def save_forecast_run(self, commodity_id: int, metrics: dict, params: dict) -> None:
        """
        Simpan metadata run forecast ke tabel forecast_results
        (hanya menyimpan ringkasan metrics, bukan detail per-tanggal).
        """
        try:
            with self.engine.begin() as conn:
                conn.execute(text("""
                    INSERT INTO forecast_results
                        (komoditas_id, run_at, mape, rmse,
                         changepoint_prior_scale, seasonality_prior_scale,
                         seasonality_mode, weekly_seasonality, yearly_seasonality,
                         status, created_at, updated_at)
                    VALUES
                        (:komoditas_id, NOW(), :mape, :rmse,
                         :cp_scale, :ss_scale,
                         :s_mode, :weekly, :yearly,
                         'success', NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                        run_at = NOW(), mape = VALUES(mape), rmse = VALUES(rmse),
                        status = 'success', updated_at = NOW()
                """), {
                    'komoditas_id': commodity_id,
                    'mape':         round(float(metrics.get('mape', 0)), 4),
                    'rmse':         round(float(metrics.get('rmse', 0)), 4),
                    'cp_scale':     params.get('changepoint_prior_scale', 0.05),
                    'ss_scale':     params.get('seasonality_prior_scale', 10.0),
                    's_mode':       params.get('seasonality_mode', 'multiplicative'),
                    'weekly':       1 if params.get('weekly_seasonality') else 0,
                    'yearly':       1 if params.get('yearly_seasonality') else 0,
                })
                print(f"   [DB] ✅ Metadata forecast run tersimpan di forecast_results untuk komoditas_id={commodity_id}")

        except Exception as e:
            print(f"   [DB] ⚠️  Gagal simpan metadata forecast_results: {e}")
            # Non-blocking — tidak raise, agar forecast tetap sukses