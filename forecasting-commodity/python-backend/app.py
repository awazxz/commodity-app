from flask import Flask, request, jsonify
from flask_cors import CORS
import pandas as pd
from prophet import Prophet
import os
from datetime import datetime
import json

app = Flask(__name__)
CORS(app)  # Izinkan akses dari frontend

# Folder untuk upload
UPLOAD_FOLDER = 'uploads'
if not os.path.exists(UPLOAD_FOLDER):
    os.makedirs(UPLOAD_FOLDER)

# Endpoint untuk cek API hidup
@app.route('/', methods=['GET'])
def home():
    return jsonify({
        'status': 'success',
        'message': 'Forecasting API is running',
        'version': '1.0.0'
    })

# Endpoint untuk forecast dengan upload file
@app.route('/forecast', methods=['POST'])
def forecast():
    try:
        # Cek apakah ada file
        if 'file' not in request.files:
            return jsonify({
                'status': 'error', 
                'message': 'Tidak ada file yang diupload'
            }), 400
        
        file = request.files['file']
        
        # Validasi file tidak kosong
        if file.filename == '':
            return jsonify({
                'status': 'error', 
                'message': 'Nama file kosong'
            }), 400
        
        # Ambil parameter jumlah hari prediksi
        periods = int(request.form.get('periods', 30))
        
        # Simpan file dengan timestamp
        filename = f"{datetime.now().strftime('%Y%m%d%H%M%S')}_{file.filename}"
        filepath = os.path.join(UPLOAD_FOLDER, filename)
        file.save(filepath)
        
        # Baca data berdasarkan tipe file
        if filename.endswith('.csv'):
            df = pd.read_csv(filepath)
        elif filename.endswith(('.xls', '.xlsx')):
            df = pd.read_excel(filepath)
        else:
            return jsonify({
                'status': 'error', 
                'message': 'Format file tidak didukung. Gunakan CSV atau Excel'
            }), 400
        
        # Validasi kolom wajib
        if 'ds' not in df.columns or 'y' not in df.columns:
            return jsonify({
                'status': 'error', 
                'message': 'Data harus memiliki kolom "ds" (tanggal) dan "y" (nilai). Kolom yang ada: ' + ', '.join(df.columns.tolist())
            }), 400
        
        # Konversi kolom ds ke datetime
        df['ds'] = pd.to_datetime(df['ds'])
        
        # Hapus data yang kosong
        df = df.dropna(subset=['ds', 'y'])
        
        # Validasi minimal data
        if len(df) < 2:
            return jsonify({
                'status': 'error', 
                'message': 'Data terlalu sedikit. Minimal 2 baris data diperlukan'
            }), 400
        
        print(f"Training model dengan {len(df)} data points...")
        
        # Inisialisasi dan training model Prophet
        model = Prophet(
            daily_seasonality=False,
            weekly_seasonality=True,
            yearly_seasonality=True
        )
        model.fit(df)
        
        print("Model berhasil di-training!")
        
        # Buat dataframe untuk prediksi
        future = model.make_future_dataframe(periods=periods)
        
        print(f"Melakukan prediksi untuk {periods} hari ke depan...")
        
        # Lakukan prediksi
        forecast = model.predict(future)
        
        # Format hasil untuk frontend
        # Historical data (data asli)
        historical = df[['ds', 'y']].copy()
        historical['ds'] = historical['ds'].dt.strftime('%Y-%m-%d')
        
        # Forecast data (hanya prediksi ke depan)
        forecast_future = forecast[['ds', 'yhat', 'yhat_lower', 'yhat_upper']].tail(periods).copy()
        forecast_future['ds'] = forecast_future['ds'].dt.strftime('%Y-%m-%d')
        
        # All forecast (termasuk historical + prediksi)
        all_forecast = forecast[['ds', 'yhat', 'yhat_lower', 'yhat_upper']].copy()
        all_forecast['ds'] = all_forecast['ds'].dt.strftime('%Y-%m-%d')
        
        result = {
            'status': 'success',
            'message': 'Forecasting berhasil',
            'data': {
                'historical_data': historical.to_dict('records'),
                'forecast_data': forecast_future.to_dict('records'),
                'all_forecast': all_forecast.to_dict('records'),
                'periods': periods,
                'total_historical': len(df),
                'total_forecast': periods
            }
        }
        
        print("Forecasting selesai!")
        
        return jsonify(result)
    
    except ValueError as ve:
        return jsonify({
            'status': 'error', 
            'message': f'Error validasi data: {str(ve)}'
        }), 400
    
    except Exception as e:
        print(f"Error: {str(e)}")
        return jsonify({
            'status': 'error', 
            'message': f'Terjadi kesalahan: {str(e)}'
        }), 500

# Endpoint untuk forecast dengan data JSON (tanpa upload file)
@app.route('/forecast-json', methods=['POST'])
def forecast_json():
    try:
        data = request.get_json()
        
        if 'data' not in data:
            return jsonify({
                'status': 'error', 
                'message': 'No data provided'
            }), 400
        
        periods = data.get('periods', 30)
        
        # Konversi ke DataFrame
        df = pd.DataFrame(data['data'])
        
        # Validasi kolom
        if 'ds' not in df.columns or 'y' not in df.columns:
            return jsonify({
                'status': 'error', 
                'message': 'Data harus memiliki kolom "ds" (tanggal) dan "y" (nilai)'
            }), 400
        
        df['ds'] = pd.to_datetime(df['ds'])
        df = df.dropna(subset=['ds', 'y'])
        
        if len(df) < 2:
            return jsonify({
                'status': 'error', 
                'message': 'Data terlalu sedikit'
            }), 400
        
        # Training model
        model = Prophet()
        model.fit(df)
        
        # Prediksi
        future = model.make_future_dataframe(periods=periods)
        forecast = model.predict(future)
        
        # Format hasil
        historical = df[['ds', 'y']].copy()
        historical['ds'] = historical['ds'].dt.strftime('%Y-%m-%d')
        
        forecast_future = forecast[['ds', 'yhat', 'yhat_lower', 'yhat_upper']].tail(periods).copy()
        forecast_future['ds'] = forecast_future['ds'].dt.strftime('%Y-%m-%d')
        
        result = {
            'status': 'success',
            'data': {
                'historical_data': historical.to_dict('records'),
                'forecast_data': forecast_future.to_dict('records'),
                'periods': periods
            }
        }
        
        return jsonify(result)
    
    except Exception as e:
        return jsonify({
            'status': 'error', 
            'message': str(e)
        }), 500

if __name__ == '__main__':
    print("="*50)
    print(" Starting Forecasting API Server...")
    print(" Server: http://localhost:5000")
    print(" Endpoints:")
    print("   GET  /           - Health check")
    print("   POST /forecast   - Upload file & forecast")
    print("   POST /forecast-json - Forecast with JSON data")
    print("="*50)
    app.run(debug=True, port=5000, host='0.0.0.0')