<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HotelReservation;
use App\Models\Invoice;
use App\Services\HotelInvoiceService;
use App\Services\ObrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HotelInvoiceController extends Controller
{
    public function __construct(
        protected HotelInvoiceService $hotelInvoiceService,
        protected ObrService $obrService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Invoice::where('company_id', auth()->user()->company_id)
            ->whereIn('invoice_identifier', ['HOTEL', 'RESTAURANT'])
            ->with(['customer', 'invoiceItems', 'hotelReservation.room', 'hotelReceptionBooking.receptionHall', 'hotelConferenceBooking.conferenceRoom']);

        if ($status = $request->input('payment_status')) {
            $query->where('payment_status', $status);
        }

        if ($type = $request->input('invoice_type')) {
            $query->where('invoice_identifier', strtoupper($type));
        }

        if ($obrStatus = $request->input('obr_status')) {
            $query->where('obr_submission_status', $obrStatus);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($cq) => $cq->where('customer_name', 'like', "%{$search}%"));
            });
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('invoice_date', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('invoice_date', '<=', $dateTo);
        }

        $invoices = $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $invoices,
        ]);
    }

    public function generate(Request $request, HotelReservation $hotelReservation): JsonResponse
    {
        if ($hotelReservation->company_id !== auth()->user()->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Réservation non trouvée.',
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $invoice = $this->hotelInvoiceService->generateInvoice($hotelReservation);
            $obrResult = $this->obrService->sendInvoiceIfSuperAdmin($invoice, $request->user());

            return response()->json([
                'success' => true,
                'message' => $obrResult ? 'Facture créée avec succès. Envoi OBR direct traité.' : 'Facture créée avec succès. En attente d\'envoi OBR.',
                'data' => $invoice->fresh(['customer', 'invoiceItems']),
                'obr_result' => $obrResult,
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
        if ($invoice->company_id !== auth()->user()->company_id
            || ! in_array($invoice->invoice_identifier, ['HOTEL', 'RESTAURANT'])) {
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
