<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\Invoice;
use App\Models\StockMovement;
use App\Models\WarehouseProduct;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Rapport des ventes
     */
    public function sales(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id;

        $startDate = $request->get('date_from', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->get('date_to', Carbon::now()->endOfMonth()->toDateString());
        $userId = $request->get('user_id');
        $warehouseId = $request->get('warehouse_id');

        $query = Invoice::query()
            ->where('company_id', $companyId)
            ->whereBetween('created_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ])
            ->where('invoice_type', 'FN');

        if ($userId && $userId !== 'all') {
            $query->where('user_id', $userId);
        }

        if ($warehouseId && $warehouseId !== 'all') {
            $query->whereHas('stockMovements', function ($q) use ($warehouseId) {
                $q->where('warehouse_id', $warehouseId);
            });
        }

        $totalRevenue = (clone $query)->sum('invoice_total_amount');
        $totalInvoices = (clone $query)->count();
        $averageBasket = $totalInvoices > 0 ? $totalRevenue / $totalInvoices : 0;

        $dailySales = (clone $query)
            ->selectRaw('DATE(created_at) as date, SUM(invoice_total_amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $salesByUser = (clone $query)
            ->select('user_id', DB::raw('SUM(invoice_total_amount) as total'))
            ->with('user:id,name')
            ->groupBy('user_id')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->user->name ?? 'Inconnu',
                    'total' => (float) $item->total,
                ];
            });

        $salesByWarehouse = [];
        if (! $warehouseId || $warehouseId === 'all') {
            $salesByWarehouse = StockMovement::query()
                ->where('company_id', $companyId)
                ->whereBetween('created_at', [
                    Carbon::parse($startDate)->startOfDay(),
                    Carbon::parse($endDate)->endOfDay(),
                ])
                ->where('item_movement_type', 'SN')
                ->select('warehouse_id', DB::raw('SUM(item_quantity * item_purchase_or_sale_price) as total'))
                ->with('warehouse:id,name')
                ->groupBy('warehouse_id')
                ->get()
                ->map(function ($item) {
                    return [
                        'name' => $item->warehouse->name ?? 'Inconnu',
                        'total' => (float) $item->total,
                    ];
                });
        }

        return response()->json([
            'success' => true,
            'data' => [
                'metrics' => [
                    'revenue' => $totalRevenue,
                    'count' => $totalInvoices,
                    'average_basket' => $averageBasket,
                ],
                'daily_sales' => $dailySales,
                'sales_by_user' => $salesByUser,
                'sales_by_warehouse' => $salesByWarehouse,
            ],
        ]);
    }

    /**
     * Historique des factures avec détails
     */
    public function invoicesHistory(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id;

        $startDate = $request->get('date_from', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->get('date_to', Carbon::now()->toDateString());
        $invoiceType = $request->get('invoice_type'); // FN, FA, RC, etc.
        $paymentMode = $request->get('payment_mode');

        $query = Invoice::query()
            ->where('company_id', $companyId)
            ->where(function ($q) {
                $q->where('is_cancelled', false)->orWhereNull('is_cancelled');
            })
            ->whereBetween('created_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ])
            ->with(['invoiceItems.product', 'customer', 'user']);

        if ($invoiceType && $invoiceType !== 'all') {
            $query->where('invoice_type', $invoiceType);
        }

        // Statistiques globales
        $totalInvoices = (clone $query)->count();
        $totalAmountTVAC = (clone $query)->sum('invoice_total_amount');
        $totalTVA = (clone $query)->sum('invoice_vat_amount');
        $totalHTVA = (clone $query)->sum('invoice_amount_nvat');

        // Liste des factures avec items
        $invoices = $query->orderBy('created_at', 'desc')->get()->map(function ($invoice) {
            return [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'date' => $invoice->created_at->format('d/m/Y H:i'),
                'customer_name' => $invoice->customer?->customer_name ?? $invoice->customer_name ?? 'Client Anonyme',
                'amount_htva' => $invoice->invoice_amount_nvat,
                'tva' => $invoice->invoice_vat_amount,
                'amount_tvac' => $invoice->invoice_total_amount,
                'invoice_type' => $invoice->invoice_type,
                'invoice_type_label' => $this->getInvoiceTypeLabel($invoice->invoice_type),
                'payment_mode' => $invoice->payment_type ?? 'cash',
                'status' => $invoice->obr_submission_status ?? 'pending',
                'user_name' => $invoice->user?->name ?? 'Inconnu',
                'items' => $invoice->invoiceItems->map(function ($item) {
                    return [
                        'product_name' => $item->product?->name ?? $item->item_designation ?? 'Produit',
                        'quantity' => $item->item_quantity,
                        'unit_price' => $item->item_price,
                        'total' => $item->item_total_amount,
                    ];
                }),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'date_from' => $startDate,
                    'date_to' => $endDate,
                    'total_invoices' => $totalInvoices,
                    'total_amount_tvac' => $totalAmountTVAC,
                    'total_tva' => $totalTVA,
                    'total_htva' => $totalHTVA,
                ],
                'invoices' => $invoices,
            ],
        ]);
    }

    /**
     * Fiche de stock - État actuel des stocks
     */
    public function stockSheet(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id;
        $warehouseId = $request->get('warehouse_id');

        $query = WarehouseProduct::query()
            ->whereHas('warehouse', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })
            ->with(['product', 'warehouse']);

        if ($warehouseId && $warehouseId !== 'all') {
            $query->where('warehouse_id', $warehouseId);
        }

        $stockItems = $query->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name ?? 'Produit inconnu',
                'product_code' => $item->product?->code ?? '',
                'warehouse_name' => $item->warehouse?->name ?? 'Stock inconnu',
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'total_value' => $item->quantity * $item->unit_price,
                'currency' => $item->currency ?? 'FBU',
            ];
        });

        $totalItems = $stockItems->count();
        $totalQuantity = $stockItems->sum('quantity');
        $totalValue = $stockItems->sum('total_value');

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_items' => $totalItems,
                    'total_quantity' => $totalQuantity,
                    'total_value' => $totalValue,
                ],
                'items' => $stockItems,
            ],
        ]);
    }

    /**
     * Mouvements de stock (entrées et sorties)
     */
    public function stockMovements(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id;

        $startDate = $request->get('date_from', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->get('date_to', Carbon::now()->toDateString());
        $warehouseId = $request->get('warehouse_id');
        $movementType = $request->get('movement_type'); // EN (entrée), SN (sortie), all

        $query = StockMovement::query()
            ->where('company_id', $companyId)
            ->whereBetween('created_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ])
            ->with(['product', 'warehouse', 'user']);

        if ($warehouseId && $warehouseId !== 'all') {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($movementType && $movementType !== 'all') {
            if ($movementType === 'entry') {
                $query->whereIn('item_movement_type', ['EN', 'EI', 'ER', 'ET']); // Entrées
            } elseif ($movementType === 'exit') {
                $query->whereIn('item_movement_type', ['SN', 'SC', 'SD', 'SP', 'ST']); // Sorties
            }
        }

        // Statistiques
        $totalMovements = (clone $query)->count();

        $entriesQuery = (clone $query)->whereIn('item_movement_type', ['EN', 'EI', 'ER', 'ET']);
        $totalEntries = $entriesQuery->count();
        $totalEntriesValue = $entriesQuery->sum(DB::raw('item_quantity * item_purchase_or_sale_price'));

        $exitsQuery = (clone $query)->whereIn('item_movement_type', ['SN', 'SC', 'SD', 'SP', 'ST']);
        $totalExits = $exitsQuery->count();
        $totalExitsValue = $exitsQuery->sum(DB::raw('item_quantity * item_purchase_or_sale_price'));

        $movements = $query->orderBy('created_at', 'desc')->get()->map(function ($movement) {
            return [
                'id' => $movement->id,
                'date' => $movement->created_at->format('d/m/Y H:i'),
                'product_name' => $movement->product?->name ?? $movement->item_designation ?? 'Produit',
                'product_code' => $movement->item_code,
                'warehouse_name' => $movement->warehouse?->name ?? 'Stock inconnu',
                'quantity' => $movement->item_quantity,
                'unit_price' => $movement->item_purchase_or_sale_price,
                'total' => $movement->item_quantity * $movement->item_purchase_or_sale_price,
                'movement_type' => $movement->item_movement_type,
                'movement_type_label' => $this->getMovementTypeLabel($movement->item_movement_type),
                'is_entry' => in_array($movement->item_movement_type, ['EN', 'EI', 'ER', 'ET']),
                'description' => $movement->item_movement_description,
                'invoice_ref' => $movement->item_movement_invoice_ref,
                'user_name' => $movement->user?->name ?? 'Inconnu',
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'date_from' => $startDate,
                    'date_to' => $endDate,
                    'total_movements' => $totalMovements,
                    'total_entries' => $totalEntries,
                    'total_entries_value' => $totalEntriesValue,
                    'total_exits' => $totalExits,
                    'total_exits_value' => $totalExitsValue,
                ],
                'movements' => $movements,
            ],
        ]);
    }

    /**
     * Historique des entrées en stock uniquement
     */
    public function stockEntries(Request $request)
    {
        $request->merge(['movement_type' => 'entry']);

        return $this->stockMovements($request);
    }

    /**
     * Factures à crédit
     */
    public function creditInvoices(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id;

        $startDate = $request->get('date_from', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->get('date_to', Carbon::now()->toDateString());

        $query = Invoice::query()
            ->where('company_id', $companyId)
            ->whereBetween('created_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ])
            ->where('invoice_type', 'FC') // Facture à crédit
            ->with(['customer', 'user', 'invoiceItems']);

        $totalInvoices = (clone $query)->count();
        $totalAmount = (clone $query)->sum('invoice_total_amount');

        $invoices = $query->orderBy('created_at', 'desc')->get()->map(function ($invoice) {
            return [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'date' => $invoice->created_at->format('d/m/Y H:i'),
                'customer_name' => $invoice->customer?->customer_name ?? $invoice->customer_name ?? 'Client Anonyme',
                'amount_htva' => $invoice->invoice_amount_nvat,
                'tva' => $invoice->invoice_vat_amount,
                'amount_tvac' => $invoice->invoice_total_amount,
                'status' => $invoice->obr_submission_status ?? 'pending',
                'user_name' => $invoice->user?->name ?? 'Inconnu',
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'date_from' => $startDate,
                    'date_to' => $endDate,
                    'total_invoices' => $totalInvoices,
                    'total_amount' => $totalAmount,
                ],
                'invoices' => $invoices,
            ],
        ]);
    }

    /**
     * Proformas
     */
    public function proformas(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id;

        $startDate = $request->get('date_from', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->get('date_to', Carbon::now()->toDateString());

        $query = Invoice::query()
            ->where('company_id', $companyId)
            ->whereBetween('created_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ])
            ->where('invoice_type', 'FP') // Proforma
            ->with(['customer', 'user', 'invoiceItems']);

        $totalProformas = (clone $query)->count();
        $totalAmount = (clone $query)->sum('invoice_total_amount');

        $proformas = $query->orderBy('created_at', 'desc')->get()->map(function ($invoice) {
            return [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'date' => $invoice->created_at->format('d/m/Y H:i'),
                'customer_name' => $invoice->customer?->customer_name ?? $invoice->customer_name ?? 'Client Anonyme',
                'amount_htva' => $invoice->invoice_amount_nvat,
                'tva' => $invoice->invoice_vat_amount,
                'amount_tvac' => $invoice->invoice_total_amount,
                'status' => $invoice->obr_submission_status ?? 'pending',
                'user_name' => $invoice->user?->name ?? 'Inconnu',
                'items_count' => $invoice->invoiceItems->count(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'date_from' => $startDate,
                    'date_to' => $endDate,
                    'total_proformas' => $totalProformas,
                    'total_amount' => $totalAmount,
                ],
                'proformas' => $proformas,
            ],
        ]);
    }

    /**
     * Récupérer les factures pour impression multiple
     */
    public function invoicesForPrint(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id;

        $ids = $request->get('ids', []);

        if (empty($ids)) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune facture sélectionnée',
            ], 400);
        }

        $invoices = Invoice::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $ids)
            ->with(['invoiceItems.product', 'customer', 'user', 'company'])
            ->get()
            ->map(function ($invoice) {
                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'invoice_date' => $invoice->invoice_date?->format('d/m/Y') ?? $invoice->created_at->format('d/m/Y'),
                    'customer' => [
                        'name' => $invoice->customer?->customer_name ?? $invoice->customer_name ?? 'Client Anonyme',
                        'tin' => $invoice->customer?->customer_TIN ?? $invoice->customer_TIN ?? '',
                        'address' => $invoice->customer?->customer_address ?? $invoice->customer_address ?? '',
                    ],
                    'company' => [
                        'name' => $invoice->company?->name ?? '',
                        'tin' => $invoice->company?->tp_TIN ?? $invoice->tp_TIN ?? '',
                        'address' => $invoice->company?->address ?? '',
                    ],
                    'items' => $invoice->invoiceItems->map(function ($item) {
                        return [
                            'designation' => $item->product?->name ?? $item->item_designation ?? 'Produit',
                            'quantity' => $item->item_quantity,
                            'unit_price' => $item->item_price,
                            'vat' => $item->item_ct ?? 0,
                            'total' => $item->item_total_amount,
                        ];
                    }),
                    'amount_htva' => $invoice->invoice_amount_nvat,
                    'tva' => $invoice->invoice_vat_amount,
                    'amount_tvac' => $invoice->invoice_total_amount,
                    'invoice_type' => $invoice->invoice_type,
                    'electronic_signature' => $invoice->electronic_signature,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $invoices,
        ]);
    }

    /**
     * Rapport de balance de caisse journalière.
     *
     * Retourne pour chaque jour : solde reportée, entrées, sorties (dépenses + pertes), solde actuel.
     */
    public function cashBalance(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id;

        $startDate = Carbon::parse($request->get('date_from', Carbon::now()->startOfMonth()->toDateString()))->startOfDay();
        $endDate = Carbon::parse($request->get('date_to', Carbon::now()->toDateString()))->endOfDay();
        $hotelSection = $request->get('hotel_section');

        $registersQuery = CashRegister::where('company_id', $companyId);

        if ($hotelSection && $hotelSection !== 'all') {
            if ($hotelSection === 'general') {
                $registersQuery->whereNull('hotel_section');
            } else {
                $registersQuery->where('hotel_section', $hotelSection);
            }
        }

        $registerIds = $registersQuery->pluck('id');

        $movementsBeforePeriod = CashMovement::whereIn('cash_register_id', $registerIds)
            ->where('created_at', '<', $startDate)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as total_income,
                COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as total_expense
            ")
            ->first();

        $initialBalance = ($movementsBeforePeriod->total_income ?? 0) - ($movementsBeforePeriod->total_expense ?? 0);

        $isLoss = fn (string $desc): bool => str_contains(strtolower($desc), 'perte');

        $dailyMovements = CashMovement::whereIn('cash_register_id', $registerIds)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                DATE(created_at) as date,
                type,
                amount,
                description
            ')
            ->orderBy('date')
            ->get();

        $grouped = $dailyMovements->groupBy('date');

        $rows = [];
        $runningBalance = $initialBalance;

        $currentDate = $startDate->copy();
        $end = $endDate->copy()->startOfDay();

        $totalIncome = 0;
        $totalExpenses = 0;
        $totalLosses = 0;

        while ($currentDate->lte($end)) {
            $dateKey = $currentDate->toDateString();
            $dayMovements = $grouped->get($dateKey, collect());

            $dayIncome = $dayMovements->where('type', 'income')->sum('amount');
            $dayExpenseAll = $dayMovements->where('type', 'expense');
            $dayLosses = $dayExpenseAll->filter(fn ($m) => $isLoss($m->description ?? ''))->sum('amount');
            $dayExpenses = $dayExpenseAll->reject(fn ($m) => $isLoss($m->description ?? ''))->sum('amount');

            $dayTotalOut = $dayExpenses + $dayLosses;
            $carriedBalance = $runningBalance;
            $runningBalance = $carriedBalance + $dayIncome - $dayTotalOut;

            $totalIncome += $dayIncome;
            $totalExpenses += $dayExpenses;
            $totalLosses += $dayLosses;

            if ($dayIncome > 0 || $dayTotalOut > 0 || $carriedBalance != 0) {
                $rows[] = [
                    'date' => $currentDate->format('d/m/Y'),
                    'carried_balance' => $carriedBalance,
                    'income' => $dayIncome,
                    'expenses' => $dayExpenses,
                    'losses' => $dayLosses,
                    'total_out' => $dayTotalOut,
                    'current_balance' => $runningBalance,
                ];
            }

            $currentDate->addDay();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'date_from' => $startDate->toDateString(),
                    'date_to' => $endDate->toDateString(),
                    'initial_balance' => $initialBalance,
                    'total_income' => $totalIncome,
                    'total_expenses' => $totalExpenses,
                    'total_losses' => $totalLosses,
                    'final_balance' => $runningBalance,
                ],
                'rows' => $rows,
            ],
        ]);
    }

    /**
     * Helper: Label du type de facture
     */
    private function getInvoiceTypeLabel($type)
    {
        $labels = [
            'FN' => 'Facture Normale',
            'FA' => 'Facture Avoir',
            'FC' => 'Facture à Crédit',
            'FP' => 'Proforma',
            'RC' => 'Reçu de Caisse',
        ];

        return $labels[$type] ?? $type;
    }

    /**
     * Helper: Label du type de mouvement
     */
    private function getMovementTypeLabel($type)
    {
        $labels = [
            'EN' => 'Entrée Normale',
            'EI' => 'Entrée Inventaire',
            'ER' => 'Entrée Retour',
            'ET' => 'Entrée Transfert',
            'SN' => 'Sortie Normale (Vente)',
            'SC' => 'Sortie Casse',
            'SD' => 'Sortie Don',
            'SP' => 'Sortie Perte',
            'ST' => 'Sortie Transfert',
        ];

        return $labels[$type] ?? $type;
    }

    /**
     * Export Historique des factures en CSV
     */
    public function exportInvoicesHistory(Request $request)
    {
        $data = $this->invoicesHistory($request)->getData(true);

        if (! $data['success']) {
            return response()->json($data, 400);
        }

        $format = $request->get('format', 'csv');
        $invoices = $data['data']['invoices'];
        $summary = $data['data']['summary'];

        $headers = ['#', 'Date', 'N° Facture', 'Client', 'Montant HTVA', 'TVA', 'Montant TVAC', 'Type', 'Utilisateur'];

        $rows = [];
        foreach ($invoices as $index => $invoice) {
            $rows[] = [
                $index + 1,
                $invoice['date'],
                $invoice['invoice_number'],
                $invoice['customer_name'],
                $invoice['amount_htva'],
                $invoice['tva'],
                $invoice['amount_tvac'],
                $invoice['invoice_type_label'],
                $invoice['user_name'],
            ];
        }

        // Add summary at the end
        $rows[] = [];
        $rows[] = ['RÉSUMÉ'];
        $rows[] = ['Période', $summary['date_from'].' - '.$summary['date_to']];
        $rows[] = ['Total Factures', $summary['total_invoices']];
        $rows[] = ['Total HTVA', $summary['total_htva']];
        $rows[] = ['Total TVA', $summary['total_tva']];
        $rows[] = ['Total TVAC', $summary['total_amount_tvac']];

        return $this->generateExport($headers, $rows, 'historique_factures', $format);
    }

    /**
     * Export Fiche de stock en CSV
     */
    public function exportStockSheet(Request $request)
    {
        $data = $this->stockSheet($request)->getData(true);

        if (! $data['success']) {
            return response()->json($data, 400);
        }

        $format = $request->get('format', 'csv');
        $items = $data['data']['items'];
        $summary = $data['data']['summary'];

        $headers = ['#', 'Code', 'Produit', 'Stock', 'Quantité', 'Prix Unitaire', 'Valeur Totale'];

        $rows = [];
        foreach ($items as $index => $item) {
            $rows[] = [
                $index + 1,
                $item['product_code'],
                $item['product_name'],
                $item['warehouse_name'],
                $item['quantity'],
                $item['unit_price'],
                $item['total_value'],
            ];
        }

        $rows[] = [];
        $rows[] = ['RÉSUMÉ'];
        $rows[] = ['Total Produits', $summary['total_items']];
        $rows[] = ['Quantité Totale', $summary['total_quantity']];
        $rows[] = ['Valeur Totale', $summary['total_value']];

        return $this->generateExport($headers, $rows, 'fiche_stock', $format);
    }

    /**
     * Export Mouvements de stock en CSV
     */
    public function exportStockMovements(Request $request)
    {
        $data = $this->stockMovements($request)->getData(true);

        if (! $data['success']) {
            return response()->json($data, 400);
        }

        $format = $request->get('format', 'csv');
        $movements = $data['data']['movements'];
        $summary = $data['data']['summary'];

        $headers = ['#', 'Date', 'Produit', 'Code', 'Stock', 'Type', 'Quantité', 'Prix Unitaire', 'Total', 'Utilisateur'];

        $rows = [];
        foreach ($movements as $index => $mov) {
            $rows[] = [
                $index + 1,
                $mov['date'],
                $mov['product_name'],
                $mov['product_code'],
                $mov['warehouse_name'],
                $mov['movement_type_label'],
                $mov['quantity'],
                $mov['unit_price'],
                $mov['total'],
                $mov['user_name'],
            ];
        }

        $rows[] = [];
        $rows[] = ['RÉSUMÉ'];
        $rows[] = ['Période', $summary['date_from'].' - '.$summary['date_to']];
        $rows[] = ['Total Mouvements', $summary['total_movements']];
        $rows[] = ['Entrées', $summary['total_entries'], 'Valeur', $summary['total_entries_value']];
        $rows[] = ['Sorties', $summary['total_exits'], 'Valeur', $summary['total_exits_value']];

        return $this->generateExport($headers, $rows, 'mouvements_stock', $format);
    }

    /**
     * Export Entrées de stock en CSV
     */
    public function exportStockEntries(Request $request)
    {
        $request->merge(['movement_type' => 'entry']);

        return $this->exportStockMovements($request);
    }

    /**
     * Export Factures à crédit en CSV
     */
    public function exportCreditInvoices(Request $request)
    {
        $data = $this->creditInvoices($request)->getData(true);

        if (! $data['success']) {
            return response()->json($data, 400);
        }

        $format = $request->get('format', 'csv');
        $invoices = $data['data']['invoices'];
        $summary = $data['data']['summary'];

        $headers = ['#', 'Date', 'N° Facture', 'Client', 'HTVA', 'TVA', 'TVAC', 'Statut', 'Utilisateur'];

        $rows = [];
        foreach ($invoices as $index => $invoice) {
            $rows[] = [
                $index + 1,
                $invoice['date'],
                $invoice['invoice_number'],
                $invoice['customer_name'],
                $invoice['amount_htva'],
                $invoice['tva'],
                $invoice['amount_tvac'],
                $invoice['status'] === 'success' ? 'Validé' : 'En attente',
                $invoice['user_name'],
            ];
        }

        $rows[] = [];
        $rows[] = ['RÉSUMÉ'];
        $rows[] = ['Période', $summary['date_from'].' - '.$summary['date_to']];
        $rows[] = ['Total Factures', $summary['total_invoices']];
        $rows[] = ['Montant Total', $summary['total_amount']];

        return $this->generateExport($headers, $rows, 'factures_credit', $format);
    }

    /**
     * Export Proformas en CSV
     */
    public function exportProformas(Request $request)
    {
        $data = $this->proformas($request)->getData(true);

        if (! $data['success']) {
            return response()->json($data, 400);
        }

        $format = $request->get('format', 'csv');
        $proformas = $data['data']['proformas'];
        $summary = $data['data']['summary'];

        $headers = ['#', 'Date', 'N° Proforma', 'Client', 'Articles', 'HTVA', 'TVA', 'TVAC', 'Utilisateur'];

        $rows = [];
        foreach ($proformas as $index => $proforma) {
            $rows[] = [
                $index + 1,
                $proforma['date'],
                $proforma['invoice_number'],
                $proforma['customer_name'],
                $proforma['items_count'],
                $proforma['amount_htva'],
                $proforma['tva'],
                $proforma['amount_tvac'],
                $proforma['user_name'],
            ];
        }

        $rows[] = [];
        $rows[] = ['RÉSUMÉ'];
        $rows[] = ['Période', $summary['date_from'].' - '.$summary['date_to']];
        $rows[] = ['Total Proformas', $summary['total_proformas']];
        $rows[] = ['Montant Total', $summary['total_amount']];

        return $this->generateExport($headers, $rows, 'proformas', $format);
    }

    /**
     * Export Rapport des ventes en CSV
     */
    public function exportSales(Request $request)
    {
        $data = $this->sales($request)->getData(true);

        if (! $data['success']) {
            return response()->json($data, 400);
        }

        $format = $request->get('format', 'csv');
        $metrics = $data['data']['metrics'];
        $dailySales = $data['data']['daily_sales'];
        $salesByUser = $data['data']['sales_by_user'];

        $headers = ['Date', 'Montant'];

        $rows = [];
        $rows[] = ['VENTES JOURNALIÈRES'];
        foreach ($dailySales as $sale) {
            $rows[] = [$sale['date'], $sale['total']];
        }

        $rows[] = [];
        $rows[] = ['VENTES PAR UTILISATEUR'];
        $rows[] = ['Utilisateur', 'Montant'];
        foreach ($salesByUser as $sale) {
            $rows[] = [$sale['name'], $sale['total']];
        }

        $rows[] = [];
        $rows[] = ['RÉSUMÉ'];
        $rows[] = ['Chiffre d\'Affaires', $metrics['revenue']];
        $rows[] = ['Nombre de Factures', $metrics['count']];
        $rows[] = ['Panier Moyen', $metrics['average_basket']];

        return $this->generateExport($headers, $rows, 'rapport_ventes', $format);
    }

    /**
     * Générer le fichier d'export (CSV ou Excel)
     */
    private function generateExport(array $headers, array $rows, string $filename, string $format = 'csv')
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $fullFilename = "{$filename}_{$timestamp}";

        if ($format === 'csv') {
            return $this->generateCsv($headers, $rows, $fullFilename);
        } else {
            return $this->generateExcel($headers, $rows, $fullFilename);
        }
    }

    /**
     * Générer CSV
     */
    private function generateCsv(array $headers, array $rows, string $filename)
    {
        $callback = function () use ($headers, $rows) {
            $file = fopen('php://output', 'w');

            // BOM for UTF-8
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Headers
            fputcsv($file, $headers, ';');

            // Data
            foreach ($rows as $row) {
                if (is_array($row)) {
                    fputcsv($file, $row, ';');
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
        ]);
    }

    /**
     * Générer Excel (format XML simple compatible Excel)
     */
    private function generateExcel(array $headers, array $rows, string $filename)
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<?mso-application progid="Excel.Sheet"?>'."\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
            xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">'."\n";
        $xml .= '<Worksheet ss:Name="Rapport"><Table>'."\n";

        // Headers
        $xml .= '<Row>';
        foreach ($headers as $header) {
            $xml .= '<Cell><Data ss:Type="String">'.htmlspecialchars($header).'</Data></Cell>';
        }
        $xml .= '</Row>'."\n";

        // Data
        foreach ($rows as $row) {
            if (is_array($row) && count($row) > 0) {
                $xml .= '<Row>';
                foreach ($row as $cell) {
                    $type = is_numeric($cell) ? 'Number' : 'String';
                    $xml .= '<Cell><Data ss:Type="'.$type.'">'.htmlspecialchars($cell ?? '').'</Data></Cell>';
                }
                $xml .= '</Row>'."\n";
            }
        }

        $xml .= '</Table></Worksheet></Workbook>';

        return response($xml, 200, [
            'Content-Type' => 'application/vnd.ms-excel',
            'Content-Disposition' => "attachment; filename=\"{$filename}.xls\"",
        ]);
    }
}
