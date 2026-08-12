<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashRegisterController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->get('per_page', 15), 100));
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

        $registers = $query->orderBy('opened_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $registers,
        ]);
    }

    public function current(Request $request)
    {
        $user = $request->user();
        $hotelSection = $request->input('hotel_section');
        $isAdmin = $this->isAdminUser($user);

        $query = CashRegister::with([
                'openedBy',
                'warehouse',
                'movements' => function ($query) use ($isAdmin, $user) {
                    $query->with('createdBy')
                        ->when(! $isAdmin, fn ($q) => $q->where('created_by', $user->id));
                },
            ])
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
        $isAdmin = $this->isAdminUser($user);

        $register = CashRegister::with([
                'openedBy',
                'closedBy',
                'warehouse',
                'movements' => function ($query) use ($isAdmin, $user) {
                    $query->with('createdBy')
                        ->when(! $isAdmin, fn ($q) => $q->where('created_by', $user->id));
                },
            ])
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
        $isAdmin = $this->isAdminUser($user);
        $perPage = max(1, min((int) $request->get('per_page', 50), 100));

        $movements = CashMovement::with(['createdBy', 'invoice', 'payment'])
            ->where('cash_register_id', $id)
            ->when(! $isAdmin, fn ($query) => $query->where('created_by', $user->id))
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $movements,
        ]);
    }

    public function dailySummary(Request $request)
    {
        $user = $request->user();
        $date = $request->get('date', Carbon::today()->format('Y-m-d'));
        $dayStart = Carbon::parse($date)->startOfDay();
        $dayEnd = Carbon::parse($date)->endOfDay();
        $isAdmin = $this->isAdminUser($user);
        $selectedUserId = $request->input('user_id');
        $filteredUserId = null;

        if (! $isAdmin) {
            $filteredUserId = $user->id;
        } elseif ($selectedUserId && $selectedUserId !== 'all') {
            $filteredUserId = User::where('company_id', $user->company_id)
                ->whereKey($selectedUserId)
                ->value('id') ?? 0;
        }

        $registers = CashRegister::with(['openedBy', 'closedBy'])
            ->where('company_id', $user->company_id)
            ->when($request->has('hotel_section'), function ($query) use ($request) {
                $hotelSection = $request->hotel_section;

                if ($hotelSection === 'null' || $hotelSection === '') {
                    $query->whereNull('hotel_section');
                } else {
                    $query->where('hotel_section', $hotelSection);
                }
            })
            ->whereDate('opened_at', $date)
            ->get();

        $companyRegisters = CashRegister::where('company_id', $user->company_id)
            ->when($request->has('hotel_section'), function ($query) use ($request) {
                $hotelSection = $request->hotel_section;

                if ($hotelSection === 'null' || $hotelSection === '') {
                    $query->whereNull('hotel_section');
                } else {
                    $query->where('hotel_section', $hotelSection);
                }
            })
            ->pluck('id');

        $totalOpening = $registers->sum('opening_balance');
        $totalClosing = $registers->where('status', 'closed')->sum('closing_balance');
        $totalDifference = $registers->where('status', 'closed')->sum('difference');

        $movementSummary = CashMovement::whereIn('cash_register_id', $companyRegisters)
            ->whereBetween('created_at', [$dayStart, $dayEnd])
            ->when(! is_null($filteredUserId), fn ($query) => $query->where('created_by', $filteredUserId))
            ->selectRaw("
                COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as total_income,
                COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as total_expense,
                COALESCE(SUM(CASE WHEN type = 'adjustment' THEN amount ELSE 0 END), 0) as total_adjustment
            ")
            ->first();

        $totalIncome = (float) ($movementSummary->total_income ?? 0);
        $totalExpense = (float) ($movementSummary->total_expense ?? 0);
        $totalAdjustment = (float) ($movementSummary->total_adjustment ?? 0);

        $userSummaries = CashMovement::with('createdBy:id,name,email')
            ->whereIn('cash_register_id', $companyRegisters)
            ->whereBetween('created_at', [$dayStart, $dayEnd])
            ->when(! is_null($filteredUserId), fn ($query) => $query->where('created_by', $filteredUserId))
            ->select('created_by')
            ->selectRaw("
                COUNT(*) as transaction_count,
                COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as total_income,
                COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as total_expense,
                COALESCE(SUM(CASE WHEN type = 'adjustment' THEN amount ELSE 0 END), 0) as total_adjustment,
                MAX(created_at) as latest_transaction_at
            ")
            ->groupBy('created_by')
            ->orderByDesc(DB::raw("COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0)"))
            ->get()
            ->map(function ($row) {
                $totalIncome = (float) ($row->total_income ?? 0);
                $totalExpense = (float) ($row->total_expense ?? 0);
                $totalAdjustment = (float) ($row->total_adjustment ?? 0);
                $netAmount = $totalIncome - $totalExpense + $totalAdjustment;

                return [
                    'user_id' => $row->created_by,
                    'user_name' => $row->createdBy?->name ?? 'Utilisateur supprimé',
                    'user_email' => $row->createdBy?->email,
                    'transaction_count' => (int) $row->transaction_count,
                    'total_income' => $totalIncome,
                    'total_expense' => $totalExpense,
                    'total_adjustment' => $totalAdjustment,
                    'net_amount' => $netAmount,
                    'cash_amount' => $netAmount,
                    'latest_transaction_at' => $row->latest_transaction_at,
                ];
            })
            ->values();

        $recentMovements = CashMovement::with([
                'createdBy:id,name,email',
                'cashRegister:id,opened_by,opened_at,status',
                'cashRegister.openedBy:id,name',
            ])
            ->whereIn('cash_register_id', $companyRegisters)
            ->whereBetween('created_at', [$dayStart, $dayEnd])
            ->when(! is_null($filteredUserId), fn ($query) => $query->where('created_by', $filteredUserId))
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $date,
                'filtered_user_id' => $filteredUserId,
                'can_filter_users' => $isAdmin,
                'registers_count' => $registers->count(),
                'registers' => $registers,
                'summary' => [
                    'total_opening' => $totalOpening,
                    'total_income' => $totalIncome,
                    'total_expense' => $totalExpense,
                    'total_adjustment' => $totalAdjustment,
                    'net_amount' => $totalIncome - $totalExpense + $totalAdjustment,
                    'total_closing' => $totalClosing,
                    'total_difference' => $totalDifference,
                    'total_transactions' => $userSummaries->sum('transaction_count'),
                ],
                'user_summaries' => $userSummaries,
                'recent_movements' => $recentMovements,
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

        $summaryRow = (clone $movementsQuery)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as total_income,
                COALESCE(SUM(CASE WHEN type = 'expense' AND LOWER(COALESCE(description, '')) LIKE '%perte%' THEN amount ELSE 0 END), 0) as total_losses,
                COALESCE(SUM(CASE WHEN type = 'expense' AND LOWER(COALESCE(description, '')) NOT LIKE '%perte%' THEN amount ELSE 0 END), 0) as total_expense
            ")
            ->first();

        $totalIncome = (float) ($summaryRow->total_income ?? 0);
        $totalLosses = (float) ($summaryRow->total_losses ?? 0);
        $totalExpenseOnly = (float) ($summaryRow->total_expense ?? 0);
        $totalProfit = $totalIncome - $totalExpenseOnly - $totalLosses;

        $sectionSummaries = [];
        foreach ($hotelSections as $section) {
            $sectionRegisterIds = $registers->where('hotel_section', $section)->pluck('id');
            $sectionSummaryRow = CashMovement::whereIn('cash_register_id', $sectionRegisterIds)
                ->when(
                    $request->filled('start_date'),
                    fn ($q) => $q->where('created_at', '>=', Carbon::parse($request->start_date)->startOfDay())
                )
                ->when(
                    $request->filled('end_date'),
                    fn ($q) => $q->where('created_at', '<=', Carbon::parse($request->end_date)->endOfDay())
                )
                ->selectRaw("
                    COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as total_income,
                    COALESCE(SUM(CASE WHEN type = 'expense' AND LOWER(COALESCE(description, '')) LIKE '%perte%' THEN amount ELSE 0 END), 0) as total_losses,
                    COALESCE(SUM(CASE WHEN type = 'expense' AND LOWER(COALESCE(description, '')) NOT LIKE '%perte%' THEN amount ELSE 0 END), 0) as total_expense
                ")
                ->first();

            $sectionIncome = (float) ($sectionSummaryRow->total_income ?? 0);
            $sectionLosses = (float) ($sectionSummaryRow->total_losses ?? 0);
            $sectionExpenseOnly = (float) ($sectionSummaryRow->total_expense ?? 0);

            $sectionSummaries[] = [
                'section' => $section,
                'total_income' => $sectionIncome,
                'total_expense' => $sectionExpenseOnly,
                'total_losses' => $sectionLosses,
                'profit' => $sectionIncome - $sectionExpenseOnly - $sectionLosses,
                'registers_count' => $registers->where('hotel_section', $section)->count(),
            ];
        }

        $perPage = max(1, min((int) $request->get('per_page', 20), 100));
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

    private function isAdminUser(User $user): bool
    {
        return $user->roles->contains(function ($role) {
            $name = strtolower($role->name ?? '');
            $label = strtolower($role->label ?? '');

            return in_array($name, ['admin', 'super_admin'])
                || in_array($label, ['administrateur', 'super administrateur']);
        });
    }
}
