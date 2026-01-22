<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ActivityLogController extends Controller
{
    /**
     * Liste des activités avec pagination et filtres
     */
    public function index(Request $request)
    {
        $query = ActivityLog::with(['user'])
            ->where('company_id', auth()->user()->company_id)
            ->orderBy('created_at', 'desc');

        // Recherche globale
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Filtre par type
        if ($type = $request->input('log_type')) {
            if ($type !== 'all') {
                $query->where('log_type', $type);
            }
        }

        // Filtre par action
        if ($action = $request->input('action')) {
            if ($action !== 'all') {
                $query->where('action', $action);
            }
        }

        // Filtre par utilisateur
        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
        }

        // Filtre par date
        if ($startDate = $request->input('start_date')) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate = $request->input('end_date')) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $activities = $query->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $activities
        ], Response::HTTP_OK);
    }

    /**
     * Détails d'une activité
     */
    public function show(ActivityLog $activityLog)
    {
        if ($activityLog->company_id !== auth()->user()->company_id) {
            abort(403);
        }

        return response()->json([
            'success' => true,
            'data' => $activityLog->load(['user'])
        ], Response::HTTP_OK);
    }

    /**
     * Statistiques des activités
     */
    public function stats(Request $request)
    {
        $companyId = auth()->user()->company_id;
        
        // Activités aujourd'hui
        $today = now()->startOfDay();
        $todayCount = ActivityLog::where('company_id', $companyId)
            ->where('created_at', '>=', $today)
            ->count();

        // Activités cette semaine
        $weekStart = now()->startOfWeek();
        $weekCount = ActivityLog::where('company_id', $companyId)
            ->where('created_at', '>=', $weekStart)
            ->count();

        // Activités par type (ce mois)
        $monthStart = now()->startOfMonth();
        $byType = ActivityLog::where('company_id', $companyId)
            ->where('created_at', '>=', $monthStart)
            ->selectRaw('log_type, count(*) as count')
            ->groupBy('log_type')
            ->pluck('count', 'log_type')
            ->toArray();

        // Activités par jour (7 derniers jours)
        $dailyStats = ActivityLog::where('company_id', $companyId)
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('DATE(created_at) as date, count(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date')
            ->toArray();

        // Utilisateurs les plus actifs
        $topUsers = ActivityLog::where('company_id', $companyId)
            ->where('created_at', '>=', $monthStart)
            ->whereNotNull('user_id')
            ->selectRaw('user_id, count(*) as count')
            ->groupBy('user_id')
            ->orderByDesc('count')
            ->limit(5)
            ->with('user:id,name')
            ->get()
            ->map(fn($item) => [
                'user' => $item->user?->name ?? 'Inconnu',
                'count' => $item->count
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'today' => $todayCount,
                'this_week' => $weekCount,
                'by_type' => $byType,
                'daily_stats' => $dailyStats,
                'top_users' => $topUsers,
            ]
        ], Response::HTTP_OK);
    }

    /**
     * Types d'activités disponibles
     */
    public function types()
    {
        $types = [
            ['value' => 'auth', 'label' => 'Authentification'],
            ['value' => 'invoice', 'label' => 'Factures'],
            ['value' => 'product', 'label' => 'Produits'],
            ['value' => 'stock', 'label' => 'Stock'],
            ['value' => 'customer', 'label' => 'Clients'],
            ['value' => 'payment', 'label' => 'Paiements'],
            ['value' => 'expense', 'label' => 'Dépenses'],
            ['value' => 'user', 'label' => 'Utilisateurs'],
            ['value' => 'warehouse', 'label' => 'Entrepôts'],
            ['value' => 'order', 'label' => 'Commandes'],
            ['value' => 'system', 'label' => 'Système'],
        ];

        return response()->json([
            'success' => true,
            'data' => $types
        ], Response::HTTP_OK);
    }

    /**
     * Actions disponibles
     */
    public function actions()
    {
        $actions = [
            ['value' => 'created', 'label' => 'Création'],
            ['value' => 'updated', 'label' => 'Modification'],
            ['value' => 'deleted', 'label' => 'Suppression'],
            ['value' => 'viewed', 'label' => 'Consultation'],
            ['value' => 'login', 'label' => 'Connexion'],
            ['value' => 'logout', 'label' => 'Déconnexion'],
            ['value' => 'approved', 'label' => 'Approbation'],
            ['value' => 'cancelled', 'label' => 'Annulation'],
            ['value' => 'paid', 'label' => 'Paiement'],
            ['value' => 'exported', 'label' => 'Export'],
        ];

        return response()->json([
            'success' => true,
            'data' => $actions
        ], Response::HTTP_OK);
    }

    /**
     * Exporter les activités
     */
    public function export(Request $request)
    {
        $query = ActivityLog::with(['user'])
            ->where('company_id', auth()->user()->company_id)
            ->orderBy('created_at', 'desc');

        // Appliquer les mêmes filtres
        if ($type = $request->input('log_type')) {
            if ($type !== 'all') {
                $query->where('log_type', $type);
            }
        }

        if ($startDate = $request->input('start_date')) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate = $request->input('end_date')) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $activities = $query->limit(1000)->get();

        // Log l'export
        ActivityLog::log('system', 'exported', 'Export du journal d\'activités');

        return response()->json([
            'success' => true,
            'data' => $activities
        ], Response::HTTP_OK);
    }
}
