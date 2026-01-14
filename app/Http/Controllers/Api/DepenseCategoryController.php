<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DepenseCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DepenseCategoryController extends Controller
{
    /**
     * Display a listing of depense categories.
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => DepenseCategory::with(['company', 'user'])->paginate(15)
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created depense category.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            //'company_id' => 'required|exists:companies,id',
        ]);

        $validated['user_id'] = auth()->id();

        $depenseCategory = DepenseCategory::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Depense category created successfully',
            'data' => $depenseCategory->load(['company', 'user'])
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified depense category.
     */
    public function show(DepenseCategory $depenseCategory)
    {
        return response()->json([
            'success' => true,
            'data' => $depenseCategory->load(['company', 'user', 'depenses'])
        ], Response::HTTP_OK);
    }

    /**
     * Update the specified depense category.
     */
    public function update(Request $request, DepenseCategory $depenseCategory)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'company_id' => 'sometimes|required|exists:companies,id',
        ]);

        $depenseCategory->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Depense category updated successfully',
            'data' => $depenseCategory->load(['company', 'user'])
        ], Response::HTTP_OK);
    }

    /**
     * Remove the specified depense category (soft delete).
     */
    public function destroy(DepenseCategory $depenseCategory)
    {
        $depenseCategory->delete();

        return response()->json([
            'success' => true,
            'message' => 'Depense category deleted successfully'
        ], Response::HTTP_OK);
    }

    /**
     * Restore a soft-deleted depense category.
     */
    public function restore($id)
    {
        $depenseCategory = DepenseCategory::withTrashed()->findOrFail($id);
        $depenseCategory->restore();

        return response()->json([
            'success' => true,
            'message' => 'Depense category restored successfully',
            'data' => $depenseCategory
        ], Response::HTTP_OK);
    }
}
