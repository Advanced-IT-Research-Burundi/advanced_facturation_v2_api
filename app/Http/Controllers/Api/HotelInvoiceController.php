<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HotelReservation;
use App\Models\Invoice;
use App\Services\HotelInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HotelInvoiceController extends Controller
{
    public function __construct(
        protected HotelInvoiceService $hotelInvoiceService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Invoice::where('company_id', auth()->user()->company_id)
            ->where('invoice_identifier', 'HOTEL')
            ->with(['customer', 'invoiceItems', 'hotelReservation.room']);

        if ($status = $request->input('payment_status')) {
            $query->where('payment_status', $status);
        }

        if ($obrStatus = $request->input('obr_status')) {
            $query->where('obr_submission_status', $obrStatus);
        }

        $invoices = $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $invoices,
        ]);
    }

    public function generate(HotelReservation $hotelReservation): JsonResponse
    {
        if ($hotelReservation->company_id !== auth()->user()->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Réservation non trouvée.',
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $invoice = $this->hotelInvoiceService->generateInvoice($hotelReservation);

            return response()->json([
                'success' => true,
                'message' => 'Facture créée avec succès',
                'data' => $invoice->load(['customer', 'invoiceItems']),
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function show(Invoice $invoice): JsonResponse
    {
        if ($invoice->company_id !== auth()->user()->company_id || $invoice->invoice_identifier !== 'HOTEL') {
            return response()->json([
                'success' => false,
                'message' => 'Facture non trouvée',
            ], Response::HTTP_NOT_FOUND);
        }

        $invoice->load(['customer', 'invoiceItems', 'payments', 'hotelReservation.room']);

        return response()->json([
            'success' => true,
            'data' => $invoice,
        ]);
    }
}
