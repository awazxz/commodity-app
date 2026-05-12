"""
ihk_routes.py
==============
Flask Blueprint untuk semua endpoint IHK, inflasi, dan forecast IHK.

Endpoints:
  POST /api/ihk/calculate                          — hitung + simpan IHK semua bulan
  POST /api/ihk/recalculate                        — recalculate N bulan terakhir
  GET  /api/ihk/summary                            — ringkasan IHK terbaru
  GET  /api/ihk/history                            — riwayat IHK bulanan
  GET  /api/ihk/detail                             — detail per komoditas (RH, IHK, andil)
  GET  /api/inflasi/comparison                     — perbandingan bulan x vs x-1
  POST /api/ihk/forecast                           — jalankan forecast IHK agregat
  GET  /api/ihk/forecast/result                    — ambil hasil forecast agregat dari DB
  GET  /api/ihk/forecast/vs-aktual                 — forecast vs aktual (evaluasi)
  GET  /api/ihk/forecast/summary                   — ringkasan forecast untuk dashboard

  [BARU] POST /api/ihk/forecast/komoditas          — forecast IHK per komoditas (Prophet)
  [BARU] GET  /api/ihk/forecast/komoditas/bulan    — hasil forecast komoditas bulan tertentu
  [BARU] GET  /api/ihk/forecast/komoditas/summary  — ringkasan forecast komoditas bulan depan
  [BARU] GET  /api/ihk/forecast/komoditas/detail   — historis + forecast 1 komoditas (chart)

Cara daftarkan di app.py:
  from ihk_routes import ihk_bp, init_ihk_routes
  init_ihk_routes(db)
  app.register_blueprint(ihk_bp)
"""

import traceback
from flask import Blueprint, jsonify, request
from models.ihk_calculator import IHKCalculator
from models.ihk_forecaster import IHKForecaster
from models.ihk_komoditas_forecaster import IHKKomoditasForecaster

# ═══════════════════════════════════════════════════════════════
# BLUEPRINT SETUP
# ═══════════════════════════════════════════════════════════════

ihk_bp = Blueprint('ihk', __name__)

_db                    = None
_calculator            = None
_forecaster            = None
_komoditas_forecaster  = None


def init_ihk_routes(db_connector):
    """
    Inject DatabaseConnector ke blueprint ini.
    Dipanggil sekali saat app startup di app.py.
    """
    global _db, _calculator, _forecaster, _komoditas_forecaster
    _db                   = db_connector
    _calculator           = IHKCalculator(db_connector)
    _forecaster           = IHKForecaster(db_connector)
    _komoditas_forecaster = IHKKomoditasForecaster(db_connector)
    print("[IHK Routes] Diinisialisasi — calculator + forecaster + komoditas forecaster siap.")


def _ensure_init():
    if _calculator is None or _forecaster is None or _komoditas_forecaster is None:
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
            "start_date": "2023-01-01",
            "end_date":   "2025-12-01"
        }
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
    Recalculate N bulan terakhir.

    Body (opsional):
        { "n_bulan": 3 }
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
    Query params:
        start_date, end_date, limit (default 60)
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
    Query params:
        bulan : YYYY-MM (default bulan terakhir)
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
    Query params:
        bulan : YYYY-MM (default bulan terakhir)
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
# FORECAST IHK AGREGAT
# ═══════════════════════════════════════════════════════════════

@ihk_bp.route('/api/ihk/forecast', methods=['POST'])
def run_ihk_forecast():
    """
    Forecast IHK agregat N bulan ke depan.
    Hasil disimpan ke ihk_forecast_bulanan.

    Body (opsional):
        { "periods": 6 }   // default 6, max 24
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
    Query params:
        bulan : YYYY-MM — bulan referensi user (forecast = bulan berikutnya)
    """
    try:
        _ensure_init()
        bulan  = request.args.get('bulan') or None
        result = _forecaster.get_inflasi_forecast_summary(bulan=bulan)
        status = 200 if result.get('success') else 404
        return jsonify(result), status

    except Exception as e:
        print(traceback.format_exc())
        return jsonify({'success': False, 'message': str(e)}), 500


