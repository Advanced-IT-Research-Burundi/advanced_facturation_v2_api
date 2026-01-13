<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WarehouseProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WarehouseProductController extends Controller
{
    public function index(Request $request)
    {
        try {
            // Initialisation avec les relations
            $query = WarehouseProduct::with(['product', 'warehouse']);

            // RECHERCHE : Utilisation des colonnes réelles de votre table 'products'
            if ($request->filled('search')) {
                $search = $request->search;
                
                $query->whereHas('product', function($q) use ($search) {
                    $q->where(function($inner) use ($search) {
                        // Correction ici : item_designation au lieu de name, item_code au lieu de sku
                        $inner->where('item_designation', 'like', "%{$search}%")
                              ->orWhere('item_code', 'like', "%{$search}%")
                              ->orWhere('barcode', 'like', "%{$search}%"); // Ajouté car présent dans votre table
                    });
                });
            }

            // FILTRE DE STOCK
            if ($request->filled('filter') && $request->filter !== 'TOUT') {
                if ($request->filter === 'STOCK VIDE') {
                    $query->where('quantity', '<=', 0);
                } elseif ($request->filter === 'STOCK NON VIDE') {
                    $query->where('quantity', '>', 0);
                }
            }

            // Pagination
            $results = $query->latest()->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $results
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur SQL',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}