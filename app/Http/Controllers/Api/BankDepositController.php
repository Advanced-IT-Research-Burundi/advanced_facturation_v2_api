<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BankDeposit;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\Depense;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BankDepositController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->get('per_page', 15), 100));
        $user = $request->user();

        $query = BankDeposit::with(['cashRegister.openedBy', 'createdBy'])
            ->where('company_id', $user->company_id);

        if ($request->filled('cash_register_id')) {
            $query->where('cash_register_id', $request->cash_register_id);
        }

        if ($request->filled('bank_name')) {
            $query->where('bank_name', 'like', '%'.$request->bank_name.'%');
        }

        if ($request->filled('start_date')) {
            $query->whereDate('deposit_date', '>=', Carbon::parse($request->start_date)->toDateString());
        }

        if ($request->filled('end_date')) {
            $query->whereDate('deposit_date', '<=', Carbon::parse($request->end_date)->toDateString());
        }

        $deposits = $query->orderBy('deposit_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $deposits,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'cash_register_id' => 'required|exists:cash_registers,id',
            'amount' => 'required|numeric|min:0.01',
            'deposit_date' => 'required|date',
            'bank_name' => 'required|string|max:255',
            'account_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:255',
            'reference' => 'nullable|string|max:255',
            'note' => 'nullable|string',
        ]);

        $user = $request->user();

        return DB::transaction(function () use ($request, $user) {
            $register = CashRegister::where('company_id', $user->company_id)
                ->where('status', 'open')
                ->lockForUpdate()
                ->findOrFail($request->cash_register_id);

            $expectedBalance = $register->calculateExpectedBalance();
            if ((float) $request->amount > $expectedBalance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le montant du versement dépasse le solde actuel de la caisse.',
                ], 422);
            }

            $deposit = BankDeposit::create([
                'company_id' => $user->company_id,
                'cash_register_id' => $register->id,
                'created_by' => $user->id,
                'amount' => $request->amount,
                'deposit_date' => Carbon::parse($request->deposit_date)->toDateString(),
                'bank_name' => $request->bank_name,
                'account_name' => $request->account_name,
                'account_number' => $request->account_number,
                'reference' => $request->reference,
                'note' => $request->note,
            ]);

            CashMovement::create([
                'cash_register_id' => $register->id,
                'bank_deposit_id' => $deposit->id,
                'type' => 'expense',
                'amount' => $deposit->amount,
                'description' => 'Versement banque - '.$deposit->bank_name,
                'reference' => $deposit->reference,
                'created_by' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Versement bancaire enregistré avec succès',
                'data' => $deposit->load(['cashRegister.openedBy', 'createdBy', 'cashMovement']),
            ], 201);
        });
    }

    public function show(Request $request, BankDeposit $bankDeposit)
    {
        abort_unless($bankDeposit->company_id === $request->user()->company_id, 404);

        return response()->json([
            'success' => true,
            'data' => $bankDeposit->load(['cashRegister.openedBy', 'createdBy', 'cashMovement']),
        ]);
    }

    public function destroy(Request $request, BankDeposit $bankDeposit)
    {
        abort_unless($bankDeposit->company_id === $request->user()->company_id, 404);

        DB::transaction(function () use ($bankDeposit) {
            CashMovement::where('bank_deposit_id', $bankDeposit->id)->delete();
            $bankDeposit->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Versement bancaire supprimé avec succès',
        ]);
    }

    public function summary(Request $request)
    {
        $user = $request->user();

        $query = BankDeposit::where('company_id', $user->company_id);
        $salesQuery = Invoice::where('company_id', $user->company_id)
            ->where('invoice_type', 'FN')
            ->where('invoice_identifier', 'POS')
            ->where('is_cancelled', false);
        $expensesQuery = Depense::where('company_id', $user->company_id)
            ->whereNull('hotel_section');

        if ($request->filled('start_date')) {
            $query->whereDate('deposit_date', '>=', Carbon::parse($request->start_date)->toDateString());
            $salesQuery->whereDate('invoice_date', '>=', Carbon::parse($request->start_date)->toDateString());
            $expensesQuery->whereDate('created_at', '>=', Carbon::parse($request->start_date)->toDateString());
        }

        if ($request->filled('end_date')) {
            $query->whereDate('deposit_date', '<=', Carbon::parse($request->end_date)->toDateString());
            $salesQuery->whereDate('invoice_date', '<=', Carbon::parse($request->end_date)->toDateString());
            $expensesQuery->whereDate('created_at', '<=', Carbon::parse($request->end_date)->toDateString());
        }

        $todayTotal = BankDeposit::where('company_id', $user->company_id)
            ->whereDate('deposit_date', Carbon::today()->toDateString())
            ->sum('amount');

        return response()->json([
            'success' => true,
            'data' => [
                'total_amount' => (float) (clone $query)->sum('amount'),
                'deposits_count' => (clone $query)->count(),
                'today_amount' => (float) $todayTotal,
                'expenses_total' => (float) $expensesQuery->sum('montant'),
                'sales_by_payment' => [
                    'cash' => (float) (clone $salesQuery)
                        ->where('payment_type', 'cash')
                        ->sum('invoice_total_amount'),
                    'bank' => (float) (clone $salesQuery)
                        ->where('payment_type', 'bank_transfer')
                        ->sum('invoice_total_amount'),
                    'credit' => (float) (clone $salesQuery)
                        ->where('payment_type', 'credit')
                        ->sum('invoice_total_amount'),
                ],
                'latest_deposit' => (clone $query)
                    ->with(['createdBy', 'cashRegister'])
                    ->orderBy('deposit_date', 'desc')
                    ->orderBy('created_at', 'desc')
                    ->first(),
            ],
        ]);
    }
}
