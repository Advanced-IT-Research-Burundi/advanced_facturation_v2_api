<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\AppConfig;
use App\Services\ObrService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function __construct(private readonly ObrService $obrService)
    {
    }

    public function checkTin(Request $request, $tp_TIN = null)
    {
        $tpTin = trim((string) ($request->input('tp_TIN', $tp_TIN) ?? ''));

        if ($tpTin === '') {
            return response()->json([
                'success' => false,
                'message' => 'Veuillez fournir le NIF du contribuable.',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (! AppConfig::getConfigKey('CAN_SYNCRONISE_TO_OBR')) {
            return response()->json([
                'success' => false,
                'message' => 'OBR désactivé. Vérification NIF non disponible.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $result = $this->obrService->checkTin($tpTin);

        if (! $result['success']) {
            $message = $result['message'] ?? 'NIF du contribuable inconnu.';
            $status = str_contains(mb_strtolower($message), 'inconnu')
                ? Response::HTTP_BAD_REQUEST
                : Response::HTTP_BAD_GATEWAY;

            return response()->json([
                'success' => false,
                'message' => $message,
            ], $status);
        }

        return response()->json([
            'success' => true,
            'message' => 'NIF valide.',
            'data' => $result['data'],
        ]);
    }

    /**
     * Display a listing of customers.
     */
    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->input('per_page', 15), 100));
        $query = Customer::with(['company'])->withCount('invoices');

        // Recherche par nom, téléphone ou NIF
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%")
                    ->orWhere('customer_TIN', 'like', "%{$search}%")
                    ->orWhere('customer_address', 'like', "%{$search}%");
            });
        }

        // Tri par défaut : plus récents d'abord
        $query->orderBy('created_at', 'desc');

        return response()->json([
            'success' => true,
            'data' => $query->paginate($perPage),
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created customer.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'type' => ['required', 'string', Rule::in(['PERSONNE PHYSIQUE', 'PERSONNE MORAL'])],
            'customer_TIN' => [
                Rule::requiredIf($request->input('type') === 'PERSONNE MORAL'),
                'nullable',
                'string',
                'max:255',
                Rule::unique('customers', 'customer_TIN')->whereNull('deleted_at'),
            ],
            'customer_phone' => 'nullable|string|max:255|unique:customers,customer_phone,NULL,id,deleted_at,NULL',
            'customer_address' => 'nullable|string|max:255',
            'vat_customer_payer' => 'required|string|max:255',
        ]);

        $validated['user_id'] = auth()->id();
        if (! auth()->user()->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune entreprise associée à votre compte.',
            ], Response::HTTP_FORBIDDEN);
        }
        $validated['company_id'] = auth()->user()->company_id;

        $customer = Customer::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Customer created successfully',
            'data' => $customer->load('company'),
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified customer.
     */
    public function show(Customer $customer)
    {
        return response()->json([
            'success' => true,
            'data' => $customer->load(['company', 'invoices']),
        ], Response::HTTP_OK);
    }

    /**
     * Update the specified customer.
     */
    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'customer_name' => 'sometimes|required|string|max:255',
            'type' => ['sometimes', 'required', 'string', Rule::in(['PERSONNE PHYSIQUE', 'PERSONNE MORAL'])],
            'customer_TIN' => [
                Rule::requiredIf($request->input('type') === 'PERSONNE MORAL'),
                'nullable',
                'string',
                'max:255',
                Rule::unique('customers', 'customer_TIN')
                    ->ignore($customer->id)
                    ->whereNull('deleted_at'),
            ],
            'customer_phone' => 'nullable|string|unique:customers,customer_phone,'.$customer->id.'|max:255',
            'customer_address' => 'nullable|string|max:255',
            'vat_customer_payer' => 'sometimes|required|string|max:255',
        ]);

        $customer->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Customer updated successfully',
            'data' => $customer->load('company'),
        ], Response::HTTP_OK);
    }

    /**
     * Remove the specified customer (soft delete).
     */
    public function destroy(Customer $customer)
    {
        $customer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Customer deleted successfully',
        ], Response::HTTP_OK);
    }

    /**
     * Restore a soft-deleted customer.
     */
    public function restore($id)
    {
        $customer = Customer::withTrashed()->findOrFail($id);
        $customer->restore();

        return response()->json([
            'success' => true,
            'message' => 'Customer restored successfully',
            'data' => $customer,
        ], Response::HTTP_OK);
    }

    /**
     * Get customer deposits (cautions)
     */
    public function deposits(Customer $customer)
    {
        // Get all deposits (invoice_type = FC) for this customer
        $deposits = \App\Models\Invoice::where('customer_id', $customer->id)
            ->where('invoice_type', 'FC')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($invoice) {
                // Calculate refunded amount from RC invoices referencing this deposit
                $refundedAmount = \App\Models\Invoice::where('reference_invoice_id', $invoice->id)
                    ->where('invoice_type', 'RC')
                    ->sum('invoice_total_amount');

                return [
                    'id' => $invoice->id,
                    'reference' => $invoice->deposit_reference ?? $invoice->invoice_number,
                    'amount' => abs($invoice->invoice_total_amount),
                    'refunded_amount' => abs($refundedAmount),
                    'remaining_amount' => abs($invoice->invoice_total_amount) - abs($refundedAmount),
                    'currency' => $invoice->invoice_currency,
                    'created_at' => $invoice->created_at,
                    'invoice_number' => $invoice->invoice_number,
                ];
            })
            ->filter(function ($deposit) {
                return $deposit['remaining_amount'] > 0; // Only show deposits with remaining balance
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $deposits,
        ], Response::HTTP_OK);
    }
}
