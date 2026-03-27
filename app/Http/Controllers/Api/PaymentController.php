<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id;

        $query = Payment::with(['invoice', 'createdBy'])
            ->where('company_id', $companyId);

        // Filter by invoice
        if ($request->has('invoice_id')) {
            $query->where('invoice_id', $request->invoice_id);
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('payment_date', [
                Carbon::parse($request->start_date)->startOfDay(),
                Carbon::parse($request->end_date)->endOfDay(),
            ]);
        }

        // Filter by payment method
        if ($request->has('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        $payments = $query->orderBy('payment_date', 'desc')->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $payments,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:cash,bank_transfer,mobile_money,check,card,other',
            'reference' => 'nullable|string|max:255',
            'note' => 'nullable|string',
        ]);

        $user = $request->user();

        DB::beginTransaction();
        try {
            $invoice = Invoice::where('company_id', $user->company_id)
                ->lockForUpdate()
                ->findOrFail($request->invoice_id);

            $remainingAmount = $invoice->invoice_total_amount - $invoice->total_paid;
            if ($request->amount > $remainingAmount) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => "Le montant du paiement ({$request->amount}) dépasse le montant restant ({$remainingAmount})",
                ], 422);
            }
            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'amount' => $request->amount,
                'payment_date' => Carbon::parse($request->payment_date),
                'payment_method' => $request->payment_method,
                'reference' => $request->reference,
                'note' => $request->note,
                'created_by' => $user->id,
                'company_id' => $user->company_id,
            ]);

            // Update invoice payment status
            $invoice->updatePaymentStatus();

            // Sync hotel reservation advance_payment / balance_due when invoice is linked
            $hotelReservation = $invoice->hotelReservation;
            if ($hotelReservation) {
                $hotelReservation->advance_payment = (float) $invoice->fresh()->total_paid;
                $hotelReservation->balance_due = $hotelReservation->total_amount - $hotelReservation->advance_payment;
                $hotelReservation->saveQuietly();
            }

            // Add to cash register if it's a cash payment and there's an open register
            if ($request->payment_method === 'cash') {
                $openRegister = CashRegister::where('company_id', $user->company_id)
                    ->where('status', 'open')
                    ->first();

                if ($openRegister) {
                    CashMovement::create([
                        'cash_register_id' => $openRegister->id,
                        'invoice_id' => $invoice->id,
                        'payment_id' => $payment->id,
                        'type' => 'income',
                        'amount' => $payment->amount,
                        'description' => "Paiement facture #{$invoice->invoice_number}",
                        'reference' => $payment->reference,
                        'created_by' => $user->id,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Paiement enregistré avec succès',
                'data' => $payment->load(['invoice', 'createdBy']),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement du paiement',
            ], 500);
        }
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        $payment = Payment::with(['invoice', 'createdBy'])
            ->where('company_id', $user->company_id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $payment,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $payment = Payment::where('company_id', $user->company_id)->findOrFail($id);

        DB::beginTransaction();
        try {
            $invoice = $payment->invoice;

            CashMovement::where('payment_id', $payment->id)->delete();
            $payment->delete();

            if ($invoice) {
                $invoice->updatePaymentStatus();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Paiement supprimé avec succès',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression',
            ], 500);
        }
    }

    public function invoicePayments(Request $request, $invoiceId)
    {
        $user = $request->user();
        $invoice = Invoice::where('company_id', $user->company_id)->findOrFail($invoiceId);

        $payments = Payment::with('createdBy')
            ->where('invoice_id', $invoiceId)
            ->orderBy('payment_date', 'desc')
            ->get();

        $totalPaid = $payments->sum('amount');
        $remaining = $invoice->invoice_total_amount - $totalPaid;

        return response()->json([
            'success' => true,
            'data' => [
                'invoice' => [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'total_amount' => $invoice->invoice_total_amount,
                    'total_paid' => $totalPaid,
                    'remaining' => $remaining,
                    'payment_status' => $invoice->payment_status,
                ],
                'payments' => $payments,
            ],
        ]);
    }

    public function paymentMethods()
    {
        return response()->json([
            'success' => true,
            'data' => Payment::PAYMENT_METHODS,
        ]);
    }
}
