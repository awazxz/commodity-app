<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Commodity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CommodityController extends Controller
{
    private $pythonApiUrl;
    
    public function __construct()
    {
        $this->pythonApiUrl = env('PYTHON_ML_API_URL', 'http://localhost:5000/api');
    }
    public function index()
    {
        $commodities = Commodity::with('predictions')
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $commodities
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:commodities,code',
            'current_price' => 'required|numeric|min:0',
            'price_date' => 'required|date',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $commodity = Commodity::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Commodity created successfully',
            'data' => $commodity
        ], 201);
    }

    public function show($id)
    {
        $commodity = Commodity::with('predictions')->find($id);

        if (!$commodity) {
            return response()->json([
                'success' => false,
                'message' => 'Commodity not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $commodity
        ]);
    }

    public function update(Request $request, $id)
    {
        $commodity = Commodity::find($id);

        if (!$commodity) {
            return response()->json([
                'success' => false,
                'message' => 'Commodity not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'code' => 'string|unique:commodities,code,' . $id,
            'current_price' => 'numeric|min:0',
            'price_date' => 'date',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $commodity->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Commodity updated successfully',
            'data' => $commodity
        ]);
    }

    public function destroy($id)
    {
        $commodity = Commodity::find($id);

        if (!$commodity) {
            return response()->json([
                'success' => false,
                'message' => 'Commodity not found'
            ], 404);
        }

        $commodity->delete();

        return response()->json([
            'success' => true,
            'message' => 'Commodity deleted successfully'
        ]);
    }
}