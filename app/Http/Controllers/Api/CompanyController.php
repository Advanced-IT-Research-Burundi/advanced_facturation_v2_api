<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class CompanyController extends Controller
{
    /**
     * Display a listing of companies.
     */
    public function index()
    {
        $query = Company::query();

        if (auth()->user()->company_id) {
            $query->where('id', auth()->user()->company_id);
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate(15),
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created company.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'tp_type' => 'required|string|max:255',
            'tp_name' => 'required|string|max:255',
            'tp_TIN' => 'required|string|unique:companies|max:255',
            'tp_trade_number' => 'nullable|string|max:255',
            'tp_postal_number' => 'nullable|string|max:255',
            'tp_phone_number' => 'nullable|string|max:255',
            'tp_address_province' => 'nullable|string|max:255',
            'tp_address_commune' => 'nullable|string|max:255',
            'tp_address_quartier' => 'nullable|string|max:255',
            'tp_address_avenue' => 'nullable|string|max:255',
            'tp_address_rue' => 'nullable|string|max:255',
            'tp_address_number' => 'nullable|string|max:255',
            'tp_fiscal_center' => 'nullable|string|max:255',
            'tp_activity_sector' => 'nullable|string|max:255',
            'tp_legal_form' => 'nullable|string|max:255',
            'vat_taxpayer' => 'required|string|max:255',
            'ct_taxpayer' => 'required|string|max:255',
            'tl_taxpayer' => 'required|string|max:255',
            'system_or_device_id' => 'required|string|max:255',
            'default_currency' => 'required|string|max:255',
            'company_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $validated['user_id'] = auth()->id();

        if ($request->hasFile('company_logo')) {
            $validated['company_logo'] = $request->file('company_logo')->store('logos', 'public');
        }

        $company = Company::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Company created successfully',
            'data' => $company,
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified company.
     */
    public function show(Company $company)
    {
        if (auth()->user()->company_id && auth()->user()->company_id !== $company->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this company',
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'success' => true,
            'data' => $company,
        ], Response::HTTP_OK);
    }

    /**
     * Update the specified company.
     */
    public function update(Request $request, Company $company)
    {
        if (auth()->user()->company_id && auth()->user()->company_id !== $company->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized update for this company',
            ], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'tp_type' => 'sometimes|required|string|max:255',
            'tp_name' => 'sometimes|required|string|max:255',
            'tp_TIN' => 'sometimes|required|string|unique:companies,tp_TIN,'.$company->id.'|max:255',
            'tp_trade_number' => 'nullable|string|max:255',
            'tp_postal_number' => 'nullable|string|max:255',
            'tp_phone_number' => 'nullable|string|max:255',
            'tp_address_province' => 'nullable|string|max:255',
            'tp_address_commune' => 'nullable|string|max:255',
            'tp_address_quartier' => 'nullable|string|max:255',
            'tp_address_avenue' => 'nullable|string|max:255',
            'tp_address_rue' => 'nullable|string|max:255',
            'tp_address_number' => 'nullable|string|max:255',
            'tp_fiscal_center' => 'nullable|string|max:255',
            'tp_activity_sector' => 'nullable|string|max:255',
            'tp_legal_form' => 'nullable|string|max:255',
            'vat_taxpayer' => 'sometimes|required|string|max:255',
            'ct_taxpayer' => 'sometimes|required|string|max:255',
            'tl_taxpayer' => 'sometimes|required|string|max:255',
            'system_or_device_id' => 'sometimes|required|string|max:255',
            'default_currency' => 'sometimes|required|string|max:255',
            'company_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        if ($request->hasFile('company_logo')) {
            if ($company->company_logo) {
                Storage::disk('public')->delete($company->company_logo);
            }
            $validated['company_logo'] = $request->file('company_logo')->store('logos', 'public');
        }

        $company->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Company updated successfully',
            'data' => $company,
        ], Response::HTTP_OK);
    }

    /**
     * Remove the specified company (soft delete).
     */
    public function destroy(Company $company)
    {
        if (auth()->user()->company_id && auth()->user()->company_id !== $company->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized deletion for this company',
            ], Response::HTTP_FORBIDDEN);
        }

        $company->delete();

        return response()->json([
            'success' => true,
            'message' => 'Company deleted successfully',
        ], Response::HTTP_OK);
    }

    /**
     * Restore a soft-deleted company.
     */
    public function restore($id)
    {
        $company = Company::withTrashed()->findOrFail($id);
        $company->restore();

        return response()->json([
            'success' => true,
            'message' => 'Company restored successfully',
            'data' => $company,
        ], Response::HTTP_OK);
    }
}
