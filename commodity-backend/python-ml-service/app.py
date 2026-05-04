"""
app.py

Flask API untuk sistem forecasting harga komoditas.

v8.3 (Monthly Frequency Fix):
- Validasi periods frequency-aware: MS/M max 24, W max 104, D max 730
- Menambahkan 'ME' sebagai alias frequency yang valid
- Sinkron dengan perubahan AdminController & OperatorController ke frequency='MS'

v8.2 (Hyperparams Sync Fix):
- _DEFAULT_HP disinkronkan dengan prophet_forecasting.py v10.2:
    changepoint_prior_scale: 0.3 → 0.1
    seasonality_prior_scale: 5.0 → 10.0
    weekly_seasonality:      True → False
    changepoint_range:       0.95 → 0.85

v8.1 (Fitted Values Fix):
- predict_forecast_advanced() sekarang menyertakan 'fitted_values' di response.

v8 (Metrics Filter + Full Sync):
- Extended metrics di-strip dari response frontend.
"""

import os
import traceback
import glob
import shutil

import pandas as pd
from datetime import datetime
from dotenv import load_dotenv
from flask import Flask, jsonify, request, send_file
from flask_cors import CORS
from sqlalchemy import text
from werkzeug.utils import secure_filename

from data.database_connector import DatabaseConnector
from models.predictor import CommodityPredictor, _normalize_freq, _convert_periods
from models.prophet_forecasting import CommodityForecastModel
from ihk_routes import ihk_bp, init_ihk_routes



load_dotenv()

# ═══════════════════════════════════════════════════════════════
# APP INIT
# ═══════════════════════════════════════════════════════════════

app = Flask(__name__)

CORS(app, resources={
    r"/api/*": {
        "origins": [
            "http://localhost:3000",
            "http://localhost:8000",
            "http://localhost:5173",
            "http://127.0.0.1:3000",
            "http://127.0.0.1:8000",
            "http://127.0.0.1:5173",
        ],
        "methods":       ["GET", "POST", "PUT", "DELETE", "OPTIONS"],
        "allow_headers": ["Content-Type", "Authorization"],
    }
})

db        = DatabaseConnector()
predictor = CommodityPredictor()
init_ihk_routes(db)

app.register_blueprint(ihk_bp)

PORT  = int(os.getenv('FLASK_PORT', 5000))
DEBUG = os.getenv('FLASK_ENV', 'production') == 'development'

UPLOAD_FOLDER      = 'uploads'
ALLOWED_EXTENSIONS = {'csv'}
os.makedirs(UPLOAD_FOLDER, exist_ok=True)


# v8.2 FIX: Sinkronkan dengan prophet_forecasting.py v10.2
_DEFAULT_HP = {
    'changepoint_prior_scale': 0.1,
    'seasonality_prior_scale': 10.0,
    'seasonality_mode':        'additive',
    'weekly_seasonality':      False,
    'yearly_seasonality':      True,
    'yearly_fourier_order':    20,
    'monthly_seasonality':     True,
    'n_changepoints':          25,
    'changepoint_range':       0.85,
}

# v8.3 FIX: Batas periods per frequency — sinkron dengan PHP controller
_PERIODS_MAX = {
    'D':  730,   # harian max 2 tahun
    'W':  104,   # mingguan max 2 tahun
    'M':   24,   # bulanan max 2 tahun
    'MS':  24,   # bulanan (Month Start) max 2 tahun
    'ME':  24,   # bulanan (Month End) max 2 tahun
}


# ═══════════════════════════════════════════════════════════════
# HELPERS
# ═══════════════════════════════════════════════════════════════

def allowed_file(filename: str) -> bool:
    return '.' in filename and filename.rsplit('.', 1)[1].lower() in ALLOWED_EXTENSIONS


def _parse_bool(value, default: bool = False) -> bool:
    if value is None:
        return default
    if isinstance(value, bool):
        return value
    if isinstance(value, (int, float)):
        return bool(value)
    if isinstance(value, str):
        return value.strip().lower() in ('true', '1', 'yes', 'on')
    return default


def _read_csv_safe(filepath: str):
    for encoding in ('utf-8', 'utf-8-sig', 'latin-1', 'cp1252'):
        try:
            return pd.read_csv(filepath, encoding=encoding)
        except Exception:
            continue
    return None


