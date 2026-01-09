<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InvoiceItemController extends Controller
{
    /**
     * Display a listing of invoice items.
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => InvoiceItem::with('invoice')->paginate(15)
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created invoice item.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'item_designation' => 'required|string|max:255',
            'item_quantity' => 'required|numeric|min:0.01',
            'item_price' => 'required|numeric|min:0',
            'item_ct' => 'nullable|numeric|min:0',
            'item_tl' => 'nullable|numeric|min:0',
            'item_ott_tax' => 'nullable|numeric|min:0',
            'item_tsce_tax' => 'nullable|numeric|min:0',
            'item_price_nvat' => 'required|numeric|min:0',
            'vat' => 'required|numeric|min:0',
            'item_price_wvat' => 'required|numeric|min:0',
            'item_total_amount' => 'required|numeric|min:0',
        ]);

        $validated['user_id'] = auth()->id();

        $invoiceItem = InvoiceItem::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Invoice item created successfully',
            'data' => $invoiceItem->load('invoice')
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified invoice item.
     */
    public function show(InvoiceItem $invoiceItem)
    {
        return response()->json([
            'success' => true,
            'data' => $invoiceItem->load('invoice')
        ], Response::HTTP_OK);
    }

    /**
     * Update the specified invoice item.
     */
    public function update(Request $request, InvoiceItem $invoiceItem)
    {
        $validated = $request->validate([
            'item_designation' => 'sometimes|required|string|max:255',
            'item_quantity' => 'sometimes|required|numeric|min:0.01',
            'item_price' => 'sometimes|required|numeric|min:0',
            'item_ct' => 'nullable|numeric|min:0',
            'item_tl' => 'nullable|numeric|min:0',
            'item_ott_tax' => 'nullable|numeric|min:0',
            'item_tsce_tax' => 'nullable|numeric|min:0',
            'item_price_nvat' => 'sometimes|required|numeric|min:0',
            'vat' => 'sometimes|required|numeric|min:0',
            'item_price_wvat' => 'sometimes|required|numeric|min:0',
            'item_total_amount' => 'sometimes|required|numeric|min:0',
        ]);

        $invoiceItem->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Invoice item updated successfully',
            'data' => $invoiceItem->load('invoice')
        ], Response::HTTP_OK);
    }

    /**
     * Remove the specified invoice item (soft delete).
     */
    public function destroy(InvoiceItem $invoiceItem)
    {
        $invoiceItem->delete();

        return response()->json([
            'success' => true,
            'message' => 'Invoice item deleted successfully'
        ], Response::HTTP_OK);
    }

    /**
     * Restore a soft-deleted invoice item.
     */
    public function restore($id)
    {
        $invoiceItem = InvoiceItem::withTrashed()->findOrFail($id);
        $invoiceItem->restore();

        return response()->json([
            'success' => true,
            'message' => 'Invoice item restored successfully',
            'data' => $invoiceItem
        ], Response::HTTP_OK);
    }
}
