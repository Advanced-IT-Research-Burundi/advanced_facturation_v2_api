<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\User;
use App\Models\WarehouseProduct;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id;

        // Période actuelle (ce mois)
        $currentMonthStart = Carbon::now()->startOfMonth();
        $currentMonthEnd = Carbon::now()->endOfMonth();

        // Période précédente (mois dernier)
        $lastMonthStart = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

        // 1. Revenu Total (ce mois)
        $currentRevenue = Invoice::where('company_id', $companyId)
            ->whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
            ->sum('invoice_total_amount');

        // Revenu du mois dernier (pour calculer la croissance)
        $lastMonthRevenue = Invoice::where('company_id', $companyId)
            ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])
            ->sum('invoice_total_amount');

        // Calcul du pourcentage de croissance du revenu
        $revenueGrowth = 0;
        if ($lastMonthRevenue > 0) {
            $revenueGrowth = round((($currentRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1);
        }

        // 2. Total en Stock
        $totalStock = WarehouseProduct::whereHas('warehouse', function ($query) use ($companyId) {
            $query->where('company_id', $companyId);
        })->sum('quantity');

        // Stock du mois dernier
        $lastMonthStock = WarehouseProduct::whereHas('warehouse', function ($query) use ($companyId) {
            $query->where('company_id', $companyId);
        })->where('created_at', '<=', $lastMonthEnd)->sum('quantity');

        $stockChange = 0;
        if ($lastMonthStock > 0) {
            $stockChange = round((($totalStock - $lastMonthStock) / $lastMonthStock) * 100, 1);
        }

        // 3. Utilisateurs Actifs
        $activeUsers = User::where('company_id', $companyId)->count();

        // Nouveaux utilisateurs aujourd'hui
        $newUsersToday = User::where('company_id', $companyId)
            ->whereDate('created_at', Carbon::today())
            ->count();

        // 4. Croissance globale (basée sur les factures)
        $currentMonthInvoices = Invoice::where('company_id', $companyId)
            ->whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
            ->count();

        $lastMonthInvoices = Invoice::where('company_id', $companyId)
            ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])
            ->count();

        $invoiceGrowth = 0;
        if ($lastMonthInvoices > 0) {
            $invoiceGrowth = round((($currentMonthInvoices - $lastMonthInvoices) / $lastMonthInvoices) * 100, 1);
        }

        // 5. Factures Récentes
        $recentInvoices = Invoice::where('company_id', $companyId)
            ->with('customer:id,customer_name')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($invoice) {
                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'customer_name' => $invoice->customer?->customer_name ?? $invoice->customer_name ?? 'Client Anonyme',
                    'amount' => $invoice->invoice_total_amount,
                    'status' => $invoice->obr_submission_status ?? 'pending',
                    'date' => $invoice->created_at->format('d/m/Y'),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'revenue' => [
                    'total' => $currentRevenue,
                    'growth' => $revenueGrowth,
                ],
                'stock' => [
                    'total' => $totalStock,
                    'change' => $stockChange,
                ],
                'users' => [
                    'total' => $activeUsers,
                    'new_today' => $newUsersToday,
                ],
                'growth' => [
                    'percentage' => $invoiceGrowth,
                    'trend' => $invoiceGrowth >= 0 ? 'up' : 'down',
                ],
                'recent_invoices' => $recentInvoices,
            ],
        ]);
    }
}
