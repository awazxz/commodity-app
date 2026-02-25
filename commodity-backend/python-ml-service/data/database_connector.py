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
        self.engine = self._create_engine()

    def _create_engine(self):
        """Buat koneksi ke MySQL"""
        DB_USER = os.getenv('DB_USER', 'root')
        DB_PASS = os.getenv('DB_PASSWORD', '')
        DB_HOST = os.getenv('DB_HOST', 'localhost')
        DB_PORT = os.getenv('DB_PORT', '3306')
        DB_NAME = os.getenv('DB_DATABASE', 'commodityapp')

        connection_string = f'mysql+pymysql://{DB_USER}:{DB_PASS}@{DB_HOST}:{DB_PORT}/{DB_NAME}'
        engine = create_engine(connection_string, pool_pre_ping=True, pool_recycle=3600)
        return engine

    def test_connection(self):
        """Test apakah koneksi berhasil"""
        with self.engine.connect() as conn:
            conn.execute(text("SELECT 1"))
        return True

    def get_all_commodities(self):
        """Ambil semua komoditas"""
        query = """
            SELECT 
                mk.id,
                mk.nama_komoditas,
                mk.nama_varian,
                mk.satuan,
                mk.kategori,
                COUNT(pd.id) as jumlah_data,
                MAX(pd.tanggal) as tanggal_terakhir,
                MAX(pd.harga) as harga_terakhir
            FROM master_komoditas mk
            LEFT JOIN price_data pd ON mk.id = pd.komoditas_id
            GROUP BY mk.id, mk.nama_komoditas, mk.nama_varian, mk.satuan, mk.kategori
            ORDER BY mk.nama_komoditas, mk.nama_varian
        """
        with self.engine.connect() as conn:
            result = conn.execute(text(query)).fetchall()

        commodities = []
        for row in result:
            commodities.append({
                'id': row[0],
                'nama_komoditas': row[1],
                'nama_varian': row[2] or '',
                'satuan': row[3] or 'Kg',
                'kategori': row[4] or '',
                'jumlah_data': row[5],
                'tanggal_terakhir': str(row[6]) if row[6] else None,
                'harga_terakhir': float(row[7]) if row[7] else None,
                'full_name': f"{row[1]} {row[2] or ''}".strip()
            })
        return commodities

    def get_commodity_info(self, commodity_id):
        """Ambil info satu komoditas"""
        query = """
            SELECT id, nama_komoditas, nama_varian, satuan, kategori
            FROM master_komoditas
            WHERE id = :id
        """
        with self.engine.connect() as conn:
            result = conn.execute(text(query), {'id': commodity_id}).fetchone()

        if not result:
            return None

        return {
            'id': result[0],
            'nama_komoditas': result[1],
            'nama_varian': result[2] or '',
            'satuan': result[3] or 'Kg',
            'kategori': result[4] or '',
            'full_name': f"{result[1]} {result[2] or ''}".strip()
        }

    def get_commodity_prices(self, commodity_id, start_date=None, end_date=None):
        """
        Ambil data harga historis sebuah komoditas.
        Kembalikan DataFrame dengan kolom 'ds' (tanggal) dan 'y' (harga)
        sesuai format yang dibutuhkan Prophet.
        """
        query = """
            SELECT tanggal as ds, harga as y
            FROM price_data
            WHERE komoditas_id = :id
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
            df['y'] = df['y'].astype(float)

        return df

    def get_price_statistics(self, commodity_id, start_date=None, end_date=None):
        """Ambil statistik harga sebuah komoditas"""
        query = """
            SELECT 
                COUNT(*) as total_data,
                AVG(harga) as rata_rata,
                MIN(harga) as harga_min,
                MAX(harga) as harga_max,
                MIN(tanggal) as tanggal_awal,
                MAX(tanggal) as tanggal_akhir,
                STDDEV(harga) as std_deviasi,
                (SELECT harga FROM price_data WHERE komoditas_id = :id ORDER BY tanggal DESC LIMIT 1) as harga_terkini
            FROM price_data
            WHERE komoditas_id = :id
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
            'total_data': result[0],
            'rata_rata': round(float(result[1]), 2) if result[1] else 0,
            'harga_min': round(float(result[2]), 2) if result[2] else 0,
            'harga_max': round(float(result[3]), 2) if result[3] else 0,
            'tanggal_awal': str(result[4]) if result[4] else None,
            'tanggal_akhir': str(result[5]) if result[5] else None,
            'std_deviasi': round(float(result[6]), 2) if result[6] else 0,
            'harga_terkini': round(float(result[7]), 2) if result[7] else 0,
        }

    def save_forecast_results(self, commodity_id, forecast_df):
        """Simpan hasil forecast ke database"""
        # Hapus forecast lama untuk komoditas ini
        delete_query = "DELETE FROM forecast_results WHERE komoditas_id = :id"
        with self.engine.begin() as conn:
            conn.execute(text(delete_query), {'id': commodity_id})

        # Insert forecast baru
        insert_query = """
            INSERT INTO forecast_results (komoditas_id, tanggal_prediksi, harga_prediksi, lower_bound, upper_bound)
            VALUES (:komoditas_id, :tanggal, :harga, :lower, :upper)
        """
        with self.engine.begin() as conn:
            for _, row in forecast_df.iterrows():
                conn.execute(text(insert_query), {
                    'komoditas_id': commodity_id,
                    'tanggal': row['ds'].strftime('%Y-%m-%d'),
                    'harga': round(float(row['yhat']), 2),
                    'lower': round(float(row['yhat_lower']), 2),
                    'upper': round(float(row['yhat_upper']), 2),
                })