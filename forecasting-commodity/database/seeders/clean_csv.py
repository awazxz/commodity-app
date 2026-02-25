import csv

input_file = 'newcommoditydataset.csv'
output_file = 'cleaned_dataset.csv'

print("Sedang memproses 7800+ data... Harap tunggu.")

cleaned_data = []
# Header standar yang kita inginkan
header = ["No", "nama_varian", "kuantitas", "satuan", "waktu", "harga", "tahun", "bulan", "minggu"]

with open(input_file, 'r', encoding='utf-8') as f:
    next(f) # Lewati header asli
    for line in f:
        line = line.strip()
        if not line: continue
        
        # Bersihkan pembungkus kutip di awal dan akhir baris jika ada
        if line.startswith('"') and line.endswith('"'):
            line = line[1:-1]
        
        # Standarisasi kutip ganda
        line = line.replace('""', '"')
        
        # Gunakan csv reader untuk memecah baris
        reader = csv.reader([line])
        try:
            row = next(reader)
            if len(row) >= 9:
                # Teknik "Reverse Mapping" (ambil dari belakang agar aman dari koma di nama barang)
                minggu = row[-1]
                bulan = row[-2]
                tahun = row[-3]
                harga = row[-4]
                waktu = row[-5]
                satuan = row[-6]
                kuantitas = row[-7]
                no = row[0]
                # Gabungkan sisanya menjadi nama_varian
                nama_varian = ",".join(row[1:-7]).replace('"', '').strip()
                
                cleaned_data.append([no, nama_varian, kuantitas, satuan, waktu, harga, tahun, bulan, minggu])
        except:
            continue

# Simpan ke file baru yang "Sehat"
with open(output_file, 'w', newline='', encoding='utf-8') as f:
    writer = csv.writer(f, quoting=csv.QUOTE_ALL)
    writer.writerow(header)
    writer.writerows(cleaned_data)

print(f"SELESAI! Berhasil memperbaiki {len(cleaned_data)} baris.")
print(f"File baru: {output_file}")