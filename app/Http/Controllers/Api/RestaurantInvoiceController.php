<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\User;
use App\Services\RestaurantInvoiceService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RestaurantInvoiceController extends Controller
{
    protected RestaurantInvoiceService $invoiceService;

    public function __construct(RestaurantInvoiceService $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }

    public function index(Request $request)
    {
        $query = Invoice::where('company_id', auth()->user()->company_id)
            ->where('is_restaurant', true)
            ->with(['restaurantTable', 'server', 'customer']);

        if ($status = $request->input('status')) {
            $query->where('payment_status', $status);
        }

        if ($obrStatus = $request->input('obr_status')) {
            $query->where('obr_submission_status', $obrStatus);
        }

        $invoices = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $invoices,
        ]);
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'table_id' => 'required|exists:restaurant_tables,id',
            'customer_id' => 'nullable|exists:customers,id',
            'order_ids' => 'nullable|array',
            'order_ids.*' => 'exists:restaurant_orders,id',
        ]);

        try {
            $invoice = $this->invoiceService->generateInvoice(
                $validated['table_id'],
                $validated['customer_id'] ?? null,
                $validated['order_ids'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Facture générée',
                'data' => $invoice,
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function show(Invoice $invoice)
    {
        if (!$invoice->is_restaurant) {
            return response()->json([
                'success' => false,
                'message' => 'Facture non trouvée',
            ], Response::HTTP_NOT_FOUND);
        }

        $invoice->load(['restaurantTable', 'server', 'customer', 'invoiceItems', 'payments']);

        return response()->json([
            'success' => true,
            'data' => $invoice,
        ]);
    }

    public function pay(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'payment_method' => 'required|string|in:cash,card,mobile_money,transfer',
            'amount' => 'nullable|numeric|min:0',
        ]);

        try {
            $invoice = $this->invoiceService->payInvoice(
                $invoice->id,
                $validated['payment_method'],
                $validated['amount'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Paiement enregistré',
                'data' => $invoice,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function sendToObr(Invoice $invoice)
    {
        try {
            $result = $this->invoiceService->sendToObr($invoice->id);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'] ?? ($result['success'] ? 'Envoyée à OBR' : 'Erreur OBR'),
                'data' => $invoice->fresh(),
            ], $result['success'] ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function obrStatus(Invoice $invoice)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'obr_submission_status' => $invoice->obr_submission_status,
                'obr_response_message' => $invoice->obr_response_message,
                'obr_invoice_identifier' => $invoice->obr_invoice_identifier,
                'obr_electronic_signature' => $invoice->obr_electronic_signature,
                'obr_sent_at' => $invoice->obr_sent_at,
            ],
        ]);
    }

    public function tableInvoices(int $tableId)
    {
        $invoices = $this->invoiceService->getTableInvoices($tableId);

        return response()->json([
            'success' => true,
            'data' => $invoices,
        ]);
    }

    public function servers()
    {
        $servers = User::where('company_id', auth()->user()->company_id)
            ->where('is_server', true)
            ->get(['id', 'name', 'server_code']);

        return response()->json([
            'success' => true,
            'data' => $servers,
        ]);
    }

    public function dashboard()
    {
        $companyId = auth()->user()->company_id;

        $stats = [
            'tables_occupied' => \App\Models\RestaurantTable::where('company_id', $companyId)
                ->where('status', 'occupied')->count(),
            'tables_free' => \App\Models\RestaurantTable::where('company_id', $companyId)
                ->where('status', 'free')->count(),
            'open_orders' => \App\Models\RestaurantOrder::where('company_id', $companyId)
                ->whereIn('status', ['open', 'served'])->count(),
            'today_revenue' => Invoice::where('company_id', $companyId)
                ->where('is_restaurant', true)
                ->where('payment_status', 'paid')
                ->whereDate('created_at', today())
                ->sum('invoice_total_amount'),
            'pending_obr' => Invoice::where('company_id', $companyId)
                ->where('is_restaurant', true)
                ->where('obr_submission_status', 'PENDING')
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
