<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{StockMovement, WarehouseProduct, Warehouse, Product, WarehouseTransfer, WarehouseTransferItem};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Auth};

class BakeryProductionController extends Controller
{
    public function dashboard()
    {
        $prodWarehouse = Warehouse::where('is_production', true)
            ->where('company_id', Auth::user()->company_id)
            ->first();

        if (!$prodWarehouse) {
            return response()->json(['success' => false, 'message' => 'Aucun entrepôt de production'], 404);
        }

        $prodStock = WarehouseProduct::with('product:id,item_code,item_designation,item_measurement_unit')
            ->where('warehouse_id', $prodWarehouse->id)
            ->where('quantity', '>', 0)
            ->get();

        $salesWarehouses = Warehouse::where('is_production', false)
            ->where('company_id', Auth::user()->company_id)
            ->select('id', 'name', 'location')
            ->get();

        $todayProduction = StockMovement::where('warehouse_id', $prodWarehouse->id)
            ->where('is_production', true)
            ->whereDate('item_movement_date', today())
            ->where('item_movement_type', 'EN')
            ->sum('item_quantity');

        $todayTransfers = WarehouseTransfer::where('source_warehouse_id', $prodWarehouse->id)
            ->whereDate('created_at', today())
            ->where('status', 'APPROVED')
            ->withSum('items', 'quantity')
            ->get()
            ->sum('items_sum_quantity');

        return response()->json([
            'success' => true,
            'data' => [
                'production_warehouse' => $prodWarehouse,
                'production_stock' => $prodStock,
                'sales_warehouses' => $salesWarehouses,
                'today_production' => $todayProduction,
                'today_transfers' => $todayTransfers
            ]
        ]);
    }

    public function changeStatus(Request $request)
    {
        $request->validate([
            'warehouse_product_id' => 'required|exists:warehouse_products,id',
            'status' => 'required|in:RAW,FINISHED'
        ]);

        try {
            $stock = WarehouseProduct::findOrFail($request->warehouse_product_id);
            $stock->production_status = $request->status;
            $stock->save();

            return response()->json([
                'success' => true,
                'message' => 'Statut modifié avec succès'
            ]);
        } catch (\Exception $e) {
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
            'movement_type' => 'required|in:EN,ER,EI,EAJ'
        ]);

