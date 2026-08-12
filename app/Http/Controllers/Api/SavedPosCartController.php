<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SavedPosCart;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SavedPosCartController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->input('per_page', 50), 100));

        $savedCarts = SavedPosCart::query()
            ->with(['customer', 'warehouse'])
            ->where('user_id', $request->user()->id)
            ->latest('updated_at')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $savedCarts,
        ], Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'local_id' => 'required|string|max:120',
            'identifier' => 'required|string|max:120',
            'customer_id' => 'nullable|exists:customers,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'currency' => 'required|string|max:3',
            'payment_type' => 'required|string|max:50',
            'total_ht' => 'nullable|numeric|min:0',
            'total_tva' => 'nullable|numeric|min:0',
            'total_ttc' => 'nullable|numeric|min:0',
            'customer_snapshot' => 'nullable|array',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required',
            'items.*.name' => 'required|string|max:255',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        $user = $request->user();

        $savedCart = SavedPosCart::updateOrCreate(
            [
                'company_id' => $user->company_id,
                'local_id' => $validated['local_id'],
            ],
            [
                'identifier' => $validated['identifier'],
                'user_id' => $user->id,
                'customer_id' => $validated['customer_id'] ?? null,
                'warehouse_id' => $validated['warehouse_id'] ?? null,
                'currency' => $validated['currency'],
                'payment_type' => $validated['payment_type'],
                'total_ht' => $validated['total_ht'] ?? 0,
                'total_tva' => $validated['total_tva'] ?? 0,
                'total_ttc' => $validated['total_ttc'] ?? 0,
                'customer_snapshot' => $validated['customer_snapshot'] ?? null,
                'items' => $validated['items'],
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Facture enregistrée avec succès',
            'data' => $savedCart->load(['customer', 'warehouse']),
        ], $savedCart->wasRecentlyCreated ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    public function destroy(Request $request, string $savedPosCart)
    {
        $deleted = SavedPosCart::query()
            ->where('company_id', $request->user()->company_id)
            ->where(function ($query) use ($savedPosCart) {
                $query->where('local_id', $savedPosCart)
                    ->orWhere('identifier', $savedPosCart);
            })
            ->delete();

        return response()->json([
            'success' => true,
            'deleted' => $deleted,
        ], Response::HTTP_OK);
    }
}
