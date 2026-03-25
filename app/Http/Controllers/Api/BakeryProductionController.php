<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WarehouseProduct;
use App\Models\WarehouseTransfer;
use App\Models\WarehouseTransferItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BakeryProductionController extends Controller
{
    public function dashboard()
    {
        try {
            // Chercher un entrepôt de production (si la colonne is_production existe)
            $prodWarehouse = null;
            try {
                $prodWarehouse = Warehouse::where('is_production', true)
                    ->where('company_id', Auth::user()->company_id)
                    ->latest()
                    ->first();
            } catch (\Exception $e) {
                // Si la colonne n'existe pas, prendre le premier entrepôt
                $prodWarehouse = Warehouse::where('company_id', Auth::user()->company_id)
                    ->latest()
                    ->first();
            }

            if (! $prodWarehouse) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'production_warehouse' => null,
                        'production_stock' => [],
                        'sales_warehouses' => [],
                        'today_production' => 0,
                        'today_transfers' => 0,
                        'message' => 'Aucun entrepôt de production configuré',
                    ],
                ]);
            }

            $prodStock = WarehouseProduct::with(['product' => function ($query) {
                $query->select('id', 'item_code', 'item_designation', 'item_measurement_unit', 'product_category_id')
                    ->with(['categoryProduct:id,name']);
            }])
                ->where('warehouse_id', $prodWarehouse->id)
                ->where('quantity', '>', 0)
                ->whereHas('product.categoryProduct', function ($query) {
                    $query->where(function ($q) {
                        $q->where('name', 'LIKE', '%boulang%')
                            ->orWhere('name', 'LIKE', '%bakery%')
                            ->orWhere('name', 'LIKE', '%Bakery%');
                    });
                })
                ->orderBy('updated_at', 'desc')
                ->get();

            $salesWarehouses = Warehouse::where('company_id', Auth::user()->company_id)
                ->where('id', '!=', $prodWarehouse->id)
                ->select('id', 'name', 'location')
                ->orderBy('updated_at', 'desc')
                ->get();

            $todayProduction = StockMovement::where('warehouse_id', $prodWarehouse->id)
                ->whereDate('item_movement_date', today())
                ->where('item_movement_type', 'EN')
                ->sum('item_quantity');

            $todayTransfers = 0;
            try {
                $todayTransfers = WarehouseTransfer::where('source_warehouse_id', $prodWarehouse->id)
                    ->whereDate('created_at', today())
                    ->where('status', 'APPROVED')
                    ->withSum('items', 'quantity')
                    ->get()
                    ->sum('items_sum_quantity') ?? 0;
            } catch (\Exception $e) {
                // Ignore si la table n'existe pas
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'production_warehouse' => $prodWarehouse,
                    'production_stock' => $prodStock,
                    'sales_warehouses' => $salesWarehouses,
                    'today_production' => $todayProduction,
                    'today_transfers' => $todayTransfers,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function changeStatus(Request $request)
    {
        $request->validate([
            'warehouse_product_id' => 'required|exists:warehouse_products,id',
            'status' => 'required|in:RAW,FINISHED',
        ]);

        try {
            $stock = WarehouseProduct::findOrFail($request->warehouse_product_id);
            $stock->production_status = $request->status;
            $stock->save();

            return response()->json([
                'success' => true,
                'message' => 'Statut modifié avec succès',
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function markAsFinished(Request $request)
    {
        $request->validate([
            'warehouse_product_id' => 'required|exists:warehouse_products,id',
            'product_id' => 'required|exists:products,id',
            'finished_quantity' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            // Get the production warehouse
            $prodWarehouse = Warehouse::where('is_production', true)
                ->where('company_id', Auth::user()->company_id)
                ->firstOrFail();

            // Get the RAW product stock
            $rawStock = WarehouseProduct::findOrFail($request->warehouse_product_id);

            // Verify it's in RAW status
            if ($rawStock->production_status !== 'RAW') {
                throw new \Exception('Le produit doit être en statut RAW (en production)');
            }

            // Verify quantity
            if ($rawStock->quantity < $request->finished_quantity) {
                throw new \Exception('Quantité insuffisante. Stock disponible: ' . $rawStock->quantity);
            }

            $product = Product::findOrFail($request->product_id);
            $movementCode = 'PROD-FIN-' . date('Ymd-His');

            // Create stock movement for the transfer
            $movement = StockMovement::create([
                'system_or_device_id' => uniqid('PROD-FIN-'),
                'item_code' => $product->item_code,
                'item_designation' => $product->item_designation,
                'item_quantity' => $request->finished_quantity,
                'item_measurement_unit' => $product->item_measurement_unit,
                'item_purchase_or_sale_price' => $rawStock->unit_price,
                'item_purchase_or_sale_currency' => $rawStock->currency,
                'item_movement_type' => 'PF',  // Production Finished
                'is_production' => true,
                'item_movement_invoice_ref' => $movementCode,
                'item_movement_description' => 'Transfert de matière première vers produit fini' . ($request->notes ? ' - ' . $request->notes : ''),
                'item_movement_date' => now(),
                'obr_submission_status' => 'PENDING',
                'company_id' => Auth::user()->company_id,
                'product_id' => $request->product_id,
                'warehouse_id' => $prodWarehouse->id,
                'created_by' => Auth::id(),
                'user_id' => Auth::id(),
            ]);

            // Deduct from RAW stock
            $rawStock->quantity -= $request->finished_quantity;
            $rawStock->last_stock_movement_id = $movement->id;
            $rawStock->user_id = Auth::id();
            $rawStock->save();

            // Get or create FINISHED stock for the same product
            $finishedStock = WarehouseProduct::where('warehouse_id', $prodWarehouse->id)
                ->where('product_id', $request->product_id)
                ->where('production_status', 'FINISHED')
                ->first();

            if ($finishedStock) {
                // Add to existing FINISHED stock
                $finishedStock->quantity += $request->finished_quantity;
                $finishedStock->last_stock_movement_id = $movement->id;
                $finishedStock->user_id = Auth::id();
                $finishedStock->save();
            } else {
                // Create new FINISHED stock entry
                WarehouseProduct::create([
                    'warehouse_id' => $prodWarehouse->id,
                    'product_id' => $request->product_id,
                    'quantity' => $request->finished_quantity,
                    'unit_price' => $rawStock->unit_price,
                    'currency' => $rawStock->currency,
                    'production_status' => 'FINISHED',
                    'last_stock_movement_id' => $movement->id,
                    'company_id' => Auth::user()->company_id,
                    'user_id' => Auth::id(),
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Produit marqué comme fini avec succès',
                'movement_code' => $movementCode,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function markAsRaw(Request $request)
    {
        $request->validate([
            'warehouse_product_id' => 'required|exists:warehouse_products,id',
            'product_id' => 'required|exists:products,id',
            'raw_quantity' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            // Get the production warehouse
            $prodWarehouse = Warehouse::where('is_production', true)
                ->where('company_id', Auth::user()->company_id)
                ->firstOrFail();

            // Get the FINISHED product stock
            $finishedStock = WarehouseProduct::findOrFail($request->warehouse_product_id);

            // Verify it's in FINISHED status
            if ($finishedStock->production_status !== 'FINISHED') {
                throw new \Exception('Le produit doit être en statut FINISHED (produit fini)');
            }

            // Verify quantity
            if ($finishedStock->quantity < $request->raw_quantity) {
                throw new \Exception('Quantité insuffisante. Stock disponible: ' . $finishedStock->quantity);
            }

            $product = Product::findOrFail($request->product_id);
            $movementCode = 'PROD-RAW-' . date('Ymd-His');

            // Create stock movement for the return
            $movement = StockMovement::create([
                'system_or_device_id' => uniqid('PROD-RAW-'),
                'item_code' => $product->item_code,
                'item_designation' => $product->item_designation,
                'item_quantity' => $request->raw_quantity,
                'item_measurement_unit' => $product->item_measurement_unit,
                'item_purchase_or_sale_price' => $finishedStock->unit_price,
                'item_purchase_or_sale_currency' => $finishedStock->currency,
                'item_movement_type' => 'PR',  // Production Return to Raw
                'is_production' => true,
                'item_movement_invoice_ref' => $movementCode,
                'item_movement_description' => 'Retour de produit fini vers matière première' . ($request->notes ? ' - ' . $request->notes : ''),
                'item_movement_date' => now(),
                'obr_submission_status' => 'PENDING',
                'company_id' => Auth::user()->company_id,
                'product_id' => $request->product_id,
                'warehouse_id' => $prodWarehouse->id,
                'created_by' => Auth::id(),
                'user_id' => Auth::id(),
            ]);

            // Deduct from FINISHED stock
            $finishedStock->quantity -= $request->raw_quantity;
            $finishedStock->last_stock_movement_id = $movement->id;
            $finishedStock->user_id = Auth::id();
            $finishedStock->save();

            // Get or create RAW stock for the same product
            $rawStock = WarehouseProduct::where('warehouse_id', $prodWarehouse->id)
                ->where('product_id', $request->product_id)
                ->where('production_status', 'RAW')
                ->first();

            if ($rawStock) {
                // Add to existing RAW stock
                $rawStock->quantity += $request->raw_quantity;
                $rawStock->last_stock_movement_id = $movement->id;
                $rawStock->user_id = Auth::id();
                $rawStock->save();
            } else {
                // Create new RAW stock entry
                WarehouseProduct::create([
                    'warehouse_id' => $prodWarehouse->id,
                    'product_id' => $request->product_id,
                    'quantity' => $request->raw_quantity,
                    'unit_price' => $finishedStock->unit_price,
                    'currency' => $finishedStock->currency,
                    'production_status' => 'RAW',
                    'last_stock_movement_id' => $movement->id,
                    'company_id' => Auth::user()->company_id,
                    'user_id' => Auth::id(),
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Produit retourné en matière première avec succès',
                'movement_code' => $movementCode,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function quickEntry(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|numeric|min:0.01',
            'unit_price' => 'required|numeric|min:0',
            'currency' => 'required|string',
            'movement_type' => 'required|in:EN,ER,EI,EAJ',
        ]);

        try {
            DB::beginTransaction();

            $prodWarehouse = Warehouse::where('is_production', true)
                ->where('company_id', Auth::user()->company_id)
                ->firstOrFail();

            $product = Product::findOrFail($request->product_id);

            // Si c'est une entrée par retour, vérifier la quantité en produits finis
            if ($request->movement_type === 'ER') {
                $finishedStock = WarehouseProduct::where('warehouse_id', $prodWarehouse->id)
                    ->where('product_id', $request->product_id)
                    ->where('production_status', 'FINISHED')
                    ->first();

                if (!$finishedStock || $finishedStock->quantity < $request->quantity) {
                    throw new \Exception('Quantité insuffisante en produits finis. Stock disponible: ' . ($finishedStock?->quantity ?? 0));
                }

                // Déduire de la quantité des produits finis
                $finishedStock->quantity -= $request->quantity;
                $finishedStock->user_id = Auth::id();

                // Si la quantité devient 0, supprimer l'entrée
                if ($finishedStock->quantity <= 0) {
                    $finishedStock->delete();
                } else {
                    $finishedStock->save();
                }
            }

            $movement = StockMovement::create([
                'system_or_device_id' => uniqid('ENTRY-'),
                'item_code' => $product->item_code,
                'item_designation' => $product->item_designation,
                'item_quantity' => $request->quantity,
                'item_measurement_unit' => $product->item_measurement_unit,
                'item_purchase_or_sale_price' => $request->unit_price,
                'item_purchase_or_sale_currency' => $request->currency,
                'item_movement_type' => $request->movement_type,
                'is_production' => true,
                'item_movement_description' => $request->movement_type === 'ER' ? 'Entrée par retour des produits finis' : 'Entrée matière première',
                'item_movement_date' => now(),
                'obr_submission_status' => 'PENDING',
                'company_id' => Auth::user()->company_id,
                'product_id' => $request->product_id,
                'warehouse_id' => $prodWarehouse->id,
                'created_by' => Auth::id(),
                'user_id' => Auth::id(),
            ]);

            // Ajouter au stock RAW
            $stock = WarehouseProduct::firstOrNew([
                'warehouse_id' => $prodWarehouse->id,
                'product_id' => $request->product_id,
                'production_status' => 'RAW',
            ]);

            $stock->quantity = ($stock->quantity ?? 0) + $request->quantity;
            $stock->unit_price = $request->unit_price;
            $stock->currency = $request->currency;
            $stock->production_status = 'RAW';
            $stock->last_stock_movement_id = $movement->id;
            $stock->user_id = Auth::id();
            $stock->save();

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Entrée enregistrée avec succès']);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function quickExit(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|numeric|min:0.01',
            'movement_type' => 'required|in:SC,SP,SD,SAJ',
        ]);

        try {
            DB::beginTransaction();

            $prodWarehouse = Warehouse::where('is_production', true)
                ->where('company_id', Auth::user()->company_id)
                ->firstOrFail();

            $stock = WarehouseProduct::where('warehouse_id', $prodWarehouse->id)
                ->where('product_id', $request->product_id)
                ->first();

            if (! $stock || $stock->quantity < $request->quantity) {
                throw new \Exception('Stock insuffisant');
            }

            $product = Product::findOrFail($request->product_id);

            $movement = StockMovement::create([
                'system_or_device_id' => uniqid('EXIT-'),
                'item_code' => $product->item_code,
                'item_designation' => $product->item_designation,
                'item_quantity' => $request->quantity,
                'item_measurement_unit' => $product->item_measurement_unit,
                'item_purchase_or_sale_price' => $stock->unit_price,
                'item_purchase_or_sale_currency' => $stock->currency,
                'item_movement_type' => $request->movement_type,
                'is_production' => true,
                'item_movement_description' => 'Consommation matière première',
                'item_movement_date' => now(),
                'obr_submission_status' => 'PENDING',
                'company_id' => Auth::user()->company_id,
                'product_id' => $request->product_id,
                'warehouse_id' => $prodWarehouse->id,
                'created_by' => Auth::id(),
                'user_id' => Auth::id(),
            ]);

            $stock->quantity -= $request->quantity;
            $stock->last_stock_movement_id = $movement->id;
            $stock->production_status = 'RAW';
            $stock->user_id = Auth::id();
            $stock->save();

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Sortie enregistrée avec succès']);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function finishedProducts()
    {
        $products = Product::select('id', 'item_code', 'item_designation', 'item_measurement_unit', 'product_category_id')
            ->where('is_production', true)
            ->whereHas('categoryProduct', function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'LIKE', '%boulang%')
                        ->orWhere('name', 'LIKE', '%bakery%')
                        ->orWhere('name', 'LIKE', '%Bakery%');
                });
            })
            ->orderBy('item_designation')
            ->get();

        return response()->json(['success' => true, 'data' => $products]);
    }

    public function recordProduction(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.currency' => 'required|string',
            'production_date' => 'required|date',
            'batch_number' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $prodWarehouse = Warehouse::where('is_production', true)
                ->where('company_id', Auth::user()->company_id)
                ->firstOrFail();

            $batchRef = $request->batch_number ?? 'PROD-'.date('Ymd-His');

            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);

                $movement = StockMovement::create([
                    'system_or_device_id' => uniqid('PROD-EN-'),
                    'item_code' => $product->item_code,
                    'item_designation' => $product->item_designation,
                    'item_quantity' => $item['quantity'],
                    'item_measurement_unit' => $product->item_measurement_unit,
                    'item_purchase_or_sale_price' => $item['unit_price'],
                    'item_purchase_or_sale_currency' => $item['currency'],
                    'item_movement_type' => 'EN',
                    'is_production' => true,
                    'item_movement_invoice_ref' => $batchRef,
                    'item_movement_description' => $request->notes ?? 'Production boulangerie',
                    'item_movement_date' => $request->production_date,
                    'obr_submission_status' => 'PENDING',
                    'company_id' => Auth::user()->company_id,
                    'product_id' => $item['product_id'],
                    'warehouse_id' => $prodWarehouse->id,
                    'created_by' => Auth::id(),
                    'user_id' => Auth::id(),
                ]);

                $stock = WarehouseProduct::firstOrNew([
                    'warehouse_id' => $prodWarehouse->id,
                    'product_id' => $item['product_id'],
                ]);

                $stock->quantity = ($stock->quantity ?? 0) + $item['quantity'];
                $stock->unit_price = $item['unit_price'];
                $stock->currency = $item['currency'];
                $stock->production_status = 'FINISHED';
                $stock->last_stock_movement_id = $movement->id;
                $stock->user_id = Auth::id();
                $stock->save();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Production enregistrée avec succès',
                'batch_number' => $batchRef,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function transferData()
    {
        $prodWarehouse = Warehouse::where('is_production', true)
            ->where('company_id', Auth::user()->company_id)
            ->first();

        if (! $prodWarehouse) {
            return response()->json(['success' => false, 'message' => 'Aucun entrepôt de production'], 404);
        }

        $salesWarehouses = Warehouse::where('is_production', false)
            ->where('company_id', Auth::user()->company_id)
            ->select('id', 'name', 'location')
            ->get();

        $finishedStock = WarehouseProduct::with('product:id,item_code,item_designation,item_measurement_unit')
            ->where('warehouse_id', $prodWarehouse->id)
            ->where('quantity', '>', 0)
            ->where('production_status', 'FINISHED')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'sales_warehouses' => $salesWarehouses,
                'finished_stock' => $finishedStock,
            ],
        ]);
    }

    public function quickTransfer(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'destination_warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $prodWarehouse = Warehouse::where('is_production', true)
                ->where('company_id', Auth::user()->company_id)
                ->firstOrFail();

            $salesWarehouse = Warehouse::findOrFail($request->destination_warehouse_id);

            // Récupérer le stock avec verrous pour éviter les conditions de concurrence
            $stock = WarehouseProduct::where('warehouse_id', $prodWarehouse->id)
                ->where('product_id', $request->product_id)
                ->where('production_status', 'FINISHED')  // Doit être en statut FINISHED
                ->lockForUpdate()
                ->first();

            // Vérifier que le stock existe
            if (!$stock) {
                $product = Product::findOrFail($request->product_id);
                throw new \Exception("Produit '{$product->item_designation}' non trouvé en tant que produit fini ou n'existe pas.");
            }

            // Convertir et vérifier la quantité
            $quantity = floatval($request->quantity);
            $stockQuantity = floatval($stock->quantity);
            
            if ($stockQuantity <= 0) {
                throw new \Exception("Le stock du produit '{$stock->product->item_designation}' est vide.");
            }

            // Vérifier que la quantité demandée ne dépasse pas le stock
            if ($quantity > $stockQuantity) {
                throw new \Exception(
                    "Stock insuffisant pour '{$stock->product->item_designation}'. "
                    . "Demandé: {$quantity} {$stock->product->item_measurement_unit}, "
                    . "Disponible: {$stockQuantity} {$stock->product->item_measurement_unit}"
                );
            }

            $product = Product::findOrFail($request->product_id);
            $transferCode = 'TRF-'.date('Ymd-His');

            $transfer = WarehouseTransfer::create([
                'transfer_code' => $transferCode,
                'source_warehouse_id' => $prodWarehouse->id,
                'destination_warehouse_id' => $salesWarehouse->id,
                'status' => 'PENDING',
                'notes' => $request->notes,
                'created_by' => Auth::id(),
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            $movementOut = StockMovement::create([
                'system_or_device_id' => uniqid('PROD-ST-'),
                'item_code' => $product->item_code,
                'item_designation' => $product->item_designation,
                'item_quantity' => $quantity,
                'item_measurement_unit' => $product->item_measurement_unit,
                'item_purchase_or_sale_price' => $stock->unit_price,
                'item_purchase_or_sale_currency' => $stock->currency,
                'item_movement_type' => 'ST',
                'is_production' => true,
                'item_movement_invoice_ref' => $transferCode,
                'item_movement_description' => "Transfert vers {$salesWarehouse->name}",
                'item_movement_date' => now(),
                'obr_submission_status' => 'PENDING',
                'company_id' => Auth::user()->company_id,
                'product_id' => $request->product_id,
                'warehouse_id' => $prodWarehouse->id,
                'created_by' => Auth::id(),
                'user_id' => Auth::id(),
            ]);

            $movementIn = StockMovement::create([
                'system_or_device_id' => uniqid('SALE-ET-'),
                'item_code' => $product->item_code,
                'item_designation' => $product->item_designation,
                'item_quantity' => $quantity,
                'item_measurement_unit' => $product->item_measurement_unit,
                'item_purchase_or_sale_price' => $stock->unit_price,
                'item_purchase_or_sale_currency' => $stock->currency,
                'item_movement_type' => 'ET',
                'is_production' => false,
                'item_movement_invoice_ref' => $transferCode,
                'item_movement_description' => 'Réception de production',
                'item_movement_date' => now(),
                'obr_submission_status' => 'PENDING',
                'company_id' => Auth::user()->company_id,
                'product_id' => $request->product_id,
                'warehouse_id' => $salesWarehouse->id,
                'created_by' => Auth::id(),
                'user_id' => Auth::id(),
            ]);

            $res = WarehouseTransferItem::create([
                'transfer_id' => $transfer->id,
                'product_id' => $request->product_id,
                'quantity' => $quantity,
                'unit_price' => $stock->unit_price,
                'currency' => $stock->currency,
                'stock_movement_out_id' => $movementOut->id,
                'stock_movement_in_id' => $movementIn->id,
            ]);
            // return $res;
            $stock->quantity -= $quantity;
            $stock->last_stock_movement_id = $movementOut->id;
            $stock->user_id = Auth::id();
            $stock->save();

            $salesStock = WarehouseProduct::firstOrNew([
                'warehouse_id' => $salesWarehouse->id,
                'product_id' => $request->product_id,
            ]);

            $salesStock->quantity = ($salesStock->quantity ?? 0) + $quantity;
            $salesStock->unit_price = $stock->unit_price;
            $salesStock->currency = $stock->currency;
            $salesStock->production_status = 'RAW';
            $salesStock->last_stock_movement_id = $movementIn->id;
            $salesStock->user_id = Auth::id();
            $salesStock->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transfert effectué avec succès',
                'transfer_code' => $transferCode,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function transferToSales(Request $request)
    {
        $request->validate([
            'destination_warehouse_id' => 'required|exists:warehouses,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'transfer_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $prodWarehouse = Warehouse::where('is_production', true)
                ->where('company_id', Auth::user()->company_id)
                ->firstOrFail();

            $salesWarehouse = Warehouse::findOrFail($request->destination_warehouse_id);

            // Valider tous les stocks avec verrous AVANT de créer le transfert
            $stocksToTransfer = [];
            foreach ($request->items as $item) {
                $quantity = floatval($item['quantity']);
                
                $stock = WarehouseProduct::where('warehouse_id', $prodWarehouse->id)
                    ->where('product_id', $item['product_id'])
                    ->where('production_status', 'FINISHED')  // Vérifier le statut
                    ->lockForUpdate()
                    ->first();

                if (!$stock) {
                    $product = Product::findOrFail($item['product_id']);
                    throw new \Exception("Produit '{$product->item_designation}' non trouvé en tant que produit fini.");
                }

                $stockQuantity = floatval($stock->quantity);
                if ($stockQuantity <= 0) {
                    throw new \Exception("Le stock du produit '{$stock->product->item_designation}' est vide.");
                }

                if ($quantity > $stockQuantity) {
                    throw new \Exception(
                        "Stock insuffisant pour '{$stock->product->item_designation}'. "
                        . "Demandé: {$quantity} {$stock->product->item_measurement_unit}, "
                        . "Disponible: {$stockQuantity} {$stock->product->item_measurement_unit}"
                    );
                }

                $stocksToTransfer[] = [
                    'stock' => $stock,
                    'product' => $stock->product,
                    'quantity' => $quantity,
                ];
            }

            $transferCode = 'TRF-'.date('Ymd-His');
            $transfer = WarehouseTransfer::create([
                'transfer_code' => $transferCode,
                'source_warehouse_id' => $prodWarehouse->id,
                'destination_warehouse_id' => $salesWarehouse->id,
                'status' => 'PENDING',
                'notes' => $request->notes,
                'created_by' => Auth::id(),
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            // Traiter les transferts avec les stocks déjà validés et verrouillés
            foreach ($stocksToTransfer as $transferData) {
                $stock = $transferData['stock'];
                $product = $transferData['product'];
                $quantity = $transferData['quantity'];

                $movementOut = StockMovement::create([
                    'system_or_device_id' => uniqid('PROD-ST-'),
                    'item_code' => $product->item_code,
                    'item_designation' => $product->item_designation,
                    'item_quantity' => $quantity,
                    'item_measurement_unit' => $product->item_measurement_unit,
                    'item_purchase_or_sale_price' => $stock->unit_price,
                    'item_purchase_or_sale_currency' => $stock->currency,
                    'item_movement_type' => 'ST',
                    'is_production' => true,
                    'item_movement_invoice_ref' => $transferCode,
                    'item_movement_description' => "Transfert vers {$salesWarehouse->name}",
                    'item_movement_date' => $request->transfer_date,
                    'obr_submission_status' => 'PENDING',
                    'company_id' => Auth::user()->company_id,
                    'product_id' => $item['product_id'],
                    'warehouse_id' => $prodWarehouse->id,
                    'created_by' => Auth::id(),
                    'user_id' => Auth::id(),
                ]);

                $movementIn = StockMovement::create([
                    'system_or_device_id' => uniqid('SALE-ET-'),
                    'item_code' => $product->item_code,
                    'item_designation' => $product->item_designation,
                    'item_quantity' => $quantity,
                    'item_measurement_unit' => $product->item_measurement_unit,
                    'item_purchase_or_sale_price' => $stock->unit_price,
                    'item_purchase_or_sale_currency' => $stock->currency,
                    'item_movement_type' => 'ET',
                    'is_production' => false,
                    'item_movement_invoice_ref' => $transferCode,
                    'item_movement_description' => 'Réception de production',
                    'item_movement_date' => $request->transfer_date,
                    'obr_submission_status' => 'PENDING',
                    'company_id' => Auth::user()->company_id,
                    'product_id' => $product->id,
                    'warehouse_id' => $salesWarehouse->id,
                    'created_by' => Auth::id(),
                    'user_id' => Auth::id(),
                ]);

                WarehouseTransferItem::create([
                    'transfer_id' => $transfer->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $stock->unit_price,
                    'currency' => $stock->currency,
                    'stock_movement_out_id' => $movementOut->id,
                    'stock_movement_in_id' => $movementIn->id,
                ]);

                $stock->quantity -= $quantity;
                $stock->last_stock_movement_id = $movementOut->id;
                $stock->user_id = Auth::id();
                $stock->save();

                $salesStock = WarehouseProduct::firstOrNew([
                    'warehouse_id' => $salesWarehouse->id,
                    'product_id' => $product->id,
                ]);

                $salesStock->quantity = ($salesStock->quantity ?? 0) + $quantity;
                $salesStock->unit_price = $stock->unit_price;
                $salesStock->currency = $stock->currency;
                $salesStock->production_status = 'RAW';
                $salesStock->last_stock_movement_id = $movementIn->id;
                $salesStock->user_id = Auth::id();
                $salesStock->save();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transfert effectué avec succès',
                'transfer_code' => $transferCode,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Rapport de production par période : résumé, par produit, et par jour.
     */
    public function productionReport(Request $request)
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        $prodWarehouse = Warehouse::where('is_production', true)
            ->where('company_id', Auth::user()->company_id)
            ->first();

        if (! $prodWarehouse) {
            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => ['total_produced' => 0, 'total_transferred' => 0],
                    'by_product' => [],
                    'by_day' => [],
                ],
            ]);
        }

        $dateFrom = $request->date_from;
        $dateTo = $request->date_to;

        $produced = StockMovement::where('warehouse_id', $prodWarehouse->id)
            ->where('is_production', true)
            ->whereIn('item_movement_type', ['EN', 'ER', 'EI', 'EAJ'])
            ->whereDate('item_movement_date', '>=', $dateFrom)
            ->whereDate('item_movement_date', '<=', $dateTo)
            ->selectRaw('
                product_id,
                item_code,
                item_designation,
                item_measurement_unit,
                item_purchase_or_sale_currency as currency,
                SUM(item_quantity) as total_produced,
                AVG(item_purchase_or_sale_price) as avg_price
            ')
            ->groupBy('product_id', 'item_code', 'item_designation', 'item_measurement_unit', 'currency')
            ->get();

        $transferred = StockMovement::where('warehouse_id', $prodWarehouse->id)
            ->where('is_production', true)
            ->where('item_movement_type', 'ST')
            ->whereDate('item_movement_date', '>=', $dateFrom)
            ->whereDate('item_movement_date', '<=', $dateTo)
            ->selectRaw('product_id, SUM(item_quantity) as total_transferred')
            ->groupBy('product_id')
            ->pluck('total_transferred', 'product_id');

        $byProduct = $produced->map(function ($item) use ($transferred) {
            return [
                'product_id' => $item->product_id,
                'item_code' => $item->item_code,
                'item_designation' => $item->item_designation,
                'item_measurement_unit' => $item->item_measurement_unit,
                'currency' => $item->currency ?? 'BIF',
                'total_produced' => round($item->total_produced, 2),
                'total_transferred' => round($transferred[$item->product_id] ?? 0, 2),
                'avg_price' => round($item->avg_price, 2),
            ];
        });

        $dailyProduced = StockMovement::where('warehouse_id', $prodWarehouse->id)
            ->where('is_production', true)
            ->whereIn('item_movement_type', ['EN', 'ER', 'EI', 'EAJ'])
            ->whereDate('item_movement_date', '>=', $dateFrom)
            ->whereDate('item_movement_date', '<=', $dateTo)
            ->selectRaw('DATE(item_movement_date) as date, SUM(item_quantity) as produced')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('produced', 'date');

        $dailyTransferred = StockMovement::where('warehouse_id', $prodWarehouse->id)
            ->where('is_production', true)
            ->where('item_movement_type', 'ST')
            ->whereDate('item_movement_date', '>=', $dateFrom)
            ->whereDate('item_movement_date', '<=', $dateTo)
            ->selectRaw('DATE(item_movement_date) as date, SUM(item_quantity) as transferred')
            ->groupBy('date')
            ->pluck('transferred', 'date');

        $allDates = $dailyProduced->keys()->merge($dailyTransferred->keys())->unique()->sort();

        $byDay = $allDates->map(fn (string $date) => [
            'date' => $date,
            'produced' => round($dailyProduced[$date] ?? 0, 2),
            'transferred' => round($dailyTransferred[$date] ?? 0, 2),
        ])->values();

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_produced' => round($byProduct->sum('total_produced'), 2),
                    'total_transferred' => round($byProduct->sum('total_transferred'), 2),
                ],
                'by_product' => $byProduct->values(),
                'by_day' => $byDay,
            ],
        ]);
    }

    public function productionHistory(Request $request)
    {
        $prodWarehouse = Warehouse::where('is_production', true)
            ->where('company_id', Auth::user()->company_id)
            ->first();

        if (! $prodWarehouse) {
            return response()->json(['success' => true, 'data' => ['data' => [], 'total' => 0]]);
        }

        $query = StockMovement::where('warehouse_id', $prodWarehouse->id)
            ->where('is_production', true);

        if ($request->date_from) {
            $query->whereDate('item_movement_date', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->whereDate('item_movement_date', '<=', $request->date_to);
        }

        if ($request->movement_type) {
            $query->where('item_movement_type', $request->movement_type);
        }

        $movements = $query->orderBy('item_movement_date', 'desc')->paginate(50);

        return response()->json(['success' => true, 'data' => $movements]);
    }
}