def _filter_by_date_range(
    df: pd.DataFrame,
    start_date: str = None,
    end_date: str   = None,
) -> pd.DataFrame:
    if df.empty:
        return df
    if start_date:
        try:
            df = df[df['ds'] >= pd.to_datetime(start_date)]
        except Exception as e:
            print(f"   Filter start_date gagal: {e}")
    if end_date:
        try:
            df = df[df['ds'] <= pd.to_datetime(end_date)]
        except Exception as e:
            print(f"   Filter end_date gagal: {e}")
    return df.reset_index(drop=True)


def _detect_user_override_app(hyperparams: dict) -> bool:
    for key, default_val in _DEFAULT_HP.items():
        if key not in hyperparams:
            continue
        user_val = hyperparams[key]
        if isinstance(default_val, float):
            try:
                if abs(float(user_val) - default_val) > 1e-9:
                    print(f"   [App] Override terdeteksi: {key}={user_val} (default={default_val})")
                    return True
            except (TypeError, ValueError):
                pass
        elif isinstance(default_val, bool):
            if bool(user_val) != default_val:
                print(f"   [App] Override terdeteksi: {key}={user_val} (default={default_val})")
                return True
        elif isinstance(default_val, int):
            try:
                if int(user_val) != default_val:
                    print(f"   [App] Override terdeteksi: {key}={user_val} (default={default_val})")
                    return True
            except (TypeError, ValueError):
                pass
        else:
            if user_val != default_val:
                print(f"   [App] Override terdeteksi: {key}={user_val} (default={default_val})")
                return True
    return False


def _build_hyperparams_from_request(data: dict) -> dict:
    return {
        'changepoint_prior_scale': float(data.get(
            'changepoint_prior_scale', _DEFAULT_HP['changepoint_prior_scale'])),
        'seasonality_prior_scale': float(data.get(
            'seasonality_prior_scale', _DEFAULT_HP['seasonality_prior_scale'])),
        'seasonality_mode':        data.get(
            'seasonality_mode', _DEFAULT_HP['seasonality_mode']),
        'weekly_seasonality':      _parse_bool(
            data.get('weekly_seasonality'), _DEFAULT_HP['weekly_seasonality']),
        'yearly_seasonality':      _parse_bool(
            data.get('yearly_seasonality'), _DEFAULT_HP['yearly_seasonality']),
        'yearly_fourier_order':    int(data.get(
            'yearly_fourier_order', _DEFAULT_HP['yearly_fourier_order'])),
        'monthly_seasonality':     _parse_bool(
            data.get('monthly_seasonality'), _DEFAULT_HP['monthly_seasonality']),
        'n_changepoints':          int(data.get(
            'n_changepoints', _DEFAULT_HP['n_changepoints'])),
        'changepoint_range': float(data.get('changepoint_range', _DEFAULT_HP['changepoint_range'])),
    }


def _strip_extended_metrics(model_metrics: dict) -> dict:
    return {k: v for k, v in model_metrics.items() if k != 'extended_metrics'}


# ═══════════════════════════════════════════════════════════════
# ROOT & HEALTH
# ═══════════════════════════════════════════════════════════════

@app.route('/')
def index():
    return jsonify({
        'success': True,
        'message': 'Commodity Price Forecasting API',
        'version': '8.3.0',
        'endpoints': {
            'health':            '/api/health',
            'predict_advanced':  '/api/forecast/predict-advanced',
            'evaluate':          '/api/forecast/evaluate',
            'model_info':        '/api/forecast/model-info/<id>',
            'model_status':      '/api/forecast/model-status',
            'clear_cache':       '/api/forecast/clear-cache/<id>',
            'clear_cache_all':   '/api/forecast/clear-cache-all',
            'commodities':       '/api/commodities',
            'commodity_history': '/api/commodities/<id>/history',
            'search':            '/api/commodities/search?q=keyword',
            'upload_csv':        '/api/upload/csv',
            'download_template': '/api/upload/template',
        }
    })


@app.route('/api/health', methods=['GET'])
@app.route('/api/flask-health', methods=['GET'])
def health_check():
    try:
        db.test_connection()
        return jsonify({
            'success':      True,
            'status':       'online',
            'flask_online': True,
            'message':      'Service is healthy',
            'timestamp':    datetime.now().isoformat(),
        }), 200
    except Exception as e:
        return jsonify({'status': 'offline', 'error': str(e)}), 500


