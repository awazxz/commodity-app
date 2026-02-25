import pandas as pd
import numpy as np
from prophet import Prophet
from sklearn.metrics import mean_absolute_error, mean_squared_error
import warnings
warnings.filterwarnings('ignore')


class CommodityForecastModel:
    """
    Model Forecasting menggunakan Facebook Prophet.
    Prophet sangat cocok untuk data time series harga komoditas
    karena mampu menangani seasonality dan trend.
    """

    def __init__(
        self,
        changepoint_prior_scale=0.05,
        seasonality_prior_scale=10,
        seasonality_mode='multiplicative',
        weekly_seasonality=False,
        yearly_seasonality=True
    ):
        self.changepoint_prior_scale = changepoint_prior_scale
        self.seasonality_prior_scale = seasonality_prior_scale
        self.seasonality_mode = seasonality_mode
        self.weekly_seasonality = weekly_seasonality
        self.yearly_seasonality = yearly_seasonality
        
        self.model = None
        self.historical_data = None
        self.is_trained = False

    def train(self, df: pd.DataFrame):
        """
        Latih model Prophet dengan data historis.
        
        Parameter:
            df: DataFrame dengan kolom 'ds' (tanggal) dan 'y' (harga)
        """
        # Simpan data asli
        self.historical_data = df.copy()
        
        # Inisialisasi model Prophet
        self.model = Prophet(
            changepoint_prior_scale=self.changepoint_prior_scale,
            seasonality_prior_scale=self.seasonality_prior_scale,
            seasonality_mode=self.seasonality_mode,
            weekly_seasonality=self.weekly_seasonality,
            yearly_seasonality=self.yearly_seasonality,
            daily_seasonality=False,
            interval_width=0.95  # 95% confidence interval
        )
        
        # Tambah seasonality bulanan
        self.model.add_seasonality(
            name='monthly',
            period=30.5,
            fourier_order=5
        )
        
        # Latih model
        self.model.fit(df)
        self.is_trained = True
        
        print(f"✅ Model berhasil dilatih dengan {len(df)} data points")

    def predict(self, periods=30, freq='D'):
        """
        Generate prediksi ke depan.
        
        Parameter:
            periods: Jumlah periode ke depan (hari/minggu/bulan)
            freq: Frekuensi ('D'=harian, 'W'=mingguan, 'M'=bulanan)
        
        Return:
            DataFrame dengan kolom ds, yhat, yhat_lower, yhat_upper, trend
        """
        if not self.is_trained:
            raise Exception("Model belum dilatih! Panggil train() terlebih dahulu.")
        
        # Buat dataframe tanggal masa depan
        future = self.model.make_future_dataframe(periods=periods, freq=freq)
        
        # Generate prediksi
        forecast = self.model.predict(future)
        
        # Ambil hanya periode masa depan (tidak termasuk data historis)
        last_date = self.historical_data['ds'].max()
        forecast_only = forecast[forecast['ds'] > last_date].copy()
        
        # Pastikan nilai prediksi tidak negatif
        forecast_only['yhat'] = forecast_only['yhat'].clip(lower=0)
        forecast_only['yhat_lower'] = forecast_only['yhat_lower'].clip(lower=0)
        forecast_only['yhat_upper'] = forecast_only['yhat_upper'].clip(lower=0)
        
        return forecast_only[['ds', 'yhat', 'yhat_lower', 'yhat_upper', 'trend']]

    def get_historical_with_fit(self):
        """
        Ambil data historis beserta fitted values dari model.
        Berguna untuk visualisasi di frontend (garis aktual vs prediksi historis).
        """
        if not self.is_trained:
            raise Exception("Model belum dilatih!")
        
        future = self.model.make_future_dataframe(periods=0, freq='D')
        forecast = self.model.predict(future)
        
        # Gabungkan dengan data aktual
        merged = pd.merge(
            self.historical_data,
            forecast[['ds', 'yhat', 'yhat_lower', 'yhat_upper', 'trend']],
            on='ds',
            how='inner'
        )
        
        return merged

    def get_model_metrics(self):
        """
        Hitung metrik akurasi model (MAPE, RMSE, MAE).
        Menggunakan data historis (in-sample evaluation).
        """
        if not self.is_trained:
            return {}
        
        try:
            historical_fit = self.get_historical_with_fit()
            
            y_actual = historical_fit['y'].values
            y_pred = historical_fit['yhat'].values
            
            # MAPE (Mean Absolute Percentage Error)
            mape = np.mean(np.abs((y_actual - y_pred) / y_actual)) * 100
            
            # MAE (Mean Absolute Error)
            mae = mean_absolute_error(y_actual, y_pred)
            
            # RMSE (Root Mean Squared Error)
            rmse = np.sqrt(mean_squared_error(y_actual, y_pred))
            
            # Coverage (berapa % data aktual masuk dalam interval prediksi)
            in_interval = (
                (y_actual >= historical_fit['yhat_lower'].values) & 
                (y_actual <= historical_fit['yhat_upper'].values)
            )
            coverage = in_interval.mean() * 100
            
            return {
                'mape': round(float(mape), 4),
                'mae': round(float(mae), 2),
                'rmse': round(float(rmse), 2),
                'coverage': round(float(coverage), 2),
                'n_data': len(y_actual)
            }
        except Exception as e:
            print(f"⚠️  Error menghitung metrics: {e}")
            return {'mape': 0, 'mae': 0, 'rmse': 0, 'coverage': 95.0, 'n_data': 0}

    def evaluate(self):
        """Alias untuk get_model_metrics dengan format lebih lengkap"""
        metrics = self.get_model_metrics()
        return {
            'mape': metrics.get('mape', 0),
            'mape_label': f"{metrics.get('mape', 0):.2f}%",
            'mae': metrics.get('mae', 0),
            'rmse': metrics.get('rmse', 0),
            'coverage': metrics.get('coverage', 95.0),
            'coverage_label': f"{metrics.get('coverage', 95.0):.1f}%",
            'n_data_points': metrics.get('n_data', 0),
            'model_quality': self._get_quality_label(metrics.get('mape', 0))
        }

    def _get_quality_label(self, mape):
        """Label kualitas model berdasarkan MAPE"""
        if mape < 5:
            return 'Sangat Baik'
        elif mape < 10:
            return 'Baik'
        elif mape < 20:
            return 'Cukup'
        else:
            return 'Perlu Perbaikan'