import requests
import json
import time

FLASK_URL = "http://localhost:5000"

# Komoditas ID yang ada di database (sesuai master_komoditas)
# Sesuaikan jika ada ID yang berbeda
COMMODITY_IDS = list(range(9, 21))  # ID 9 sampai 20

# Parameter forecast (bisa disesuaikan)
FORECAST_PARAMS = {
    "periods": 84,                      # 84 hari = 12 minggu ke depan
    "frequency": "W",                   # mingguan
    "changepoint_prior_scale": 0.05,
    "seasonality_prior_scale": 10.0,
    "seasonality_mode": "multiplicative",
    "weekly_seasonality": False,
    "yearly_seasonality": True,
}


def check_health():
    try:
        r = requests.get(f"{FLASK_URL}/api/health", timeout=5)
        return r.json().get("success", False)
    except Exception as e:
        print(f"❌ Flask API tidak bisa diakses: {e}")
        return False


def get_all_commodities():
    try:
        r = requests.get(f"{FLASK_URL}/api/commodities", timeout=10)
        data = r.json()
        if data.get("success"):
            return data["data"]
        return []
    except Exception as e:
        print(f"❌ Gagal ambil daftar komoditas: {e}")
        return []


def run_forecast(commodity_id, commodity_name):
    payload = {"commodity_id": commodity_id, **FORECAST_PARAMS}

    try:
        r = requests.post(
            f"{FLASK_URL}/api/forecast/predict-advanced",
            json=payload,
            timeout=120  # Prophet butuh waktu
        )
        result = r.json()

        if result.get("success"):
            data = result["data"]
            metrics = data.get("model_metrics", {})
            print(f"   ✅ Berhasil | MAPE: {metrics.get('mape', 0):.2f}% | "
                  f"Prediksi: {len(data.get('predictions', []))} titik")
            return True
        else:
            print(f"   ❌ Gagal: {result.get('message', 'Unknown error')}")
            return False

    except requests.Timeout:
        print(f"   ⚠️  Timeout (>120s) untuk komoditas ini")
        return False
    except Exception as e:
        print(f"   ❌ Error: {e}")
        return False


def main():
    print("=" * 60)
    print("  BATCH FORECAST - Semua Komoditas")
    print("=" * 60)

    # Cek Flask API
    print("\n🔍 Cek koneksi Flask API...")
    if not check_health():
        print("❌ Flask API tidak berjalan! Jalankan: python app.py")
        return

    print("✅ Flask API online!\n")

    # Ambil daftar komoditas dari API
    print("📋 Mengambil daftar komoditas...")
    commodities = get_all_commodities()

    # Filter hanya yang punya data
    commodities = [c for c in commodities if c.get("jumlah_data", 0) > 0]

    if not commodities:
        print("❌ Tidak ada komoditas dengan data historis!")
        return

    print(f"✅ Ditemukan {len(commodities)} komoditas dengan data\n")
    print("=" * 60)

    success = 0
    failed  = 0

    for i, commodity in enumerate(commodities, 1):
        cid  = commodity["id"]
        nama = commodity.get("full_name") or commodity.get("nama_komoditas", f"ID {cid}")
        jumlah_data = commodity.get("jumlah_data", 0)

        print(f"\n[{i}/{len(commodities)}] {nama} (ID: {cid}, Data: {jumlah_data} baris)")

        result = run_forecast(cid, nama)

        if result:
            success += 1
        else:
            failed += 1

        # Jeda antar request agar tidak overload
        if i < len(commodities):
            time.sleep(1)

    print("\n" + "=" * 60)
    print(f"  SELESAI: {success} berhasil, {failed} gagal dari {len(commodities)} komoditas")
    print("=" * 60)
    print("\n✅ Hasil prediksi sudah tersimpan di tabel price_forecasts!")
    print("   Refresh halaman laporan untuk melihat hasilnya.\n")


if __name__ == "__main__":
    main()