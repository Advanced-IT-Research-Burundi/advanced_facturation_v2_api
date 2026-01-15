<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Product;
use App\Services\PharmaceuticalService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class PrescriptionController extends Controller
{
    protected PharmaceuticalService $pharmaceuticalService;

    public function __construct(PharmaceuticalService $pharmaceuticalService)
    {
        $this->pharmaceuticalService = $pharmaceuticalService;
    }

    /**
     * Display a listing of prescriptions.
     */
    public function index(Request $request)
    {
        $query = Prescription::with(['customer', 'items.product', 'user', 'dispensedBy']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('prescription_number', 'like', "%{$search}%")
                  ->orWhere('patient_name', 'like', "%{$search}%")
                  ->orWhere('prescriber_name', 'like', "%{$search}%");
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        return response()->json([
            'success' => true,
            'data' => $query->latest()->paginate(15),
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created prescription.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'patient_name' => 'required|string|max:255',
            'patient_birthdate' => 'nullable|date',
            'patient_phone' => 'nullable|string|max:20',
            'prescriber_name' => 'required|string|max:255',
            'prescriber_registration' => 'nullable|string|max:255',
            'prescriber_phone' => 'nullable|string|max:20',
            'prescriber_address' => 'nullable|string|max:255',
            'prescription_date' => 'required|date',
            'validity_date' => 'nullable|date|after_or_equal:prescription_date',
            'diagnosis' => 'nullable|string',
            'notes' => 'nullable|string',
            'prescription_image' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.prescribed_quantity' => 'required|numeric|min:0.01',
            'items.*.dosage_instructions' => 'nullable|string|max:255',
            'items.*.treatment_duration' => 'nullable|integer|min:1',
            'items.*.notes' => 'nullable|string',
        ]);

        // Verifier que tous les produits necessitent une ordonnance
        foreach ($validated['items'] as $item) {
            $product = Product::find($item['product_id']);
            if (!$product->requires_prescription && !$product->is_pharmaceutical) {
                return response()->json([
                    'success' => false,
                    'message' => "Le produit {$product->item_designation} ne necessite pas d'ordonnance",
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        try {
            $prescription = DB::transaction(function () use ($validated) {
                $prescriptionData = collect($validated)->except('items')->toArray();
                $prescriptionData['prescription_number'] = Prescription::generateNumber();
                $prescriptionData['status'] = 'pending';
                $prescriptionData['user_id'] = auth()->id();

                $prescription = Prescription::create($prescriptionData);

                foreach ($validated['items'] as $item) {
                    PrescriptionItem::create([
                        'prescription_id' => $prescription->id,
                        'product_id' => $item['product_id'],
                        'prescribed_quantity' => $item['prescribed_quantity'],
                        'dosage_instructions' => $item['dosage_instructions'] ?? null,
                        'treatment_duration' => $item['treatment_duration'] ?? null,
                        'notes' => $item['notes'] ?? null,
                        'status' => 'pending',
                    ]);
                }

                return $prescription;
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ordonnance creee avec succes',
            'data' => $prescription->load(['customer', 'items.product', 'user']),
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified prescription.
     */
    public function show(Prescription $prescription)
    {
        return response()->json([
            'success' => true,
            'data' => $prescription->load(['customer', 'items.product', 'items.productLot', 'user', 'dispensedBy', 'invoice']),
        ], Response::HTTP_OK);
    }

    /**
     * Update the specified prescription.
     */
    public function update(Request $request, Prescription $prescription)
    {
        if ($prescription->status === 'fully_dispensed') {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de modifier une ordonnance entierement delivree',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validated = $request->validate([
            'patient_name' => 'sometimes|string|max:255',
            'patient_birthdate' => 'nullable|date',
            'patient_phone' => 'nullable|string|max:20',
            'prescriber_name' => 'sometimes|string|max:255',
            'prescriber_registration' => 'nullable|string|max:255',
            'prescriber_phone' => 'nullable|string|max:20',
            'prescriber_address' => 'nullable|string|max:255',
            'validity_date' => 'nullable|date',
            'diagnosis' => 'nullable|string',
            'notes' => 'nullable|string',
            'prescription_image' => 'nullable|string|max:255',
            'status' => 'sometimes|in:pending,cancelled',
        ]);

        try {
            $prescription->update($validated);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ordonnance mise a jour avec succes',
            'data' => $prescription->load(['customer', 'items.product', 'user']),
        ], Response::HTTP_OK);
    }

    /**
     * Remove the specified prescription.
     */
    public function destroy(Prescription $prescription)
    {
        if ($prescription->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Seules les ordonnances en attente peuvent etre supprimees',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $prescription->items()->delete();
        $prescription->delete();

        return response()->json([
            'success' => true,
            'message' => 'Ordonnance supprimee avec succes',
        ], Response::HTTP_OK);
    }

    /**
     * Dispense items from a prescription.
     */
    public function dispense(Request $request, Prescription $prescription)
    {
        if (!$prescription->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'Cette ordonnance a expire',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'items' => 'required|array|min:1',
            'items.*.prescription_item_id' => 'required|exists:prescription_items,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'invoice_id' => 'nullable|exists:invoices,id',
        ]);

        try {
            $results = DB::transaction(function () use ($validated, $prescription) {
                $dispensedItems = [];

                foreach ($validated['items'] as $itemData) {
                    $prescriptionItem = PrescriptionItem::find($itemData['prescription_item_id']);

                    if ($prescriptionItem->prescription_id !== $prescription->id) {
                        throw new \Exception('Item ne correspond pas a cette ordonnance');
                    }

                    if ($itemData['quantity'] > $prescriptionItem->remaining_quantity) {
                        throw new \Exception(
                            "Quantite demandee ({$itemData['quantity']}) superieure a la quantite restante ({$prescriptionItem->remaining_quantity})"
                        );
                    }

                    // Vendre avec FEFO
                    $saleResult = $this->pharmaceuticalService->sellWithFEFO(
                        $prescriptionItem->product_id,
                        $validated['warehouse_id'],
                        $itemData['quantity'],
                        $prescription->customer_id,
                        $validated['invoice_id'] ?? null,
                        $prescription->id
                    );

                    // Mettre a jour l'item de l'ordonnance
                    $lotId = !empty($saleResult['sold_lots']) ? $saleResult['sold_lots'][0]['lot_id'] : null;
                    $prescriptionItem->dispense($itemData['quantity'], $lotId);

                    $dispensedItems[] = [
                        'product_id' => $prescriptionItem->product_id,
                        'quantity' => $itemData['quantity'],
                        'lots' => $saleResult['sold_lots'],
                    ];
                }

                // Mettre a jour le statut de l'ordonnance
                $prescription->dispensed_by = auth()->id();
                $prescription->dispensed_at = now();
                if (isset($validated['invoice_id'])) {
                    $prescription->invoice_id = $validated['invoice_id'];
                }
                $prescription->save();
                $prescription->updateStatus();

                return $dispensedItems;
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'success' => true,
            'message' => 'Produits delivres avec succes',
            'data' => [
                'prescription' => $prescription->fresh(['items.product', 'items.productLot']),
                'dispensed_items' => $results,
            ],
        ], Response::HTTP_OK);
    }

    /**
     * Restore a soft-deleted prescription.
     */
    public function restore($id)
    {
        $prescription = Prescription::withTrashed()->findOrFail($id);
        $prescription->restore();

        return response()->json([
            'success' => true,
            'message' => 'Ordonnance restauree avec succes',
            'data' => $prescription->load(['customer', 'items.product']),
        ], Response::HTTP_OK);
    }
}
