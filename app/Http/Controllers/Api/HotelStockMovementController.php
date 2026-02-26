<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HotelBarStock;
use App\Models\HotelKitchenStock;
use App\Models\HotelStockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class HotelStockMovementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = HotelStockMovement::with(['user'])
            ->orderBy('created_at', 'desc');

        if ($type = $request->input('stock_type')) {
            $query->where('stock_type', $type);
        }

        if ($movType = $request->input('movement_type')) {
            $query->where('movement_type', $movType);
        }

        if ($request->input('today')) {
            $query->whereDate('created_at', today());
        }

        $movements = $query->paginate($request->get('per_page', 30));

        return response()->json(['success' => true, 'data' => $movements]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'stock_type' => 'required|in:bar,kitchen',
            'stock_item_id' => 'required|integer',
            'movement_type' => 'required|in:in,out',
            'quantity' => 'required|numeric|min:0.001',
            'reason' => 'nullable|string|max:255',
            'reference' => 'nullable|string|max:100',
        ]);

        $user = $request->user();

        return DB::transaction(function () use ($validated, $user) {
            if ($validated['stock_type'] === 'bar') {
                $item = HotelBarStock::findOrFail($validated['stock_item_id']);

                $before = (float) $item->quantity;
                $after = $validated['movement_type'] === 'in'
                    ? $before + $validated['quantity']
                    : max(0, $before - $validated['quantity']);

                $item->update(['quantity' => $after]);
                $itemName = $item->name;
            } else {
                $item = HotelKitchenStock::findOrFail($validated['stock_item_id']);

                $before = (float) $item->quantity;
                $after = $validated['movement_type'] === 'in'
                    ? $before + $validated['quantity']
                    : max(0, $before - $validated['quantity']);

                $item->update(['quantity' => $after]);
                $itemName = $item->name;
            }

            $movement = HotelStockMovement::create([
                'company_id' => $user->company_id,
                'stock_type' => $validated['stock_type'],
                'stock_item_id' => $validated['stock_item_id'],
                'stock_item_name' => $itemName,
                'movement_type' => $validated['movement_type'],
                'quantity' => $validated['quantity'],
                'quantity_before' => $before,
                'quantity_after' => $after,
                'reason' => $validated['reason'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => $validated['movement_type'] === 'in' ? 'Entrée enregistrée' : 'Sortie enregistrée',
                'data' => $movement,
                'new_quantity' => $after,
            ], Response::HTTP_CREATED);
        });
    }
}
