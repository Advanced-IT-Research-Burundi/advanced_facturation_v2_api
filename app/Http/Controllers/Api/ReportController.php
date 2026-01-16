<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function sales(Request $request)
    {
        $startDate = $request->get('date_from', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->get('date_to', Carbon::now()->endOfMonth()->toDateString());
        $userId = $request->get('user_id');
        $warehouseId = $request->get('warehouse_id');

        // Base query for Invoices (Sales)
        $query = Invoice::query()
            ->whereBetween('created_at', [
                Carbon::parse($startDate)->startOfDay(), 
                Carbon::parse($endDate)->endOfDay()
            ])
            ->where('invoice_type', 'FN'); // Sales only

        if ($userId && $userId !== 'all') {
            $query->where('user_id', $userId);
        }

        // Filter by warehouse
        if ($warehouseId && $warehouseId !== 'all') {
            // Assuming we can link via stock movements using the relationship in Invoice model
            $query->whereHas('stockMovements', function($q) use ($warehouseId) {
                $q->where('warehouse_id', $warehouseId);
            });
        }

        // 1. Key Metrics
        $totalRevenue = (clone $query)->sum('invoice_total_amount');
        $totalInvoices = (clone $query)->count();
        $averageBasket = $totalInvoices > 0 ? $totalRevenue / $totalInvoices : 0;

        // 2. Sales Over Time (Daily)
        $dailySales = (clone $query)
            ->selectRaw('DATE(created_at) as date, SUM(invoice_total_amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // 3. Sales By User
        $salesByUser = (clone $query)
            ->select('user_id', DB::raw('SUM(invoice_total_amount) as total'))
            ->with('user:id,name')
            ->groupBy('user_id')
            ->get()
            ->map(function($item) {
                return [
                    'name' => $item->user->name ?? 'Inconnu',
                    'total' => (float)$item->total
                ];
            });

        // 4. Sales by Warehouse
        // This is tricky without a direct column. We will aggregate based on stock movements.
        // We select the invoices first, then get their movements to find the warehouse.
        // Note: Use this only if needed or approximate it.
        // A better approach for "Sales by Warehouse" might be tallying StockMovements directly (Outflows)
        // But stock movements record COST price or BUY price usually? No, for sales it should be sale price?
        // Let's check StockMovement structure.
        // 'item_purchase_or_sale_price' exists.
        
        $salesByWarehouse = [];
        // Only compute this if we are not filtering by warehouse (otherwise it's 100% that warehouse)
        if (!$warehouseId || $warehouseId === 'all') {
             // We can use the stock_movements table directly to sum outflows (Sales) by warehouse
             // This might differ slightly if invoice has tax/discount not in movement, but it's a good proxy.
             $salesByWarehouse = StockMovement::query()
                ->whereBetween('created_at', [
                    Carbon::parse($startDate)->startOfDay(), 
                    Carbon::parse($endDate)->endOfDay()
                ])
                ->whereIn('item_movement_type', ['SN', 'SC', 'SV']) // Sortie Normale etc. Usually sales is SN? Let's assume SN for now or check types.
                // Actually, let's rely on 'item_movement_invoice_ref' presence or 'SN' type.
                ->where('item_movement_type', 'SN') // Assuming SN = Sale Normal
                ->select('warehouse_id', DB::raw('SUM(item_quantity * item_purchase_or_sale_price) as total'))
                ->with('warehouse:id,name')
                ->groupBy('warehouse_id')
                ->get()
                ->map(function($item) {
                    return [
                        'name' => $item->warehouse->name ?? 'Inconnu',
                        'total' => (float)$item->total
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
                'sales_by_warehouse' => $salesByWarehouse
            ]
        ]);
    }
}
