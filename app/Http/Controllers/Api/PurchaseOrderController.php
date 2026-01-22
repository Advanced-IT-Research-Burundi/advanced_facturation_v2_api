<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    /**
     * Liste des bons de commande avec pagination et recherche
     */
    public function index(Request $request)
    {
        $query = PurchaseOrder::with(['fourinsseur', 'warehouse', 'user', 'items.product'])
            ->where('company_id', auth()->user()->company_id)
            ->orderBy('created_at', 'desc');

        // Recherche
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('ref_code', 'like', "%{$search}%")
                  ->orWhereHas('fourinsseur', function ($fq) use ($search) {
                      $fq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Filtre par statut
        if ($status = $request->input('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        // Filtre par fournisseur
        if ($fourinsseurId = $request->input('fourinsseur_id')) {
            $query->where('fourinsseur_id', $fourinsseurId);
        }

        // Filtre par dates
        if ($startDate = $request->input('start_date')) {
            $query->whereDate('order_date', '>=', $startDate);
        }
        if ($endDate = $request->input('end_date')) {
            $query->whereDate('order_date', '<=', $endDate);
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate($request->input('per_page', 15))
        ], Response::HTTP_OK);
    }

    /**
     * Créer un nouveau bon de commande
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'fourinsseur_id' => 'required|exists:fourinsseurs,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'expected_delivery_date' => 'nullable|date',
            'currency' => 'required|string|max:3',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            // Générer le code de référence
            $refCode = $this->generateRefCode();

            // Calculer le total
            $totalAmount = collect($validated['items'])->sum(function ($item) {
                return $item['quantity'] * $item['unit_price'];
            });

            // Créer le bon de commande
            $purchaseOrder = PurchaseOrder::create([
                'ref_code' => $refCode,
                'order_date' => now(),
                'expected_delivery_date' => $validated['expected_delivery_date'] ?? null,
                'fourinsseur_id' => $validated['fourinsseur_id'],
                'warehouse_id' => $validated['warehouse_id'],
                'status' => 'draft',
                'total_amount' => $totalAmount,
                'currency' => $validated['currency'],
                'notes' => $validated['notes'] ?? null,
                'company_id' => auth()->user()->company_id,
                'user_id' => auth()->id(),
            ]);

            // Créer les items
            foreach ($validated['items'] as $item) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['quantity'] * $item['unit_price'],
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Bon de commande créé avec succès',
                'data' => $purchaseOrder->load(['fourinsseur', 'warehouse', 'items.product'])
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du bon de commande',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Afficher un bon de commande
     */
    public function show(PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->company_id !== auth()->user()->company_id) {
            abort(403);
        }

        return response()->json([
            'success' => true,
            'data' => $purchaseOrder->load(['fourinsseur', 'warehouse', 'user', 'items.product'])
        ], Response::HTTP_OK);
    }

    /**
     * Mettre à jour un bon de commande
     */
    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->company_id !== auth()->user()->company_id) {
            abort(403);
        }

        // Ne peut modifier que les brouillons ou en attente
        if (!in_array($purchaseOrder->status, ['draft', 'pending'])) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de modifier un bon de commande avec ce statut'
            ], Response::HTTP_BAD_REQUEST);
        }

        $validated = $request->validate([
            'fourinsseur_id' => 'sometimes|exists:fourinsseurs,id',
            'warehouse_id' => 'sometimes|exists:warehouses,id',
            'expected_delivery_date' => 'nullable|date',
            'currency' => 'sometimes|string|max:3',
            'notes' => 'nullable|string',
            'items' => 'sometimes|array|min:1',
            'items.*.product_id' => 'required_with:items|exists:products,id',
            'items.*.quantity' => 'required_with:items|numeric|min:0.01',
            'items.*.unit_price' => 'required_with:items|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            // Mise à jour des informations de base
            $purchaseOrder->update([
                'fourinsseur_id' => $validated['fourinsseur_id'] ?? $purchaseOrder->fourinsseur_id,
                'warehouse_id' => $validated['warehouse_id'] ?? $purchaseOrder->warehouse_id,
                'expected_delivery_date' => $validated['expected_delivery_date'] ?? $purchaseOrder->expected_delivery_date,
                'currency' => $validated['currency'] ?? $purchaseOrder->currency,
                'notes' => $validated['notes'] ?? $purchaseOrder->notes,
            ]);

            // Mise à jour des items si fournis
            if (isset($validated['items'])) {
                // Supprimer les anciens items
                $purchaseOrder->items()->delete();

                // Créer les nouveaux items
                $totalAmount = 0;
                foreach ($validated['items'] as $item) {
                    $itemTotal = $item['quantity'] * $item['unit_price'];
                    $totalAmount += $itemTotal;

                    PurchaseOrderItem::create([
                        'purchase_order_id' => $purchaseOrder->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total_price' => $itemTotal,
                    ]);
                }

                $purchaseOrder->update(['total_amount' => $totalAmount]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Bon de commande mis à jour avec succès',
                'data' => $purchaseOrder->fresh()->load(['fourinsseur', 'warehouse', 'items.product'])
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Supprimer un bon de commande
     */
    public function destroy(PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->company_id !== auth()->user()->company_id) {
            abort(403);
        }

        // Ne peut supprimer que les brouillons
        if ($purchaseOrder->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les brouillons peuvent être supprimés'
            ], Response::HTTP_BAD_REQUEST);
        }

        $purchaseOrder->items()->delete();
        $purchaseOrder->delete();

        return response()->json([
            'success' => true,
            'message' => 'Bon de commande supprimé avec succès'
        ], Response::HTTP_OK);
    }

    /**
     * Changer le statut d'un bon de commande
     */
    public function updateStatus(Request $request, PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->company_id !== auth()->user()->company_id) {
            abort(403);
        }

        $validated = $request->validate([
            'status' => 'required|in:draft,pending,approved,received,cancelled'
        ]);

        $allowedTransitions = [
            'draft' => ['pending', 'cancelled'],
            'pending' => ['approved', 'cancelled'],
            'approved' => ['received', 'cancelled'],
            'received' => [],
            'cancelled' => ['draft'],
        ];

        if (!in_array($validated['status'], $allowedTransitions[$purchaseOrder->status] ?? [])) {
            return response()->json([
                'success' => false,
                'message' => "Transition de statut non autorisée de '{$purchaseOrder->status}' vers '{$validated['status']}'"
            ], Response::HTTP_BAD_REQUEST);
        }

        $purchaseOrder->update(['status' => $validated['status']]);

        return response()->json([
            'success' => true,
            'message' => 'Statut mis à jour avec succès',
            'data' => $purchaseOrder->fresh()->load(['fourinsseur', 'warehouse', 'items.product'])
        ], Response::HTTP_OK);
    }

    /**
     * Générer le code de référence unique
     */
    private function generateRefCode(): string
    {
        $year = now()->year;
        $prefix = "BC-{$year}";

        $lastOrder = PurchaseOrder::where('ref_code', 'LIKE', "{$prefix}-%")
            ->orderBy('ref_code', 'desc')
            ->first();

        if ($lastOrder) {
            $lastNumber = (int) substr($lastOrder->ref_code, -5);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return sprintf('%s-%05d', $prefix, $newNumber);
    }

    /**
     * Statistiques des bons de commande
     */
    public function stats()
    {
        $companyId = auth()->user()->company_id;
        
        $stats = [
            'total' => PurchaseOrder::where('company_id', $companyId)->count(),
            'draft' => PurchaseOrder::where('company_id', $companyId)->where('status', 'draft')->count(),
            'pending' => PurchaseOrder::where('company_id', $companyId)->where('status', 'pending')->count(),
            'approved' => PurchaseOrder::where('company_id', $companyId)->where('status', 'approved')->count(),
            'received' => PurchaseOrder::where('company_id', $companyId)->where('status', 'received')->count(),
            'cancelled' => PurchaseOrder::where('company_id', $companyId)->where('status', 'cancelled')->count(),
            'total_amount_this_month' => PurchaseOrder::where('company_id', $companyId)
                ->whereMonth('order_date', now()->month)
                ->whereYear('order_date', now()->year)
                ->whereIn('status', ['approved', 'received'])
                ->sum('total_amount'),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ], Response::HTTP_OK);
    }
}
