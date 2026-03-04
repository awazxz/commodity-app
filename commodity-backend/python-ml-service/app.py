from flask import Flask, request, jsonify, send_file
from flask_cors import CORS
import os
from dotenv import load_dotenv
import traceback
import pandas as pd
from werkzeug.utils import secure_filename
from datetime import datetime
from sqlalchemy import text

from data.database_connector import DatabaseConnector
from models.prophet_forecasting import CommodityForecastModel

load_dotenv()

app = Flask(__name__)

CORS(app, resources={
    r"/api/*": {
        "origins": [
            "http://localhost:3000",
            "http://localhost:8000",
            "http://localhost:5173",
            "http://127.0.0.1:3000",
            "http://127.0.0.1:8000",
            "http://127.0.0.1:5173"
        ],
        "methods":      ["GET", "POST", "PUT", "DELETE", "OPTIONS"],
        "allow_headers": ["Content-Type", "Authorization"]
    }
})

db = DatabaseConnector()

PORT  = int(os.getenv('FLASK_PORT', 5000))
DEBUG = os.getenv('FLASK_ENV', 'production') == 'development'

UPLOAD_FOLDER      = 'uploads'
ALLOWED_EXTENSIONS = {'csv'}
os.makedirs(UPLOAD_FOLDER, exist_ok=True)


# ═══════════════════════════════════════════════════════════════
# HELPERS
# ═══════════════════════════════════════════════════════════════

def allowed_file(filename: str) -> bool:
    return '.' in filename and filename.rsplit('.', 1)[1].lower() in ALLOWED_EXTENSIONS


def _parse_bool(value, default: bool = False) -> bool:
    """
    Parse boolean dengan benar dari berbagai tipe input.

    BUG PYTHON: bool("false") → True karena string non-kosong selalu truthy.
    Solusi: cek string secara eksplisit.
    """
    if value is None:
        return default
    if isinstance(value, bool):
        return value
    if isinstance(value, (int, float)):
        return bool(value)
    if isinstance(value, str):
        return value.strip().lower() in ('true', '1', 'yes', 'on')
    return default


def _normalize_freq(freq: str) -> str:
    """
    Normalisasi kode frekuensi agar kompatibel dengan Prophet/pandas.

    PHP mengirim 'W' atau 'M'. Prophet butuh 'W' (mingguan) atau 'MS'
    (awal bulan — lebih stabil dari 'M' di pandas versi terbaru).
    """
    mapping = {
        'D':  'D',
        'W':  'W',
        'M':  'MS',
        'MS': 'MS',
        'ME': 'MS',
    }
    return mapping.get(freq.upper().strip(), 'W')  # default: mingguan


