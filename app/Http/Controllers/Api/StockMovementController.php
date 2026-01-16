<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use App\Models\WarehouseProduct;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StockMovementController extends Controller
{
    /**
     * Types de mouvements valides
     */
    const ENTRY_TYPES = ['EN', 'ER', 'EI', 'EAJ', 'ET', 'EAU'];
    const EXIT_TYPES = ['SN', 'SP', 'SV', 'SD', 'SC', 'SAJ', 'ST', 'SAU'];

    const MOVEMENT_LABELS = [
        'EN' => 'Entree Normale',
        'ER' => 'Entree Retour',
        'EI' => 'Entree Inventaire',
        'EAJ' => 'Entree Ajustement',
        'ET' => 'Entree Transfert',
        'EAU' => 'Entree Autres',
        'SN' => 'Sortie Normale',
        'SP' => 'Sortie Perte',
        'SV' => 'Sortie Vol',
        'SD' => 'Sortie Desuetude',
        'SC' => 'Sortie Casse',
        'SAJ' => 'Sortie Ajustement',
        'ST' => 'Sortie Transfert',
        'SAU' => 'Sortie Autres',
    ];

    /**
     * Display a listing of stock movements.
     */
    public function index(Request $request)
    {
        $query = StockMovement::with(['company', 'product', 'warehouse', 'user']);

        // Filtres
        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }
        if ($request->filled('movement_type')) {
            $query->where('item_movement_type', $request->movement_type);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('item_movement_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('item_movement_date', '<=', $request->date_to);
        }

        $movements = $query->orderBy('item_movement_date', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $movements
        ], Response::HTTP_OK);
    }

    /**
     * Get stock for a specific warehouse
     */
    public function warehouseStock($warehouseId)
    {
        $stocks = WarehouseProduct::with(['product', 'lastStockMovement'])
            ->where('warehouse_id', $warehouseId)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $stocks
        ], Response::HTTP_OK);
    }

    /**
     * Create stock entry (multiple items)
     */
    public function createEntry(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'movement_type' => ['required', Rule::in(self::ENTRY_TYPES)],
            'movement_date' => 'required|date',
            'invoice_ref' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.currency' => 'required|string|max:3',
        ]);

        DB::beginTransaction();
        try {
            $warehouse = Warehouse::findOrFail($validated['warehouse_id']);
            $movements = [];

            foreach ($validated['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);

                // Create stock movement
                $movement = StockMovement::create([
                    'system_or_device_id' => 'WEB-' . auth()->id(),
                    'item_code' => $product->item_code,
                    'item_designation' => $product->item_designation,
                    'item_quantity' => $item['quantity'],
                    'item_measurement_unit' => $product->item_measurement_unit ?? 'PCE',
                    'item_purchase_or_sale_price' => $item['unit_price'],
                    'item_purchase_or_sale_currency' => $item['currency'],
                    'item_movement_type' => $validated['movement_type'],
                    'item_movement_invoice_ref' => $validated['invoice_ref'],
                    'item_movement_description' => $validated['description'],
                    'item_movement_date' => $validated['movement_date'],
                    'obr_submission_status' => 'PENDING',
                    'company_id' => auth()->user()->company_id,
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                    'user_id' => auth()->id(),
                    'created_by' => auth()->id(),
                ]);

                // Update or create warehouse product stock
                $warehouseProduct = WarehouseProduct::firstOrNew([
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                ]);

                $warehouseProduct->quantity = ($warehouseProduct->quantity ?? 0) + $item['quantity'];
                $warehouseProduct->unit_price = $item['unit_price'];
                $warehouseProduct->currency = $item['currency'];
                $warehouseProduct->last_stock_movement_id = $movement->id;
                $warehouseProduct->user_id = auth()->id();
                $warehouseProduct->save();

                $movements[] = $movement;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Entree de stock enregistree avec succes',
                'data' => $movements
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Create stock exit (multiple items)
     */
    public function createExit(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'movement_type' => ['required', Rule::in(self::EXIT_TYPES)],
            'movement_date' => 'required|date',
            'invoice_ref' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
        ]);

        DB::beginTransaction();
        try {
            $warehouse = Warehouse::findOrFail($validated['warehouse_id']);
            $movements = [];

            foreach ($validated['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);

                // Check available stock
                $warehouseProduct = WarehouseProduct::where('product_id', $product->id)
                    ->where('warehouse_id', $warehouse->id)
                    ->first();

                if (!$warehouseProduct || $warehouseProduct->quantity < $item['quantity']) {
                    throw new \Exception("Stock insuffisant pour {$product->item_designation}. Disponible: " .
                        ($warehouseProduct->quantity ?? 0));
                }

                // Create stock movement
                $movement = StockMovement::create([
                    'system_or_device_id' => 'WEB-' . auth()->id(),
                    'item_code' => $product->item_code,
                    'item_designation' => $product->item_designation,
                    'item_quantity' => $item['quantity'],
                    'item_measurement_unit' => $product->item_measurement_unit ?? 'PCE',
                    'item_purchase_or_sale_price' => $warehouseProduct->unit_price ?? 0,
                    'item_purchase_or_sale_currency' => $warehouseProduct->currency ?? 'BIF',
                    'item_movement_type' => $validated['movement_type'],
                    'item_movement_invoice_ref' => $validated['invoice_ref'],
                    'item_movement_description' => $validated['description'],
                    'item_movement_date' => $validated['movement_date'],
                    'obr_submission_status' => 'PENDING',
                    'company_id' => auth()->user()->company_id,
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                    'user_id' => auth()->id(),
                    'created_by' => auth()->id(),
                ]);

                // Update warehouse product stock
                $warehouseProduct->quantity -= $item['quantity'];
                $warehouseProduct->last_stock_movement_id = $movement->id;
                $warehouseProduct->save();

                $movements[] = $movement;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Sortie de stock enregistree avec succes',
                'data' => $movements
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get movement types
     */
    public function getMovementTypes()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'entry_types' => array_map(fn($code) => [
                    'code' => $code,
                    'label' => self::MOVEMENT_LABELS[$code]
                ], self::ENTRY_TYPES),
                'exit_types' => array_map(fn($code) => [
                    'code' => $code,
                    'label' => self::MOVEMENT_LABELS[$code]
                ], self::EXIT_TYPES),
            ]
        ], Response::HTTP_OK);
    }

    /**
     * Get movements by warehouse
     */
    public function byWarehouse($warehouseId, Request $request)
    {
        $query = StockMovement::with(['product', 'user'])
            ->where('warehouse_id', $warehouseId);

        if ($request->filled('movement_type')) {
            $query->where('item_movement_type', $request->movement_type);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('item_movement_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('item_movement_date', '<=', $request->date_to);
        }

        $movements = $query->orderBy('item_movement_date', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $movements
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
            'item_movement_type' => ['required', Rule::in(array_merge(self::ENTRY_TYPES, self::EXIT_TYPES))],
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

        DB::beginTransaction();
        try {
            $stockMovement = StockMovement::create($validated);

            // Update warehouse product stock
            $warehouseProduct = WarehouseProduct::firstOrNew([
                'product_id' => $validated['product_id'],
                'warehouse_id' => $validated['warehouse_id'],
            ]);

            if (in_array($validated['item_movement_type'], self::ENTRY_TYPES)) {
                $warehouseProduct->quantity = ($warehouseProduct->quantity ?? 0) + $validated['item_quantity'];
            } else {
                $warehouseProduct->quantity = ($warehouseProduct->quantity ?? 0) - $validated['item_quantity'];
            }

            $warehouseProduct->unit_price = $validated['item_purchase_or_sale_price'];
            $warehouseProduct->currency = $validated['item_purchase_or_sale_currency'];
            $warehouseProduct->last_stock_movement_id = $stockMovement->id;
            $warehouseProduct->user_id = auth()->id();
            $warehouseProduct->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock movement created successfully',
                'data' => $stockMovement->load(['company', 'product', 'warehouse'])
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
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
