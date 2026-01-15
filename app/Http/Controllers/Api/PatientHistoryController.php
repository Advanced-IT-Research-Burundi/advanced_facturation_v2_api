<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PatientHistory;
use App\Models\Customer;
use App\Services\PharmaceuticalService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PatientHistoryController extends Controller
{
    protected PharmaceuticalService $pharmaceuticalService;

    public function __construct(PharmaceuticalService $pharmaceuticalService)
    {
        $this->pharmaceuticalService = $pharmaceuticalService;
    }

    /**
     * Display a listing of patient histories.
     */
    public function index(Request $request)
    {
        $query = PatientHistory::with(['customer', 'product', 'productLot', 'invoice', 'prescription']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('customer', function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%");
            })->orWhereHas('product', function ($q) use ($search) {
                $q->where('item_designation', 'like', "%{$search}%")
                  ->orWhere('item_code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('from_date')) {
            $query->where('purchase_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->where('purchase_date', '<=', $request->to_date);
        }

        if ($request->boolean('requires_followup')) {
            $query->requiringFollowup();
        }

        return response()->json([
            'success' => true,
            'data' => $query->latest('purchase_date')->paginate(15),
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created patient history.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'product_id' => 'required|exists:products,id',
            'product_lot_id' => 'nullable|exists:product_lots,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'prescription_id' => 'nullable|exists:prescriptions,id',
            'quantity' => 'required|numeric|min:0.01',
            'unit_price' => 'required|numeric|min:0',
            'purchase_date' => 'required|date',
            'lot_number' => 'nullable|string|max:255',
            'lot_expiration' => 'nullable|date',
            'notes' => 'nullable|string',
            'requires_followup' => 'boolean',
            'followup_date' => 'nullable|date|after:today',
        ]);

        $validated['user_id'] = auth()->id();

        try {
            $history = PatientHistory::create($validated);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'success' => true,
            'message' => 'Historique patient cree avec succes',
            'data' => $history->load(['customer', 'product', 'productLot']),
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified patient history.
     */
    public function show(PatientHistory $patientHistory)
    {
        return response()->json([
            'success' => true,
            'data' => $patientHistory->load(['customer', 'product', 'productLot', 'invoice', 'prescription', 'user']),
        ], Response::HTTP_OK);
    }

    /**
     * Update the specified patient history.
     */
    public function update(Request $request, PatientHistory $patientHistory)
    {
        $validated = $request->validate([
            'notes' => 'nullable|string',
            'requires_followup' => 'boolean',
            'followup_date' => 'nullable|date',
        ]);

        try {
            $patientHistory->update($validated);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'success' => true,
            'message' => 'Historique patient mis a jour avec succes',
            'data' => $patientHistory->load(['customer', 'product']),
        ], Response::HTTP_OK);
    }

    /**
     * Remove the specified patient history.
     */
    public function destroy(PatientHistory $patientHistory)
    {
        $patientHistory->delete();

        return response()->json([
            'success' => true,
            'message' => 'Historique patient supprime avec succes',
        ], Response::HTTP_OK);
    }

    /**
     * Get history by customer.
     */
    public function byCustomer(Customer $customer, Request $request)
    {
        $query = PatientHistory::with(['product', 'productLot', 'invoice', 'prescription'])
            ->where('customer_id', $customer->id);

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        $histories = $query->orderBy('purchase_date', 'desc')->get();

        // Calculer les statistiques
        $stats = [
            'total_purchases' => $histories->count(),
            'total_amount' => $histories->sum(fn ($h) => $h->quantity * $h->unit_price),
            'unique_products' => $histories->pluck('product_id')->unique()->count(),
            'last_purchase' => $histories->first()?->purchase_date,
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'customer' => $customer,
                'histories' => $histories,
                'stats' => $stats,
            ],
        ], Response::HTTP_OK);
    }

    /**
     * Get recent purchases for a customer and check for interactions.
     */
    public function checkInteractions(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'product_id' => 'required|exists:products,id',
            'days' => 'nullable|integer|min:1|max:365',
        ]);

        $result = $this->pharmaceuticalService->checkRecentPurchases(
            $validated['customer_id'],
            $validated['product_id'],
            $validated['days'] ?? 30
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ], Response::HTTP_OK);
    }

    /**
     * Get patients requiring followup.
     */
    public function followups(Request $request)
    {
        $histories = PatientHistory::with(['customer', 'product'])
            ->requiringFollowup()
            ->orderBy('followup_date', 'asc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $histories,
        ], Response::HTTP_OK);
    }
}
