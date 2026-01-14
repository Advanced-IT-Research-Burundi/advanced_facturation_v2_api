<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WarehouseResource;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WarehouseController extends Controller
{
    public function index(){
        $stocks = Warehouse::with(['company'])->get();
        
        return response()->json([
            'success' => true,
            'data' =>  WarehouseResource::collection($stocks)
        ], Response::HTTP_OK);
    }

    public function show($id)
    {
        $warehouse = Warehouse::with(['company'])->findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => $warehouse->load(['company'])
        ], Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
          //  'company_id' => 'required|exists:companies,id',
        ]);

        $validated['user_id'] = auth()->id() ?? 1;

        $warehouse = Warehouse::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Entrepôt créé',
            'data' => $warehouse->load('company')
        ], Response::HTTP_CREATED);
    }

    public function products($id)
    {
        $warehouse = Warehouse::with('warehouseProducts')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $warehouse->warehouseProducts
        ], Response::HTTP_OK);
    }
}