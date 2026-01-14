<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
   public function index(Request $request)
{
    $search = $request->query('search');

    $users = User::with(['role', 'company'])
        ->when($search, function ($query) use ($search) {
            $query->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhereHas('company', function ($q) use ($search) {
                      $q->where('name', 'LIKE', "%{$search}%");
                  });
        })
        ->paginate(15);

    return response()->json([
        'success' => true,
        'data' => $users
    ], Response::HTTP_OK);
}


    /**
     * Store a newly created user.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'required|email|unique:users|max:255',
            'password' => 'required|string|min:6',
            'company_id' => 'required|exists:companies,id',
        ]);

        $validated['password'] = bcrypt($validated['password']);
        $validated['user_id'] = auth()->id();

        $user = User::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'data' => $user->load(['role', 'company'])
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified user.
     */
    public function show(User $user)
    {
        return response()->json([
            'success' => true,
            'data' => $user->load(['role', 'company'])
        ], Response::HTTP_OK);
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'sometimes|nullable|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $user->id . '|max:255',
            'password' => 'sometimes|required|string|min:6',
            'company_id' => 'sometimes|required|exists:companies,id',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => $user->load(['role', 'company'])
        ], Response::HTTP_OK);
    }

    /**
     * Remove the specified user (soft delete).
     */
    public function destroy(User $user)
    {
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ], Response::HTTP_OK);
    }

    /**
     * Restore a soft-deleted user.
     */
    public function restore($id)
    {
        $user = User::withTrashed()->findOrFail($id);
        $user->restore();

        return response()->json([
            'success' => true,
            'message' => 'User restored successfully',
            'data' => $user
        ], Response::HTTP_OK);
    }
}