# ═══════════════════════════════════════════════════════════════
# FORECAST — PREDICT ADVANCED
# ═══════════════════════════════════════════════════════════════

@app.route('/api/forecast/predict-advanced', methods=['POST'])
def predict_forecast_advanced():
    """
    Advanced forecast dengan model persistence.

    v8.3: Validasi periods frequency-aware (MS/M max 24, W max 104, D max 730).
    v8.2: _DEFAULT_HP disinkronkan dengan prophet_forecasting.py v10.2.
    v8.1: Response menyertakan 'fitted_values' (in-sample yhat Prophet).
    """
    try:
        data = request.get_json()

        if not data or 'commodity_id' not in data:
            return jsonify({'success': False, 'message': 'commodity_id is required'}), 400

        commodity_id  = data.get('commodity_id')
        periods       = int(data.get('periods', 12))
        frequency     = data.get('frequency', 'MS')
        force_retrain = _parse_bool(data.get('force_retrain'), default=False)
        start_date    = data.get('start_date') or None
        end_date      = data.get('end_date')   or None

        hyperparams      = _build_hyperparams_from_request(data)
        is_user_override = _detect_user_override_app(hyperparams)
        hyperparams['user_override'] = is_user_override

        print(f"   [App] is_user_override={is_user_override}")
        print(f"   [App] hyperparams={hyperparams}")

        cp = hyperparams['changepoint_prior_scale']
        ss = hyperparams['seasonality_prior_scale']
        sm = hyperparams['seasonality_mode']

        if not (0.001 <= cp <= 0.5):
            return jsonify({'success': False,
                            'message': 'changepoint_prior_scale harus 0.001-0.5'}), 400
        if not (0.01 <= ss <= 50):
            return jsonify({'success': False,
                            'message': 'seasonality_prior_scale harus 0.01-50'}), 400
        if sm not in ['additive', 'multiplicative']:
            return jsonify({'success': False,
                            'message': 'seasonality_mode harus additive atau multiplicative'}), 400

        # FIX v8.3: Validasi frequency dan periods secara frequency-aware
        freq_upper = frequency.upper()
        if freq_upper not in _PERIODS_MAX:
            return jsonify({'success': False,
                            'message': 'frequency harus D, W, M, atau MS'}), 400

        max_periods = _PERIODS_MAX.get(freq_upper, 730)
        if not (1 <= periods <= max_periods):
            return jsonify({'success': False,
                            'message': (
                                f'periods untuk frequency {freq_upper} '
                                f'harus antara 1–{max_periods}'
                            )}), 400

        commodity_info = db.get_commodity_info(commodity_id)
        if not commodity_info:
            return jsonify({'success': False,
                            'message': f'Komoditas ID {commodity_id} tidak ditemukan'}), 404

        historical_data = db.get_commodity_prices(commodity_id)
        if historical_data.empty:
            return jsonify({'success': False,
                            'message': 'Tidak ada data historis untuk komoditas ini'}), 404

        if start_date or end_date:
            original_count  = len(historical_data)
            historical_data = _filter_by_date_range(historical_data, start_date, end_date)
            filtered_count  = len(historical_data)
            print(f"   date_filter: {start_date} s/d {end_date} | "
                  f"{original_count} -> {filtered_count} baris")

            if historical_data.empty:
                return jsonify({'success': False,
                                'message': f'Tidak ada data dalam rentang {start_date} s/d {end_date}'}), 404

        if len(historical_data) < 10:
            return jsonify({'success': False,
                            'message': f'Minimum 10 data diperlukan. Ditemukan: {len(historical_data)}'}), 400

        if not force_retrain and not is_user_override:
            current_last_date = historical_data['ds'].max()
            cached_info       = CommodityForecastModel.get_model_info(commodity_id)

            if cached_info.get('exists'):
                try:
                    cached_last_date = pd.to_datetime(cached_info['last_date'])
                    date_diff_days   = abs((current_last_date - cached_last_date).days)
                    if date_diff_days > 7:
                        force_retrain = True
                        print(f"   [App] Auto force_retrain: diff={date_diff_days}d > 7d")
                except Exception as e:
                    print(f"   [App] Gagal cek date diff: {e}")

        print(f"\n{'='*60}")
        print(f"   Forecast Request — {commodity_info.get('full_name')} (ID={commodity_id})")
        print(f"   force_retrain    : {force_retrain}")
        print(f"   is_user_override : {is_user_override}")
        print(f"   periods          : {periods} ({freq_upper})")
        print(f"   hyperparams      : {hyperparams}")
        print(f"   data range       : {historical_data['ds'].min().date()} s/d "
              f"{historical_data['ds'].max().date()}")

        detected_freq   = CommodityForecastModel.detect_frequency(historical_data)
        use_freq        = _normalize_freq(detected_freq)
        freq_to_periode = {'D': 'daily', 'W': 'weekly', 'MS': 'monthly'}
        periode_value   = freq_to_periode.get(use_freq, 'monthly')

        result = predictor.predict(
            commodity_id  = commodity_id,
            historical_df = historical_data,
            periods       = periods,
            frequency     = frequency,
            hyperparams   = hyperparams,
            force_retrain = force_retrain,
        )

        predictions           = result['predictions']
        fitted_values         = result.get('fitted_values', [])
        model_metrics         = result['model_metrics']
        model_source          = result['model_source']
        trend_direction       = result['trend_direction']
        future_interval_width = result['future_interval_width']
        forecast_periods      = result['forecast_periods']
        engine_used           = result['engine']

        model_metrics['trend_direction']       = trend_direction
        model_metrics['future_interval_width'] = future_interval_width

        print(f"   engine       : {engine_used}")
        print(f"   model_source : {model_source}")
        print(f"   prediksi     : {len(predictions)} titik")
        print(f"   fitted_values: {len(fitted_values)} titik (in-sample yhat)")
        print(f"   MAPE         : {model_metrics.get('mape', 0):.4f}%")
        if predictions:
            print(f"   forecast     : {predictions[0]['date']} -> {predictions[-1]['date']}")
        print(f"{'='*60}\n")

        forecast_df = pd.DataFrame([{
            'ds':         pd.to_datetime(p['date']),
            'yhat':       p['predicted_price'],
            'yhat_lower': p['lower_bound'],
            'yhat_upper': p['upper_bound'],
        } for p in predictions])

        saved_to_db = False
        try:
            db.save_forecast_results(commodity_id, forecast_df, periode=periode_value)
            db.save_forecast_run(
                commodity_id = commodity_id,
                metrics      = model_metrics,
                params       = hyperparams,
                engine_used  = engine_used,
                reason       = model_source or '',
            )
            saved_to_db = True
        except Exception as e:
            print(f"   Gagal simpan ke DB: {e}")

        metrics_for_frontend = _strip_extended_metrics(model_metrics)

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
                'engine':                 engine_used,
                'model_source':           model_source,
                'saved_to_db':            saved_to_db,
                'is_user_override':       is_user_override,
                'data_range': {
                    'start': str(historical_data['ds'].min().date()),
                    'end':   str(historical_data['ds'].max().date()),
                },
                'hyperparameters': {
                    **hyperparams,
                    'detected_frequency': use_freq,
                },
                'predictions':   predictions,
                'fitted_values': fitted_values,
                'model_metrics': {
                    'mape':                  float(metrics_for_frontend.get('mape', 0)),
                    'rmse':                  float(metrics_for_frontend.get('rmse', 0)),
                    'mae':                   float(metrics_for_frontend.get('mae',  0)),
                    'coverage':              float(metrics_for_frontend.get('coverage', 0.95)),
                    'in_sample_mape':        float(metrics_for_frontend.get('in_sample_mape', 0)),
                    'in_sample_rmse':        float(metrics_for_frontend.get('in_sample_rmse', 0)),
                    'in_sample_mae':         float(metrics_for_frontend.get('in_sample_mae',  0)),
                    'avg_interval_width':    float(metrics_for_frontend.get('avg_interval_width', 0)),
                    'future_interval_width': round(future_interval_width, 2),
                    'changepoint_count':     int(metrics_for_frontend.get('changepoint_count', 0)),
                    'trend_flexibility':     float(metrics_for_frontend.get('trend_flexibility', 0)),
                    'seasonality_strength':  float(metrics_for_frontend.get('seasonality_strength', 0)),
                    'trend_direction':       trend_direction,
                    'confidence_level':      0.95,
                    'cv_method':             metrics_for_frontend.get('cv_method', ''),
                    'data_frequency':        use_freq,
                    'smape':              float(metrics_for_frontend.get('smape', 0)),
                    'directional_acc':    float(metrics_for_frontend.get('directional_acc', 0)),
                    'winkler_score':      float(metrics_for_frontend.get('winkler_score', 0)),
                    'pinball_lower':      float(metrics_for_frontend.get('pinball_lower', 0)),
                    'pinball_upper':      float(metrics_for_frontend.get('pinball_upper', 0)),
                    'interval_sharpness': float(metrics_for_frontend.get('interval_sharpness', 0)),
                    'in_sample_smape':    float(metrics_for_frontend.get('in_sample_smape', 0)),
                    'in_sample_dir_acc':  float(metrics_for_frontend.get('in_sample_dir_acc', 0)),
                    'rolling_cv_mape':    float(metrics_for_frontend.get('rolling_cv_mape', 0)),
                    'rolling_cv_dir_acc': float(metrics_for_frontend.get('rolling_cv_dir_acc', 0)),
                    'rolling_cv_winkler': float(metrics_for_frontend.get('rolling_cv_winkler', 0)),
                    'rolling_cv_coverage':float(metrics_for_frontend.get('rolling_cv_coverage', 0)),
                },
            }
        }), 200

    except Exception as e:
        print("Error di predict_forecast_advanced:")
        print(traceback.format_exc())
        return jsonify({'success': False, 'message': str(e)}), 500


