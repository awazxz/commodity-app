"""
ihk_routes.py
==============
Flask Blueprint untuk semua endpoint IHK, inflasi, dan forecast IHK.

Endpoints:
  POST /api/ihk/calculate              — hitung + simpan IHK semua bulan
  POST /api/ihk/recalculate            — recalculate N bulan terakhir
  GET  /api/ihk/summary                — ringkasan IHK terbaru
  GET  /api/ihk/history                — riwayat IHK bulanan
  GET  /api/ihk/detail                 — detail per komoditas (RH, IHK, andil)
  GET  /api/inflasi/comparison         — perbandingan bulan x vs x-1
  POST /api/ihk/forecast               — jalankan forecast IHK + inflasi
  GET  /api/ihk/forecast/result        — ambil hasil forecast dari DB
  GET  /api/ihk/forecast/vs-aktual     — forecast vs aktual (evaluasi)
  GET  /api/ihk/forecast/summary       — ringkasan forecast untuk dashboard

Cara daftarkan di app.py:
  from ihk_routes import ihk_bp, init_ihk_routes
  init_ihk_routes(db)           # inject db connector
  app.register_blueprint(ihk_bp)
"""

import traceback
from flask import Blueprint, jsonify, request
from models.ihk_calculator import IHKCalculator
from models.ihk_forecaster import IHKForecaster

# ═══════════════════════════════════════════════════════════════
# BLUEPRINT SETUP
# ═══════════════════════════════════════════════════════════════

ihk_bp = Blueprint('ihk', __name__)

# db connector diinject via init_ihk_routes()
_db           = None
_calculator   = None
_forecaster   = None


def init_ihk_routes(db_connector):
    """
    Inject DatabaseConnector ke blueprint ini.
    Dipanggil sekali saat app startup di app.py.

    Contoh:
        from ihk_routes import ihk_bp, init_ihk_routes
        init_ihk_routes(db)
        app.register_blueprint(ihk_bp)
    """
    global _db, _calculator, _forecaster
    _db         = db_connector
    _calculator = IHKCalculator(db_connector)
    _forecaster = IHKForecaster(db_connector)
    print("[IHK Routes] Diinisialisasi — calculator + forecaster siap.")


def _ensure_init():
    """Guard: pastikan init_ihk_routes sudah dipanggil."""
    if _calculator is None or _forecaster is None:
        raise RuntimeError(
            "IHK routes belum diinisialisasi. "
            "Panggil init_ihk_routes(db) di app.py sebelum request masuk."
        )


# ═══════════════════════════════════════════════════════════════
# KALKULASI IHK
# ═══════════════════════════════════════════════════════════════

@ihk_bp.route('/api/ihk/calculate', methods=['POST'])
def calculate_ihk():
    """
    Hitung IHK + inflasi (MtoM, YtD, YoY) + andil per komoditas
    untuk semua bulan yang tersedia, simpan ke DB.

    Body (opsional):
        {
            "start_date": "2023-01-01",   // hitung mulai bulan ini
            "end_date":   "2025-12-01"    // hitung sampai bulan ini
        }

    Jika body kosong → hitung semua bulan dari rh_komoditas.
    """
    try:
        _ensure_init()
        data       = request.get_json(silent=True) or {}
        start_date = data.get('start_date') or None
        end_date   = data.get('end_date')   or None

        print(f"\n[POST /api/ihk/calculate] start={start_date} end={end_date}")
        result = _calculator.calculate_and_save_all(
            start_date=start_date,
            end_date=end_date,
        )
        return jsonify(result), 200

    except ValueError as e:
        return jsonify({'success': False, 'message': str(e)}), 400
    except Exception as e:
        print(traceback.format_exc())
        return jsonify({'success': False, 'message': str(e)}), 500


@ihk_bp.route('/api/ihk/recalculate', methods=['POST'])
def recalculate_ihk():
    """
    Recalculate N bulan terakhir (aman, tidak overwrite data lama dengan NaN).

    Body (opsional):
        { "n_bulan": 3 }   // default 3 bulan
    """
    try:
        _ensure_init()
        data    = request.get_json(silent=True) or {}
        n_bulan = int(data.get('n_bulan', 3))
        n_bulan = max(1, min(n_bulan, 24))

        print(f"\n[POST /api/ihk/recalculate] n_bulan={n_bulan}")
        result = _calculator.recalculate_latest(n_bulan=n_bulan)
        return jsonify(result), 200

    except ValueError as e:
        return jsonify({'success': False, 'message': str(e)}), 400
    except Exception as e:
        print(traceback.format_exc())
        return jsonify({'success': False, 'message': str(e)}), 500


