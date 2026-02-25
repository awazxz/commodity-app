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

# Load environment variables
load_dotenv()

# Initialize Flask app
app = Flask(__name__)

# Configure CORS
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
        "methods": ["GET", "POST", "PUT", "DELETE", "OPTIONS"],
        "allow_headers": ["Content-Type", "Authorization"]
    }
})

# Initialize database
db = DatabaseConnector()

# Configuration
PORT = int(os.getenv('FLASK_PORT', 5000))
DEBUG = os.getenv('FLASK_ENV', 'production') == 'development'

# Upload configuration
UPLOAD_FOLDER = 'uploads'
ALLOWED_EXTENSIONS = {'csv'}
os.makedirs(UPLOAD_FOLDER, exist_ok=True)

def allowed_file(filename):
    """Check if file extension is allowed"""
    return '.' in filename and filename.rsplit('.', 1)[1].lower() in ALLOWED_EXTENSIONS


# ═══════════════════════════════════════════════════════════════
# ROOT & HEALTH CHECK
# ═══════════════════════════════════════════════════════════════

@app.route('/')
def index():
    """Root endpoint"""
    return jsonify({
        'success': True,
        'message': 'Commodity Price Forecasting API',
        'version': '1.0.0',
        'endpoints': {
            'health': '/api/health',
            'predict': '/api/forecast/predict',
            'predict_advanced': '/api/forecast/predict-advanced',
            'evaluate': '/api/forecast/evaluate',
            'commodities': '/api/commodities',
            'upload_csv': '/api/upload/csv',
            'download_template': '/api/upload/template',
            'search_commodities': '/api/commodities/search?q=keyword'
        }
    })


@app.route('/api/health', methods=['GET'])
def health_check():
    """Health check endpoint"""
    try:
        db.test_connection()
        
        return jsonify({
            'success': True,
            'message': 'Service is healthy',
            'database': 'connected',
            'status': 'running'
        }), 200
    except Exception as e:
        return jsonify({
            'success': False,
            'message': 'Service health check failed',
            'error': str(e)
        }), 500


# ═══════════════════════════════════════════════════════════════
# FORECAST ENDPOINTS
# ═══════════════════════════════════════════════════════════════

