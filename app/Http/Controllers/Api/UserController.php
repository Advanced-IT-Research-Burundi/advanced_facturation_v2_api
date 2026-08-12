<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    private const PRIVILEGED_ROLE_NAMES = ['super_admin', 'admin'];

    /**
     * Display a listing of users with pagination and search.
     */
    public function index(Request $request)
    {
        $query = User::with(['roles', 'company']);

        if ($request->user()->company_id) {
            $query->where('company_id', $request->user()->company_id);
        }

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        $users = $query->latest()->paginate($request->per_page ?? 10);

        return response()->json([
            'success' => true,
            'data' => $users,
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request)
    {
        if (! $request->user()->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune entreprise associée à votre compte.',
            ], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users|max:255',
            'password' => 'required|string|min:8|confirmed',
            'roles' => 'required|array|min:1',
            'roles.*' => 'exists:roles,id',
        ]);

        $validated['roles'] = $this->normalizeRoleIds($validated['roles']);
        $validated['password'] = bcrypt($validated['password']);
        $validated['user_id'] = auth()->id();
        $companyId = $request->user()->company_id;

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'company_id' => $companyId,
            'user_id' => $validated['user_id'],
        ]);

        // Attach roles to user
        $user->roles()->attach($validated['roles']);

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur créé avec succès',
            'data' => $user->load(['roles', 'company']),
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified user.
     */
    public function show(Request $request, User $user)
    {
        if ($request->user()->company_id && $user->company_id !== $request->user()->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé.',
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'success' => true,
            'data' => $user->load(['roles', 'company']),
        ], Response::HTTP_OK);
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, User $user)
    {
        if ($request->user()->company_id && $user->company_id !== $request->user()->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé.',
            ], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,'.$user->id.'|max:255',
            'password' => 'sometimes|nullable|string|min:8|confirmed',
            'roles' => 'sometimes|required|array|min:1',
            'roles.*' => 'exists:roles,id',
        ]);

        if (isset($validated['roles'])) {
            $validated['roles'] = $this->normalizeRoleIds($validated['roles']);
        }

        // Update password only if provided
        if (isset($validated['password']) && ! empty($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update(array_filter([
            'name' => $validated['name'] ?? $user->name,
            'email' => $validated['email'] ?? $user->email,
            'password' => $validated['password'] ?? null,
        ]));

        // Sync roles if provided
        if (isset($validated['roles'])) {
            $user->roles()->sync($validated['roles']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur mis à jour avec succès',
            'data' => $user->load(['roles', 'company']),
        ], Response::HTTP_OK);
    }

    /**
     * Remove the specified user.
     */
    public function destroy(Request $request, User $user)
    {
        if ($request->user()->company_id && $user->company_id !== $request->user()->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé.',
            ], Response::HTTP_FORBIDDEN);
        }

        $user->roles()->detach(); // Remove role associations
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur supprimé avec succès',
        ], Response::HTTP_OK);
    }

    /**
     * Get all available roles.
     */
    public function getRoles(): \Illuminate\Http\JsonResponse
    {
        $companyDomain = auth()->user()?->company?->domain ?? 'general';

        $roles = Role::select('id', 'name', 'label', 'description', 'domain')
            ->where(function ($query) use ($companyDomain) {
                // Rôles universels (domain = null) + rôles du domaine de l'entreprise
                $query->whereNull('domain')
                    ->orWhere('domain', $companyDomain);
            })
            ->orderByRaw('ISNULL(domain), domain, label')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $roles,
        ], Response::HTTP_OK);
    }

    /**
     * Restore a soft-deleted user.
     */
    public function restore(Request $request, $id)
    {
        $user = User::withTrashed()->findOrFail($id);

        if ($request->user()->company_id && $user->company_id !== $request->user()->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé.',
            ], Response::HTTP_FORBIDDEN);
        }

        $user->restore();

        return response()->json([
            'success' => true,
            'message' => 'User restored successfully',
            'data' => $user,
        ], Response::HTTP_OK);
    }

    private function normalizeRoleIds(array $roleIds): array
    {
        $roleIds = array_values(array_unique(array_map('intval', $roleIds)));

        $privilegedRoles = Role::whereIn('id', $roleIds)
            ->get(['id', 'name']);

        $superAdmin = $privilegedRoles->first(fn ($role) => strtolower($role->name) === 'super_admin');
        if ($superAdmin) {
            return [$superAdmin->id];
        }

        $admin = $privilegedRoles->first(fn ($role) => strtolower($role->name) === 'admin');
        if ($admin) {
            return [$admin->id];
        }

        return $roleIds;
    }
}