# ═══════════════════════════════════════════════════════════════
# DATA IHK AKTUAL
# ═══════════════════════════════════════════════════════════════

@ihk_bp.route('/api/ihk/summary', methods=['GET'])
def get_ihk_summary():
    """
    Ringkasan IHK terbaru untuk dashboard.

    Response:
        {
            "periode": "2025-03",
            "nilai_ihk": 104.123456,
            "inflasi_mtom": 0.5,
            "inflasi_ytd": 1.2,
            "inflasi_yoy": 2.1,
            "kondisi": "inflasi",
            "perbandingan_bulan_lalu": { ... }
        }
    """
    try:
        _ensure_init()
        result = _calculator.get_ihk_summary()
        status = 200 if result.get('success') else 404
        return jsonify(result), status

    except Exception as e:
        print(traceback.format_exc())
        return jsonify({'success': False, 'message': str(e)}), 500


@ihk_bp.route('/api/ihk/history', methods=['GET'])
def get_ihk_history():
    """
    Riwayat IHK bulanan untuk grafik/tabel.

    Query params:
        start_date : YYYY-MM-DD (opsional)
        end_date   : YYYY-MM-DD (opsional)
        limit      : int (default 60)
    """
    try:
        _ensure_init()
        start_date = request.args.get('start_date') or None
        end_date   = request.args.get('end_date')   or None
        limit      = int(request.args.get('limit', 60))
        limit      = max(1, min(limit, 120))

        result = _calculator.get_ihk_history(
            start_date=start_date,
            end_date=end_date,
            limit=limit,
        )
        status = 200 if result.get('success') else 404
        return jsonify(result), status

    except Exception as e:
        print(traceback.format_exc())
        return jsonify({'success': False, 'message': str(e)}), 500


@ihk_bp.route('/api/ihk/detail', methods=['GET'])
def get_ihk_detail():
    """
    Detail RH, IHK komoditas, bobot dinamis, dan andil per komoditas
    untuk bulan tertentu.

    Query params:
        bulan : YYYY-MM (opsional, default bulan terakhir)

    Response:
        {
            "periode": "2025-03",
            "nilai_ihk": 104.123456,
            "inflasi_mtom": 0.5,
            "kondisi": "inflasi",
            "komoditas": [
                {
                    "nama": "Beras",
                    "nilai_rh": 105.23,
                    "nilai_ihk_komoditas": 106.12,
                    "bobot_dinamis": 4.52,
                    "inflasi_mtom_komoditas": 0.8,
                    "andil_mtom": 0.036
                },
                ...
            ]
        }
    """
    try:
        _ensure_init()
        bulan  = request.args.get('bulan') or None
        result = _calculator.get_commodity_detail(bulan=bulan)
        status = 200 if result.get('success') else 404
        return jsonify(result), status

    except Exception as e:
        print(traceback.format_exc())
        return jsonify({'success': False, 'message': str(e)}), 500


@ihk_bp.route('/api/inflasi/comparison', methods=['GET'])
def get_inflasi_comparison():
    """
    Perbandingan inflasi bulan X vs bulan X-1.

    Query params:
        bulan : YYYY-MM (opsional, default bulan terakhir)

    Response:
        {
            "bulan_ini": { "periode", "nilai_ihk", "inflasi_mtom", ... },
            "bulan_lalu": { ... },
            "perbandingan": {
                "selisih_ihk": 0.5,
                "naik_turun": "naik",
                "kondisi_berubah": false
            },
            "andil_komoditas": [ ... urut by |andil| terbesar ]
        }
    """
    try:
        _ensure_init()
        bulan  = request.args.get('bulan') or None
        result = _calculator.get_inflasi_comparison(bulan=bulan)
        status = 200 if result.get('success') else 404
        return jsonify(result), status

    except Exception as e:
        print(traceback.format_exc())
        return jsonify({'success': False, 'message': str(e)}), 500


# ═══════════════════════════════════════════════════════════════
# FORECAST IHK & INFLASI
# ═══════════════════════════════════════════════════════════════