# ═══════════════════════════════════════════════════════════════
# FORECAST IHK PER KOMODITAS  [BARU]
# ═══════════════════════════════════════════════════════════════

@ihk_bp.route('/api/ihk/forecast/komoditas', methods=['POST'])
def run_ihk_forecast_komoditas():
    """
    Jalankan forecast IHK per komoditas menggunakan Prophet.
    Setiap komoditas dilatih model Prophet terpisah.
    Hasil disimpan ke ihk_komoditas_forecast.

    User dapat memilih periode forecast secara bebas (1–12 bulan).

    Body (opsional):
        {
            "periods": 6,                  // default 6, max 12
            "komoditas_ids": [1, 2, 3]     // opsional, default semua komoditas
        }

    Response:
        {
            "success": true,
            "total_komoditas": 21,
            "berhasil": 20,
            "gagal": 1,
            "periods": 6,
            "detail": [
                {
                    "komoditas_id": 1,
                    "nama": "Beras",
                    "success": true,
                    "mape_insample": 1.23,
                    "bulan_depan": {
                        "tanggal": "2026-05-01",
                        "nilai_ihk_forecast": 265.12,
                        "inflasi_mtom_forecast": 1.23,
                        "andil_forecast": 0.042,
                        "kondisi_forecast": "inflasi"
                    }
                },
                ...
            ]
        }
    """
    try:
        _ensure_init()
        data          = request.get_json(silent=True) or {}
        periods       = int(data.get('periods', 6))
        periods       = max(1, min(periods, 12))
        komoditas_ids = data.get('komoditas_ids') or None

        # Validasi komoditas_ids jika dikirim
        if komoditas_ids is not None:
            if not isinstance(komoditas_ids, list) or len(komoditas_ids) == 0:
                return jsonify({
                    'success': False,
                    'message': 'komoditas_ids harus berupa array ID yang tidak kosong',
                }), 400
            komoditas_ids = [int(k) for k in komoditas_ids]

        print(f"\n[POST /api/ihk/forecast/komoditas] periods={periods} "
              f"komoditas_ids={komoditas_ids}")

        result = _komoditas_forecaster.forecast_all(
            periods=periods,
            komoditas_ids=komoditas_ids,
        )
        status = 200 if result.get('success') else 400
        return jsonify(result), status

    except Exception as e:
        print(traceback.format_exc())
        return jsonify({'success': False, 'message': str(e)}), 500


@ihk_bp.route('/api/ihk/forecast/komoditas/bulan', methods=['GET'])
def get_ihk_forecast_komoditas_bulan():
    """
    Ambil forecast IHK semua komoditas untuk bulan tertentu dari DB.
    Diurutkan berdasarkan |andil| terbesar (komoditas paling berpengaruh duluan).

    Query params:
        bulan : YYYY-MM  (wajib, misal '2026-05')

    Response:
        {
            "success": true,
            "data": {
                "bulan": "2026-05",
                "total_komoditas": 21,
                "n_inflasi": 12,
                "n_deflasi": 5,
                "n_stabil": 4,
                "total_andil_inflasi": 1.234,
                "total_andil_deflasi": -0.456,
                "komoditas": [
                    {
                        "komoditas_id": 1,
                        "nama": "Beras",
                        "nilai_ihk_forecast": 265.12,
                        "ihk_lower": 260.10,
                        "ihk_upper": 270.14,
                        "inflasi_mtom_forecast": 1.23,
                        "andil_forecast": 0.042,
                        "kondisi_forecast": "inflasi",
                        "mape_insample": 1.45
                    },
                    ...
                ]
            }
        }
    """
    try:
        _ensure_init()
        bulan = request.args.get('bulan') or None
        if not bulan:
            return jsonify({
                'success': False,
                'message': 'Parameter bulan wajib diisi. Format: YYYY-MM (misal 2026-05)',
            }), 400

        result = _komoditas_forecaster.get_forecast_bulan(bulan=bulan)
        status = 200 if result.get('success') else 404
        return jsonify(result), status

    except Exception as e:
        print(traceback.format_exc())
        return jsonify({'success': False, 'message': str(e)}), 500


