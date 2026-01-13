<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Depense;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DepenseController extends Controller
{
    /**
     * Display a listing of depenses.
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => Depense::with(['depenseCategory', 'company', 'user'])->paginate(15)
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created depense.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'montant' => 'required|numeric|min:0',
            'depense_category_id' => 'required|exists:depense_categories,id',
            'company_id' => 'required|exists:companies,id',
            'justification_file' => 'nullable|string|max:255',
        ]);

        $validated['user_id'] = auth()->id();

        $depense = Depense::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Depense created successfully',
            'data' => $depense->load(['depenseCategory', 'company', 'user'])
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified depense.
     */
    public function show(Depense $depense)
    {
        return response()->json([
            'success' => true,
            'data' => $depense->load(['depenseCategory', 'company', 'user'])
        ], Response::HTTP_OK);
    }

    /**
     * Update the specified depense.
     */
    public function update(Request $request, Depense $depense)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'montant' => 'sometimes|required|numeric|min:0',
            'depense_category_id' => 'sometimes|required|exists:depense_categories,id',
            'company_id' => 'sometimes|required|exists:companies,id',
            'justification_file' => 'nullable|string|max:255',
        ]);

        $depense->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Depense updated successfully',
            'data' => $depense->load(['depenseCategory', 'company', 'user'])
        ], Response::HTTP_OK);
    }

    /**
     * Remove the specified depense (soft delete).
     */
    public function destroy(Depense $depense)
    {
        $depense->delete();

        return response()->json([
            'success' => true,
            'message' => 'Depense deleted successfully'
        ], Response::HTTP_OK);
    }

    /**
     * Restore a soft-deleted depense.
     */
    public function restore($id)
    {
        $depense = Depense::withTrashed()->findOrFail($id);
        $depense->restore();

        return response()->json([
            'success' => true,
            'message' => 'Depense restored successfully',
            'data' => $depense
        ], Response::HTTP_OK);
    }
}
