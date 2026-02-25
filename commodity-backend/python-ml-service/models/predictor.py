import pandas as pd
import numpy as np
from sklearn.linear_model import LinearRegression
from sklearn.ensemble import RandomForestRegressor
from sklearn.model_selection import train_test_split
from sklearn.metrics import mean_squared_error, r2_score
import joblib
import os
from datetime import datetime, timedelta

class CommodityPredictor:
    def __init__(self):
        self.models = {}
        self.model_dir = 'saved_models'
        os.makedirs(self.model_dir, exist_ok=True)
        
    def prepare_data(self, historical_data):
        """Prepare data for training/prediction"""
        if not historical_data:
            # Generate sample data if no historical data
            return self._generate_sample_data()
        
        df = pd.DataFrame(historical_data)
        df['prediction_date'] = pd.to_datetime(df['prediction_date'])
        df = df.sort_values('prediction_date')
        
        # Feature engineering
        df['day_of_year'] = df['prediction_date'].dt.dayofyear
        df['month'] = df['prediction_date'].dt.month
        df['year'] = df['prediction_date'].dt.year
        df['days_since_start'] = (df['prediction_date'] - df['prediction_date'].min()).dt.days
        
        return df
    
    def _generate_sample_data(self):
        """Generate sample historical data for demo purposes"""
        dates = pd.date_range(start='2023-01-01', end='2025-01-31', freq='D')
        np.random.seed(42)
        
        # Simulate price with trend and seasonality
        trend = np.linspace(100, 150, len(dates))
        seasonality = 10 * np.sin(np.linspace(0, 4*np.pi, len(dates)))
        noise = np.random.normal(0, 5, len(dates))
        prices = trend + seasonality + noise
        
        df = pd.DataFrame({
            'prediction_date': dates,
            'predicted_price': prices
        })
        
        df['day_of_year'] = df['prediction_date'].dt.dayofyear
        df['month'] = df['prediction_date'].dt.month
        df['year'] = df['prediction_date'].dt.year
        df['days_since_start'] = (df['prediction_date'] - df['prediction_date'].min()).dt.days
        
        return df
    
    def train_model(self, commodity_code, training_data):
        """Train prediction model"""
        df = self.prepare_data(training_data)
        
        # Features and target
        feature_cols = ['days_since_start', 'month', 'day_of_year']
        X = df[feature_cols]
        y = df['predicted_price']
        
        # Split data
        X_train, X_test, y_train, y_test = train_test_split(
            X, y, test_size=0.2, random_state=42
        )
        
        # Train model (using Random Forest)
        model = RandomForestRegressor(n_estimators=100, random_state=42)
        model.fit(X_train, y_train)
        
        # Evaluate
        y_pred = model.predict(X_test)
        mse = mean_squared_error(y_test, y_pred)
        r2 = r2_score(y_test, y_pred)
        
        # Save model
        model_path = os.path.join(self.model_dir, f'{commodity_code}_model.pkl')
        joblib.dump(model, model_path)
        self.models[commodity_code] = model
        
        return {
            'mse': float(mse),
            'r2': float(r2),
            'rmse': float(np.sqrt(mse))
        }
    
    def predict(self, commodity_code, prediction_date, historical_data):
        """Make price prediction"""
        # Load or train model
        model_path = os.path.join(self.model_dir, f'{commodity_code}_model.pkl')
        
        if os.path.exists(model_path):
            model = joblib.load(model_path)
        else:
            # Train new model if doesn't exist
            metrics = self.train_model(commodity_code, historical_data)
            model = self.models.get(commodity_code)
        
        # Prepare prediction date features
        pred_date = pd.to_datetime(prediction_date)
        df = self.prepare_data(historical_data)
        
        days_since_start = (pred_date - df['prediction_date'].min()).days
        
        features = pd.DataFrame({
            'days_since_start': [days_since_start],
            'month': [pred_date.month],
            'day_of_year': [pred_date.dayofyear]
        })
        
        # Predict
        predicted_price = model.predict(features)[0]
        
        # Calculate confidence score (simplified)
        confidence_score = min(95, max(70, 85 + np.random.normal(0, 5)))
        
        # Generate chart data (historical + prediction)
        chart_data = self._generate_chart_data(df, pred_date, predicted_price)
        
        return {
            'predicted_price': float(predicted_price),
            'confidence_score': float(confidence_score),
            'model_parameters': {
                'model_type': 'Random Forest',
                'n_estimators': 100,
                'features_used': ['days_since_start', 'month', 'day_of_year']
            },
            'chart_data': chart_data
        }
    
    def _generate_chart_data(self, historical_df, prediction_date, predicted_price):
        """Generate data for charting"""
        # Last 30 days of historical data
        recent_data = historical_df.tail(30)
        
        chart_data = {
            'historical': [
                {
                    'date': row['prediction_date'].strftime('%Y-%m-%d'),
                    'price': float(row['predicted_price'])
                }
                for _, row in recent_data.iterrows()
            ],
            'prediction': {
                'date': prediction_date.strftime('%Y-%m-%d'),
                'price': float(predicted_price)
            }
        }
        
        return chart_data