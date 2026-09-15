<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Libelle;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LibelleController extends Controller
{
    /**
     * Display a listing of libelles.
     */
    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->input('per_page', 15), 100));
        $query = Libelle::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('price', 'like', "%{$search}%")
                    ->orWhere('tva', 'like', "%{$search}%");
            });
        }

        return response()->json([
            'success' => true,
            'data' => $query->latest()->paginate($perPage),
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created libelle.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'tva' => 'nullable|numeric|min:0|max:100',
        ]);

        $validated['user_id'] = auth()->id();

        $libelle = Libelle::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Libelle created successfully',
            'data' => $libelle->load(['company', 'user']),
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified libelle.
     */
    public function show(Libelle $libelle)
    {
        return response()->json([
            'success' => true,
            'data' => $libelle->load(['company', 'user']),
        ], Response::HTTP_OK);
    }

    /**
     * Update the specified libelle.
     */
    public function update(Request $request, Libelle $libelle)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'tva' => 'nullable|numeric|min:0|max:100',
            'company_id' => 'sometimes|required|exists:companies,id',
        ]);

        $libelle->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Libelle updated successfully',
            'data' => $libelle->load(['company', 'user']),
        ], Response::HTTP_OK);
    }

    /**
     * Remove the specified libelle.
     */
    public function destroy(Libelle $libelle)
    {
        $libelle->delete();

        return response()->json([
            'success' => true,
            'message' => 'Libelle deleted successfully',
        ], Response::HTTP_OK);
    }

    /**
     * Restore a soft-deleted libelle.
     */
    public function restore($id)
    {
        $libelle = Libelle::withTrashed()->findOrFail($id);
        $libelle->restore();

        return response()->json([
            'success' => true,
            'message' => 'Libelle restored successfully',
            'data' => $libelle->load(['company', 'user']),
        ], Response::HTTP_OK);
    }
}
