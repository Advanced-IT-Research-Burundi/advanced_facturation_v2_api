<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WarehouseUserController extends Controller
{
    /**
     * Récupérer les utilisateurs assignés à un entrepôt
     * @NiBienvenu
     */
    public function getAssignedUsers($warehouseId)
    {
        try {
            $warehouse = Warehouse::findOrFail($warehouseId);

            $assignedUsers = $warehouse->users()
                ->select('users.id', 'users.name', 'users.email')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $assignedUsers
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des utilisateurs'
            ], 500);
        }
    }

    /**
     * Récupérer les utilisateurs non assignés à un entrepôt
     * @NiBienvenu
     */
    public function getAvailableUsers($warehouseId)
    {
        try {
            Warehouse::findOrFail($warehouseId);
            $availableUsers = User::whereDoesntHave('warehouses')
                ->select('id', 'name', 'email')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $availableUsers
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des utilisateurs disponibles'
            ], 500);
        }
    }



    /**
     * Assigner un utilisateur à un entrepôt
     * @NiBienvenu
     */
    public function assignUser(Request $request, $warehouseId)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);

        try {
            $warehouse = Warehouse::findOrFail($warehouseId);
            $userId = $request->user_id;

            // Vérifier si l'utilisateur est déjà assigné
            if ($warehouse->users()->where('user_warehouse.user_id', $userId)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cet utilisateur est déjà assigné à cet entrepôt'
                ], 400);
            }

            // Assigner l'utilisateur
            $warehouse->users()->attach($userId, [
                'assigned_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur assigné avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'assignation de l\'utilisateur'
            ], 500);
        }
    }

    /**
     * Désassigner un utilisateur d'un entrepôt
     * @NiBienvenu
     */
    public function unassignUser(Request $request, $warehouseId)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);

        try {
            $warehouse = Warehouse::findOrFail($warehouseId);
            $userId = $request->user_id;

            // Vérifier si l'utilisateur est assigné
            if (!$warehouse->users()->where('user_warehouse.user_id', $userId)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cet utilisateur n\'est pas assigné à cet entrepôt'
                ], 400);
            }

            // Désassigner l'utilisateur
            $warehouse->users()->detach($userId);

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur désassigné avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la désassignation de l\'utilisateur'
            ], 500);
        }
    }

    /**
     * Assigner plusieurs utilisateurs en une fois
     * @NiBienvenu
     */
    public function assignMultipleUsers(Request $request, $warehouseId)
    {
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id'
        ]);

        try {
            $warehouse = Warehouse::findOrFail($warehouseId);

            DB::beginTransaction();

            foreach ($request->user_ids as $userId) {
                if (!$warehouse->users()->where('user_warehouse.user_id', $userId)->exists()) {
                    $warehouse->users()->attach($userId, [
                        'assigned_at' => now()
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Utilisateurs assignés avec succès'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'assignation des utilisateurs'
            ], 500);
        }
    }
}
