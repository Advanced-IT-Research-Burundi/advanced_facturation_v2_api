<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExpirationAlert;
use App\Services\PharmaceuticalService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ExpirationAlertController extends Controller
{
    protected PharmaceuticalService $pharmaceuticalService;

    public function __construct(PharmaceuticalService $pharmaceuticalService)
    {
        $this->pharmaceuticalService = $pharmaceuticalService;
    }

    /**
     * Display a listing of expiration alerts.
     */
    public function index(Request $request)
    {
        $query = ExpirationAlert::with(['product', 'productLot', 'warehouse', 'acknowledgedBy']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            $query->active();
        }

        if ($request->filled('alert_level')) {
            $query->where('alert_level', $request->alert_level);
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->boolean('critical_only')) {
            $query->critical();
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderBy('days_until_expiration', 'asc')->paginate(15),
        ], Response::HTTP_OK);
    }

    /**
     * Display the specified expiration alert.
     */
    public function show(ExpirationAlert $expirationAlert)
    {
        return response()->json([
            'success' => true,
            'data' => $expirationAlert->load(['product', 'productLot', 'warehouse', 'acknowledgedBy']),
        ], Response::HTTP_OK);
    }

    /**
     * Acknowledge an alert.
     */
    public function acknowledge(Request $request, ExpirationAlert $alert)
    {
        $validated = $request->validate([
            'action_taken' => 'nullable|string|max:500',
        ]);

        $alert->acknowledge(auth()->id(), $validated['action_taken'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'Alerte acquittee avec succes',
            'data' => $alert->load(['product', 'productLot', 'warehouse', 'acknowledgedBy']),
        ], Response::HTTP_OK);
    }

    /**
     * Resolve an alert.
     */
    public function resolve(Request $request, ExpirationAlert $alert)
    {
        $validated = $request->validate([
            'action_taken' => 'nullable|string|max:500',
        ]);

        $alert->resolve($validated['action_taken'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'Alerte resolue avec succes',
            'data' => $alert->load(['product', 'productLot', 'warehouse']),
        ], Response::HTTP_OK);
    }

    /**
     * Generate expiration alerts.
     */
    public function generate(Request $request)
    {
        $companyId = $request->filled('company_id') ? $request->company_id : auth()->user()->company_id;

        $result = $this->pharmaceuticalService->generateExpirationAlerts($companyId);

        return response()->json([
            'success' => true,
            'message' => "Alertes generees: {$result['alerts_created']} nouvelles alertes",
            'data' => $result,
        ], Response::HTTP_OK);
    }

    /**
     * Get alert statistics.
     */
    public function stats(Request $request)
    {
        $companyId = $request->filled('company_id') ? $request->company_id : auth()->user()->company_id;

        $stats = [
            'active' => ExpirationAlert::where('company_id', $companyId)->active()->count(),
            'critical' => ExpirationAlert::where('company_id', $companyId)->active()->critical()->count(),
            'warning' => ExpirationAlert::where('company_id', $companyId)->active()->warning()->count(),
            'acknowledged' => ExpirationAlert::where('company_id', $companyId)->where('status', 'acknowledged')->count(),
            'resolved' => ExpirationAlert::where('company_id', $companyId)->where('status', 'resolved')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ], Response::HTTP_OK);
    }

    /**
     * Bulk acknowledge alerts.
     */
    public function bulkAcknowledge(Request $request)
    {
        $validated = $request->validate([
            'alert_ids' => 'required|array|min:1',
            'alert_ids.*' => 'exists:expiration_alerts,id',
            'action_taken' => 'nullable|string|max:500',
        ]);

        $count = 0;
        foreach ($validated['alert_ids'] as $alertId) {
            $alert = ExpirationAlert::find($alertId);
            if ($alert && $alert->status === 'active') {
                $alert->acknowledge(auth()->id(), $validated['action_taken'] ?? null);
                $count++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$count} alertes acquittees avec succes",
        ], Response::HTTP_OK);
    }
}