def _convert_periods(periods: int, freq: str) -> int:
    """
    Konversi periods dari satuan HARI ke satuan frekuensi data.

    PHP selalu mengirim periods dalam hari (forecastWeeks × 7).
    Prophet butuh periods dalam unit frekuensi data:
      - freq='W'  → bagi 7   (84 hari → 12 minggu)
      - freq='MS' → bagi 30  (84 hari → 3 bulan)
      - freq='D'  → tetap    (84 hari → 84 hari)
    """
    if freq == 'W':
        return max(1, periods // 7)
    elif freq in ('MS', 'M', 'ME'):
        return max(1, periods // 30)
    return max(1, periods)


def _build_forecaster(params: dict) -> CommodityForecastModel:
    """Buat instance CommodityForecastModel dari dict params."""
    return CommodityForecastModel(
        changepoint_prior_scale = float(params.get('changepoint_prior_scale', 0.05)),
        seasonality_prior_scale = float(params.get('seasonality_prior_scale', 10.0)),
        seasonality_mode        = params.get('seasonality_mode', 'multiplicative'),
        weekly_seasonality      = _parse_bool(params.get('weekly_seasonality'), default=False),
        yearly_seasonality      = _parse_bool(params.get('yearly_seasonality'), default=True),
    )


def _read_csv_safe(filepath: str):
    """Baca CSV dengan berbagai encoding, return None jika semua gagal."""
    for encoding in ('utf-8', 'utf-8-sig', 'latin-1', 'cp1252'):
        try:
            return pd.read_csv(filepath, encoding=encoding)
        except Exception:
            continue
    return None


def _find_commodity_id(nama_komoditas: str):
    """Cari komoditas_id dari nama_komoditas (fuzzy match)."""
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
        with db.engine.connect() as conn:
            result = conn.execute(text(query), params).fetchone()
            return result[0] if result else None
    except Exception:
        return None


# ═══════════════════════════════════════════════════════════════
# ROOT & HEALTH CHECK
# ═══════════════════════════════════════════════════════════════

@app.route('/')
def index():
    return jsonify({
        'success': True,
        'message': 'Commodity Price Forecasting API',
        'version': '2.0.0',
        'endpoints': {
            'health':            '/api/health',
            'predict_advanced':  '/api/forecast/predict-advanced',
            'evaluate':          '/api/forecast/evaluate',
            'commodities':       '/api/commodities',
            'commodity_history': '/api/commodities/<id>/history',
            'search':            '/api/commodities/search?q=keyword',
            'upload_csv':        '/api/upload/csv',
            'download_template': '/api/upload/template',
        }
    })


# Tambahkan alias /api/flask-health agar Laravel bisa menemukan Flask
@app.route('/api/health', methods=['GET'])
@app.route('/api/flask-health', methods=['GET']) 
def health_check():
    try:
        # Menghasilkan respon JSON yang diharapkan Laravel
        return jsonify({
            'success': True,
            'status': 'online',  # Kunci utama untuk dashboard
            'flask_online': True,
            'message': 'Service is healthy'
        }), 200
    except Exception as e:
        return jsonify({'status': 'offline', 'error': str(e)}), 500


# ═══════════════════════════════════════════════════════════════
# FORECAST — PREDICT ADVANCED
# ═══════════════════════════════════════════════════════════════

@app.route('/api/forecast/predict-advanced', methods=['POST'])
def predict_forecast_advanced():
    """
    Advanced forecast dengan custom hyperparameters Prophet.

    PERBAIKAN DARI VERSI LAMA:
    1. freq dideteksi otomatis dari data aktual di DB (detect_frequency)
    2. periods dikonversi dari hari → unit frekuensi sebelum ke Prophet
       (84 hari → 12 minggu untuk data mingguan)
    3. train() menerima freq agar CV internal pakai frekuensi yang sama
    4. Minimum data diturunkan 30 → 10 agar komoditas data sedikit tetap jalan
    5. Threshold validasi periods dinaikkan 365 → 730 (mendukung 52 minggu ke depan)
    6. trend_direction pakai threshold 1% (tidak langsung bandingkan nilai float)
    """
    try:
        data = request.get_json()

        if not data or 'commodity_id' not in data:
            return jsonify({'success': False, 'message': 'commodity_id is required'}), 400

        commodity_id = data.get('commodity_id')
        periods      = int(data.get('periods', 84))    # default 84 hari = 12 minggu
        frequency    = data.get('frequency', 'W')      # PHP mengirim 'W' untuk mingguan

        # ── Hyperparameter dari request ───────────────────────
        cp_scale = float(data.get('changepoint_prior_scale', 0.05))
        ss_scale = float(data.get('seasonality_prior_scale', 10.0))
        s_mode   = data.get('seasonality_mode', 'multiplicative')
        weekly   = _parse_bool(data.get('weekly_seasonality'), default=False)
        yearly   = _parse_bool(data.get('yearly_seasonality'), default=True)

        # ── Validasi parameter ────────────────────────────────
        if not (0.001 <= cp_scale <= 0.5):
            return jsonify({
                'success': False,
                'message': 'changepoint_prior_scale harus antara 0.001 dan 0.5'
            }), 400

        if not (0.01 <= ss_scale <= 50):
            return jsonify({
                'success': False,
                'message': 'seasonality_prior_scale harus antara 0.01 dan 50'
            }), 400

        if s_mode not in ['additive', 'multiplicative']:
            return jsonify({
                'success': False,
                'message': 'seasonality_mode harus additive atau multiplicative'
            }), 400

        # ✅ FIX: naikkan batas periods ke 730 agar mendukung 52 minggu (364 hari)
        if not (1 <= periods <= 730):
            return jsonify({
                'success': False,
                'message': 'periods harus antara 1 dan 730'
            }), 400

        if frequency.upper() not in ['D', 'W', 'M', 'MS']:
            return jsonify({
                'success': False,
                'message': 'frequency harus D, W, atau M'
            }), 400

        # ── Cek komoditas ─────────────────────────────────────
        commodity_info = db.get_commodity_info(commodity_id)
        if not commodity_info:
            return jsonify({
                'success': False,
                'message': f'Komoditas ID {commodity_id} tidak ditemukan'
            }), 404

        # ── Ambil data historis ───────────────────────────────
        historical_data = db.get_commodity_prices(commodity_id)
        if historical_data.empty:
            return jsonify({
                'success': False,
                'message': 'Tidak ada data historis untuk komoditas ini'
            }), 404

        # ✅ FIX: turunkan minimum data 30 → 10
        # Data mingguan 10 minggu (~2.5 bulan) sudah cukup untuk Prophet dasar
        if len(historical_data) < 10:
            return jsonify({
                'success': False,
                'message': f'Minimum 10 data diperlukan untuk forecasting. Ditemukan: {len(historical_data)}'
            }), 400

        print(f"\n{'='*60}")
        print(f"📊 Forecasting — komoditas ID={commodity_id}")
        print(f"   nama                       : {commodity_info.get('full_name', commodity_info.get('nama_komoditas', ''))}")
        print(f"   jumlah data historis        : {len(historical_data)}")
        print(f"   changepoint_prior_scale     : {cp_scale}")
        print(f"   seasonality_prior_scale     : {ss_scale}")
        print(f"   seasonality_mode            : {s_mode}")
        print(f"   weekly_seasonality          : {weekly}  (raw: {data.get('weekly_seasonality')!r})")
        print(f"   yearly_seasonality          : {yearly}  (raw: {data.get('yearly_seasonality')!r})")
        print(f"   periods (dari PHP, hari)    : {periods}")
        print(f"   frequency (dari PHP)        : {frequency}")

        # ── ✅ Deteksi frekuensi aktual dari data ─────────────
        # Jangan percaya parameter 'frequency' dari PHP saja —
        # deteksi dari interval antar baris di DB
        detected_freq = CommodityForecastModel.detect_frequency(historical_data)
        use_freq      = _normalize_freq(detected_freq)

        print(f"   freq terdeteksi dari data   : {detected_freq} → normalized: {use_freq}")

        # ── ✅ Konversi periods dari hari ke unit frekuensi ───
        forecast_periods = _convert_periods(periods, use_freq)
        print(f"   forecast_periods (konversi) : {periods} hari → {forecast_periods} {use_freq}")

        # ── Inisialisasi model ────────────────────────────────
        forecaster = CommodityForecastModel(
            changepoint_prior_scale = cp_scale,
            seasonality_prior_scale = ss_scale,
            seasonality_mode        = s_mode,
            weekly_seasonality      = weekly,
            yearly_seasonality      = yearly,
        )

        # ── ✅ Train dengan frekuensi terdeteksi ──────────────
        forecaster.train(historical_data, freq=use_freq)

        # ── ✅ Predict dengan frekuensi & periods yang benar ──
        forecast_result = forecaster.predict(periods=forecast_periods, freq=use_freq)

        # ── Hitung semua metrics ──────────────────────────────
        metrics = forecaster.get_model_metrics()

        print(f"   MAPE (CV walk-forward)      : {metrics.get('mape', 0):.4f}%")
        print(f"   In-sample MAPE              : {metrics.get('in_sample_mape', 0):.4f}%")
        print(f"   RMSE                        : {metrics.get('rmse', 0):.2f}")
        print(f"   Coverage                    : {metrics.get('coverage', 0):.4f}")
        print(f"   Avg interval width          : {metrics.get('avg_interval_width', 0):.2f}")
        print(f"   Changepoint count           : {metrics.get('changepoint_count', 0)}")
        print(f"   Seasonality strength        : {metrics.get('seasonality_strength', 0):.4f}")

        # ── Format predictions untuk response ────────────────
        predictions = []
        for _, row in forecast_result.iterrows():
            predictions.append({
                'date':            row['ds'].strftime('%Y-%m-%d'),
                'predicted_price': round(float(row['yhat']), 2),
                'lower_bound':     round(float(row['yhat_lower']), 2),
                'upper_bound':     round(float(row['yhat_upper']), 2),
                'trend':           round(float(row.get('trend', row['yhat'])), 2),
            })

        # ── ✅ FIX: Arah tren dengan threshold 1% ─────────────
        # Sebelumnya: langsung bandingkan float → tidak stabil
        # Sekarang: minimal 1% perubahan baru dianggap tren
        trend_values    = forecast_result['yhat'].values
        trend_direction = 'stable'
        if len(trend_values) > 1:
            first_val = float(trend_values[0])
            last_val  = float(trend_values[-1])
            threshold = first_val * 0.01
            if last_val > first_val + threshold:
                trend_direction = 'increasing'
            elif last_val < first_val - threshold:
                trend_direction = 'decreasing'

        # ── Simpan ke DB (non-blocking) ───────────────────────
        try:
            db.save_forecast_results(commodity_id, forecast_result)
        except Exception as e:
            print(f"⚠️  Tidak bisa simpan forecast ke DB: {e}")

        # ── Lebar interval rata-rata dari forecast ke depan ───
        future_interval_width = float(
            forecast_result['yhat_upper'].mean() - forecast_result['yhat_lower'].mean()
        )

        print(f"   trend_direction             : {trend_direction}")
        print(f"   jumlah prediksi             : {len(predictions)}")
        print(f"{'='*60}\n")

        return jsonify({
            'success': True,
            'data': {
                'commodity_id':           commodity_id,
                'commodity_name':         commodity_info['nama_komoditas'],
                'commodity_variant':      commodity_info.get('nama_varian', ''),
                'unit':                   commodity_info.get('satuan', ''),
                'historical_data_points': len(historical_data),
                'forecast_period':        forecast_periods,
                'frequency':              use_freq,

                'hyperparameters': {
                    'changepoint_prior_scale': cp_scale,
                    'seasonality_prior_scale': ss_scale,
                    'seasonality_mode':        s_mode,
                    'weekly_seasonality':      weekly,
                    'yearly_seasonality':      yearly,
                    'detected_frequency':      use_freq,
                },

                'predictions': predictions,

                'model_metrics': {
                    # CV walk-forward 80/20 — akurat, berubah per hyperparameter
                    'mape':     float(metrics.get('mape', 0)),
                    'rmse':     float(metrics.get('rmse', 0)),
                    'mae':      float(metrics.get('mae', 0)),
                    'coverage': float(metrics.get('coverage', 0.95)),

                    # In-sample — selalu berubah per hyperparameter
                    'in_sample_mape': float(metrics.get('in_sample_mape', 0)),
                    'in_sample_rmse': float(metrics.get('in_sample_rmse', 0)),
                    'in_sample_mae':  float(metrics.get('in_sample_mae', 0)),

                    # Hyperparameter-sensitive metrics
                    'avg_interval_width':    float(metrics.get('avg_interval_width', 0)),
                    'future_interval_width': round(future_interval_width, 2),
                    'changepoint_count':     int(metrics.get('changepoint_count', 0)),
                    'trend_flexibility':     float(metrics.get('trend_flexibility', 0)),
                    'seasonality_strength':  float(metrics.get('seasonality_strength', 0)),

                    # Meta
                    'trend_direction':  trend_direction,
                    'confidence_level': 0.95,
                    'cv_method':        metrics.get('cv_method', f'walk_forward_80_20_{use_freq}'),
                    'data_frequency':   use_freq,
                },
            }
        }), 200

    except Exception as e:
        print("❌ Error di predict_forecast_advanced:")
        print(traceback.format_exc())
        return jsonify({'success': False, 'message': str(e)}), 500


# ═══════════════════════════════════════════════════════════════
# FORECAST — EVALUATE
# ═══════════════════════════════════════════════════════════════

@app.route('/api/forecast/evaluate', methods=['POST'])
def evaluate_model():
    """
    Evaluasi model dengan hyperparameter tertentu (tanpa prediksi ke depan).
    Berguna untuk membandingkan performa berbagai kombinasi hyperparameter.
    """
    try:
        data = request.get_json()

        if not data or 'commodity_id' not in data:
            return jsonify({'success': False, 'message': 'commodity_id is required'}), 400

        commodity_id    = data.get('commodity_id')
        historical_data = db.get_commodity_prices(commodity_id)

        if historical_data.empty:
            return jsonify({'success': False, 'message': 'Tidak ada data historis'}), 404

        # ✅ FIX: minimum 10, bukan 30
        if len(historical_data) < 10:
            return jsonify({
                'success': False,
                'message': f'Minimum 10 data diperlukan. Ditemukan: {len(historical_data)}'
            }), 400

        # ✅ Deteksi frekuensi dan teruskan ke train
        detected_freq = CommodityForecastModel.detect_frequency(historical_data)
        use_freq      = _normalize_freq(detected_freq)

        forecaster = _build_forecaster(data)
        forecaster.train(historical_data, freq=use_freq)
        metrics = forecaster.evaluate()

        return jsonify({
            'success': True,
            'data': {
                'commodity_id':   commodity_id,
                'data_frequency': use_freq,
                'hyperparameters': {
                    'changepoint_prior_scale': forecaster.changepoint_prior_scale,
                    'seasonality_prior_scale': forecaster.seasonality_prior_scale,
                    'seasonality_mode':        forecaster.seasonality_mode,
                    'weekly_seasonality':      forecaster.weekly_seasonality,
                    'yearly_seasonality':      forecaster.yearly_seasonality,
                },
                'evaluation_metrics': metrics,
            }
        }), 200

    except Exception as e:
        print(traceback.format_exc())
        return jsonify({'success': False, 'message': str(e)}), 500


# ═══════════════════════════════════════════════════════════════
# COMMODITIES ENDPOINTS
# ═══════════════════════════════════════════════════════════════

@app.route('/api/commodities', methods=['GET'])
def get_commodities():
    try:
        commodities = db.get_all_commodities()
        return jsonify({'success': True, 'data': commodities}), 200
    except Exception as e:
        return jsonify({'success': False, 'message': str(e)}), 500


@app.route('/api/commodities/<int:commodity_id>/history', methods=['GET'])
def get_commodity_history(commodity_id):
    try:
        start_date = request.args.get('start_date', None)
        end_date   = request.args.get('end_date',   None)

        commodity_info = db.get_commodity_info(commodity_id)
        if not commodity_info:
            return jsonify({
                'success': False,
                'message': f'Komoditas ID {commodity_id} tidak ditemukan'
            }), 404

        df = db.get_commodity_prices(commodity_id, start_date=start_date, end_date=end_date)
        if df.empty:
            return jsonify({'success': False, 'message': 'Tidak ada data historis'}), 404

        stats = db.get_price_statistics(commodity_id, start_date=start_date, end_date=end_date)

        history = [
            {'date': row['ds'].strftime('%Y-%m-%d'), 'price': float(row['y'])}
            for _, row in df.iterrows()
        ]

        return jsonify({
            'success': True,
            'data': {
                'commodity_id':   commodity_id,
                'commodity_name': commodity_info.get('full_name', commodity_info.get('nama_komoditas', '')),
                'unit':           commodity_info.get('satuan', ''),
                'history':        history,
                'statistics':     stats,
            }
        }), 200

    except Exception as e:
        print(traceback.format_exc())
        return jsonify({'success': False, 'message': str(e)}), 500


@app.route('/api/commodities/search', methods=['GET'])
def search_commodities():
    try:
        search_query = request.args.get('q', '').strip()
        if not search_query:
            return jsonify({'success': False, 'message': 'Parameter q (query) wajib diisi'}), 400

        query = """
            SELECT id, nama_komoditas, nama_varian,
                   CONCAT(nama_komoditas, ' ', IFNULL(nama_varian, '')) AS full_name
            FROM master_komoditas
            WHERE nama_komoditas LIKE :search
               OR nama_varian    LIKE :search
               OR CONCAT(nama_komoditas, ' ', IFNULL(nama_varian, '')) LIKE :search
            ORDER BY nama_komoditas, nama_varian
            LIMIT 20
        """
        with db.engine.connect() as conn:
            results = conn.execute(text(query), {'search': f'%{search_query}%'}).fetchall()

        commodities = [
            {
                'id':             row[0],
                'nama_komoditas': row[1],
                'nama_varian':    row[2] or '',
                'full_name':      row[3].strip(),
            }
            for row in results
        ]
        return jsonify({'success': True, 'data': commodities}), 200

    except Exception as e:
        return jsonify({'success': False, 'message': str(e)}), 500


# ═══════════════════════════════════════════════════════════════
# UPLOAD CSV ENDPOINTS
# ═══════════════════════════════════════════════════════════════

@app.route('/api/upload/csv', methods=['POST'])
def upload_csv():
    try:
        if 'file' not in request.files:
            return jsonify({'success': False, 'message': 'Tidak ada file yang dikirim'}), 400

        file = request.files['file']
        if file.filename == '':
            return jsonify({'success': False, 'message': 'Nama file kosong'}), 400
        if not allowed_file(file.filename):
            return jsonify({'success': False, 'message': 'Hanya file CSV yang diizinkan'}), 400

        filename = f"{datetime.now().strftime('%Y%m%d_%H%M%S')}_{secure_filename(file.filename)}"
        filepath = os.path.join(UPLOAD_FOLDER, filename)
        file.save(filepath)

        df = _read_csv_safe(filepath)
        if df is None:
            os.remove(filepath)
            return jsonify({
                'success': False,
                'message': 'Gagal membaca file CSV. Pastikan encoding UTF-8.'
            }), 400

        df.columns = df.columns.str.strip().str.lower()

        required_columns = ['id', 'tanggal', 'nama_komoditas', 'harga']
        missing = [col for col in required_columns if col not in df.columns]
        if missing:
            os.remove(filepath)
            return jsonify({
                'success':          False,
                'message':          f'Kolom tidak ditemukan: {", ".join(missing)}',
                'required_columns': required_columns,
                'found_columns':    list(df.columns),
            }), 400

        inserted, updated, skipped = 0, 0, 0
        errors          = []
        commodity_cache = {}

        for index, row in df.iterrows():
            try:
                tanggal        = row['tanggal']
                nama_komoditas = str(row['nama_komoditas']).strip()
                harga          = row['harga']

                # Skip baris kosong
                if pd.isna(tanggal) or pd.isna(harga):
                    errors.append({'row': index + 2, 'error': 'Data kosong (tanggal/harga)'})
                    skipped += 1
                    continue

                # Validasi tanggal
                try:
                    date_str = pd.to_datetime(tanggal).strftime('%Y-%m-%d')
                except Exception:
                    errors.append({'row': index + 2, 'error': f'Format tanggal tidak valid: {tanggal}'})
                    skipped += 1
                    continue

                # Validasi harga
                # ✅ FIX: jangan replace '.' karena bisa jadi desimal (15000.50)
                try:
                    price = float(
                        str(harga)
                        .replace(',', '')
                        .replace('Rp', '')
                        .replace(' ', '')
                        .strip()
                    )
                    if price <= 0:
                        raise ValueError('Harga harus > 0')
                except Exception:
                    errors.append({'row': index + 2, 'error': f'Harga tidak valid: {harga}'})
                    skipped += 1
                    continue

                # Cari komoditas_id (pakai cache)
                if nama_komoditas not in commodity_cache:
                    cid = _find_commodity_id(nama_komoditas)
                    if cid is None:
                        errors.append({'row': index + 2, 'error': f'Komoditas tidak ditemukan: {nama_komoditas}'})
                        skipped += 1
                        continue
                    commodity_cache[nama_komoditas] = cid

                cid = commodity_cache[nama_komoditas]

                # Upsert ke price_data
                with db.engine.begin() as conn:
                    result = conn.execute(text("""
                        INSERT INTO price_data (komoditas_id, tanggal, harga, created_at, updated_at)
                        VALUES (:cid, :date, :price, NOW(), NOW())
                        ON DUPLICATE KEY UPDATE harga = VALUES(harga), updated_at = NOW()
                    """), {'cid': cid, 'date': date_str, 'price': price})

                    if result.rowcount == 1:
                        inserted += 1
                    else:
                        updated += 1

            except Exception as e:
                errors.append({'row': index + 2, 'error': str(e)})
                skipped += 1

        # Hapus file sementara
        try:
            os.remove(filepath)
        except Exception:
            pass

        return jsonify({
            'success': True,
            'message': 'CSV berhasil diproses',
            'data': {
                'total_records': len(df),
                'inserted':      inserted,
                'updated':       updated,
                'skipped':       skipped,
                'errors':        errors[:20],  # batasi 20 error pertama
            }
        }), 200

    except Exception as e:
        print(traceback.format_exc())
        return jsonify({'success': False, 'message': str(e)}), 500


@app.route('/api/upload/template', methods=['GET'])
def download_template():
    try:
        template = pd.DataFrame({
            'id':             [1, 2, 3, 4],
            'tanggal':        ['2026-01-06', '2026-01-13', '2026-01-20', '2026-01-27'],
            'nama_komoditas': ['Beras Premium', 'Gula Pasir', 'Cabai Merah', 'Cabai Merah'],
            'harga':          [12000.00, 15000.00, 35000.00, 33500.00],
        })
        template_path = os.path.join(UPLOAD_FOLDER, 'template_price_data.csv')
        template.to_csv(template_path, index=False)
        return send_file(
            template_path,
            mimetype='text/csv',
            as_attachment=True,
            download_name='template_price_data.csv'
        )
    except Exception as e:
        return jsonify({'success': False, 'message': str(e)}), 500


# ═══════════════════════════════════════════════════════════════
# ERROR HANDLERS
# ═══════════════════════════════════════════════════════════════

@app.errorhandler(404)
def not_found(e):
    return jsonify({'success': False, 'message': 'Endpoint tidak ditemukan'}), 404

@app.errorhandler(405)
def method_not_allowed(e):
    return jsonify({'success': False, 'message': 'Method tidak diizinkan'}), 405

@app.errorhandler(500)
def internal_error(e):
    return jsonify({'success': False, 'message': 'Internal server error'}), 500




if __name__ == '__main__':
    # Pastikan variabel memiliki nilai fallback jika .env gagal dimuat
    port_run = int(os.getenv('FLASK_PORT', 5000))
    debug_run = os.getenv('FLASK_ENV', 'production') == 'development'
    
    print("=" * 60)
    print("  COMMODITY PRICE FORECASTING API  v2.0")
    print("=" * 60)
    print(f"   PORT  : {port_run}")
    print(f"   DEBUG : {debug_run}")
    print(f"   URL   : http://127.0.0.1:{port_run}")
    print("=" * 60)
    
    # host='0.0.0.0' penting agar Laravel bisa mengakses Flask via IP/Localhost
    app.run(host='0.0.0.0', port=port_run, debug=debug_run)