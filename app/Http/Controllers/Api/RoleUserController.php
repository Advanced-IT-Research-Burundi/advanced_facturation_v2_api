<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RoleUser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RoleUserController extends Controller
{
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

        // Check for existing assignment
        $existing = RoleUser::where('role_id', $validated['role_id'])
            ->where('user_id', $validated['user_id'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'This role is already assigned to this user'
            ], Response::HTTP_CONFLICT);
        }

        $roleUser = RoleUser::create($validated);

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
}