@app.route('/api/forecast/predict-advanced', methods=['POST'])
def predict_forecast_advanced():
    """
    Advanced forecast dengan custom hyperparameters dari frontend
    
    Request Body:
    {
        "commodity_id": 1,
        "periods": 30,
        "frequency": "D",
        "changepoint_prior_scale": 0.05,
        "seasonality_prior_scale": 10,
        "seasonality_mode": "multiplicative",
        "weekly_seasonality": false,
        "yearly_seasonality": true
    }
    """
    try:
        data = request.get_json()
        
        # Validate required fields
        if not data or 'commodity_id' not in data:
            return jsonify({'success': False, 'message': 'commodity_id is required'}), 400
        
        commodity_id = data.get('commodity_id')
        periods = data.get('periods', 30)
        frequency = data.get('frequency', 'D')
        
        # Get hyperparameters with defaults
        cp_scale = float(data.get('changepoint_prior_scale', 0.05))
        ss_scale = float(data.get('seasonality_prior_scale', 10))
        s_mode = data.get('seasonality_mode', 'multiplicative')
        weekly = bool(data.get('weekly_seasonality', False))
        yearly = bool(data.get('yearly_seasonality', True))
        
        # Validate hyperparameters
        if not (0.001 <= cp_scale <= 0.5):
            return jsonify({'success': False, 'message': 'changepoint_prior_scale must be between 0.001 and 0.5'}), 400
        
        if not (0.01 <= ss_scale <= 50):
            return jsonify({'success': False, 'message': 'seasonality_prior_scale must be between 0.01 and 50'}), 400
        
        if s_mode not in ['additive', 'multiplicative']:
            return jsonify({'success': False, 'message': 'seasonality_mode must be additive or multiplicative'}), 400
        
        if not (1 <= periods <= 365):
            return jsonify({'success': False, 'message': 'periods must be between 1 and 365'}), 400
        
        if frequency not in ['D', 'W', 'M']:
            return jsonify({'success': False, 'message': 'frequency must be D, W, or M'}), 400
        
        # Get commodity info
        commodity_info = db.get_commodity_info(commodity_id)
        if not commodity_info:
            return jsonify({'success': False, 'message': f'Commodity {commodity_id} not found'}), 404
        
        # Get historical data
        historical_data = db.get_commodity_prices(commodity_id)
        if historical_data.empty:
            return jsonify({'success': False, 'message': 'No historical data found'}), 404
        
        if len(historical_data) < 30:
            return jsonify({
                'success': False,
                'message': f'Minimum 30 data points required. Found: {len(historical_data)}'
            }), 400
        
        print(f"📊 Training commodity {commodity_id} with hyperparameters:")
        print(f"   changepoint_prior_scale: {cp_scale}")
        print(f"   seasonality_prior_scale: {ss_scale}")
        print(f"   seasonality_mode: {s_mode}")
        print(f"   weekly: {weekly}, yearly: {yearly}")
        
        # Initialize model with custom hyperparameters
        forecaster = CommodityForecastModel(
            changepoint_prior_scale=cp_scale,
            seasonality_prior_scale=ss_scale,
            seasonality_mode=s_mode,
            weekly_seasonality=weekly,
            yearly_seasonality=yearly
        )
        
        # Train model
        forecaster.train(historical_data)
        
        # Generate forecast
        forecast_result = forecaster.predict(periods=periods, freq=frequency)
        
        # Get metrics
        metrics = forecaster.get_model_metrics()
        
        # Format predictions
        predictions = []
        for _, row in forecast_result.iterrows():
            predictions.append({
                'date': row['ds'].strftime('%Y-%m-%d'),
                'predicted_price': round(float(row['yhat']), 2),
                'lower_bound': round(float(row['yhat_lower']), 2),
                'upper_bound': round(float(row['yhat_upper']), 2),
                'trend': round(float(row.get('trend', row['yhat'])), 2)
            })
        
        # Calculate trend direction
        trend_values = forecast_result['yhat'].values
        trend_direction = 'increasing' if len(trend_values) > 1 and trend_values[-1] > trend_values[0] else 'decreasing'
        
        # Save to database
        try:
            db.save_forecast_results(commodity_id, forecast_result)
            print(f"✅ Forecast saved to database")
        except Exception as e:
            print(f"⚠️  Could not save forecast: {e}")
        
        return jsonify({
            'success': True,
            'data': {
                'commodity_id': commodity_id,
                'commodity_name': commodity_info['nama_komoditas'],
                'commodity_variant': commodity_info['nama_varian'],
                'unit': commodity_info['satuan'],
                'historical_data_points': len(historical_data),
                'forecast_period': periods,
                'frequency': frequency,
                'hyperparameters': {
                    'changepoint_prior_scale': cp_scale,
                    'seasonality_prior_scale': ss_scale,
                    'seasonality_mode': s_mode,
                    'weekly_seasonality': weekly,
                    'yearly_seasonality': yearly
                },
                'predictions': predictions,
                'model_metrics': {
                    'mape': float(metrics.get('mape', 0)),
                    'rmse': float(metrics.get('rmse', 0)),
                    'mae': float(metrics.get('mae', 0)),
                    'coverage': float(metrics.get('coverage', 0.95)),
                    'trend_direction': trend_direction,
                    'confidence_level': 0.95,
                    'average_prediction_interval': float(
                        forecast_result['yhat_upper'].mean() - forecast_result['yhat_lower'].mean()
                    )
                }
            }
        }), 200
        
    except Exception as e:
        print("Error in predict_forecast_advanced:")
        print(traceback.format_exc())
        return jsonify({'success': False, 'message': str(e)}), 500