# ═══════════════════════════════════════════════════════════════
# FORECAST — EVALUATE
# ═══════════════════════════════════════════════════════════════

@app.route('/api/forecast/evaluate', methods=['POST'])
def evaluate_model():
    try:
        data = request.get_json()
        if not data or 'commodity_id' not in data:
            return jsonify({'success': False, 'message': 'commodity_id is required'}), 400

        commodity_id    = data.get('commodity_id')
        start_date      = data.get('start_date') or None
        end_date        = data.get('end_date')   or None
        historical_data = db.get_commodity_prices(commodity_id)

        if historical_data.empty:
            return jsonify({'success': False, 'message': 'Tidak ada data historis'}), 404

        if start_date or end_date:
            historical_data = _filter_by_date_range(historical_data, start_date, end_date)

        if len(historical_data) < 10:
            return jsonify({'success': False,
                            'message': f'Minimum 10 data diperlukan. Ditemukan: {len(historical_data)}'}), 400

        detected_freq = CommodityForecastModel.detect_frequency(historical_data)
        use_freq      = _normalize_freq(detected_freq)

        hyperparams = _build_hyperparams_from_request(data)
        is_user_override = _detect_user_override_app(hyperparams)
        forecaster = CommodityForecastModel(
            **hyperparams,
            user_override=is_user_override,
        )
        forecaster.train(historical_data, freq=use_freq)
        metrics = forecaster.evaluate()

        return jsonify({
            'success': True,
            'data': {
                'commodity_id':       commodity_id,
                'data_frequency':     use_freq,
                'data_points':        len(historical_data),
                'data_range': {
                    'start': str(historical_data['ds'].min().date()),
                    'end':   str(historical_data['ds'].max().date()),
                },
                'hyperparameters':    hyperparams,
                'evaluation_metrics': metrics,
            }
        }), 200

    except Exception as e:
        print(traceback.format_exc())
        return jsonify({'success': False, 'message': str(e)}), 500


