<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FourinsseurResource;
use App\Models\Fourinsseur;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FourinsseurController extends Controller
{
    /**
     * Display a listing of fourinsseurs.
     */
    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->input('per_page', 15), 100));
        $query = Fourinsseur::with(['company', 'user']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('phone_number', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('nif', 'like', "%{$search}%");
        }

        return response()->json([
            'success' => true,
            'data' => FourinsseurResource::collection($query->paginate($perPage)),
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created fourinsseur.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone_number' => 'nullable|string|max:255',
            'nif' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'company_id' => 'nullable|exists:companies,id',
        ]);

        $validated['user_id'] = auth()->id();

        $fourinsseur = Fourinsseur::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Fournisseur créé avec succès',
            'data' => new FourinsseurResource($fourinsseur->load(['company', 'user'])),
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified fourinsseur.
     */
    public function show(Fourinsseur $fourinsseur)
    {
        return response()->json([
            'success' => true,
            'data' => new FourinsseurResource($fourinsseur->load(['company', 'user'])),
        ], Response::HTTP_OK);
    }

    /**
     * Update the specified fourinsseur.
     */
    public function update(Request $request, Fourinsseur $fourinsseur)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'phone_number' => 'nullable|string|max:255',
            'nif' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'company_id' => 'nullable|exists:companies,id',
        ]);

        $fourinsseur->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Fournisseur mis à jour avec succès',
            'data' => new FourinsseurResource($fourinsseur->load(['company', 'user'])),
        ], Response::HTTP_OK);
    }

    /**
     * Remove the specified fourinsseur.
     */
    public function destroy(Fourinsseur $fourinsseur)
    {
        $fourinsseur->delete();

        return response()->json([
            'success' => true,
            'message' => 'Fournisseur supprimé avec succès',
        ], Response::HTTP_OK);
    }
}
