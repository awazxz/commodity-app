<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ForecastController extends Controller
{
    private $pythonApiUrl;
    
    public function __construct()
    {
        $this->pythonApiUrl = env('PYTHON_ML_API_URL', 'http://localhost:5000/api');
    }
    
    /**
     * Generate forecast prediction
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function predict(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'commodity_id' => 'required|integer|exists:commodities,id',
            'periods' => 'nullable|integer|min:1|max:365',
            'frequency' => 'nullable|string|in:D,W,M'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $commodityId = $request->commodity_id;
        $periods = $request->periods ?? 30;
        $frequency = $request->frequency ?? 'D';
        
        // Check if commodity has enough historical data
        $dataCount = DB::table('commodity_prices')
            ->where('commodity_id', $commodityId)
            ->count();
        
        if ($dataCount < 30) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient historical data. Minimum 30 data points required.',
                'current_data_points' => $dataCount
            ], 400);
        }
        
        try {
            // Call Python ML service
            $response = Http::timeout(120)->post($this->pythonApiUrl . '/forecast/predict', [
                'commodity_id' => $commodityId,
                'periods' => $periods,
                'frequency' => $frequency
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                // Log forecast request
                DB::table('forecast_logs')->insert([
                    'commodity_id' => $commodityId,
                    'periods' => $periods,
                    'frequency' => $frequency,
                    'status' => 'success',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                
                return response()->json($data);
            } else {
                // Log error
                Log::error('Python ML Service Error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to generate forecast',
                    'error' => $response->json()
                ], $response->status());
            }
            
        } catch (\Exception $e) {
            Log::error('Forecast Prediction Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error connecting to forecasting service',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Evaluate forecasting model
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function evaluate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'commodity_id' => 'required|integer|exists:commodities,id'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $response = Http::timeout(120)->post($this->pythonApiUrl . '/forecast/evaluate', [
                'commodity_id' => $request->commodity_id
            ]);
            
            if ($response->successful()) {
                return response()->json($response->json());
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Model evaluation failed',
                    'error' => $response->json()
                ], $response->status());
            }
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error during model evaluation',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get forecast results for a commodity
     * 
     * @param int $commodityId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getResults($commodityId)
    {
        try {
            $results = DB::table('forecast_results')
                ->where('commodity_id', $commodityId)
                ->where('forecast_date', '>=', now()->toDateString())
                ->orderBy('forecast_date', 'asc')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $results
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching forecast results',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get forecast history for a commodity
     * 
     * @param int $commodityId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getHistory($commodityId)
    {
        try {
            $history = DB::table('forecast_logs')
                ->where('commodity_id', $commodityId)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $history
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching forecast history',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}