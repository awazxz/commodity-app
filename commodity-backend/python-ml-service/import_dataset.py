# import_dataset.py
# Jalankan SEKALI untuk import dataset awal kamu
import pandas as pd
from sqlalchemy import create_engine, text
from dotenv import load_dotenv
import os

load_dotenv()

# Koneksi database
DB_USER = os.getenv('DB_USER', 'root')
DB_PASS = os.getenv('DB_PASSWORD', '')
DB_HOST = os.getenv('DB_HOST', 'localhost')
DB_PORT = os.getenv('DB_PORT', '3306')
DB_NAME = os.getenv('DB_DATABASE', 'commodityapp')

engine = create_engine(f'mysql+pymysql://{DB_USER}:{DB_PASS}@{DB_HOST}:{DB_PORT}/{DB_NAME}')

# ======================================================
# GANTI PATH INI dengan lokasi dataset kamu
# ======================================================
DATASET_PATH = r'C:\laragon\www\FORECASTING\commodity-backend\laravel-api\database\seeders\csv\testing_data.csv'

print("📂 Membaca dataset...")
df = pd.read_csv(DATASET_PATH)
df.columns = df.columns.str.strip().str.lower()

print(f"✅ Dataset dibaca: {len(df)} baris")
print(f"📋 Kolom: {list(df.columns)}")
print(df.head())

# Ambil daftar komoditas dari database
with engine.connect() as conn:
    komoditas_db = conn.execute(text("SELECT id, nama_komoditas, nama_varian FROM master_komoditas")).fetchall()

# Buat mapping nama_komoditas -> id
komoditas_map = {}
for row in komoditas_db:
    nama = row[1].strip().lower()
    varian = (row[2] or '').strip().lower()
    full_name = f"{nama} {varian}".strip()
    komoditas_map[full_name] = row[0]
    komoditas_map[nama] = row[0]  # fallback tanpa varian

inserted = skipped = 0
errors = []

print("\n⚙️  Memproses data...")
for index, row in df.iterrows():
    try:
        nama_komoditas = str(row['nama_komoditas']).strip().lower()
        tanggal = pd.to_datetime(row['tanggal']).strftime('%Y-%m-%d')
        harga = float(str(row['harga']).replace(',', '').replace('Rp', '').strip())
        
        # Cari commodity_id
        commodity_id = komoditas_map.get(nama_komoditas)
        if not commodity_id:
            # Coba partial match
            for key, val in komoditas_map.items():
                if nama_komoditas in key or key in nama_komoditas:
                    commodity_id = val
                    break
        
        if not commodity_id:
            errors.append(f"Row {index+2}: Komoditas '{nama_komoditas}' tidak ditemukan")
            skipped += 1
            continue
        
        query = """
            INSERT INTO price_data (komoditas_id, tanggal, harga, created_at, updated_at)
            VALUES (:commodity_id, :date, :price, NOW(), NOW())
            ON DUPLICATE KEY UPDATE harga = VALUES(harga), updated_at = NOW()
        """
        with engine.begin() as conn:
            conn.execute(text(query), {
                'commodity_id': commodity_id,
                'date': tanggal,
                'price': harga
            })
        inserted += 1
        
    except Exception as e:
        errors.append(f"Row {index+2}: {str(e)}")
        skipped += 1

print(f"\n✅ SELESAI!")
print(f"   Inserted/Updated : {inserted}")
print(f"   Skipped          : {skipped}")

if errors[:10]:
    print(f"\n⚠️  Sample errors:")
    for err in errors[:10]:
        print(f"   {err}")