<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\RoleUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RoleUserController extends Controller
{
    private const PRIVILEGED_ROLE_NAMES = ['super_admin', 'admin'];

    /**
     * Display a listing of role users.
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => RoleUser::with(['role', 'user'])->paginate(15)
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created role user assignment.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'role_id' => 'required|exists:roles,id',
            'user_id' => 'required|exists:users,id',
        ]);

        $role = Role::findOrFail($validated['role_id']);
        $user = User::with('roles')->findOrFail($validated['user_id']);

        if ($this->isPrivilegedRole($role)) {
            RoleUser::where('user_id', $user->id)
                ->where('role_id', '!=', $role->id)
                ->delete();

            $roleUser = RoleUser::withTrashed()
                ->where('role_id', $role->id)
                ->where('user_id', $user->id)
                ->first();

            if ($roleUser) {
                if ($roleUser->trashed()) {
                    $roleUser->restore();
                }
            } else {
                $roleUser = RoleUser::create($validated);
            }

            return response()->json([
                'success' => true,
                'message' => 'Role assigned to user successfully',
                'data' => $roleUser->load(['role', 'user'])
            ], Response::HTTP_CREATED);
        }

        if ($user->roles->contains(fn ($assignedRole) => $this->isPrivilegedRole($assignedRole))) {
            return response()->json([
                'success' => false,
                'message' => 'Un rôle administrateur contient déjà toutes les permissions.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $existing = RoleUser::withTrashed()
            ->where('role_id', $validated['role_id'])
            ->where('user_id', $validated['user_id'])
            ->first();

        if ($existing && ! $existing->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'This role is already assigned to this user'
            ], Response::HTTP_CONFLICT);
        }

        if ($existing) {
            $existing->restore();
            $roleUser = $existing;
        } else {
            $roleUser = RoleUser::create($validated);
        }

        return response()->json([
            'success' => true,
            'message' => 'Role assigned to user successfully',
            'data' => $roleUser->load(['role', 'user'])
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified role user.
     */
    public function show(RoleUser $roleUser)
    {
        return response()->json([
            'success' => true,
            'data' => $roleUser->load(['role', 'user'])
        ], Response::HTTP_OK);
    }

    /**
     * Remove the specified role user assignment (soft delete).
     */
    public function destroy(RoleUser $roleUser)
    {
        $roleUser->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role removed from user successfully'
        ], Response::HTTP_OK);
    }

    /**
     * Restore a soft-deleted role user assignment.
     */
    public function restore($id)
    {
        $roleUser = RoleUser::withTrashed()->findOrFail($id);
        $roleUser->restore();

        return response()->json([
            'success' => true,
            'message' => 'Role user assignment restored successfully',
            'data' => $roleUser
        ], Response::HTTP_OK);
    }

    private function isPrivilegedRole(Role $role): bool
    {
        return in_array(strtolower($role->name), self::PRIVILEGED_ROLE_NAMES);
    }
}
