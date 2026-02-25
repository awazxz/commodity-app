"""
Complete Workflow Test for Commodity Forecasting System
Tests the entire pipeline from database to forecast generation
"""

import sys
import os
sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), '..')))

from data.database_connector import DatabaseConnector
from models.prophet_forecasting import CommodityForecastModel
import json

def print_header(title):
    """Print formatted header"""
    print("\n" + "=" * 80)
    print(f"  {title}")
    print("=" * 80)

def test_full_workflow():
    """Test complete forecasting workflow"""
    
    print_header("🚀 COMMODITY FORECASTING - FULL WORKFLOW TEST")
    
    try:
        # Step 1: Initialize Database
        print("\n📊 Step 1: Initialize Database Connection")
        db = DatabaseConnector()
        
        # Step 2: Test Connection
        print("\n🔌 Step 2: Test Database Connection")
        db.test_connection()
        
        # Step 3: Get All Commodities
        print("\n📦 Step 3: Get All Commodities")
        commodities = db.get_all_commodities()
        print(f"   Found {len(commodities)} commodities:")
        
        for i, comm in enumerate(commodities[:5], 1):
            print(f"   {i}. ID: {comm['id']}, Name: {comm['nama_komoditas']} - {comm['nama_varian']}")
        
        if not commodities:
            print("\n❌ No commodities found! Please insert data first.")
            print("   Run: insert_sample_data.sql in phpMyAdmin")
            return
        
        # Step 4: Select Test Commodity
        test_commodity = commodities[0]
        commodity_id = test_commodity['id']
        commodity_name = f"{test_commodity['nama_komoditas']} - {test_commodity['nama_varian']}"
        
        print_header(f"Testing with: {commodity_name} (ID: {commodity_id})")
        
        # Step 5: Get Commodity Info
        print("\n📋 Step 4: Get Commodity Info")
        info = db.get_commodity_info(commodity_id)
        print(f"   Name: {info['nama_komoditas']}")
        print(f"   Variant: {info['nama_varian']}")
        print(f"   Unit: {info['satuan']}")
        
        # Step 6: Get Price Statistics
        print("\n📈 Step 5: Get Price Statistics")
        stats = db.get_price_statistics(commodity_id)
        if stats:
            print(f"   Data Points: {stats['data_points']}")
            print(f"   Date Range: {stats['earliest_date']} to {stats['latest_date']}")
            print(f"   Price Range: Rp {stats['min_price']:,.2f} - Rp {stats['max_price']:,.2f}")
            print(f"   Average Price: Rp {stats['avg_price']:,.2f}")
        
        # Step 7: Check Data Requirements
        if stats['data_points'] < 30:
            print(f"\n⚠️ Warning: Only {stats['data_points']} data points available.")
            print("   Minimum 30 data points required.")
            print("   Run: CALL generate_sample_price_data(commodity_id, 90, base_price);")
            return
        
        # Step 8: Get Historical Data
        print("\n📊 Step 6: Get Historical Price Data")
        historical_data = db.get_commodity_prices(commodity_id)
        
        if historical_data.empty:
            print("   ❌ No price data found!")
            return
        
        print(f"   Retrieved {len(historical_data)} records")
        print("\n   Sample data (first 5 rows):")
        print(historical_data.head().to_string(index=False))
        
        # Step 9: Train Model
        print("\n🤖 Step 7: Train Prophet Forecasting Model")
        forecaster = CommodityForecastModel()
        forecaster.train(historical_data)
        
        # Step 10: Generate Forecast
        print("\n🔮 Step 8: Generate 30-Day Forecast")
        forecast_periods = 30
        forecast = forecaster.predict(periods=forecast_periods, freq='D')
        
        print(f"   Generated forecast for {len(forecast)} days")
        print("\n   Forecast preview (first 7 days):")
        preview = forecast.head(7)[['ds', 'yhat', 'yhat_lower', 'yhat_upper']].copy()
        preview['ds'] = preview['ds'].dt.strftime('%Y-%m-%d')
        preview['yhat'] = preview['yhat'].apply(lambda x: f"Rp {x:,.2f}")
        preview['yhat_lower'] = preview['yhat_lower'].apply(lambda x: f"Rp {x:,.2f}")
        preview['yhat_upper'] = preview['yhat_upper'].apply(lambda x: f"Rp {x:,.2f}")
        print(preview.to_string(index=False))
        
        # Step 11: Get Model Metrics
        print("\n📊 Step 9: Get Model Metrics")
        metrics = forecaster.get_model_metrics()
        print(f"   Prediction Interval Width: Rp {metrics['average_prediction_interval']:,.2f}")
        print(f"   Trend Direction: {metrics['trend_direction']}")
        print(f"   Confidence Level: {metrics['confidence_level'] * 100}%")
        
        # Step 12: Save to Database
        print("\n💾 Step 10: Save Forecast Results")
        try:
            db.save_forecast_results(commodity_id, forecast)
        except Exception as e:
            print(f"   ⚠️ Could not save: {str(e)}")
        
        # Step 13: Generate API Response
        print("\n📦 Step 11: Generate API Response Format")
        predictions = []
        for _, row in forecast.head(7).iterrows():
            predictions.append({
                'date': row['ds'].strftime('%Y-%m-%d'),
                'predicted_price': round(float(row['yhat']), 2),
                'lower_bound': round(float(row['yhat_lower']), 2),
                'upper_bound': round(float(row['yhat_upper']), 2)
            })
        
        api_response = {
            'success': True,
            'data': {
                'commodity_id': commodity_id,
                'commodity_name': commodity_name,
                'historical_data_points': len(historical_data),
                'forecast_period': forecast_periods,
                'predictions': predictions,
                'model_metrics': metrics
            }
        }
        
        print("   Sample API Response (first 7 days):")
        print(json.dumps(api_response, indent=2))
        
        # Summary
        print_header("✅ TEST COMPLETED SUCCESSFULLY")
        print("\n📝 Summary:")
        print(f"   ✅ Database connection: OK")
        print(f"   ✅ Commodities found: {len(commodities)}")
        print(f"   ✅ Historical data: {len(historical_data)} records")
        print(f"   ✅ Forecast generated: {len(forecast)} days")
        print(f"   ✅ Trend: {metrics['trend_direction']}")
        
        print("\n🎯 Next Steps:")
        print("   1. Run Flask app: python app.py")
        print("   2. Test API: POST http://localhost:5000/api/forecast/predict")
        print("   3. Integrate with Laravel backend")
        print("   4. Connect to frontend")
        
    except Exception as e:
        print(f"\n❌ ERROR: {str(e)}")
        import traceback
        traceback.print_exc()

if __name__ == "__main__":
    test_full_workflow()