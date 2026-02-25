<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Commodity;
use App\Models\Prediction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class PredictionController extends Controller
{
    public function predict(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'commodity_id' => 'required|exists:commodities,id',
            'prediction_date' => 'required|date|after:today',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $commodity = Commodity::find($request->commodity_id);

        // Kirim request ke Python ML Service
        try {
            $response = Http::timeout(60)->post(env('PYTHON_ML_SERVICE_URL') . '/predict', [
                'commodity_code' => $commodity->code,
                'prediction_date' => $request->prediction_date,
                'historical_data' => $this->getHistoricalData($commodity->id),
            ]);

            if ($response->successful()) {
                $predictionData = $response->json();

                // Simpan hasil prediksi
                $prediction = Prediction::create([
                    'commodity_id' => $request->commodity_id,
                    'user_id' => auth()->id(),
                    'prediction_date' => $request->prediction_date,
                    'predicted_price' => $predictionData['predicted_price'],
                    'confidence_score' => $predictionData['confidence_score'] ?? null,
                    'model_parameters' => $predictionData['model_parameters'] ?? null,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Prediction generated successfully',
                    'data' => [
                        'prediction' => $prediction,
                        'commodity' => $commodity,
                        'chart_data' => $predictionData['chart_data'] ?? null,
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate prediction',
                'error' => $response->body()
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error connecting to ML service',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function history(Request $request)
    {
        $predictions = Prediction::with(['commodity', 'user'])
            ->where('user_id', auth()->id())
            ->when($request->commodity_id, function ($query) use ($request) {
                return $query->where('commodity_id', $request->commodity_id);
            })
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $predictions
        ]);
    }

    private function getHistoricalData($commodityId)
    {
        // Ambil data historis dari database atau sumber lain
        // Ini contoh sederhana
        return Prediction::where('commodity_id', $commodityId)
            ->orderBy('prediction_date')
            ->get(['prediction_date', 'predicted_price'])
            ->toArray();
    }
}