        try {
            DB::beginTransaction();

            $prodWarehouse = Warehouse::where('is_production', true)
                ->where('company_id', Auth::user()->company_id)
                ->firstOrFail();

            $product = Product::findOrFail($request->product_id);

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
                'item_movement_description' => 'Entrée matière première',
                'item_movement_date' => now(),
                'obr_submission_status' => 'PENDING',
                'company_id' => Auth::user()->company_id,
                'product_id' => $request->product_id,
                'warehouse_id' => $prodWarehouse->id,
                'created_by' => Auth::id(),
                'user_id' => Auth::id()
            ]);

            $stock = WarehouseProduct::firstOrNew([
                'warehouse_id' => $prodWarehouse->id,
                'product_id' => $request->product_id
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
            'movement_type' => 'required|in:SC,SP,SD,SAJ'
        ]);

        try {
            DB::beginTransaction();

            $prodWarehouse = Warehouse::where('is_production', true)
                ->where('company_id', Auth::user()->company_id)
                ->firstOrFail();

            $stock = WarehouseProduct::where('warehouse_id', $prodWarehouse->id)
                ->where('product_id', $request->product_id)
                ->first();

            if (!$stock || $stock->quantity < $request->quantity) {
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
                'user_id' => Auth::id()
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
        $products = Product::select('id', 'item_code', 'item_designation', 'item_measurement_unit')
            ->where('is_production',true)
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
            'notes' => 'nullable|string'
        ]);

        try {
            DB::beginTransaction();

            $prodWarehouse = Warehouse::where('is_production', true)
                ->where('company_id', Auth::user()->company_id)
                ->firstOrFail();

            $batchRef = $request->batch_number ?? 'PROD-' . date('Ymd-His');

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
                    'user_id' => Auth::id()
                ]);

                $stock = WarehouseProduct::firstOrNew([
                    'warehouse_id' => $prodWarehouse->id,
                    'product_id' => $item['product_id']
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
                'batch_number' => $batchRef
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

        if (!$prodWarehouse) {
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
                'finished_stock' => $finishedStock
            ]
        ]);
    }

    public function quickTransfer(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'destination_warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string'
        ]);

        try {
            DB::beginTransaction();

            $prodWarehouse = Warehouse::where('is_production', true)
                ->where('company_id', Auth::user()->company_id)
                ->firstOrFail();

            $salesWarehouse = Warehouse::findOrFail($request->destination_warehouse_id);

            $stock = WarehouseProduct::where('warehouse_id', $prodWarehouse->id)
                ->where('product_id', $request->product_id)
                ->first();

            if (!$stock || $stock->quantity < $request->quantity) {
                throw new \Exception('Stock insuffisant');
            }

            $product = Product::findOrFail($request->product_id);
            $transferCode = 'TRF-' . date('Ymd-His');

            $transfer = WarehouseTransfer::create([
                'transfer_code' => $transferCode,
                'source_warehouse_id' => $prodWarehouse->id,
                'destination_warehouse_id' => $salesWarehouse->id,
                'status' => 'PENDING',
                'notes' => $request->notes,
                'created_by' => Auth::id(),
                'approved_by' => Auth::id(),
                'approved_at' => now()
            ]);

            $movementOut = StockMovement::create([
                'system_or_device_id' => uniqid('PROD-ST-'),
                'item_code' => $product->item_code,
                'item_designation' => $product->item_designation,
                'item_quantity' => $request->quantity,
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
                'user_id' => Auth::id()
            ]);

            $movementIn = StockMovement::create([
                'system_or_device_id' => uniqid('SALE-ET-'),
                'item_code' => $product->item_code,
                'item_designation' => $product->item_designation,
                'item_quantity' => $request->quantity,
                'item_measurement_unit' => $product->item_measurement_unit,
                'item_purchase_or_sale_price' => $stock->unit_price,
                'item_purchase_or_sale_currency' => $stock->currency,
                'item_movement_type' => 'ET',
                'is_production' => false,
                'item_movement_invoice_ref' => $transferCode,
                'item_movement_description' => "Réception de production",
                'item_movement_date' => now(),
                'obr_submission_status' => 'PENDING',
                'company_id' => Auth::user()->company_id,
                'product_id' => $request->product_id,
                'warehouse_id' => $salesWarehouse->id,
                'created_by' => Auth::id(),
                'user_id' => Auth::id()
            ]);


            $res = WarehouseTransferItem::create([
                'transfer_id' => $transfer->id,
                'product_id' => $request->product_id,
                'quantity' => $request->quantity,
                'unit_price' => $stock->unit_price,
                'currency' => $stock->currency,
                'stock_movement_out_id' => $movementOut->id,
                'stock_movement_in_id' => $movementIn->id
            ]);
            // return $res;
            $stock->quantity -= $request->quantity;
            $stock->last_stock_movement_id = $movementOut->id;
            $stock->user_id = Auth::id();
            $stock->save();

            $salesStock = WarehouseProduct::firstOrNew([
                'warehouse_id' => $salesWarehouse->id,
                'product_id' => $request->product_id
            ]);

            $salesStock->quantity = ($salesStock->quantity ?? 0) + $request->quantity;
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
                'transfer_code' => $transferCode
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
            'notes' => 'nullable|string'
        ]);

        try {
            DB::beginTransaction();

            $prodWarehouse = Warehouse::where('is_production', true)
                ->where('company_id', Auth::user()->company_id)
                ->firstOrFail();

            $salesWarehouse = Warehouse::findOrFail($request->destination_warehouse_id);

            foreach ($request->items as $item) {
                $stock = WarehouseProduct::where('warehouse_id', $prodWarehouse->id)
                    ->where('product_id', $item['product_id'])
                    ->first();

                if (!$stock || $stock->quantity < $item['quantity']) {
                    $product = Product::findOrFail($item['product_id']);
                    throw new \Exception("Stock insuffisant pour {$product->item_designation}");
                }
            }

            $transferCode = 'TRF-' . date('Ymd-His');
            $transfer = WarehouseTransfer::create([
                'transfer_code' => $transferCode,
                'source_warehouse_id' => $prodWarehouse->id,
                'destination_warehouse_id' => $salesWarehouse->id,
                'status' => 'PENDING',
                'notes' => $request->notes,
                'created_by' => Auth::id(),
                'approved_by' => Auth::id(),
                'approved_at' => now()
            ]);

            foreach ($request->items as $item) {
                $stock = WarehouseProduct::where('warehouse_id', $prodWarehouse->id)
                    ->where('product_id', $item['product_id'])
                    ->first();

                $product = Product::findOrFail($item['product_id']);

                $movementOut = StockMovement::create([
                    'system_or_device_id' => uniqid('PROD-ST-'),
                    'item_code' => $product->item_code,
                    'item_designation' => $product->item_designation,
                    'item_quantity' => $item['quantity'],
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
                    'user_id' => Auth::id()
                ]);

                $movementIn = StockMovement::create([
                    'system_or_device_id' => uniqid('SALE-ET-'),
                    'item_code' => $product->item_code,
                    'item_designation' => $product->item_designation,
                    'item_quantity' => $item['quantity'],
                    'item_measurement_unit' => $product->item_measurement_unit,
                    'item_purchase_or_sale_price' => $stock->unit_price,
                    'item_purchase_or_sale_currency' => $stock->currency,
                    'item_movement_type' => 'ET',
                    'is_production' => false,
                    'item_movement_invoice_ref' => $transferCode,
                    'item_movement_description' => "Réception de production",
                    'item_movement_date' => $request->transfer_date,
                    'obr_submission_status' => 'PENDING',
                    'company_id' => Auth::user()->company_id,
                    'product_id' => $item['product_id'],
                    'warehouse_id' => $salesWarehouse->id,
                    'created_by' => Auth::id(),
                    'user_id' => Auth::id()
                ]);

                WarehouseTransferItem::create([
                    'transfer_id' => $transfer->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $stock->unit_price,
                    'currency' => $stock->currency,
                    'stock_movement_out_id' => $movementOut->id,
                    'stock_movement_in_id' => $movementIn->id
                ]);

                $stock->quantity -= $item['quantity'];
                $stock->last_stock_movement_id = $movementOut->id;
                $stock->user_id = Auth::id();
                $stock->save();

                $salesStock = WarehouseProduct::firstOrNew([
                    'warehouse_id' => $salesWarehouse->id,
                    'product_id' => $item['product_id']
                ]);

                $salesStock->quantity = ($salesStock->quantity ?? 0) + $item['quantity'];
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
                'transfer_code' => $transferCode
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function productionHistory(Request $request)
    {
        $prodWarehouse = Warehouse::where('is_production', true)
            ->where('company_id', Auth::user()->company_id)
            ->first();



        if (!$prodWarehouse) {
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
