<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StockMovementController extends Controller
{
    /**
     * Display a listing of stock movements.
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => StockMovement::with(['company', 'product', 'warehouse'])->paginate(15)
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created stock movement.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'system_or_device_id' => 'required|string|max:255',
            'item_code' => 'required|string|max:255',
            'item_designation' => 'required|string|max:255',
            'item_quantity' => 'required|numeric|min:0.01',
            'item_measurement_unit' => 'required|string|max:255',
            'item_purchase_or_sale_price' => 'required|numeric|min:0',
            'item_purchase_or_sale_currency' => 'required|string|max:3',
            'item_movement_type' => 'required|string|max:255',
            'item_movement_invoice_ref' => 'nullable|string|max:255',
            'item_movement_description' => 'nullable|string',
            'item_movement_date' => 'required|date',
            'obr_submission_status' => 'required|in:PENDING,SENT,ACCEPTED,REJECTED',
            'obr_response_message' => 'nullable|string',
            'obr_sent_at' => 'nullable|date',
            'company_id' => 'required|exists:companies,id',
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
        ]);

        $validated['user_id'] = auth()->id();
        $validated['created_by'] = auth()->id();

        $stockMovement = StockMovement::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Stock movement created successfully',
            'data' => $stockMovement->load(['company', 'product', 'warehouse'])
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified stock movement.
     */
    public function show(StockMovement $stockMovement)
    {
        return response()->json([
            'success' => true,
            'data' => $stockMovement->load(['company', 'product', 'warehouse'])
        ], Response::HTTP_OK);
    }

    /**
     * Update the specified stock movement.
     */
    public function update(Request $request, StockMovement $stockMovement)
    {
        $validated = $request->validate([
            'item_quantity' => 'sometimes|required|numeric|min:0.01',
            'item_purchase_or_sale_price' => 'sometimes|required|numeric|min:0',
            'item_movement_invoice_ref' => 'nullable|string|max:255',
            'item_movement_description' => 'nullable|string',
            'item_movement_date' => 'sometimes|required|date',
            'obr_submission_status' => 'sometimes|required|in:PENDING,SENT,ACCEPTED,REJECTED',
            'obr_response_message' => 'nullable|string',
            'obr_sent_at' => 'nullable|date',
        ]);

        $stockMovement->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Stock movement updated successfully',
            'data' => $stockMovement->load(['company', 'product', 'warehouse'])
        ], Response::HTTP_OK);
    }

    /**
     * Remove the specified stock movement (soft delete).
     */
    public function destroy(StockMovement $stockMovement)
    {
        $stockMovement->delete();

        return response()->json([
            'success' => true,
            'message' => 'Stock movement deleted successfully'
        ], Response::HTTP_OK);
    }

    /**
     * Restore a soft-deleted stock movement.
     */
    public function restore($id)
    {
        $stockMovement = StockMovement::withTrashed()->findOrFail($id);
        $stockMovement->restore();

        return response()->json([
            'success' => true,
            'message' => 'Stock movement restored successfully',
            'data' => $stockMovement
        ], Response::HTTP_OK);
    }
}
