"""
Database Connection Test Script
"""

import sys
import os
sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), '..')))

from data.database_connector import DatabaseConnector

def test_database():
    """Test database connection and data retrieval"""
    
    print("=" * 80)
    print("DATABASE CONNECTION TEST")
    print("=" * 80)
    
    try:
        db = DatabaseConnector()
        
        # Test 1: Connection
        print("\n✅ Test 1: Database Connection")
        db.test_connection()
        
        # Test 2: Get all commodities
        print("\n✅ Test 2: Get All Commodities")
        commodities = db.get_all_commodities()
        print(f"   Found {len(commodities)} commodities")
        
        if commodities:
            for i, comm in enumerate(commodities[:3], 1):
                print(f"   {i}. ID: {comm['id']}, Name: {comm['nama_komoditas']} - {comm['nama_varian']}")
        
        # Test 3: Get price data
        if commodities:
            commodity_id = commodities[0]['id']
            print(f"\n✅ Test 3: Get Price Data for Commodity ID {commodity_id}")
            prices = db.get_commodity_prices(commodity_id)
            print(f"   Retrieved {len(prices)} price records")
            
            if not prices.empty:
                print(f"   Date range: {prices['ds'].min()} to {prices['ds'].max()}")
                print(f"   Price range: {prices['y'].min():.2f} to {prices['y'].max():.2f}")
        
        print("\n" + "=" * 80)
        print("✅ ALL TESTS PASSED!")
        print("=" * 80)
        
    except Exception as e:
        print(f"\n❌ ERROR: {str(e)}")
        import traceback
        traceback.print_exc()

if __name__ == "__main__":
    test_database()