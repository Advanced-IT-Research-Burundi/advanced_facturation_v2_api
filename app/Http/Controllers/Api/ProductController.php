<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Resources\WarehouseProductResource;
use App\Models\AppConfig;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\WarehouseProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Picqer\Barcode\BarcodeGeneratorSVG;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Models\MouvementStockImportation;
use Illuminate\Support\Facades\DB;
use Exception;

class ProductController extends Controller
{

    public function productstMovements(Request $request)
    {
        $request->validate([
        'item_code' => 'required',
        'item_designation' => 'required|string',
        'item_quantity' => 'required|numeric',
        'item_measurement_unit' => 'required|string',
        'item_cost_price' => 'required|numeric',
        'item_cost_price_currency' => 'required|string',
        'item_movement_type' => 'required|string',
        'item_movement_date' => 'required|date_format:Y-m-d H:i:s',
        'reference_dmc' => 'required|string',
        'rubrique_tarifaire' => 'required|string',
        'nombre_par_paquet' => 'required|numeric',
        'description_paquet' => 'required|string',
    ]); 
    // Product for the current Id
    // Augmenter le stock 
    //"product_id": 26

    try{
        DB::beginTransaction();
    
    $product = WarehouseProduct::where("warehouse_id", 1)
    ->where('product_id', $request->product_id)->firstOrFail();

    $product->quantity = $product->quantity + $request->item_quantity;
    $product->save();

    $mouvement = MouvementStockImportation::create([
        'warehouse_id' => $product->warehouse_id,
        'product_id' => $product->product_id,
        'reference_dmc' => $request->reference_dmc,
        'rubrique_tarifaire' => $request->rubrique_tarifaire,
        'nombre_par_paquet' => $request->nombre_par_paquet,
        'description_paquet' => $request->description_paquet,
        'system_or_device_id' => AppConfig::getConfigKey('OBR_USERNAME'),
        'item_code' => $request->item_code,
        'item_designation' => $request->item_designation,
        'item_quantity' => $request->item_quantity,
        'item_measurement_unit' => $request->item_measurement_unit,
        'item_cost_price' => $request->item_cost_price,
        'item_cost_price_currency' => $request->item_cost_price_currency,
        'item_movement_type' => $request->item_movement_type,
        'item_movement_invoice_ref' => $request->item_movement_invoice_ref,
        'item_movement_description' => $request->item_movement_description,
        'item_movement_date' => $request->item_movement_date,
        'item_product_name' => $request->description_article,
        
        'is_sent_to_obr' => 0,
        'obr_status' => '',
        'obr_message' => '',
    ]);

    StockMovement::create([
        'system_or_device_id' => AppConfig::getConfigKey('OBR_USERNAME'),
        'item_code' => $request->item_code,
        'item_designation' => $request->item_designation,
        'item_quantity' => $request->item_quantity,
        'stock_movement_importation_id' => $mouvement->id,
        'item_measurement_unit' => $request->item_measurement_unit,
        'item_purchase_or_sale_price' => $request->item_cost_price,
        'item_purchase_or_sale_currency' => $request->item_cost_price_currency,
        'item_movement_type' => $request->item_movement_type,
        'item_cost_price' => $request->item_cost_price,
        'item_cost_price_currency' => $request->item_cost_price_currency,
        'is_production' => 1,
        'item_movement_invoice_ref' => $request->item_movement_invoice_ref,
        'item_movement_description' => $request->item_movement_description,
        'item_movement_date' => $request->item_movement_date,
        'obr_submission_status' => 'PENDING', 
        'user_id' => auth()->user()->id,
        'created_by' => auth()->user()->id,
        'obr_sent_at' => null,
        'company_id' => $product->company_id,
        'product_id' => $product->product_id,
        'warehouse_id' => $product->warehouse_id,
    ]);
    
    DB::commit();

        return response()->json([
                'success' => true,
                'message' => 'Stock added successfully',
            ], Response::HTTP_OK);     
        }catch(Exception $e){
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    


  

    }
    
    public function search(Request $request)
    {
        $search = $request->search;
        $products = Product::select('id', 'item_designation', 'item_measurement_unit')->where('item_designation', 'like', "%{$search}%")->limit(10)->get();

        return response()->json([
            'success' => true,
            'data' => $products,
        ], Response::HTTP_OK);
    }

    /**
     * Display a listing of products.
     */
    public function posProducts(Request $request)
    {
        $perPage = max(1, min((int) $request->input('per_page', 50), 100));
        $stock_id = $request->stock_id ?? auth()->user()->warehouses?->first()?->id;
        $search = $request->search;

        $query = WarehouseProduct::with(['warehouse', 'product.categoryProduct'])
            ->where('warehouse_id', $stock_id);

        // Filtrer par recherche sur les produits
        if ($search) {
            $fieldsSearch = ['item_code', 'item_designation', 'barcode', 'code_product', 'marque'];
            $query->whereHas('product', function ($q) use ($search, $fieldsSearch) {
                $q->where(function ($subQuery) use ($search, $fieldsSearch) {
                    foreach ($fieldsSearch as $field) {
                        $subQuery->orWhere($field, 'like', "%{$search}%");
                    }
                });
            });
        }

        $warehouseProducts = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => WarehouseProductResource::collection($warehouseProducts),
        ], Response::HTTP_OK);
    }

    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->input('per_page', 15), 100));
        $query = Product::with(['company', 'productUnit', 'categoryProduct', 'user']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('item_code', 'like', "%{$search}%")
                    ->orWhere('item_designation', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        return response()->json([
            'success' => true,
            'data' => $query->latest()->paginate($perPage), // ProductResource::collection()
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created product.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_code' => 'required|string|unique:products|max:255',
            'item_designation' => 'required|string|max:255',
            'item_measurement_unit' => 'sometimes|max:255',
            'barcode' => 'nullable|string|max:255',
            'vat_rate' => 'required|numeric|min:0|max:100',
            // 'company_id' => 'required|exists:companies,id',
            'product_unit_id' => 'nullable|exists:product_units,id',
            'product_category_id' => 'nullable|exists:category_products,id',
            'code_product' => 'nullable|string|max:255',
            'marque' => 'nullable|string|max:255',
            'quantite' => 'nullable|numeric|min:0',
            'quantite_alert' => 'nullable|numeric|min:0',
            'price' => 'nullable|numeric|min:0',
            'price_promo' => 'nullable|numeric|min:0',
            'price_ttc' => 'nullable|numeric|min:0',
            'price_max' => 'nullable|numeric|min:0',
            'price_min' => 'nullable|numeric|min:0',
            'price_tvac' => 'nullable|numeric|min:0',
            'item_ott_tax' => 'nullable|numeric|min:0',
            'item_tsce_tax' => 'nullable|numeric|min:0',
            'date_expiration' => 'nullable|date',
            'image' => 'nullable|string|max:255',
            'type' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_production' => 'nullable|boolean',
        ]);

        $validated['user_id'] = auth()->id();
        try {
            $product = Product::create(attributes: $validated);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du produit',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'success' => true,
            'message' => 'Produit créé avec succès',
            'data' => new ProductResource($product->load(['company', 'productUnit', 'categoryProduct', 'user'])),
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified product.
     */
    public function show(Product $product)
    {
        return response()->json([
            'success' => true,
            'data' => new ProductResource($product->load(['company', 'productUnit', 'categoryProduct', 'user', 'stockMovements', 'warehouseProducts'])),
        ], Response::HTTP_OK);
    }

    /**
     * Update the specified product.
     */
    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'item_code' => 'sometimes|required|string|unique:products,item_code,'.$product->id.'|max:255',
            'item_designation' => 'sometimes|required|string|max:255',
            'item_measurement_unit' => 'sometimes|required|string|max:255',
            'barcode' => 'nullable|string|max:255',
            'vat_rate' => 'sometimes|required|numeric|min:0|max:100',
            // 'company_id' => 'sometimes|required|exists:companies,id',
            'product_unit_id' => 'nullable|exists:product_units,id',
            'product_category_id' => 'nullable|exists:category_products,id',
            'code_product' => 'nullable|string|max:255',
            'marque' => 'nullable|string|max:255',
            'quantite' => 'nullable|numeric|min:0',
            'quantite_alert' => 'nullable|numeric|min:0',
            'price' => 'nullable|numeric|min:0',
            'price_promo' => 'nullable|numeric|min:0',
            'price_ttc' => 'nullable|numeric|min:0',
            'price_max' => 'nullable|numeric|min:0',
            'price_min' => 'nullable|numeric|min:0',
            'price_tvac' => 'nullable|numeric|min:0',
            'item_ott_tax' => 'nullable|numeric|min:0',
            'item_tsce_tax' => 'nullable|numeric|min:0',
            'date_expiration' => 'nullable|date',
            'image' => 'nullable|string|max:255',
            'type' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_production' => 'nullable|boolean',
        ]);

        $product->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Produit mis à jour avec succès',
            'data' => new ProductResource($product->load(['company', 'productUnit', 'categoryProduct', 'user'])),
        ], Response::HTTP_OK);
    }

    /**
     * Remove the specified product (soft delete).
     */
    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Produit supprimé avec succès',
        ], Response::HTTP_OK);
    }

    /**
     * Restore a soft-deleted product.
     */
    public function restore($id)
    {
        $product = Product::withTrashed()->findOrFail($id);
        $product->restore();

        return response()->json([
            'success' => true,
            'message' => 'Produit restauré avec succès',
            'data' => new ProductResource($product->load(['company', 'productUnit', 'categoryProduct', 'user'])),
        ], Response::HTTP_OK);
    }

    public function generatebarcode(Request $request, Product $product)
    {
        // Use product barcode or item_code if content is not provided
        $content = $request->get('content', $product->barcode ?? $product->item_code ?? '000000');
        $type = $request->get('type', 'TYPE_CODE_128');

        $generator = new BarcodeGeneratorSVG;

        $barcodeType = $generator::TYPE_CODE_128;
        if ($type === 'TYPE_EAN_13') {
            $barcodeType = $generator::TYPE_EAN_13;
        }
        if ($type === 'TYPE_CODE_39') {
            $barcodeType = $generator::TYPE_CODE_39;
        }

        try {
            $svg = $generator->getBarcode($content, $barcodeType);

            return response($svg)->header('Content-Type', 'image/svg+xml');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate barcode: '.$e->getMessage(),
            ], 400);
        }
    }

    public function generateqrcode(Request $request, Product $product)
    {
        $content = $request->get('content', $product->barcode ?? $product->item_code ?? '000000');
        $size = $request->get('size', 200);
        $size = min(max((int) $size, 50), 1000);

        try {
            $qrCode = QrCode::format('svg')
                ->size($size)
                ->margin(1)
                ->generate($content);

            return response($qrCode)->header('Content-Type', 'image/svg+xml');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate QR code: '.$e->getMessage(),
            ], 400);
        }
    }

    public function printLabels(Request $request)
    {
        $productId = $request->get('product_id');
        $count = min(max((int) $request->get('count', 1), 1), 100);
        $type = $request->get('type', 'barcode');

        $product = Product::findOrFail($productId);

        return view('labels.print', compact('product', 'count', 'type'));
    }
}