# ═══════════════════════════════════════════════════════════════
# FORECAST — MODEL INFO & STATUS
# ═══════════════════════════════════════════════════════════════

@app.route('/api/forecast/model-info/<int:commodity_id>', methods=['GET'])
def get_model_info(commodity_id: int):
    info     = CommodityForecastModel.get_model_info(commodity_id)
    last_run = db.get_last_forecast_run(commodity_id)
    return jsonify({'success': True, 'data': {'model_file': info, 'last_run': last_run}}), 200


@app.route('/api/forecast/model-status', methods=['GET'])
def get_all_model_status():
    try:
        commodities = db.get_all_commodities()
        status_list = []
        for c in commodities:
            if c.get('jumlah_data', 0) == 0:
                continue
            cid  = c['id']
            info = CommodityForecastModel.get_model_info(cid)
            status_list.append({
                'commodity_id':   cid,
                'commodity_name': c['full_name'],
                'jumlah_data':    c['jumlah_data'],
                'model_exists':   info.get('exists', False),
                'is_fresh':       info.get('is_fresh', False),
                'trained_at':     info.get('trained_at'),
                'last_date':      info.get('last_date'),
                'age_hours':      info.get('age_hours'),
                'data_freq':      info.get('data_freq'),
            })
        return jsonify({'success': True, 'data': status_list}), 200
    except Exception as e:
        return jsonify({'success': False, 'message': str(e)}), 500


