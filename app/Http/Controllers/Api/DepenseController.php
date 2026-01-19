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
    public function index(Request $request)
    {
        $query = Depense::with(['depenseCategory', 'company', 'user'])
            ->where('company_id', auth()->user()->company_id);

        if ($request->filled('category_id')) {
            $query->where('depense_category_id', $request->category_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        return response()->json([
            'success' => true,
            'data' => $query->latest()->paginate(15)
        ], Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'montant' => 'required|numeric|min:0',
            'depense_category_id' => 'required|exists:depense_categories,id',
            'justification_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $validated['user_id'] = auth()->id();
        $validated['company_id'] = auth()->user()->company_id;

        if ($request->hasFile('justification_file')) {
            $path = $request->file('justification_file')->store('depenses', 'public');
            $validated['justification_file'] = $path;
        }

        $depense = Depense::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Depense created successfully',
            'data' => $depense->load(['depenseCategory', 'company', 'user'])
        ], Response::HTTP_CREATED);
    }

    public function show(Depense $depense)
    {
        if ($depense->company_id !== auth()->user()->company_id) {
            abort(403);
        }
        return response()->json([
            'success' => true,
            'data' => $depense->load(['depenseCategory', 'company', 'user'])
        ], Response::HTTP_OK);
    }

    public function update(Request $request, Depense $depense)
    {
        if ($depense->company_id !== auth()->user()->company_id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'montant' => 'sometimes|required|numeric|min:0',
            'depense_category_id' => 'sometimes|required|exists:depense_categories,id',
            'justification_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        if ($request->hasFile('justification_file')) {
            // Delete old file if exists
            if ($depense->justification_file && \Illuminate\Support\Facades\Storage::disk('public')->exists($depense->justification_file)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($depense->justification_file);
            }
            
            $path = $request->file('justification_file')->store('depenses', 'public');
            $validated['justification_file'] = $path;
        }

        $depense->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Depense updated successfully',
            'data' => $depense->load(['depenseCategory', 'company', 'user'])
        ], Response::HTTP_OK);
    }

    public function destroy(Depense $depense)
    {
        if ($depense->company_id !== auth()->user()->company_id) {
            abort(403);
        }
        
        if ($depense->justification_file && \Illuminate\Support\Facades\Storage::disk('public')->exists($depense->justification_file)) {
             \Illuminate\Support\Facades\Storage::disk('public')->delete($depense->justification_file);
        }

        $depense->delete();

        return response()->json([
            'success' => true,
            'message' => 'Depense deleted successfully'
        ], Response::HTTP_OK);
    }

    public function restore($id)
    {
        $depense = Depense::withTrashed()
            ->where('company_id', auth()->user()->company_id)
            ->findOrFail($id);
            
        $depense->restore();

        return response()->json([
            'success' => true,
            'message' => 'Depense restored successfully',
            'data' => $depense
        ], Response::HTTP_OK);
    }

    public function export(Request $request)
    {
        $query = Depense::with(['depenseCategory', 'user'])
            ->where('company_id', auth()->user()->company_id);

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $depenses = $query->latest()->get();

        $csvFileName = 'depenses_export_' . date('Y-m-d_H-i-s') . '.csv';
        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$csvFileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function() use($depenses) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Date', 'Libellé', 'Catégorie', 'Montant', 'Créé par']);

            foreach ($depenses as $row) {
                fputcsv($file, [
                    $row->id,
                    $row->created_at->format('Y-m-d H:i'),
                    $row->name,
                    $row->depenseCategory?->name ?? 'N/A',
                    $row->montant,
                    $row->user?->name ?? 'N/A'
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
