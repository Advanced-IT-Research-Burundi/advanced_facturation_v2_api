<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WarehouseTransfer;
use App\Models\WarehouseTransferItem;
use App\Models\WarehouseProduct;
use App\Models\StockMovement;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class WarehouseTransferController extends Controller
{
    /**
     * Display a listing of transfers.
     */
    public function index(Request $request)
    {
        $query = WarehouseTransfer::with([
            'sourceWarehouse',
            'destinationWarehouse',
            'items.product',
            'createdByUser',
            'approvedByUser'
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('source_warehouse_id')) {
            $query->where('source_warehouse_id', $request->source_warehouse_id);
        }

        if ($request->filled('destination_warehouse_id')) {
            $query->where('destination_warehouse_id', $request->destination_warehouse_id);
        }

        $transfers = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $transfers
        ], Response::HTTP_OK);
    }

    /**
     * Get pending transfers.
     */
    public function pending()
    {
        $transfers = WarehouseTransfer::with([
            'sourceWarehouse',
            'destinationWarehouse',
            'items.product',
            'createdByUser'
        ])
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $transfers
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created transfer.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'source_warehouse_id' => 'required|exists:warehouses,id',
            'destination_warehouse_id' => 'required|exists:warehouses,id|different:source_warehouse_id',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
        ]);

        DB::beginTransaction();
        try {
            // Verify stock availability
            foreach ($validated['items'] as $item) {
                $stock = WarehouseProduct::where('warehouse_id', $validated['source_warehouse_id'])
                    ->where('product_id', $item['product_id'])
                    ->first();

                if (!$stock || $stock->quantity < $item['quantity']) {
                    $product = Product::find($item['product_id']);
                    throw new \Exception("Stock insuffisant pour {$product->item_designation}. Disponible: " .
                        ($stock->quantity ?? 0));
                }
            }

            // Create transfer
            $transfer = WarehouseTransfer::create([
                'transfer_code' => WarehouseTransfer::generateCode(),
                'source_warehouse_id' => $validated['source_warehouse_id'],
                'destination_warehouse_id' => $validated['destination_warehouse_id'],
                'status' => 'pending',
                'notes' => $validated['notes'],
                'created_by' => auth()->id(),
                'company_id' => auth()->user()->company_id,
            ]);

            // Create transfer items
            foreach ($validated['items'] as $item) {
                $stock = WarehouseProduct::where('warehouse_id', $validated['source_warehouse_id'])
                    ->where('product_id', $item['product_id'])
                    ->first();

                WarehouseTransferItem::create([
                    'warehouse_transfer_id' => $transfer->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $stock->unit_price ?? 0,
                    'currency' => $stock->currency ?? 'BIF',
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transfert cree avec succes. En attente d\'approbation.',
                'data' => $transfer->load(['sourceWarehouse', 'destinationWarehouse', 'items.product'])
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
     * Display the specified transfer.
     */
    public function show(WarehouseTransfer $warehouseTransfer)
    {
        return response()->json([
            'success' => true,
            'data' => $warehouseTransfer->load([
                'sourceWarehouse',
                'destinationWarehouse',
                'items.product',
                'createdByUser',
                'approvedByUser'
            ])
        ], Response::HTTP_OK);
    }

    /**
     * Approve a transfer.
     */
    public function approve(WarehouseTransfer $warehouseTransfer)
    {
        if ($warehouseTransfer->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Ce transfert ne peut plus etre approuve.'
            ], Response::HTTP_BAD_REQUEST);
        }

        DB::beginTransaction();
        try {
            // Verify stock still available
            foreach ($warehouseTransfer->items as $item) {
                $stock = WarehouseProduct::where('warehouse_id', $warehouseTransfer->source_warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->first();

                if (!$stock || $stock->quantity < $item->quantity) {
                    throw new \Exception("Stock insuffisant pour {$item->product->item_designation}");
                }
            }

            // Process transfer
            foreach ($warehouseTransfer->items as $item) {
                $product = $item->product;

                // Decrease source stock
                $sourceStock = WarehouseProduct::where('warehouse_id', $warehouseTransfer->source_warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->first();

                // Create exit movement (ST - Sortie Transfert)
                $exitMovement = StockMovement::create([
                    'system_or_device_id' => 'WEB-' . auth()->id(),
                    'item_code' => $product->item_code,
                    'item_designation' => $product->item_designation,
                    'item_quantity' => $item->quantity,
                    'item_measurement_unit' => $product->item_measurement_unit ?? 'PCE',
                    'item_purchase_or_sale_price' => $item->unit_price,
                    'item_purchase_or_sale_currency' => $item->currency,
                    'item_movement_type' => 'ST',
                    'item_movement_description' => "Transfert vers {$warehouseTransfer->destinationWarehouse->name} - {$warehouseTransfer->transfer_code}",
                    'item_movement_date' => now(),
                    'obr_submission_status' => 'PENDING',
                    'company_id' => $warehouseTransfer->company_id,
                    'product_id' => $item->product_id,
                    'warehouse_id' => $warehouseTransfer->source_warehouse_id,
                    'user_id' => auth()->id(),
                    'created_by' => auth()->id(),
                ]);

                $sourceStock->quantity -= $item->quantity;
                $sourceStock->last_stock_movement_id = $exitMovement->id;
                $sourceStock->save();

                // Increase destination stock
                $destStock = WarehouseProduct::firstOrNew([
                    'warehouse_id' => $warehouseTransfer->destination_warehouse_id,
                    'product_id' => $item->product_id,
                ]);

                // Create entry movement (ET - Entree Transfert)
                $entryMovement = StockMovement::create([
                    'system_or_device_id' => 'WEB-' . auth()->id(),
                    'item_code' => $product->item_code,
                    'item_designation' => $product->item_designation,
                    'item_quantity' => $item->quantity,
                    'item_measurement_unit' => $product->item_measurement_unit ?? 'PCE',
                    'item_purchase_or_sale_price' => $item->unit_price,
                    'item_purchase_or_sale_currency' => $item->currency,
                    'item_movement_type' => 'ET',
                    'item_movement_description' => "Transfert depuis {$warehouseTransfer->sourceWarehouse->name} - {$warehouseTransfer->transfer_code}",
                    'item_movement_date' => now(),
                    'obr_submission_status' => 'PENDING',
                    'company_id' => $warehouseTransfer->company_id,
                    'product_id' => $item->product_id,
                    'warehouse_id' => $warehouseTransfer->destination_warehouse_id,
                    'user_id' => auth()->id(),
                    'created_by' => auth()->id(),
                ]);

                $destStock->quantity = ($destStock->quantity ?? 0) + $item->quantity;
                $destStock->unit_price = $item->unit_price;
                $destStock->currency = $item->currency;
                $destStock->last_stock_movement_id = $entryMovement->id;
                $destStock->user_id = auth()->id();
                $destStock->save();
            }

            // Update transfer status
            $warehouseTransfer->update([
                'status' => 'completed',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transfert approuve et complete avec succes.',
                'data' => $warehouseTransfer->fresh([
                    'sourceWarehouse',
                    'destinationWarehouse',
                    'items.product'
                ])
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Reject a transfer.
     */
    public function reject(Request $request, WarehouseTransfer $warehouseTransfer)
    {
        if ($warehouseTransfer->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Ce transfert ne peut plus etre rejete.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $warehouseTransfer->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Transfert rejete.',
            'data' => $warehouseTransfer
        ], Response::HTTP_OK);
    }

    /**
     * Remove the specified transfer.
     */
    public function destroy(WarehouseTransfer $warehouseTransfer)
    {
        if ($warehouseTransfer->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Un transfert complete ne peut pas etre supprime.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $warehouseTransfer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Transfert supprime avec succes.'
        ], Response::HTTP_OK);
    }
}
