<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerReminder;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Carbon\Carbon;

class CustomerReminderController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id;

        $query = CustomerReminder::with(['invoice', 'customer', 'createdBy'])
            ->where('company_id', $companyId);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by customer
        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('reminder_date', [
                Carbon::parse($request->start_date)->startOfDay(),
                Carbon::parse($request->end_date)->endOfDay()
            ]);
        }

        // Only pending reminders
        if ($request->boolean('pending_only')) {
            $query->where('status', 'pending');
        }

        // Due today
        if ($request->boolean('due_today')) {
            $query->whereDate('reminder_date', Carbon::today());
        }

        $reminders = $query->orderBy('reminder_date', 'asc')->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $reminders
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'type' => 'required|in:email,sms,phone,letter,other',
            'reminder_date' => 'required|date',
            'next_reminder_date' => 'nullable|date|after:reminder_date',
            'message' => 'nullable|string',
        ]);

        $user = $request->user();
        $invoice = Invoice::where('company_id', $user->company_id)->findOrFail($request->invoice_id);

        // Calculate amount due
        $amountDue = $invoice->invoice_total_amount - $invoice->total_paid;

        if ($amountDue <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cette facture est déjà payée intégralement'
            ], 422);
        }

        // Get last reminder level
        $lastReminder = CustomerReminder::where('invoice_id', $invoice->id)
            ->orderBy('reminder_level', 'desc')
            ->first();

        $reminderLevel = $lastReminder ? $lastReminder->reminder_level + 1 : 1;

        $reminder = CustomerReminder::create([
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'company_id' => $user->company_id,
            'type' => $request->type,
            'status' => 'pending',
            'reminder_level' => $reminderLevel,
            'reminder_date' => Carbon::parse($request->reminder_date),
            'next_reminder_date' => $request->next_reminder_date ? Carbon::parse($request->next_reminder_date) : null,
            'message' => $request->message,
            'amount_due' => $amountDue,
            'created_by' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Relance créée avec succès',
            'data' => $reminder->load(['invoice', 'customer'])
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        $reminder = CustomerReminder::with(['invoice', 'customer', 'createdBy', 'acknowledgedBy'])
            ->where('company_id', $user->company_id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $reminder
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'type' => 'sometimes|in:email,sms,phone,letter,other',
            'status' => 'sometimes|in:pending,sent,acknowledged,paid',
            'reminder_date' => 'sometimes|date',
            'next_reminder_date' => 'nullable|date',
            'message' => 'nullable|string',
            'response_note' => 'nullable|string',
        ]);

        $user = $request->user();
        $reminder = CustomerReminder::where('company_id', $user->company_id)->findOrFail($id);

        $updateData = $request->only(['type', 'status', 'reminder_date', 'next_reminder_date', 'message', 'response_note']);

        // If status changed to acknowledged
        if ($request->status === 'acknowledged' && $reminder->status !== 'acknowledged') {
            $updateData['acknowledged_by'] = $user->id;
            $updateData['acknowledged_at'] = now();
        }

        $reminder->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Relance mise à jour',
            'data' => $reminder->fresh()->load(['invoice', 'customer'])
        ]);
    }

    public function markAsSent(Request $request, $id)
    {
        $user = $request->user();
        $reminder = CustomerReminder::where('company_id', $user->company_id)->findOrFail($id);

        $reminder->update(['status' => 'sent']);

        return response()->json([
            'success' => true,
            'message' => 'Relance marquée comme envoyée',
            'data' => $reminder
        ]);
    }

    public function markAsPaid(Request $request, $id)
    {
        $user = $request->user();
        $reminder = CustomerReminder::where('company_id', $user->company_id)->findOrFail($id);

        $reminder->update(['status' => 'paid']);

        return response()->json([
            'success' => true,
            'message' => 'Facture marquée comme payée',
            'data' => $reminder
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $reminder = CustomerReminder::where('company_id', $user->company_id)->findOrFail($id);

        $reminder->delete();

        return response()->json([
            'success' => true,
            'message' => 'Relance supprimée'
        ]);
    }

    public function unpaidInvoices(Request $request)
    {
        $user = $request->user();

        $invoices = Invoice::with('customer')
            ->where('company_id', $user->company_id)
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->orderBy('invoice_date', 'asc')
            ->get()
            ->map(function ($invoice) {
                $lastReminder = CustomerReminder::where('invoice_id', $invoice->id)
                    ->orderBy('reminder_date', 'desc')
                    ->first();

                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'invoice_date' => $invoice->invoice_date,
                    'due_date' => $invoice->due_date,
                    'customer_name' => $invoice->customer_name,
                    'customer_id' => $invoice->customer_id,
                    'total_amount' => $invoice->invoice_total_amount,
                    'total_paid' => $invoice->total_paid,
                    'amount_due' => $invoice->invoice_total_amount - $invoice->total_paid,
                    'payment_status' => $invoice->payment_status,
                    'days_overdue' => $invoice->due_date ? Carbon::parse($invoice->due_date)->diffInDays(now(), false) : null,
                    'last_reminder' => $lastReminder ? [
                        'date' => $lastReminder->reminder_date,
                        'type' => $lastReminder->type,
                        'status' => $lastReminder->status,
                        'level' => $lastReminder->reminder_level,
                    ] : null,
                    'reminder_count' => CustomerReminder::where('invoice_id', $invoice->id)->count(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'invoices' => $invoices,
                'summary' => [
                    'total_unpaid' => $invoices->count(),
                    'total_amount_due' => $invoices->sum('amount_due'),
                    'overdue_count' => $invoices->where('days_overdue', '>', 0)->count(),
                ]
            ]
        ]);
    }

    public function stats(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id;

        $pending = CustomerReminder::where('company_id', $companyId)->where('status', 'pending')->count();
        $sent = CustomerReminder::where('company_id', $companyId)->where('status', 'sent')->count();
        $acknowledged = CustomerReminder::where('company_id', $companyId)->where('status', 'acknowledged')->count();
        $paid = CustomerReminder::where('company_id', $companyId)->where('status', 'paid')->count();

        $dueTodayCount = CustomerReminder::where('company_id', $companyId)
            ->where('status', 'pending')
            ->whereDate('reminder_date', Carbon::today())
            ->count();

        $overdueCount = CustomerReminder::where('company_id', $companyId)
            ->where('status', 'pending')
            ->whereDate('reminder_date', '<', Carbon::today())
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'by_status' => [
                    'pending' => $pending,
                    'sent' => $sent,
                    'acknowledged' => $acknowledged,
                    'paid' => $paid,
                ],
                'due_today' => $dueTodayCount,
                'overdue' => $overdueCount,
                'total' => $pending + $sent + $acknowledged + $paid,
            ]
        ]);
    }
}
