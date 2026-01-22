<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ObrLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ObrLogController extends Controller
{
    /**
     * Liste des logs OBR avec filtres
     */
    public function index(Request $request)
    {
        $query = ObrLog::with(['invoice', 'user'])
            ->orderBy('created_at', 'desc');

        // Filtre par type
        if ($request->has('log_type') && $request->log_type !== 'all') {
            $query->where('log_type', $request->log_type);
        }

        // Filtre par statut
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filtre par succès
        if ($request->has('success')) {
            $query->where('success', $request->boolean('success'));
        }

        // Filtre par date
        if ($request->has('start_date') && $request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date') && $request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // Recherche par numéro de facture
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhere('invoice_identifier', 'like', "%{$search}%")
                  ->orWhere('obr_message', 'like', "%{$search}%");
            });
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate($request->input('per_page', 20))
        ], Response::HTTP_OK);
    }

    /**
     * Afficher un log spécifique
     */
    public function show(ObrLog $obrLog)
    {
        return response()->json([
            'success' => true,
            'data' => $obrLog->load(['invoice', 'stockMovement', 'user'])
        ], Response::HTTP_OK);
    }

    /**
     * Statistiques des logs OBR
     */
    public function stats(Request $request)
    {
        $stats = [
            'total' => ObrLog::count(),
            'invoices' => [
                'total' => ObrLog::invoices()->count(),
                'accepted' => ObrLog::invoices()->accepted()->count(),
                'rejected' => ObrLog::invoices()->rejected()->count(),
                'pending' => ObrLog::invoices()->pending()->count(),
            ],
            'cancellations' => [
                'total' => ObrLog::cancellations()->count(),
                'accepted' => ObrLog::cancellations()->accepted()->count(),
                'rejected' => ObrLog::cancellations()->rejected()->count(),
            ],
            'stock_movements' => [
                'total' => ObrLog::where('log_type', ObrLog::TYPE_STOCK_MOVEMENT)->count(),
                'accepted' => ObrLog::where('log_type', ObrLog::TYPE_STOCK_MOVEMENT)->accepted()->count(),
                'rejected' => ObrLog::where('log_type', ObrLog::TYPE_STOCK_MOVEMENT)->rejected()->count(),
            ],
            'recent_errors' => ObrLog::rejected()
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get(['id', 'log_type', 'invoice_number', 'obr_message', 'created_at']),
            'today' => [
                'total' => ObrLog::whereDate('created_at', today())->count(),
                'success' => ObrLog::whereDate('created_at', today())->where('success', true)->count(),
                'failed' => ObrLog::whereDate('created_at', today())->where('success', false)->count(),
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ], Response::HTTP_OK);
    }

    /**
     * Réessayer un envoi échoué
     */
    public function retry(ObrLog $obrLog)
    {
        if ($obrLog->status === ObrLog::STATUS_ACCEPTED) {
            return response()->json([
                'success' => false,
                'message' => 'Ce log a déjà été accepté par OBR'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Incrémenter le compteur de tentatives
        $obrLog->update([
            'retry_count' => $obrLog->retry_count + 1,
            'last_retry_at' => now(),
        ]);

        // TODO: Déclencher le renvoi selon le type de log
        // Cette logique dépend de votre implémentation

        return response()->json([
            'success' => true,
            'message' => 'Tentative de renvoi planifiée',
            'data' => $obrLog
        ], Response::HTTP_OK);
    }
}
