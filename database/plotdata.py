import pandas as pd
from sqlalchemy import create_engine

# 1. Load Data
df = pd.read_csv('D:/commodity-app/database/bobot_komoditas.csv')

# 2. Unpivot (Melt) kolom Bobot menjadi baris
# Kita simpan 'nama_komoditas' sebagai identitas
df_long = df.melt(id_vars=['nama_komoditas'], 
                  var_name='periode', 
                  value_name='nilai_bobot')

# 3. Bersihkan data
# Hapus baris yang nilai_bobot-nya kosong (NaN)
df_long = df_long.dropna(subset=['nilai_bobot'])

# Ubah format periode 'Bobot 2301' menjadi tanggal '2023-01-01'
def convert_periode(val):
    if val == 'Bobot0': return '2022-01-01' # Atau sesuaikan tahun dasarnya
    parts = val.split(' ')
    if len(parts) > 1:
        th_bln = parts[1] # misal '2301'
        return f"20{th_bln[:2]}-{th_bln[2:]}-01"
    return None

df_long['tanggal'] = df_long['periode'].apply(convert_periode)
df_long = df_long.dropna(subset=['tanggal'])

# 4. Ambil Mapping komoditas_id dari Database
engine = create_engine('mysql+pymysql://root:@localhost/commodityapp')
master_df = pd.read_sql("SELECT id as komoditas_id, nama_komoditas FROM master_komoditas", engine)

# Merge untuk mendapatkan komoditas_id
final_df = pd.merge(df_long, master_df, on='nama_komoditas')

# Pilih kolom yang sesuai dengan struktur tabel bobot_komoditas
final_df = final_df[['komoditas_id', 'tanggal', 'nilai_bobot']]

# 5. Insert ke Database
final_df.to_sql('bobot_komoditas', con=engine, if_exists='append', index=False)
print("Berhasil memasukkan data bobot!")