@ihk_bp.route('/api/ihk/forecast/komoditas/summary', methods=['GET'])
def get_ihk_forecast_komoditas_summary():
    """
    Ringkasan forecast IHK per komoditas untuk bulan depan.
    Cocok untuk tabel di dashboard — menampilkan prediksi bulan berikutnya
    dari bulan referensi yang dipilih user.

    Query params:
        bulan : YYYY-MM — bulan yang sedang dilihat user
                          (forecast yang dikembalikan = bulan berikutnya)
                          Default: bulan terakhir di andil_inflasi_bulanan

    Response:
        {
            "success": true,
            "data": {
                "bulan": "2026-05",
                "total_komoditas": 21,
                "n_inflasi": 12,
                "n_deflasi": 5,
                "n_stabil": 4,
                "komoditas": [ ... diurutkan |andil| DESC ]
            }
        }
    """
    try:
        _ensure_init()
        bulan  = request.args.get('bulan') or None
        result = _komoditas_forecaster.get_forecast_summary(bulan_referensi=bulan)
        status = 200 if result.get('success') else 404
        return jsonify(result), status

    except Exception as e:
        print(traceback.format_exc())
        return jsonify({'success': False, 'message': str(e)}), 500


@ihk_bp.route('/api/ihk/forecast/komoditas/detail', methods=['GET'])
def get_ihk_forecast_komoditas_detail():
    """
    Data IHK historis + forecast untuk satu komoditas.
    Berguna untuk chart tren IHK per komoditas di frontend.

    Query params:
        komoditas_id : int  (wajib)
        n_hist       : int  jumlah bulan historis (default 12, max 36)

    Response:
        {
            "success": true,
            "data": {
                "komoditas_id": 1,
                "nama": "Beras",
                "historis": [
                    {
                        "tanggal": "2025-03",
                        "nilai_ihk": 262.10,
                        "inflasi_mtom": 0.5,
                        "andil_mtom": 0.018,
                        "tipe": "aktual"
                    },
                    ...
                ],
                "forecast": [
                    {
                        "tanggal": "2026-04",
                        "nilai_ihk_forecast": 265.12,
                        "ihk_lower": 260.10,
                        "ihk_upper": 270.14,
                        "inflasi_mtom_forecast": 1.23,
                        "andil_forecast": 0.042,
                        "kondisi_forecast": "inflasi",
                        "tipe": "forecast"
                    },
                    ...
                ]
            }
        }
    """
    try:
        _ensure_init()
        komoditas_id = request.args.get('komoditas_id')
        if not komoditas_id:
            return jsonify({
                'success': False,
                'message': 'Parameter komoditas_id wajib diisi.',
            }), 400

        try:
            komoditas_id = int(komoditas_id)
        except ValueError:
            return jsonify({
                'success': False,
                'message': 'komoditas_id harus berupa angka.',
            }), 400

        n_hist = int(request.args.get('n_hist', 12))
        n_hist = max(1, min(n_hist, 36))

        result = _komoditas_forecaster.get_ihk_historis_dan_forecast(
            komoditas_id=komoditas_id,
            n_hist=n_hist,
        )
        status = 200 if result.get('success') else 404
        return jsonify(result), status

    except Exception as e:
        print(traceback.format_exc())
        return jsonify({'success': False, 'message': str(e)}), 500


# ═══════════════════════════════════════════════════════════════
# UTIL — konteks data IHK untuk Prophet (debugging)
# ═══════════════════════════════════════════════════════════════

@ihk_bp.route('/api/ihk/forecast-context', methods=['GET'])
def get_ihk_forecast_context():
    try:
        _ensure_init()
        result = _calculator.get_inflasi_forecast_context()
        status = 200 if result.get('success') else 404
        return jsonify(result), status

    except Exception as e:
        print(traceback.format_exc())
        return jsonify({'success': False, 'message': str(e)}), 500