<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExpenseFundMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Caisse dédiée aux dépenses, réservée aux administrateurs.
 */
class ExpenseFundController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if (! $user->isAdministrator()) {
            return $this->forbiddenResponse();
        }

        $movements = ExpenseFundMovement::with(['createdBy:id,name', 'depense:id,name,user_id', 'depense.user:id,name'])
            ->where('company_id', $user->company_id)
            ->latest()
            ->orderByDesc('id')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => [
                'balance' => ExpenseFundMovement::balanceForCompany($user->company_id),
                'movements' => $movements,
            ],
        ], Response::HTTP_OK);
    }

    /**
     * Alimenter la caisse des dépenses
     */
    public function deposit(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
        ]);

        $user = $request->user();

        if (! $user->isAdministrator()) {
            return $this->forbiddenResponse();
        }

        $movement = ExpenseFundMovement::create([
            'company_id' => $user->company_id,
            'type' => ExpenseFundMovement::TYPE_DEPOSIT,
            'amount' => $validated['amount'],
            'description' => $validated['description'] ?? 'Alimentation de la caisse',
            'created_by' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Caisse des dépenses alimentée',
            'data' => [
                'balance' => ExpenseFundMovement::balanceForCompany($user->company_id),
                'movement' => $movement,
            ],
        ], Response::HTTP_CREATED);
    }

    private function forbiddenResponse()
    {
        return response()->json([
            'success' => false,
            'message' => 'Réservé aux administrateurs.',
        ], Response::HTTP_FORBIDDEN);
    }
}