# ═══════════════════════════════════════════════════════════════
# CACHE MANAGEMENT
# ═══════════════════════════════════════════════════════════════

@app.route('/api/forecast/clear-cache/<int:commodity_id>', methods=['DELETE'])
def clear_model_cache(commodity_id: int):
    try:
        deleted_files = []
        BASE_DIR      = os.path.dirname(os.path.abspath(__file__))
        patterns = [
            os.path.join(BASE_DIR, 'saved_models', f'commodity_{commodity_id}.pkl'),
            os.path.join(BASE_DIR, 'saved_models', f'model_{commodity_id}.pkl'),
            os.path.join(BASE_DIR, 'saved_models', f'model_{commodity_id}_*.pkl'),
            os.path.join(BASE_DIR, 'saved_models', 'rf_fallback', f'rf_{commodity_id}.pkl'),
            os.path.join(BASE_DIR, 'models', 'saved', f'commodity_{commodity_id}.pkl'),
            os.path.join(BASE_DIR, 'cache', f'*{commodity_id}*'),
        ]
        for pattern in patterns:
            for filepath in glob.glob(pattern):
                try:
                    os.remove(filepath)
                    deleted_files.append(filepath)
                except Exception as e:
                    print(f"   Gagal hapus {filepath}: {e}")

        try:
            predictor.invalidate_cache(commodity_id)
        except Exception as e:
            print(f"   In-memory invalidation: {e}")

        count = len(deleted_files)
        return jsonify({
            'success':       True,
            'message':       f'Cache model ID {commodity_id} dihapus ({count} file). '
                             f'Model akan dilatih ulang pada prediksi berikutnya.',
            'deleted':       count > 0,
            'deleted_files': deleted_files,
            'deleted_count': count,
        }), 200

    except Exception as e:
        print(traceback.format_exc())
        return jsonify({'success': False, 'message': str(e)}), 500


@app.route('/api/forecast/clear-cache-all', methods=['DELETE'])
def clear_all_cache():
    try:
        deleted_files = []
        BASE_DIR      = os.path.dirname(os.path.abspath(__file__))
        patterns = [
            os.path.join(BASE_DIR, 'saved_models', '*.pkl'),
            os.path.join(BASE_DIR, 'saved_models', 'rf_fallback', '*.pkl'),
            os.path.join(BASE_DIR, 'models', 'saved', '*.pkl'),
            os.path.join(BASE_DIR, 'models', 'saved', '*.json'),
            os.path.join(BASE_DIR, 'cache', '*.pkl'),
            os.path.join(BASE_DIR, 'cache', '*.json'),
        ]
        for pattern in patterns:
            for filepath in glob.glob(pattern):
                try:
                    if os.path.isfile(filepath):
                        os.remove(filepath)
                        deleted_files.append(filepath)
                    elif os.path.isdir(filepath):
                        shutil.rmtree(filepath)
                        deleted_files.append(filepath)
                except Exception as e:
                    print(f"   Gagal hapus {filepath}: {e}")

        try:
            predictor.clear_all_cache()
        except Exception as e:
            print(f"   In-memory clear: {e}")

        count = len(deleted_files)
        return jsonify({
            'success':       True,
            'message':       f'{count} file cache dihapus. Semua model akan dilatih ulang.',
            'deleted_count': count,
            'deleted_files': deleted_files[:50],
        }), 200

    except Exception as e:
        print(traceback.format_exc())
        return jsonify({'success': False, 'message': str(e)}), 500


# ═══════════════════════════════════════════════════════════════
# COMMODITIES
# ═══════════════════════════════════════════════════════════════

