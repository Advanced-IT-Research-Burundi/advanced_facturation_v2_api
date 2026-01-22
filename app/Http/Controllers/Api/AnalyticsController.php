<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Customer;
use App\Models\Product;
use App\Models\WarehouseProduct;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    /**
     * Statistiques des ventes par période (jour/semaine/mois)
     */
    public function salesChart(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id;
        $period = $request->get('period', 'month'); // day, week, month, year
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        // Déterminer la période
        if (!$startDate || !$endDate) {
            switch ($period) {
                case 'day':
                    $startDate = Carbon::now()->subDays(30)->startOfDay();
                    $endDate = Carbon::now()->endOfDay();
                    break;
                case 'week':
                    $startDate = Carbon::now()->subWeeks(12)->startOfWeek();
                    $endDate = Carbon::now()->endOfWeek();
                    break;
                case 'month':
                    $startDate = Carbon::now()->subMonths(12)->startOfMonth();
                    $endDate = Carbon::now()->endOfMonth();
                    break;
                case 'year':
                    $startDate = Carbon::now()->subYears(5)->startOfYear();
                    $endDate = Carbon::now()->endOfYear();
                    break;
                default:
                    $startDate = Carbon::now()->subDays(30)->startOfDay();
                    $endDate = Carbon::now()->endOfDay();
            }
        } else {
            $startDate = Carbon::parse($startDate)->startOfDay();
            $endDate = Carbon::parse($endDate)->endOfDay();
        }

        // Format de groupement selon la période
        $groupFormat = match ($period) {
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            'year' => '%Y',
            default => '%Y-%m-%d',
        };

        $sales = Invoice::where('company_id', $companyId)
            ->where('invoice_type', 'FN')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw("DATE_FORMAT(created_at, '{$groupFormat}') as period_key")
            ->selectRaw('SUM(invoice_total_amount) as total_sales')
            ->selectRaw('SUM(invoice_vat_amount) as total_vat')
            ->selectRaw('SUM(invoice_amount_nvat) as total_htva')
            ->selectRaw('COUNT(*) as invoice_count')
            ->groupBy('period_key')
            ->orderBy('period_key')
            ->get();

        // Formater les labels
        $chartData = $sales->map(function ($item) use ($period) {
            $label = $item->period_key;

            if ($period === 'month') {
                $date = Carbon::createFromFormat('Y-m', $item->period_key);
                $label = $date->translatedFormat('M Y');
            } elseif ($period === 'day') {
                $date = Carbon::createFromFormat('Y-m-d', $item->period_key);
                $label = $date->format('d/m');
            } elseif ($period === 'week') {
                $label = 'S' . substr($item->period_key, -2);
            }

            return [
                'period' => $item->period_key,
                'label' => $label,
                'total_sales' => (float) $item->total_sales,
                'total_vat' => (float) $item->total_vat,
                'total_htva' => (float) $item->total_htva,
                'invoice_count' => (int) $item->invoice_count,
            ];
        });

        // Calcul des totaux
        $totals = [
            'total_sales' => $chartData->sum('total_sales'),
            'total_vat' => $chartData->sum('total_vat'),
            'total_htva' => $chartData->sum('total_htva'),
            'invoice_count' => $chartData->sum('invoice_count'),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'chart' => $chartData,
                'totals' => $totals,
            ]
        ]);
    }

    /**
     * Top 10 produits les plus vendus
     */
    public function topProducts(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id;
        $startDate = $request->get('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', Carbon::now()->format('Y-m-d'));
        $limit = $request->get('limit', 10);

        $topProducts = InvoiceItem::query()
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->where('invoices.company_id', $companyId)
            ->where('invoices.invoice_type', 'FN')
            ->whereBetween('invoices.created_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ])
            ->select(
                'invoice_items.item_designation as product_name',
                DB::raw('SUM(invoice_items.item_quantity) as total_quantity'),
                DB::raw('SUM(invoice_items.item_total_amount) as total_amount'),
                DB::raw('COUNT(DISTINCT invoices.id) as invoice_count')
            )
            ->groupBy('invoice_items.item_designation')
            ->orderByDesc('total_amount')
            ->limit($limit)
            ->get()
            ->map(function ($item, $index) {
                return [
                    'rank' => $index + 1,
                    'product_name' => $item->product_name ?? 'Produit',
                    'total_quantity' => (float) $item->total_quantity,
                    'total_amount' => (float) $item->total_amount,
                    'invoice_count' => (int) $item->invoice_count,
                ];
            });

        $totalSales = $topProducts->sum('total_amount');

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
                'products' => $topProducts,
                'total_sales' => $totalSales,
            ]
        ]);
    }

    /**
     * Top 10 clients par chiffre d'affaires
     */
    public function topCustomers(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id;
        $startDate = $request->get('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', Carbon::now()->format('Y-m-d'));
        $limit = $request->get('limit', 10);

        $topCustomers = Invoice::query()
            ->where('company_id', $companyId)
            ->where('invoice_type', 'FN')
            ->whereBetween('created_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ])
            ->select(
                'customer_id',
                DB::raw('COALESCE(customer_name, "Client Anonyme") as customer_name'),
                DB::raw('SUM(invoice_total_amount) as total_amount'),
                DB::raw('SUM(invoice_vat_amount) as total_vat'),
                DB::raw('COUNT(*) as invoice_count'),
                DB::raw('AVG(invoice_total_amount) as average_basket')
            )
            ->groupBy('customer_id', 'customer_name')
            ->orderByDesc('total_amount')
            ->limit($limit)
            ->get()
            ->map(function ($item, $index) {
                // Récupérer les infos client si customer_id existe
                if ($item->customer_id) {
                    $customer = Customer::find($item->customer_id);
                    if ($customer) {
                        $item->customer_name = $customer->customer_name;
                        $item->customer_phone = $customer->customer_phone;
                    }
                }

                return [
                    'rank' => $index + 1,
                    'customer_id' => $item->customer_id,
                    'customer_name' => $item->customer_name,
                    'customer_phone' => $item->customer_phone ?? '',
                    'total_amount' => (float) $item->total_amount,
                    'total_vat' => (float) $item->total_vat,
                    'invoice_count' => (int) $item->invoice_count,
                    'average_basket' => round((float) $item->average_basket, 2),
                ];
            });

        $totalRevenue = $topCustomers->sum('total_amount');

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
                'customers' => $topCustomers,
                'total_revenue' => $totalRevenue,
            ]
        ]);
    }

    /**
     * Alertes stock bas
     */
    public function lowStockAlerts(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id;
        $threshold = $request->get('threshold', 10); // Seuil par défaut
        $warehouseId = $request->get('warehouse_id');

        $query = WarehouseProduct::query()
            ->whereHas('warehouse', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })
            ->where('quantity', '<=', $threshold)
            ->with(['product', 'warehouse']);

        if ($warehouseId && $warehouseId !== 'all') {
            $query->where('warehouse_id', $warehouseId);
        }

        $alerts = $query->orderBy('quantity', 'asc')
            ->get()
            ->map(function ($item) use ($threshold) {
                $alertLevel = 'warning';
                if ($item->quantity <= 0) {
                    $alertLevel = 'critical';
                } elseif ($item->quantity <= $threshold / 2) {
                    $alertLevel = 'danger';
                }

                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product?->item_designation ?? 'Produit inconnu',
                    'product_code' => $item->product?->item_code ?? '',
                    'warehouse_id' => $item->warehouse_id,
                    'warehouse_name' => $item->warehouse?->name ?? 'Stock inconnu',
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'alert_level' => $alertLevel,
                ];
            });

        // Grouper par niveau d'alerte
        $critical = $alerts->where('alert_level', 'critical')->count();
        $danger = $alerts->where('alert_level', 'danger')->count();
        $warning = $alerts->where('alert_level', 'warning')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'threshold' => $threshold,
                'summary' => [
                    'total_alerts' => $alerts->count(),
                    'critical' => $critical,
                    'danger' => $danger,
                    'warning' => $warning,
                ],
                'alerts' => $alerts,
            ]
        ]);
    }

    /**
     * Statistiques globales pour le dashboard
     */
    public function dashboardStats(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id;

        // Période actuelle vs précédente
        $currentStart = Carbon::now()->startOfMonth();
        $currentEnd = Carbon::now();
        $previousStart = Carbon::now()->subMonth()->startOfMonth();
        $previousEnd = Carbon::now()->subMonth()->endOfMonth();

        // Ventes du mois
        $currentSales = Invoice::where('company_id', $companyId)
            ->where('invoice_type', 'FN')
            ->whereBetween('created_at', [$currentStart, $currentEnd])
            ->sum('invoice_total_amount');

        $previousSales = Invoice::where('company_id', $companyId)
            ->where('invoice_type', 'FN')
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->sum('invoice_total_amount');

        $salesGrowth = $previousSales > 0
            ? round((($currentSales - $previousSales) / $previousSales) * 100, 1)
            : 0;

        // Factures du mois
        $currentInvoices = Invoice::where('company_id', $companyId)
            ->where('invoice_type', 'FN')
            ->whereBetween('created_at', [$currentStart, $currentEnd])
            ->count();

        $previousInvoices = Invoice::where('company_id', $companyId)
            ->where('invoice_type', 'FN')
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->count();

        $invoicesGrowth = $previousInvoices > 0
            ? round((($currentInvoices - $previousInvoices) / $previousInvoices) * 100, 1)
            : 0;

        // Clients actifs
        $activeCustomers = Invoice::where('company_id', $companyId)
            ->whereBetween('created_at', [$currentStart, $currentEnd])
            ->distinct('customer_id')
            ->count('customer_id');

        // Stock total
        $totalStock = WarehouseProduct::whereHas('warehouse', function ($q) use ($companyId) {
            $q->where('company_id', $companyId);
        })->sum('quantity');

        // Alertes stock bas
        $lowStockCount = WarehouseProduct::whereHas('warehouse', function ($q) use ($companyId) {
            $q->where('company_id', $companyId);
        })->where('quantity', '<=', 10)->count();

        // Ventes aujourd'hui
        $todaySales = Invoice::where('company_id', $companyId)
            ->where('invoice_type', 'FN')
            ->whereDate('created_at', Carbon::today())
            ->sum('invoice_total_amount');

        $todayInvoices = Invoice::where('company_id', $companyId)
            ->where('invoice_type', 'FN')
            ->whereDate('created_at', Carbon::today())
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'sales' => [
                    'current' => $currentSales,
                    'previous' => $previousSales,
                    'growth' => $salesGrowth,
                    'today' => $todaySales,
                ],
                'invoices' => [
                    'current' => $currentInvoices,
                    'previous' => $previousInvoices,
                    'growth' => $invoicesGrowth,
                    'today' => $todayInvoices,
                ],
                'customers' => [
                    'active' => $activeCustomers,
                ],
                'stock' => [
                    'total' => $totalStock,
                    'low_stock_alerts' => $lowStockCount,
                ],
            ]
        ]);
    }
}
