<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use App\Models\WarehouseProduct;
use App\Models\WarehouseTransfer;
use App\Models\Warehouse;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class StockMovementController extends Controller
{
    /**
     * Dashboard du stock - Retourne tout ce qui est nécessaire pour l'interface
     */
    public function dashboard($warehouseId)
    {
        $warehouse = Warehouse::select('id', 'name', 'location')->findOrFail($warehouseId);

        // Stock actuel avec infos minimales
        $stocks = WarehouseProduct::with([
            'product:id,item_code,item_designation,item_measurement_unit',
            'lastStockMovement:id,item_movement_type,created_at'
        ])
        ->where('warehouse_id', $warehouseId)
        ->where('quantity', '>=', 0)
        ->select('id', 'product_id', 'warehouse_id', 'quantity', 'unit_price', 'currency', 'last_stock_movement_id')
        ->get();

        // Produits disponibles pour ajout
        // $availableProducts = Product::select('id', 'item_code', 'item_designation', 'item_measurement_unit')
        //     ->orderBy('item_designation')
        //     ->get();

        // Transferts en attente pour ce destinataire
        $pendingTransfers = WarehouseTransfer::with([
            'sourceWarehouse:id,name',
            'items.product:id,item_designation,item_code',
            'creator:id,name'
        ])
        ->where('destination_warehouse_id', $warehouseId)
        ->where('status', 'PENDING')
        ->select('id', 'transfer_code', 'source_warehouse_id', 'destination_warehouse_id', 'status', 'notes', 'created_by', 'created_at')
        ->orderBy('created_at', 'desc')
        ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'warehouse' => $warehouse,
                'stocks' => $stocks,
                'available_products' => $stocks->pluck('product')->unique(),
                'pending_transfers' => $pendingTransfers,
                'pending_count' => $pendingTransfers->count()
            ]
        ]);
    }

    /**
     * Historique des mouvements avec filtres
     */
    public function movements(Request $request, $warehouseId)
    {
        $query = StockMovement::with('product:id,item_designation,item_code')
            ->where('warehouse_id', $warehouseId)
            ->select('id', 'item_code', 'item_designation', 'item_quantity', 'item_measurement_unit',
                     'item_purchase_or_sale_price', 'item_purchase_or_sale_currency', 'item_movement_type',
                     'item_movement_invoice_ref', 'item_movement_date', 'obr_submission_status',
                     'product_id', 'warehouse_id')
            ->orderBy('item_movement_date', 'desc');

        if ($request->movement_type) {
            $query->where('item_movement_type', $request->movement_type);
        }

        if ($request->date_from) {
            $query->where('item_movement_date', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->where('item_movement_date', '<=', $request->date_to);
        }

        $movements = $query->paginate(50);

        return response()->json([
            'success' => true,
            'data' => $movements
        ]);
    }

    /**
     * Entrée rapide d'un seul produit
     */
    public function quickEntry(Request $request, $warehouseId)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|numeric|min:0.01',
            'unit_price' => 'required|numeric|min:0',
            'currency' => 'required|string',
            'movement_type' => 'required|in:EN,ER,EI,EAJ,EAU',
            'date_expiration' => 'nullable|date',
            'invoice_ref' => 'nullable|string'
        ]);

        try {
            DB::beginTransaction();

            $product = Product::findOrFail($request->product_id);

            $movement = StockMovement::create([
                'system_or_device_id' => uniqid('ENT-'),
                'item_code' => $product->item_code,
                'item_designation' => $product->item_designation,
                'item_quantity' => $request->quantity,
                'item_measurement_unit' => $product->item_measurement_unit,
                'item_purchase_or_sale_price' => $request->unit_price,
                'item_purchase_or_sale_currency' => $request->currency,
                'item_movement_type' => $request->movement_type,
                'item_movement_invoice_ref' => $request->invoice_ref,
                'item_movement_date' => now(),
                'obr_submission_status' => 'PENDING',
                'company_id' => Auth::user()->company_id,
                'product_id' => $request->product_id,
                'warehouse_id' => $warehouseId,
                'created_by' => Auth::id(),
                'user_id' => Auth::id()
            ]);

            $stock = WarehouseProduct::firstOrNew([
                'warehouse_id' => $warehouseId,
                'product_id' => $request->product_id
            ]);

            $stock->quantity = ($stock->quantity ?? 0) + $request->quantity;
            $stock->unit_price = $request->unit_price;
            $stock->currency = $request->currency;
            $stock->last_stock_movement_id = $movement->id;
            $stock->user_id = Auth::id();
            $stock->save();

            // Mettre à jour date d'expiration du produit si fournie
            if ($request->date_expiration) {
                $product->update(['date_expiration' => $request->date_expiration]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Entrée enregistrée avec succès'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sortie rapide d'un seul produit
     */
    public function quickExit(Request $request, $warehouseId)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|numeric|min:0.01',
            'movement_type' => 'required|in:SN,SP,SV,SD,SC,SAJ,SAU',
            'invoice_ref' => 'nullable|string'
        ]);

        try {
            DB::beginTransaction();

            $stock = WarehouseProduct::where('warehouse_id', $warehouseId)
                ->where('product_id', $request->product_id)
                ->first();

            if (!$stock || $stock->quantity < $request->quantity) {
                throw new \Exception("Stock insuffisant. Disponible: " . ($stock->quantity ?? 0));
            }

            $product = Product::findOrFail($request->product_id);

            $movement = StockMovement::create([
                'system_or_device_id' => uniqid('SRT-'),
                'item_code' => $product->item_code,
                'item_designation' => $product->item_designation,
                'item_quantity' => $request->quantity,
                'item_measurement_unit' => $product->item_measurement_unit,
                'item_purchase_or_sale_price' => $stock->unit_price,
                'item_purchase_or_sale_currency' => $stock->currency,
                'item_movement_type' => $request->movement_type,
                'item_movement_invoice_ref' => $request->invoice_ref,
                'item_movement_date' => now(),
                'obr_submission_status' => 'PENDING',
                'company_id' => Auth::user()->company_id,
                'product_id' => $request->product_id,
                'warehouse_id' => $warehouseId,
                'created_by' => Auth::id(),
                'user_id' => Auth::id()
            ]);

            $stock->quantity -= $request->quantity;
            $stock->last_stock_movement_id = $movement->id;
            $stock->user_id = Auth::id();
            $stock->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Sortie enregistrée avec succès'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Entrée multiple de produits
     */
    public function bulkEntry(Request $request, $warehouseId)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.currency' => 'required|string',
            'items.*.date_expiration' => 'nullable|date',
            'movement_type' => 'required|in:EN,ER,EI,EAJ,EAU',
            'invoice_ref' => 'nullable|string',
            'description' => 'nullable|string'
        ]);

        try {
            DB::beginTransaction();

            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);

                $movement = StockMovement::create([
                    'system_or_device_id' => uniqid('BENT-'),
                    'item_code' => $product->item_code,
                    'item_designation' => $product->item_designation,
                    'item_quantity' => $item['quantity'],
                    'item_measurement_unit' => $product->item_measurement_unit,
                    'item_purchase_or_sale_price' => $item['unit_price'],
                    'item_purchase_or_sale_currency' => $item['currency'],
                    'item_movement_type' => $request->movement_type,
                    'item_movement_invoice_ref' => $request->invoice_ref,
                    'item_movement_description' => $request->description,
                    'item_movement_date' => now(),
                    'obr_submission_status' => 'PENDING',
                    'company_id' => Auth::user()->company_id,
                    'product_id' => $item['product_id'],
                    'warehouse_id' => $warehouseId,
                    'created_by' => Auth::id(),
                    'user_id' => Auth::id()
                ]);

                $stock = WarehouseProduct::firstOrNew([
                    'warehouse_id' => $warehouseId,
                    'product_id' => $item['product_id']
                ]);

                $stock->quantity = ($stock->quantity ?? 0) + $item['quantity'];
                $stock->unit_price = $item['unit_price'];
                $stock->currency = $item['currency'];
                $stock->last_stock_movement_id = $movement->id;
                $stock->user_id = Auth::id();
                $stock->save();

                if (!empty($item['date_expiration'])) {
                    $product->update(['date_expiration' => $item['date_expiration']]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($request->items) . ' produit(s) ajouté(s) avec succès'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sortie multiple de produits
     */
    public function bulkExit(Request $request, $warehouseId)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'movement_type' => 'required|in:SN,SP,SV,SD,SC,SAJ,SAU',
            'invoice_ref' => 'nullable|string',
            'description' => 'nullable|string'
        ]);

        try {
            DB::beginTransaction();

            // Vérification du stock pour tous les produits
            foreach ($request->items as $item) {
                $stock = WarehouseProduct::where('warehouse_id', $warehouseId)
                    ->where('product_id', $item['product_id'])
                    ->first();

                if (!$stock || $stock->quantity < $item['quantity']) {
                    $product = Product::findOrFail($item['product_id']);
                    throw new \Exception("Stock insuffisant pour {$product->item_designation}. Disponible: " . ($stock->quantity ?? 0));
                }
            }

            // Création des mouvements
            foreach ($request->items as $item) {
                $stock = WarehouseProduct::where('warehouse_id', $warehouseId)
                    ->where('product_id', $item['product_id'])
                    ->first();

                $product = Product::findOrFail($item['product_id']);

                $movement = StockMovement::create([
                    'system_or_device_id' => uniqid('BSRT-'),
                    'item_code' => $product->item_code,
                    'item_designation' => $product->item_designation,
                    'item_quantity' => $item['quantity'],
                    'item_measurement_unit' => $product->item_measurement_unit,
                    'item_purchase_or_sale_price' => $stock->unit_price,
                    'item_purchase_or_sale_currency' => $stock->currency,
                    'item_movement_type' => $request->movement_type,
                    'item_movement_invoice_ref' => $request->invoice_ref,
                    'item_movement_description' => $request->description,
                    'item_movement_date' => now(),
                    'obr_submission_status' => 'PENDING',
                    'company_id' => Auth::user()->company_id,
                    'product_id' => $item['product_id'],
                    'warehouse_id' => $warehouseId,
                    'created_by' => Auth::id(),
                    'user_id' => Auth::id()
                ]);

                $stock->quantity -= $item['quantity'];
                $stock->last_stock_movement_id = $movement->id;
                $stock->user_id = Auth::id();
                $stock->save();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($request->items) . ' produit(s) sorti(s) avec succès'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Créer un transfert (source déjà connue via l'ID de route)
     */
    public function createTransfer(Request $request, $warehouseId)
    {
        $request->validate([
            'destination_warehouse_id' => 'required|exists:warehouses,id|different:' . $warehouseId,
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string'
        ]);

        try {
            DB::beginTransaction();

            // Vérification des stocks
            foreach ($request->items as $item) {
                $stock = WarehouseProduct::where('warehouse_id', $warehouseId)
                    ->where('product_id', $item['product_id'])
                    ->first();

                if (!$stock || $stock->quantity < $item['quantity']) {
                    $product = Product::findOrFail($item['product_id']);
                    throw new \Exception("Stock insuffisant pour {$product->item_designation}. Disponible: " . ($stock->quantity ?? 0));
                }
            }

            $transfer = WarehouseTransfer::create([
                'transfer_code' => 'TRF-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6)),
                'source_warehouse_id' => $warehouseId,
                'destination_warehouse_id' => $request->destination_warehouse_id,
                'created_by' => Auth::id(),
                'status' => 'PENDING',
                'notes' => $request->notes
            ]);

            $destinationName = Warehouse::find($request->destination_warehouse_id)->name;

            foreach ($request->items as $item) {
                $stock = WarehouseProduct::where('warehouse_id', $warehouseId)
                    ->where('product_id', $item['product_id'])
                    ->first();

                $product = Product::findOrFail($item['product_id']);

                $movementOut = StockMovement::create([
                    'system_or_device_id' => uniqid('TRF-OUT-'),
                    'item_code' => $product->item_code,
                    'item_designation' => $product->item_designation,
                    'item_quantity' => $item['quantity'],
                    'item_measurement_unit' => $product->item_measurement_unit,
                    'item_purchase_or_sale_price' => $stock->unit_price,
                    'item_purchase_or_sale_currency' => $stock->currency,
                    'item_movement_type' => 'ST',
                    'item_movement_invoice_ref' => $transfer->transfer_code,
                    'item_movement_description' => "Transfert vers {$destinationName}",
                    'item_movement_date' => now(),
                    'obr_submission_status' => 'PENDING',
                    'company_id' => Auth::user()->company_id,
                    'product_id' => $item['product_id'],
                    'warehouse_id' => $warehouseId,
                    'created_by' => Auth::id(),
                    'user_id' => Auth::id()
                ]);

                $stock->quantity -= $item['quantity'];
                $stock->last_stock_movement_id = $movementOut->id;
                $stock->user_id = Auth::id();
                $stock->save();

                $transfer->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $stock->unit_price,
                    'currency' => $stock->currency,
                    'stock_movement_out_id' => $movementOut->id
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transfert créé avec succès. En attente d\'approbation par le destinataire.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approuver un transfert
     */
    public function approveTransfer($warehouseId, $transferId)
    {
        try {
            DB::beginTransaction();

            $transfer = WarehouseTransfer::with('items', 'sourceWarehouse')
                ->where('destination_warehouse_id', $warehouseId)
                ->findOrFail($transferId);

            if ($transfer->status !== 'PENDING') {
                throw new \Exception('Ce transfert ne peut plus être approuvé');
            }

            foreach ($transfer->items as $item) {
                $product = Product::findOrFail($item->product_id);

                $movementIn = StockMovement::create([
                    'system_or_device_id' => uniqid('TRF-IN-'),
                    'item_code' => $product->item_code,
                    'item_designation' => $product->item_designation,
                    'item_quantity' => $item->quantity,
                    'item_measurement_unit' => $product->item_measurement_unit,
                    'item_purchase_or_sale_price' => $item->unit_price,
                    'item_purchase_or_sale_currency' => $item->currency,
                    'item_movement_type' => 'ET',
                    'item_movement_invoice_ref' => $transfer->transfer_code,
                    'item_movement_description' => "Transfert depuis {$transfer->sourceWarehouse->name}",
                    'item_movement_date' => now(),
                    'obr_submission_status' => 'PENDING',
                    'company_id' => Auth::user()->company_id,
                    'product_id' => $item->product_id,
                    'warehouse_id' => $warehouseId,
                    'created_by' => Auth::id(),
                    'user_id' => Auth::id()
                ]);

                $stock = WarehouseProduct::firstOrNew([
                    'warehouse_id' => $warehouseId,
                    'product_id' => $item->product_id
                ]);

                $stock->quantity = ($stock->quantity ?? 0) + $item->quantity;
                $stock->unit_price = $item->unit_price;
                $stock->currency = $item->currency;
                $stock->last_stock_movement_id = $movementIn->id;
                $stock->user_id = Auth::id();
                $stock->save();

                $item->update(['stock_movement_in_id' => $movementIn->id]);
            }

            $transfer->update([
                'status' => 'APPROVED',
                'approved_by' => Auth::id(),
                'approved_at' => now()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transfert approuvé avec succès'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rejeter un transfert
     */
    public function rejectTransfer(Request $request, $warehouseId, $transferId)
    {
        $request->validate([
            'rejection_reason' => 'required|string'
        ]);

        try {
            DB::beginTransaction();

            $transfer = WarehouseTransfer::with('items')
                ->where('destination_warehouse_id', $warehouseId)
                ->findOrFail($transferId);

            if ($transfer->status !== 'PENDING') {
                throw new \Exception('Ce transfert ne peut plus être rejeté');
            }

            // Restaurer le stock source
            foreach ($transfer->items as $item) {
                $stock = WarehouseProduct::where('warehouse_id', $transfer->source_warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->first();

                if ($stock) {
                    $stock->quantity += $item->quantity;
                    $stock->user_id = Auth::id();
                    $stock->save();
                }

                $product = Product::findOrFail($item->product_id);
                StockMovement::create([
                    'system_or_device_id' => uniqid('TRF-CNCL-'),
                    'item_code' => $product->item_code,
                    'item_designation' => $product->item_designation,
                    'item_quantity' => $item->quantity,
                    'item_measurement_unit' => $product->item_measurement_unit,
                    'item_purchase_or_sale_price' => $item->unit_price,
                    'item_purchase_or_sale_currency' => $item->currency,
                    'item_movement_type' => 'EAJ',
                    'item_movement_invoice_ref' => $transfer->transfer_code,
                    'item_movement_description' => "Annulation transfert - " . $request->rejection_reason,
                    'item_movement_date' => now(),
                    'obr_submission_status' => 'PENDING',
                    'company_id' => Auth::user()->company_id,
                    'product_id' => $item->product_id,
                    'warehouse_id' => $transfer->source_warehouse_id,
                    'created_by' => Auth::id(),
                    'user_id' => Auth::id()
                ]);
            }

            $transfer->update([
                'status' => 'REJECTED',
                'rejection_reason' => $request->rejection_reason,
                'approved_by' => Auth::id(),
                'approved_at' => now()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transfert rejeté. Le stock source a été restauré.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Liste des entrepôts disponibles (pour transfert)
     */
    public function availableWarehouses($warehouseId)
    {
        $warehouses = Warehouse::where('id', '!=', $warehouseId)
            ->select('id', 'name', 'location')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $warehouses
        ]);
    }
}
