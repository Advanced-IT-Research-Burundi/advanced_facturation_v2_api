<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InvoiceController extends Controller
{
    /**
     * Display a listing of invoices.
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => Invoice::with(['company', 'customer', 'invoiceItems'])->paginate(15)
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created invoice.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'invoice_number' => 'required|string|max:255',
            'invoice_date' => 'required|date',
            'invoice_type' => 'required|string|max:255',
            'invoice_identifier' => 'required|string|max:255',
            'invoice_currency' => 'required|string|max:3',
            'tp_type' => 'required|string|max:255',
            'tp_name' => 'required|string|max:255',
            'tp_TIN' => 'required|string|max:255',
            'tp_trade_number' => 'nullable|string|max:255',
            'tp_phone_number' => 'nullable|string|max:255',
            'tp_fiscal_center' => 'nullable|string|max:255',
            'vat_taxpayer' => 'required|string|max:255',
            'ct_taxpayer' => 'required|string|max:255',
            'tl_taxpayer' => 'required|string|max:255',
            'customer_name' => 'required|string|max:255',
            'customer_TIN' => 'nullable|string|max:255',
            'customer_address' => 'nullable|string|max:255',
            'vat_customer_payer' => 'required|string|max:255',
            'invoice_amount_nvat' => 'required|numeric|min:0',
            'invoice_vat_amount' => 'required|numeric|min:0',
            'invoice_total_amount' => 'required|numeric|min:0',
            'invoice_registered_number' => 'nullable|string|max:255',
            'invoice_registered_date' => 'nullable|date',
            'electronic_signature' => 'nullable|string',
            'obr_submission_status' => 'required|in:PENDING,SENT,ACCEPTED,REJECTED',
            'obr_response_message' => 'nullable|string',
            'company_id' => 'required|exists:companies,id',
            'customer_id' => 'required|exists:customers,id',
        ]);

        $validated['user_id'] = auth()->id();
        $validated['created_by'] = auth()->id();

        $invoice = Invoice::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Invoice created successfully',
            'data' => $invoice->load(['company', 'customer', 'invoiceItems'])
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified invoice.
     */
    public function show(Invoice $invoice)
    {
        return response()->json([
            'success' => true,
            'data' => $invoice->load(['company', 'customer', 'invoiceItems', 'stockMovements'])
        ], Response::HTTP_OK);
    }

    /**
     * Update the specified invoice.
     */
    public function update(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'invoice_number' => 'sometimes|required|string|max:255',
            'invoice_date' => 'sometimes|required|date',
            'invoice_type' => 'sometimes|required|string|max:255',
            'invoice_identifier' => 'sometimes|required|string|max:255',
            'invoice_currency' => 'sometimes|required|string|max:3',
            'obr_submission_status' => 'sometimes|required|in:PENDING,SENT,ACCEPTED,REJECTED',
            'obr_response_message' => 'nullable|string',
            'invoice_registered_number' => 'nullable|string|max:255',
            'invoice_registered_date' => 'nullable|date',
            'electronic_signature' => 'nullable|string',
        ]);

        $invoice->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Invoice updated successfully',
            'data' => $invoice->load(['company', 'customer', 'invoiceItems'])
        ], Response::HTTP_OK);
    }

    /**
     * Remove the specified invoice (soft delete).
     */
    public function destroy(Invoice $invoice)
    {
        $invoice->delete();

        return response()->json([
            'success' => true,
            'message' => 'Invoice deleted successfully'
        ], Response::HTTP_OK);
    }

    /**
     * Restore a soft-deleted invoice.
     */
    public function restore($id)
    {
        $invoice = Invoice::withTrashed()->findOrFail($id);
        $invoice->restore();

        return response()->json([
            'success' => true,
            'message' => 'Invoice restored successfully',
            'data' => $invoice
        ], Response::HTTP_OK);
    }
}