@app.route('/api/commodities', methods=['GET'])
def get_commodities():
    try:
        commodities = db.get_all_commodities()
        return jsonify({'success': True, 'data': commodities}), 200
    except Exception as e:
        return jsonify({'success': False, 'message': str(e)}), 500


@app.route('/api/commodities/<int:commodity_id>/history', methods=['GET'])
def get_commodity_history(commodity_id: int):
    try:
        start_date     = request.args.get('start_date')
        end_date       = request.args.get('end_date')
        commodity_info = db.get_commodity_info(commodity_id)

        if not commodity_info:
            return jsonify({'success': False,
                            'message': f'Komoditas ID {commodity_id} tidak ditemukan'}), 404

        df = db.get_commodity_prices(commodity_id, start_date=start_date, end_date=end_date)
        if df.empty:
            return jsonify({'success': False, 'message': 'Tidak ada data historis'}), 404

        stats   = db.get_price_statistics(commodity_id, start_date=start_date, end_date=end_date)
        history = [
            {'date': row['ds'].strftime('%Y-%m-%d'), 'price': float(row['y'])}
            for _, row in df.iterrows()
        ]

        return jsonify({
            'success': True,
            'data': {
                'commodity_id':   commodity_id,
                'commodity_name': commodity_info.get('full_name', ''),
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
                'id':             r[0],
                'nama_komoditas': r[1],
                'nama_varian':    r[2] or '',
                'full_name':      r[3].strip(),
            }
            for r in results
        ]
        return jsonify({'success': True, 'data': commodities}), 200

    except Exception as e:
        return jsonify({'success': False, 'message': str(e)}), 500


# ═══════════════════════════════════════════════════════════════
# UPLOAD CSV
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
            return jsonify({'success': False, 'message': 'Gagal membaca file CSV.'}), 400

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

                if pd.isna(tanggal) or pd.isna(harga):
                    errors.append({'row': index + 2, 'error': 'Data kosong (tanggal/harga)'})
                    skipped += 1
                    continue

                try:
                    date_str = pd.to_datetime(tanggal).strftime('%Y-%m-%d')
                except Exception:
                    errors.append({'row': index + 2,
                                   'error': f'Format tanggal tidak valid: {tanggal}'})
                    skipped += 1
                    continue

                try:
                    price = float(
                        str(harga).replace(',', '').replace('Rp', '').replace(' ', '').strip()
                    )
                    if price <= 0:
                        raise ValueError('Harga harus > 0')
                except Exception:
                    errors.append({'row': index + 2, 'error': f'Harga tidak valid: {harga}'})
                    skipped += 1
                    continue

                if nama_komoditas not in commodity_cache:
                    cid = db.find_commodity_id(nama_komoditas)
                    if cid is None:
                        errors.append({'row': index + 2,
                                       'error': f'Komoditas tidak ditemukan: {nama_komoditas}'})
                        skipped += 1
                        continue
                    commodity_cache[nama_komoditas] = cid

                cid    = commodity_cache[nama_komoditas]
                action = db.upsert_price(cid, date_str, price)

                if action == 'inserted':
                    inserted += 1
                else:
                    updated += 1

            except Exception as e:
                errors.append({'row': index + 2, 'error': str(e)})
                skipped += 1

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
                'errors':        errors[:20],
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
            mimetype      = 'text/csv',
            as_attachment = True,
            download_name = 'template_price_data.csv',
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


# ═══════════════════════════════════════════════════════════════
# MAIN
# ═══════════════════════════════════════════════════════════════

if __name__ == '__main__':
    from scheduler import start_scheduler

    print("=" * 60)
    print("  COMMODITY PRICE FORECASTING API  v8.3.0")
    print("=" * 60)
    print(f"   PORT      : {PORT}")
    print(f"   DEBUG     : {DEBUG}")
    print(f"   URL       : http://127.0.0.1:{PORT}")
    print(f"   SCHEDULER : aktif (retrain otomatis jam 02:00)")
    print(f"   v8.3      : validasi periods frequency-aware (MS/M max 24)")
    print(f"   v8.2      : hyperparams sync dengan prophet_forecasting v10.2")
    print(f"   v8.1      : fitted_values (in-sample yhat) di response")
    print("=" * 60)

    start_scheduler()
    app.run(host='0.0.0.0', port=PORT, debug=DEBUG)