<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Models\CashRegister;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashRegisterController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id;

        $query = CashRegister::with(['openedBy', 'closedBy', 'warehouse'])
            ->where('company_id', $companyId);

        if ($request->has('hotel_section')) {
            $hotelSection = $request->hotel_section;
            if ($hotelSection === 'null' || $hotelSection === '') {
                $query->whereNull('hotel_section');
            } else {
                $query->where('hotel_section', $hotelSection);
            }
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('opened_at', [
                Carbon::parse($request->start_date)->startOfDay(),
                Carbon::parse($request->end_date)->endOfDay(),
            ]);
        }

        $registers = $query->orderBy('opened_at', 'desc')->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $registers,
        ]);
    }

    public function current(Request $request)
    {
        $user = $request->user();
        $hotelSection = $request->input('hotel_section');

        $query = CashRegister::with(['openedBy', 'warehouse', 'movements.createdBy'])
            ->where('company_id', $user->company_id)
            ->where('status', 'open');

        if ($hotelSection) {
            $query->where('hotel_section', $hotelSection);
        } else {
            $query->whereNull('hotel_section');
        }

        $register = $query->first();

        if (! $register) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'Aucune caisse ouverte',
            ]);
        }

        // Calculate current balance
        $expectedBalance = $register->calculateExpectedBalance();
        $income = $register->movements()->where('type', 'income')->sum('amount');
        $expense = $register->movements()->where('type', 'expense')->sum('amount');

        return response()->json([
            'success' => true,
            'data' => [
                'register' => $register,
                'summary' => [
                    'opening_balance' => $register->opening_balance,
                    'total_income' => $income,
                    'total_expense' => $expense,
                    'expected_balance' => $expectedBalance,
                    'transaction_count' => $register->movements()->count(),
                ],
            ],
        ]);
    }

    public function open(Request $request)
    {
        $request->validate([
            'opening_balance' => 'required|numeric|min:0',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'hotel_section' => 'nullable|in:restaurant,bar,rooms,conference,reception',
            'opening_note' => 'nullable|string',
        ]);

        $user = $request->user();

        return DB::transaction(function () use ($request, $user) {
            $existingOpen = CashRegister::where('company_id', $user->company_id)
                ->where('status', 'open')
                ->when(
                    is_null($request->hotel_section),
                    fn ($q) => $q->whereNull('hotel_section'),
                    fn ($q) => $q->where('hotel_section', $request->hotel_section)
                )
                ->lockForUpdate()
                ->first();

            if ($existingOpen) {
                $sectionLabel = $request->hotel_section ? ' ('.$request->hotel_section.')' : '';

                return response()->json([
                    'success' => false,
                    'message' => 'Une caisse'.$sectionLabel.' est déjà ouverte. Veuillez la fermer avant d\'en ouvrir une nouvelle.',
                ], 422);
            }

            $register = CashRegister::create([
                'company_id' => $user->company_id,
                'warehouse_id' => $request->warehouse_id,
                'hotel_section' => $request->hotel_section,
                'opened_by' => $user->id,
                'opening_balance' => $request->opening_balance,
                'opened_at' => now(),
                'status' => 'open',
                'opening_note' => $request->opening_note,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Caisse ouverte avec succès',
                'data' => $register->load('openedBy'),
            ], 201);
        });
    }

    public function close(Request $request, $id)
    {
        $request->validate([
            'closing_balance' => 'required|numeric|min:0',
            'closing_note' => 'nullable|string',
        ]);

        $user = $request->user();
        $register = CashRegister::where('company_id', $user->company_id)
            ->where('status', 'open')
            ->findOrFail($id);

        $expectedBalance = $register->calculateExpectedBalance();
        $difference = $request->closing_balance - $expectedBalance;

        $register->update([
            'closed_by' => $user->id,
            'closing_balance' => $request->closing_balance,
            'expected_balance' => $expectedBalance,
            'difference' => $difference,
            'closed_at' => now(),
            'status' => 'closed',
            'closing_note' => $request->closing_note,
        ]);

        // Generate summary
        $income = $register->movements()->where('type', 'income')->sum('amount');
        $expense = $register->movements()->where('type', 'expense')->sum('amount');

        return response()->json([
            'success' => true,
            'message' => 'Caisse fermée avec succès',
            'data' => [
                'register' => $register->fresh()->load(['openedBy', 'closedBy']),
                'summary' => [
                    'opening_balance' => $register->opening_balance,
                    'total_income' => $income,
                    'total_expense' => $expense,
                    'expected_balance' => $expectedBalance,
                    'closing_balance' => $request->closing_balance,
                    'difference' => $difference,
                    'difference_status' => abs($difference) < 0.01 ? 'balanced' : ($difference > 0 ? 'surplus' : 'deficit'),
                ],
            ],
        ]);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        $register = CashRegister::with(['openedBy', 'closedBy', 'warehouse', 'movements.createdBy'])
            ->where('company_id', $user->company_id)
            ->findOrFail($id);

        $income = $register->movements()->where('type', 'income')->sum('amount');
        $expense = $register->movements()->where('type', 'expense')->sum('amount');

        return response()->json([
            'success' => true,
            'data' => [
                'register' => $register,
                'summary' => [
                    'opening_balance' => $register->opening_balance,
                    'total_income' => $income,
                    'total_expense' => $expense,
                    'expected_balance' => $register->calculateExpectedBalance(),
                    'closing_balance' => $register->closing_balance,
                    'difference' => $register->difference,
                ],
            ],
        ]);
    }

    public function addMovement(Request $request, $id)
    {
        $request->validate([
            'type' => 'required|in:income,expense,adjustment',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
            'reference' => 'nullable|string|max:255',
        ]);

        $user = $request->user();
        $register = CashRegister::where('company_id', $user->company_id)
            ->where('status', 'open')
            ->findOrFail($id);

        $movement = CashMovement::create([
            'cash_register_id' => $register->id,
            'type' => $request->type,
            'amount' => $request->amount,
            'description' => $request->description,
            'reference' => $request->reference,
            'created_by' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Mouvement enregistré',
            'data' => $movement->load('createdBy'),
        ], 201);
    }

    public function movements(Request $request, $id)
    {
        $user = $request->user();
        $register = CashRegister::where('company_id', $user->company_id)->findOrFail($id);

        $movements = CashMovement::with(['createdBy', 'invoice', 'payment'])
            ->where('cash_register_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $movements,
        ]);
    }

    public function dailySummary(Request $request)
    {
        $user = $request->user();
        $date = $request->get('date', Carbon::today()->format('Y-m-d'));

        $registers = CashRegister::with(['openedBy', 'closedBy'])
            ->where('company_id', $user->company_id)
            ->whereDate('opened_at', $date)
            ->get();

        $totalOpening = $registers->sum('opening_balance');
        $totalClosing = $registers->where('status', 'closed')->sum('closing_balance');
        $totalDifference = $registers->where('status', 'closed')->sum('difference');

        $movements = CashMovement::whereIn('cash_register_id', $registers->pluck('id'))
            ->get();

        $totalIncome = $movements->where('type', 'income')->sum('amount');
        $totalExpense = $movements->where('type', 'expense')->sum('amount');

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $date,
                'registers_count' => $registers->count(),
                'registers' => $registers,
                'summary' => [
                    'total_opening' => $totalOpening,
                    'total_income' => $totalIncome,
                    'total_expense' => $totalExpense,
                    'total_closing' => $totalClosing,
                    'total_difference' => $totalDifference,
                ],
            ],
        ]);
    }

    /**
     * Global hotel cash summary across all sections with date range filtering.
     */
    public function hotelGlobalSummary(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id;

        $hotelSections = ['restaurant', 'bar', 'rooms', 'conference', 'reception'];
        $isLoss = fn ($m) => str_contains(strtolower($m->description ?? ''), 'perte');

        $registers = CashRegister::where('company_id', $companyId)
            ->whereIn('hotel_section', $hotelSections)
            ->get();

        $registerIds = $registers->pluck('id');

        $movementsQuery = CashMovement::whereIn('cash_register_id', $registerIds);

        if ($request->filled('start_date')) {
            $movementsQuery->where('created_at', '>=', Carbon::parse($request->start_date)->startOfDay());
        }

        if ($request->filled('end_date')) {
            $movementsQuery->where('created_at', '<=', Carbon::parse($request->end_date)->endOfDay());
        }

        $movements = $movementsQuery->get();

        $allExpenses = $movements->where('type', 'expense');
        $totalIncome = $movements->where('type', 'income')->sum('amount');
        $totalLosses = $allExpenses->filter($isLoss)->sum('amount');
        $totalExpenseOnly = $allExpenses->reject($isLoss)->sum('amount');
        $totalProfit = $totalIncome - $totalExpenseOnly - $totalLosses;

        $sectionSummaries = [];
        foreach ($hotelSections as $section) {
            $sectionRegisterIds = $registers->where('hotel_section', $section)->pluck('id');
            $sectionMovements = $movements->whereIn('cash_register_id', $sectionRegisterIds);

            $sectionIncome = $sectionMovements->where('type', 'income')->sum('amount');
            $sectionExpenses = $sectionMovements->where('type', 'expense');
            $sectionLosses = $sectionExpenses->filter($isLoss)->sum('amount');
            $sectionExpenseOnly = $sectionExpenses->reject($isLoss)->sum('amount');

            $sectionSummaries[] = [
                'section' => $section,
                'total_income' => $sectionIncome,
                'total_expense' => $sectionExpenseOnly,
                'total_losses' => $sectionLosses,
                'profit' => $sectionIncome - $sectionExpenseOnly - $sectionLosses,
                'registers_count' => $registers->where('hotel_section', $section)->count(),
            ];
        }

        $perPage = (int) $request->get('per_page', 20);
        $page = (int) $request->get('page', 1);

        $paginatedQuery = CashMovement::with('createdBy')
            ->whereIn('cash_register_id', $registerIds)
            ->orderBy('created_at', 'desc');

        if ($request->filled('start_date')) {
            $paginatedQuery->where('created_at', '>=', Carbon::parse($request->start_date)->startOfDay());
        }

        if ($request->filled('end_date')) {
            $paginatedQuery->where('created_at', '<=', Carbon::parse($request->end_date)->endOfDay());
        }

        $paginatedMovements = $paginatedQuery->paginate($perPage, ['*'], 'page', $page);

        $paginatedMovements->getCollection()->transform(function ($m) use ($registers) {
            $register = $registers->firstWhere('id', $m->cash_register_id);
            $m->hotel_section = $register?->hotel_section;

            return $m;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'global' => [
                    'total_income' => $totalIncome,
                    'total_expense' => $totalExpenseOnly,
                    'total_profit' => $totalProfit,
                    'total_losses' => $totalLosses,
                    'registers_count' => $registers->count(),
                    'open_registers' => $registers->where('status', 'open')->count(),
                ],
                'sections' => $sectionSummaries,
                'movements' => $paginatedMovements,
            ],
        ]);
    }
}
