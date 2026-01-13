<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\ObrService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CustomerController extends Controller
{

    public function __construct(public ObrService $obr){

    }

    public function checkTin($tp_TIN){
        return $this->obr->checkTIN($tp_TIN);
    }
    /**
     * Display a listing of customers.
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => Customer::with(['company', 'invoices'])->paginate(15)
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created customer.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_TIN' => 'nullable|string|unique:customers|max:255',
            'customer_phone' => 'nullable|string|unique:customers|max:255',
            'customer_address' => 'nullable|string|max:255',
            'vat_customer_payer' => 'required|string|max:255',
            'company_id' => 'required|exists:companies,id',
        ]);

        $validated['user_id'] = auth()->id();

        $customer = Customer::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Customer created successfully',
            'data' => $customer->load('company')
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified customer.
     */
    public function show(Customer $customer)
    {
        return response()->json([
            'success' => true,
            'data' => $customer->load(['company', 'invoices'])
        ], Response::HTTP_OK);
    }

    /**
     * Update the specified customer.
     */
    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'customer_name' => 'sometimes|required|string|max:255',
            'customer_TIN' => 'nullable|string|unique:customers,customer_TIN,' . $customer->id . '|max:255',
            'customer_phone' => 'nullable|string|unique:customers,customer_phone,' . $customer->id . '|max:255',
            'customer_address' => 'nullable|string|max:255',
            'vat_customer_payer' => 'sometimes|required|string|max:255',
            'company_id' => 'sometimes|required|exists:companies,id',
        ]);

        $customer->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Customer updated successfully',
            'data' => $customer->load('company')
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
            'message' => 'Customer deleted successfully'
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
            'data' => $customer
        ], Response::HTTP_OK);
    }
}