@app.route('/api/forecast/evaluate', methods=['POST'])
def evaluate_model():
    """Evaluate forecasting model performance"""
    try:
        data = request.get_json()
        
        if not data or 'commodity_id' not in data:
            return jsonify({'success': False, 'message': 'commodity_id is required'}), 400
        
        commodity_id = data.get('commodity_id')
        historical_data = db.get_commodity_prices(commodity_id)
        
        if historical_data.empty:
            return jsonify({'success': False, 'message': 'No historical data found'}), 404
        
        forecaster = CommodityForecastModel()
        forecaster.train(historical_data)
        metrics = forecaster.evaluate()
        
        return jsonify({
            'success': True,
            'data': {
                'commodity_id': commodity_id,
                'evaluation_metrics': metrics
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
    """Get all commodities"""
    try:
        commodities = db.get_all_commodities()
        return jsonify({'success': True, 'data': commodities}), 200
    except Exception as e:
        return jsonify({'success': False, 'message': str(e)}), 500


@app.route('/api/commodities/<int:commodity_id>/history', methods=['GET'])
def get_commodity_history(commodity_id):
    """
    Ambil data historis harga sebuah komoditas.
    Query params: ?start_date=2025-01-01&end_date=2026-01-01
    """
    try:
        start_date = request.args.get('start_date', None)
        end_date = request.args.get('end_date', None)
        
        # Cek apakah komoditas ada
        commodity_info = db.get_commodity_info(commodity_id)
        if not commodity_info:
            return jsonify({'success': False, 'message': f'Komoditas ID {commodity_id} tidak ditemukan'}), 404
        
        # Ambil data harga
        df = db.get_commodity_prices(commodity_id, start_date=start_date, end_date=end_date)
        
        if df.empty:
            return jsonify({'success': False, 'message': 'Tidak ada data historis'}), 404
        
        # Ambil statistik
        stats = db.get_price_statistics(commodity_id, start_date=start_date, end_date=end_date)
        
        # Format data untuk response
        history = []
        for _, row in df.iterrows():
            history.append({
                'date': row['ds'].strftime('%Y-%m-%d'),
                'price': float(row['y'])
            })
        
        return jsonify({
            'success': True,
            'data': {
                'commodity_id': commodity_id,
                'commodity_name': commodity_info['full_name'],
                'unit': commodity_info['satuan'],
                'history': history,
                'statistics': stats
            }
        }), 200
        
    except Exception as e:
        print(traceback.format_exc())
        return jsonify({'success': False, 'message': str(e)}), 500


@app.route('/api/commodities/search', methods=['GET'])
def search_commodities():
    """Search commodities by name"""
    try:
        search_query = request.args.get('q', '')
        
        if not search_query:
            return jsonify({'success': False, 'message': 'Search query is required'}), 400
        
        query = """
            SELECT 
                id, nama_komoditas, nama_varian,
                CONCAT(nama_komoditas, ' ', IFNULL(nama_varian, '')) as full_name
            FROM master_komoditas
            WHERE nama_komoditas LIKE :search
               OR nama_varian LIKE :search
               OR CONCAT(nama_komoditas, ' ', IFNULL(nama_varian, '')) LIKE :search
            ORDER BY nama_komoditas, nama_varian
            LIMIT 20
        """
        
        with db.engine.connect() as conn:
            results = conn.execute(text(query), {'search': f'%{search_query}%'}).fetchall()
            
            commodities = []
            for row in results:
                commodities.append({
                    'id': row[0],
                    'nama_komoditas': row[1],
                    'nama_varian': row[2] if row[2] else '',
                    'full_name': row[3].strip()
                })
        
        return jsonify({'success': True, 'data': commodities}), 200
        
    except Exception as e:
        return jsonify({'success': False, 'message': str(e)}), 500


# ═══════════════════════════════════════════════════════════════
# UPLOAD CSV ENDPOINTS
# ═══════════════════════════════════════════════════════════════

@app.route('/api/upload/csv', methods=['POST'])
def upload_csv():
    """Upload CSV file containing price data"""
    try:
        if 'file' not in request.files:
            return jsonify({'success': False, 'message': 'No file provided'}), 400
        
        file = request.files['file']
        
        if file.filename == '':
            return jsonify({'success': False, 'message': 'No file selected'}), 400
        
        if not allowed_file(file.filename):
            return jsonify({'success': False, 'message': 'Only CSV files allowed'}), 400
        
        # Save file
        filename = secure_filename(file.filename)
        timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
        filename = f"{timestamp}_{filename}"
        filepath = os.path.join(UPLOAD_FOLDER, filename)
        file.save(filepath)
        
        # Read CSV
        try:
            df = pd.read_csv(filepath, encoding='utf-8')
        except:
            try:
                df = pd.read_csv(filepath, encoding='latin-1')
            except Exception as e:
                os.remove(filepath)
                return jsonify({'success': False, 'message': f'Error reading CSV: {e}'}), 400
        
        # Normalize columns
        df.columns = df.columns.str.strip().str.lower()
        
        # Validate columns
        required_columns = ['id', 'tanggal', 'nama_komoditas', 'harga']
        missing = [col for col in required_columns if col not in df.columns]
        if missing:
            os.remove(filepath)
            return jsonify({
                'success': False,
                'message': f'Missing columns: {", ".join(missing)}',
                'required_columns': required_columns,
                'found_columns': list(df.columns)
            }), 400
        
        # Process data
        inserted = updated = skipped = 0
        errors = []
        commodity_cache = {}
        
        print(f"📊 Processing {len(df)} records...")
        
        for index, row in df.iterrows():
            try:
                tanggal = row['tanggal']
                nama_komoditas = str(row['nama_komoditas']).strip()
                harga = row['harga']
                
                if pd.isna(tanggal) or pd.isna(harga):
                    errors.append({'row': index + 2, 'error': 'Missing data'})
                    skipped += 1
                    continue
                
                # Parse date
                try:
                    date = pd.to_datetime(tanggal).strftime('%Y-%m-%d')
                except:
                    errors.append({'row': index + 2, 'error': f'Invalid date: {tanggal}'})
                    skipped += 1
                    continue
                
                # Parse price
                try:
                    price = float(str(harga).replace(',', '').replace('Rp', '').strip())
                except:
                    errors.append({'row': index + 2, 'error': f'Invalid price: {harga}'})
                    skipped += 1
                    continue
                
                # Find commodity_id
                if nama_komoditas in commodity_cache:
                    commodity_id = commodity_cache[nama_komoditas]
                else:
                    parts = nama_komoditas.split(' ', 1)
                    nama = parts[0]
                    varian = parts[1] if len(parts) > 1 else ''
                    
                    query = "SELECT id FROM master_komoditas WHERE nama_komoditas LIKE :nama"
                    params = {'nama': f'%{nama}%'}
                    
                    if varian:
                        query += " AND (nama_varian LIKE :varian OR nama_varian IS NULL OR nama_varian = '')"
                        params['varian'] = f'%{varian}%'
                    
                    with db.engine.connect() as conn:
                        result = conn.execute(text(query), params).fetchone()
                        
                        if not result:
                            errors.append({'row': index + 2, 'error': f'Commodity not found: {nama_komoditas}'})
                            skipped += 1
                            continue
                        
                        commodity_id = result[0]
                        commodity_cache[nama_komoditas] = commodity_id
                
                # Insert or update
                query = """
                    INSERT INTO price_data (komoditas_id, tanggal, harga, created_at, updated_at)
                    VALUES (:commodity_id, :date, :price, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE harga = VALUES(harga), updated_at = NOW()
                """
                
                with db.engine.begin() as conn:
                    result = conn.execute(text(query), {
                        'commodity_id': commodity_id,
                        'date': date,
                        'price': price
                    })
                    
                    if result.rowcount == 1:
                        inserted += 1
                    elif result.rowcount == 2:
                        updated += 1
                
                if (index + 1) % 10 == 0:
                    print(f"  Processed {index + 1}/{len(df)} records...")
                        
            except Exception as e:
                errors.append({'row': index + 2, 'error': str(e)})
                skipped += 1
        
        os.remove(filepath)
        
        print(f"✅ Upload completed: {inserted} inserted, {updated} updated, {skipped} skipped")
        
        return jsonify({
            'success': True,
            'message': 'CSV processed successfully',
            'data': {
                'total_records': len(df),
                'inserted': inserted,
                'updated': updated,
                'skipped': skipped,
                'errors': errors[:20]
            }
        }), 200
        
    except Exception as e:
        print("Error in upload_csv:")
        print(traceback.format_exc())
        return jsonify({'success': False, 'message': str(e)}), 500


@app.route('/api/upload/template', methods=['GET'])
def download_template():
    """Download CSV template"""
    try:
        template = pd.DataFrame({
            'id': [1, 2, 3, 4, 5],
            'tanggal': ['2026-02-01', '2026-02-02', '2026-02-03', '2026-02-04', '2026-02-05'],
            'nama_komoditas': ['Beras Premium', 'Beras Premium', 'Gula Pasir', 'Minyak Goreng', 'Cabai Merah'],
            'harga': [12000.00, 12100.00, 15000.00, 18000.00, 35000.00]
        })
        
        template_path = os.path.join(UPLOAD_FOLDER, 'template_price_data.csv')
        template.to_csv(template_path, index=False)
        
        return send_file(template_path, mimetype='text/csv', as_attachment=True, download_name='template_price_data.csv')
        
    except Exception as e:
        return jsonify({'success': False, 'message': str(e)}), 500


# ═══════════════════════════════════════════════════════════════
# ERROR HANDLERS
# ═══════════════════════════════════════════════════════════════

@app.errorhandler(404)
def not_found(e):
    return jsonify({'success': False, 'message': 'Endpoint not found'}), 404


@app.errorhandler(500)
def internal_error(e):
    return jsonify({'success': False, 'message': 'Internal server error'}), 500


# ═══════════════════════════════════════════════════════════════
# MAIN
# ═══════════════════════════════════════════════════════════════

if __name__ == '__main__':
    print("=" * 80)
    print("🚀 COMMODITY PRICE FORECASTING API")
    print("=" * 80)
    print(f"📍 Port: {PORT}")
    print(f"🔧 Debug: {DEBUG}")
    print(f"🗄️ Database: {os.getenv('DB_DATABASE', 'commodityapp')}")
    print("=" * 80)
    print("📡 Endpoints:")
    print(f"   GET  / - API Info")
    print(f"   GET  /api/health - Health Check")
    print(f"   POST /api/forecast/predict-advanced - Advanced Forecast")
    print(f"   POST /api/forecast/evaluate - Evaluate Model")
    print(f"   GET  /api/commodities - Get All Commodities")
    print(f"   GET  /api/commodities/search?q=keyword - Search")
    print(f"   POST /api/upload/csv - Upload CSV Data")
    print(f"   GET  /api/upload/template - Download Template")
    print("=" * 80)
    
    app.run(host='0.0.0.0', port=PORT, debug=DEBUG)