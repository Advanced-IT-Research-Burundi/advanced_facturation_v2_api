<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ObrSynchronisation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ObrSyncController extends Controller
{
    /**
     * Équivalent de « php artisan app:obr-sync-command ». Avec « limite », n'envoie qu'un lot (au plus « limite »
     * éléments de chaque type) : l'interface rappelle la route tant qu'il en reste, pour afficher les réponses au fur et à mesure.
     */
    public function syncAll(Request $request, ObrSynchronisation $synchronisation): JsonResponse
    {
        $validated = $request->validate([
            'limite' => 'nullable|integer|min:1|max:100',
        ]);
        set_time_limit(0);

        $resultats = $synchronisation->toutSynchroniser($validated['limite'] ?? null);
        $envoyes = collect($resultats['details'])->where('success', true)->count();
        $erreurs = collect($resultats['details'])->where('success', false)->count();

        return response()->json([
            'success' => true,
            'message' => "Synchronisation OBR terminée : {$envoyes} envoyé(s), {$erreurs} erreur(s).",
            'data' => $resultats,
        ]);
    }
}