@ihk_bp.route('/api/ihk/forecast', methods=['POST'])
def run_ihk_forecast():
    """
    Jalankan forecast IHK dan inflasi N bulan ke depan menggunakan Prophet.
    Hasil disimpan ke ihk_forecast_bulanan.

    Body (opsional):
        { "periods": 6 }   // default 6, max 24

    Response:
        {
            "success": true,
            "periods": 6,
            "last_data_historis": "2025-03",
            "forecast_mulai": "2025-04",
            "forecast_sampai": "2025-09",
            "model_info": { "mape_insample": 1.23, ... },
            "forecast": [
                {
                    "periode": "2025-04",
                    "nilai_ihk_forecast": 104.5,
                    "ihk_lower": 103.1,
                    "ihk_upper": 105.9,
                    "inflasi_mtom_forecast": 0.3,
                    "inflasi_ytd_forecast": 1.1,
                    "inflasi_yoy_forecast": 2.0,
                    "kondisi_forecast": "inflasi"
                },
                ...
            ]
        }
    """
    try:
        _ensure_init()
        data    = request.get_json(silent=True) or {}
        periods = int(data.get('periods', 6))
        periods = max(1, min(periods, 24))

        print(f"\n[POST /api/ihk/forecast] periods={periods}")
        result = _forecaster.forecast(periods=periods)
        status = 200 if result.get('success') else 400
        return jsonify(result), status

    except Exception as e:
        print(traceback.format_exc())
        return jsonify({'success': False, 'message': str(e)}), 500


@ihk_bp.route('/api/ihk/forecast/result', methods=['GET'])
def get_ihk_forecast_result():
    """
    Ambil hasil forecast IHK terbaru dari DB (tanpa run ulang).

    Query params:
        limit : int (default 12, max 24)
    """
    try:
        _ensure_init()
        limit  = int(request.args.get('limit', 12))
        limit  = max(1, min(limit, 24))
        result = _forecaster.get_forecast_result(limit=limit)
        status = 200 if result.get('success') else 404
        return jsonify(result), status

    except Exception as e:
        print(traceback.format_exc())
        return jsonify({'success': False, 'message': str(e)}), 500


@ihk_bp.route('/api/ihk/forecast/vs-aktual', methods=['GET'])
def get_ihk_forecast_vs_aktual():
    """
    Bandingkan hasil forecast IHK dengan data aktual.
    Berguna untuk evaluasi akurasi forecast bulan-bulan sebelumnya.

    Response:
        {
            "total": 6,
            "n_terealisasi": 3,
            "mape_realized": 1.23,
            "hasil": [
                {
                    "periode": "2025-01",
                    "nilai_ihk_forecast": 103.5,
                    "nilai_ihk_aktual": 103.8,
                    "error_pct": 0.29,
                    "in_interval": true,
                    "sudah_terealisasi": true
                },
                ...
            ]
        }
    """
    try:
        _ensure_init()
        result = _forecaster.get_forecast_vs_aktual()
        status = 200 if result.get('success') else 404
        return jsonify(result), status

    except Exception as e:
        print(traceback.format_exc())
        return jsonify({'success': False, 'message': str(e)}), 500


@ihk_bp.route('/api/ihk/forecast/summary', methods=['GET'])
def get_ihk_forecast_summary():
    """
    Ringkasan forecast inflasi untuk widget dashboard.
    Menampilkan prediksi bulan depan + trend 3 bulan.

    Response:
        {
            "bulan_depan": {
                "periode": "2025-04",
                "nilai_ihk_forecast": 104.5,
                "inflasi_mtom_forecast": 0.3,
                "kondisi_forecast": "inflasi"
            },
            "trend_3_bulan": "meningkat",
            "forecast_3_bulan": [ ... ]
        }
    """
    try:
        _ensure_init()
        result = _forecaster.get_inflasi_forecast_summary()
        status = 200 if result.get('success') else 404
        return jsonify(result), status

    except Exception as e:
        print(traceback.format_exc())
        return jsonify({'success': False, 'message': str(e)}), 500


# ═══════════════════════════════════════════════════════════════
# UTIL — konteks data IHK untuk Prophet (dipakai internal)
# ═══════════════════════════════════════════════════════════════

@ihk_bp.route('/api/ihk/forecast-context', methods=['GET'])
def get_ihk_forecast_context():
    """
    Data IHK historis dalam format {'ds', 'y'} untuk Prophet.
    Endpoint ini untuk keperluan internal / debugging.
    """
    try:
        _ensure_init()
        result = _calculator.get_inflasi_forecast_context()
        status = 200 if result.get('success') else 404
        return jsonify(result), status

    except Exception as e:
        print(traceback.format_exc())
        return jsonify({'success': False, 'message': str(e)}), 500