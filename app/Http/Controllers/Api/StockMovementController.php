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
     * Liste des mouvements de stock avec filtres
     */
    public function index(Request $request)
    {
        $query = StockMovement::with(['warehouse', 'product', 'createdBy'])
            ->orderBy('item_movement_date', 'desc');

        if ($request->warehouse_id) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->movement_type) {
            $query->where('item_movement_type', $request->movement_type);
        }

        if ($request->date_from) {
            $query->where('item_movement_date', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->where('item_movement_date', '<=', $request->date_to);
        }

        $movements = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $movements
        ]);
    }

    /**
     * Liste des stocks d'un entrepôt
     */
    public function warehouseStock($warehouseId)
    {
        $stocks = WarehouseProduct::with(['product', 'lastStockMovement'])
            ->where('warehouse_id', $warehouseId)
            ->where('quantity', '>', 0)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $stocks
        ]);
    }

    /**
     * Créer une entrée de stock (EN, ER, EI, EAJ, EAU)
     */
    public function createEntry(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.currency' => 'required|string',
            'movement_type' => 'required|in:EN,ER,EI,EAJ,EAU',
            'movement_date' => 'required|date',
            'invoice_ref' => 'nullable|string',
            'description' => 'nullable|string'
        ]);

        try {
            DB::beginTransaction();

            $movements = [];

            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);

                // Créer le mouvement de stock
                $movement = StockMovement::create([
                    'system_or_device_id' => uniqid('ENT-'),
                    'item_code' => $product->item_code,
                    'item_designation' => $product->item_designation,
                    'item_quantity' => $item['quantity'],
                    'item_measurement_unit' => $product->item_designation ?? 'PCE',
                    'item_purchase_or_sale_price' => $item['unit_price'],
                    'item_purchase_or_sale_currency' => $item['currency'],
                    'item_movement_type' => $request->movement_type,
                    'item_movement_invoice_ref' => $request->invoice_ref,
                    'item_movement_description' => $request->description,
                    'item_movement_date' => $request->movement_date,
                    'obr_submission_status' => 'PENDING',
                    'company_id' => Auth::user()->company_id,
                    'product_id' => $item['product_id'],
                    'warehouse_id' => $request->warehouse_id,
                    'created_by' => Auth::id(),
                    'user_id' => Auth::id()
                ]);

                // Mettre à jour warehouse_products
                $stock = WarehouseProduct::firstOrNew([
                    'warehouse_id' => $request->warehouse_id,
                    'product_id' => $item['product_id']
                ]);

                $stock->quantity = ($stock->quantity ?? 0) + $item['quantity'];
                $stock->unit_price = $item['unit_price'];
                $stock->currency = $item['currency'];
                $stock->last_stock_movement_id = $movement->id;
                $stock->user_id = Auth::id();
                $stock->save();

                $movements[] = $movement;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Entrée de stock enregistrée avec succès',
                'data' => $movements
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Créer une sortie de stock (SN, SP, SV, SD, SC, SAJ, SAU)
     */
    public function createExit(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'movement_type' => 'required|in:SN,SP,SV,SD,SC,SAJ,SAU',
            'movement_date' => 'required|date',
            'invoice_ref' => 'nullable|string',
            'description' => 'nullable|string'
        ]);

        try {
            DB::beginTransaction();

            $movements = [];

            foreach ($request->items as $item) {
                // Vérifier le stock disponible
                $stock = WarehouseProduct::where('warehouse_id', $request->warehouse_id)
                    ->where('product_id', $item['product_id'])
                    ->first();

                if (!$stock || $stock->quantity < $item['quantity']) {
                    throw new \Exception("Stock insuffisant pour le produit ID: {$item['product_id']}. Disponible: " . ($stock->quantity ?? 0));
                }

                $product = Product::findOrFail($item['product_id']);

                // Créer le mouvement de stock
                $movement = StockMovement::create([
                    'system_or_device_id' => uniqid('SRT-'),
                    'item_code' => $product->item_code,
                    'item_designation' => $product->item_designation,
                    'item_quantity' => $item['quantity'],
                    'item_measurement_unit' => $product->item_measurement_unit ?? 'PCE',
                    'item_purchase_or_sale_price' => $stock->unit_price,
                    'item_purchase_or_sale_currency' => $stock->currency,
                    'item_movement_type' => $request->movement_type,
                    'item_movement_invoice_ref' => $request->invoice_ref,
                    'item_movement_description' => $request->description,
                    'item_movement_date' => $request->movement_date,
                    'obr_submission_status' => 'PENDING',
                    'company_id' => Auth::user()->company_id,
                    'product_id' => $item['product_id'],
                    'warehouse_id' => $request->warehouse_id,
                    'created_by' => Auth::id(),
                    'user_id' => Auth::id()
                ]);

                // Diminuer le stock
                $stock->quantity -= $item['quantity'];
                $stock->last_stock_movement_id = $movement->id;
                $stock->user_id = Auth::id();
                $stock->save();

                $movements[] = $movement;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Sortie de stock enregistrée avec succès',
                'data' => $movements
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Créer un transfert entre entrepôts
     */
    public function createTransfer(Request $request)
    {
        $request->validate([
            'source_warehouse_id' => 'required|exists:warehouses,id',
            'destination_warehouse_id' => 'required|exists:warehouses,id|different:source_warehouse_id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string'
        ]);

        try {
            DB::beginTransaction();

            // Vérifier le stock disponible
            foreach ($request->items as $item) {
                $stock = WarehouseProduct::where('warehouse_id', $request->source_warehouse_id)
                    ->where('product_id', $item['product_id'])
                    ->first();

                if (!$stock || $stock->quantity < $item['quantity']) {
                    $product = Product::findOrFail($item['product_id']);
                    throw new \Exception("Stock insuffisant pour {$product->item_designation}. Disponible: " . ($stock->quantity ?? 0));
                }
            }

            // Créer le transfert
            $transfer = WarehouseTransfer::create([
                'transfer_code' => 'TRF-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6)),
                'source_warehouse_id' => $request->source_warehouse_id,
                'destination_warehouse_id' => $request->destination_warehouse_id,
                'created_by' => Auth::id(),
                'status' => 'PENDING',
                'notes' => $request->notes
            ]);

            // Créer les mouvements de sortie (ST) et diminuer le stock source
            foreach ($request->items as $item) {
                $stock = WarehouseProduct::where('warehouse_id', $request->source_warehouse_id)
                    ->where('product_id', $item['product_id'])
                    ->first();

                $product = Product::findOrFail($item['product_id']);

                // Mouvement de sortie par transfert (ST)
                $movementOut = StockMovement::create([
                    'system_or_device_id' => uniqid('TRF-OUT-'),
                    'item_code' => $product->item_code,
                    'item_designation' => $product->item_designation,
                    'item_quantity' => $item['quantity'],
                    'item_measurement_unit' => $product->item_measurement_unit ?? 'PCE',
                    'item_purchase_or_sale_price' => $stock->unit_price,
                    'item_purchase_or_sale_currency' => $stock->currency,
                    'item_movement_type' => 'ST',
                    'item_movement_invoice_ref' => $transfer->transfer_code,
                    'item_movement_description' => "Transfert vers " . Warehouse::find($request->destination_warehouse_id)->name,
                    'item_movement_date' => now(),
                    'obr_submission_status' => 'PENDING',
                    'company_id' => Auth::user()->company_id,
                    'product_id' => $item['product_id'],
                    'warehouse_id' => $request->source_warehouse_id,
                    'created_by' => Auth::id(),
                    'user_id' => Auth::id()
                ]);

                // Diminuer le stock source
                $stock->quantity -= $item['quantity'];
                $stock->last_stock_movement_id = $movementOut->id;
                $stock->user_id = Auth::id();
                $stock->save();

                // Créer l'item de transfert
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
                'message' => 'Transfert créé avec succès. En attente d\'approbation par le destinataire.',
                'data' => $transfer->load('items.product', 'sourceWarehouse', 'destinationWarehouse')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approuver un transfert (crée les entrées ET dans l'entrepôt destination)
     */
    public function approveTransfer($transferId)
    {
        try {
            DB::beginTransaction();

            $transfer = WarehouseTransfer::with('items')->findOrFail($transferId);

            if ($transfer->status !== 'PENDING') {
                throw new \Exception('Ce transfert ne peut plus être approuvé');
            }

            // Créer les mouvements d'entrée par transfert (ET)
            foreach ($transfer->items as $item) {
                $product = Product::findOrFail($item->product_id);

                $movementIn = StockMovement::create([
                    'system_or_device_id' => uniqid('TRF-IN-'),
                    'item_code' => $product->item_code,
                    'item_designation' => $product->item_designation,
                    'item_quantity' => $item->quantity,
                    'item_measurement_unit' => $product->item_measurement_unit ?? 'PCE',
                    'item_purchase_or_sale_price' => $item->unit_price,
                    'item_purchase_or_sale_currency' => $item->currency,
                    'item_movement_type' => 'ET',
                    'item_movement_invoice_ref' => $transfer->transfer_code,
                    'item_movement_description' => "Transfert depuis " . $transfer->sourceWarehouse->name,
                    'item_movement_date' => now(),
                    'obr_submission_status' => 'PENDING',
                    'company_id' => Auth::user()->company_id,
                    'product_id' => $item->product_id,
                    'warehouse_id' => $transfer->destination_warehouse_id,
                    'created_by' => Auth::id(),
                    'user_id' => Auth::id()
                ]);

                // Augmenter le stock destination
                $stock = WarehouseProduct::firstOrNew([
                    'warehouse_id' => $transfer->destination_warehouse_id,
                    'product_id' => $item->product_id
                ]);

                $stock->quantity = ($stock->quantity ?? 0) + $item->quantity;
                $stock->unit_price = $item->unit_price;
                $stock->currency = $item->currency;
                $stock->last_stock_movement_id = $movementIn->id;
                $stock->user_id = Auth::id();
                $stock->save();

                // Mettre à jour l'item
                $item->update(['stock_movement_in_id' => $movementIn->id]);
            }

            // Marquer comme approuvé
            $transfer->update([
                'status' => 'APPROVED',
                'approved_by' => Auth::id(),
                'approved_at' => now()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transfert approuvé avec succès. Les stocks ont été mis à jour.',
                'data' => $transfer
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rejeter un transfert (restaure le stock source)
     */
    public function rejectTransfer(Request $request, $transferId)
    {
        $request->validate([
            'rejection_reason' => 'required|string'
        ]);

        try {
            DB::beginTransaction();

            $transfer = WarehouseTransfer::with('items')->findOrFail($transferId);

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

                // Créer un mouvement d'annulation
                $product = Product::findOrFail($item->product_id);
                StockMovement::create([
                    'system_or_device_id' => uniqid('TRF-CANCEL-'),
                    'item_code' => $product->item_code,
                    'item_designation' => $product->item_designation,
                    'item_quantity' => $item->quantity,
                    'item_measurement_unit' => $product->item_measurement_unit ?? 'PCE',
                    'item_purchase_or_sale_price' => $item->unit_price,
                    'item_purchase_or_sale_currency' => $item->currency,
                    'item_movement_type' => 'EAJ',
                    'item_movement_invoice_ref' => $transfer->transfer_code,
                    'item_movement_description' => "Annulation transfert rejeté - " . $request->rejection_reason,
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
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Liste des transferts en attente
     */
    public function pendingTransfers()
    {
        $transfers = WarehouseTransfer::with(['sourceWarehouse', 'destinationWarehouse', 'items.product', 'creator'])
            ->where('status', 'PENDING')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $transfers
        ]);
    }
}
