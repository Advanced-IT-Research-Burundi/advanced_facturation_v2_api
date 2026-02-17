<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExpirationAlert;
use App\Models\PatientHistory;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\ProductLot;
use App\Services\PharmaceuticalService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PharmaceuticalDashboardController extends Controller
{
    protected PharmaceuticalService $pharmaceuticalService;

    public function __construct(PharmaceuticalService $pharmaceuticalService)
    {
        $this->pharmaceuticalService = $pharmaceuticalService;
    }

    /**
     * Get pharmaceutical dashboard data.
     */
    public function index(Request $request)
    {
        $companyId = auth()->user()->company_id;

        // Statistiques produits pharmaceutiques
        $productStats = [
            'total_pharmaceutical' => Product::where('company_id', $companyId)->pharmaceutical()->count(),
            'requires_prescription' => Product::where('company_id', $companyId)->requiresPrescription()->count(),
            'controlled_substances' => Product::where('company_id', $companyId)->controlledSubstance()->count(),
        ];

        // Statistiques lots
        $lotStats = [
            'total_active_lots' => ProductLot::where('company_id', $companyId)->active()->count(),
            'expiring_30_days' => ProductLot::where('company_id', $companyId)
                ->active()
                ->where('expiration_date', '<=', Carbon::now()->addDays(30))
                ->where('expiration_date', '>', Carbon::now())
                ->count(),
            'expired' => ProductLot::where('company_id', $companyId)
                ->where('expiration_date', '<', Carbon::now())
                ->where('current_quantity', '>', 0)
                ->count(),
        ];

        // Statistiques ordonnances
        $prescriptionStats = [
            'pending' => Prescription::where('company_id', $companyId)->pending()->count(),
            'partially_dispensed' => Prescription::where('company_id', $companyId)
                ->where('status', 'partially_dispensed')
                ->count(),
            'today' => Prescription::where('company_id', $companyId)
                ->whereDate('created_at', today())
                ->count(),
        ];

        // Alertes actives
        $alertStats = [
            'total_active' => ExpirationAlert::where('company_id', $companyId)->active()->count(),
            'critical' => ExpirationAlert::where('company_id', $companyId)->active()->critical()->count(),
            'warning' => ExpirationAlert::where('company_id', $companyId)->active()->warning()->count(),
        ];

        // Statistiques expiration
        $expirationStats = $this->pharmaceuticalService->getExpirationStats($companyId);

        // Ventes du jour (produits pharma)
        $todaySales = PatientHistory::where('company_id', $companyId)
            ->whereDate('purchase_date', today())
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'products' => $productStats,
                'lots' => $lotStats,
                'prescriptions' => $prescriptionStats,
                'alerts' => $alertStats,
                'expiration' => $expirationStats,
                'today_sales' => $todaySales,
            ],
        ], Response::HTTP_OK);
    }

    /**
     * Get products expiring soon.
     */
    public function expiringSoon(Request $request)
    {
        $companyId = auth()->user()->company_id;
        $days = $request->integer('days', 90);
        $limit = $request->integer('limit', 50);

        $lots = $this->pharmaceuticalService->getExpiringSoonLots($companyId, $days, $limit);

        return response()->json([
            'success' => true,
            'data' => $lots,
        ], Response::HTTP_OK);
    }

    /**
     * Get pharmaceutical products.
     */
    public function products(Request $request)
    {
        $query = Product::with(['productUnit', 'categoryProduct', 'lots' => function ($q) {
            $q->active()->orderBy('expiration_date', 'asc');
        }])
            ->where(function ($q) {
                // Filtrer par catégorie pharmaceutique OU par le flag is_pharmaceutical
                $q->where('is_pharmaceutical', true)
                    ->orWhereHas('categoryProduct', function ($query) {
                        $query->where(function ($subQuery) {
                            $subQuery->where('name', 'LIKE', '%pharma%')
                                ->orWhere('name', 'LIKE', '%Pharma%')
                                ->orWhere('name', 'LIKE', '%pharmacy%')
                                ->orWhere('name', 'LIKE', '%Pharmacy%');
                        });
                    });
            });

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('item_designation', 'like', "%{$search}%")
                    ->orWhere('item_code', 'like', "%{$search}%")
                    ->orWhere('dci', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('requires_prescription')) {
            $query->requiresPrescription();
        }

        if ($request->filled('classe_therapeutique')) {
            $query->where('classe_therapeutique', $request->classe_therapeutique);
        }

        return response()->json([
            'success' => true,
            'data' => $query->latest()->paginate(15),
        ], Response::HTTP_OK);
    }

    /**
     * Get recent prescriptions.
     */
    public function recentPrescriptions(Request $request)
    {
        $companyId = auth()->user()->company_id;
        $limit = $request->integer('limit', 10);

        $prescriptions = Prescription::with(['customer', 'items.product'])
            ->where('company_id', $companyId)
            ->latest()
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $prescriptions,
        ], Response::HTTP_OK);
    }

    /**
     * Get critical alerts.
     */
    public function criticalAlerts(Request $request)
    {
        $companyId = auth()->user()->company_id;
        $limit = $request->integer('limit', 10);

        $alerts = ExpirationAlert::with(['product', 'productLot', 'warehouse'])
            ->where('company_id', $companyId)
            ->active()
            ->critical()
            ->orderBy('days_until_expiration', 'asc')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $alerts,
        ], Response::HTTP_OK);
    }

    /**
     * Get therapeutic classes summary.
     */
    public function therapeuticClasses(Request $request)
    {
        $companyId = auth()->user()->company_id;

        $classes = Product::where('company_id', $companyId)
            ->pharmaceutical()
            ->whereNotNull('classe_therapeutique')
            ->selectRaw('classe_therapeutique, COUNT(*) as count')
            ->groupBy('classe_therapeutique')
            ->orderBy('count', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $classes,
        ], Response::HTTP_OK);
    }

    /**
     * Get low stock pharmaceutical products.
     */
    public function lowStock(Request $request)
    {
        $companyId = auth()->user()->company_id;

        $products = Product::where('company_id', $companyId)
            ->pharmaceutical()
            ->whereColumn('quantite', '<=', 'quantite_alert')
            ->with(['productUnit', 'categoryProduct'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $products,
        ], Response::HTTP_OK);
    }
}
