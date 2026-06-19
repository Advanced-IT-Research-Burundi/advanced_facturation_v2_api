<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\Depense;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DepenseController extends Controller
{
    /**
     * Display a listing of depenses.
     */
    public function index(Request $request)
    {
        $query = Depense::with(['depenseCategory', 'company', 'user'])
            ->where('company_id', auth()->user()->company_id);

        if ($request->filled('hotel_section')) {
            $query->where('hotel_section', $request->hotel_section);
        } elseif ($request->has('hotel_section') && $request->hotel_section === '') {
            $query->whereNull('hotel_section');
        }

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
            'data' => $query->latest()->paginate(15),
        ], Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        $isHotelSection = $request->filled('hotel_section');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'montant' => 'required|numeric|min:0.01',
            'depense_category_id' => $isHotelSection
                ? 'nullable|exists:depense_categories,id'
                : 'required|exists:depense_categories,id',
            'hotel_section' => 'nullable|in:restaurant,bar,rooms,conference,reception',
            'justification_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $validated['user_id'] = auth()->id();
        $validated['company_id'] = auth()->user()->company_id;

        if ($request->hasFile('justification_file')) {
            $file = $request->file('justification_file');
            $validated['justification_file'] = $file->getClientOriginalName();
            $validated['justification_data'] = base64_encode(file_get_contents($file->getRealPath()));
            $validated['justification_mime'] = $file->getMimeType();
        }

        return DB::transaction(function () use ($validated) {
            $register = $this->openRegisterForSection($validated['hotel_section'] ?? null);
            $this->ensureSufficientBalance($register, (float) $validated['montant']);

            $depense = Depense::create($validated);

            CashMovement::create([
                'cash_register_id' => $register->id,
                'depense_id' => $depense->id,
                'type' => 'expense',
                'amount' => $depense->montant,
                'description' => $depense->name,
                'reference' => 'DEP-'.$depense->id,
                'created_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Dépense enregistrée et retirée de la caisse',
                'data' => $depense->load(['depenseCategory', 'company', 'user', 'cashMovement']),
            ], Response::HTTP_CREATED);
        });
    }

    public function show(Depense $depense)
    {
        if ($depense->company_id !== auth()->user()->company_id) {
            abort(403);
        }

        return response()->json([
            'success' => true,
            'data' => $depense->load(['depenseCategory', 'company', 'user']),
        ], Response::HTTP_OK);
    }

    public function update(Request $request, Depense $depense)
    {
        if ($depense->company_id !== auth()->user()->company_id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'montant' => 'sometimes|required|numeric|min:0.01',
            'depense_category_id' => 'sometimes|required|exists:depense_categories,id',
            'justification_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        if ($request->hasFile('justification_file')) {
            $file = $request->file('justification_file');
            $validated['justification_file'] = $file->getClientOriginalName();
            $validated['justification_data'] = base64_encode(file_get_contents($file->getRealPath()));
            $validated['justification_mime'] = $file->getMimeType();
        }

        return DB::transaction(function () use ($depense, $validated) {
            $movement = CashMovement::where('depense_id', $depense->id)
                ->lockForUpdate()
                ->first();

            if ($movement) {
                $register = CashRegister::whereKey($movement->cash_register_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $newAmount = (float) ($validated['montant'] ?? $depense->montant);
                $availableBalance = $register->calculateExpectedBalance() + (float) $movement->amount;
                $this->ensureSufficientBalance($register, $newAmount, $availableBalance);
            } else {
                $register = $this->openRegisterForSection($depense->hotel_section);
                $newAmount = (float) ($validated['montant'] ?? $depense->montant);
                $this->ensureSufficientBalance($register, $newAmount);
            }

            $depense->update($validated);

            CashMovement::updateOrCreate(
                ['depense_id' => $depense->id],
                [
                    'cash_register_id' => $register->id,
                    'type' => 'expense',
                    'amount' => $depense->montant,
                    'description' => $depense->name,
                    'reference' => 'DEP-'.$depense->id,
                    'created_by' => auth()->id(),
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Dépense et caisse mises à jour',
                'data' => $depense->load(['depenseCategory', 'company', 'user', 'cashMovement']),
            ], Response::HTTP_OK);
        });
    }

    /**
     * Serve the justification file from the database.
     */
    public function justification(Depense $depense)
    {
        if ($depense->company_id !== auth()->user()->company_id) {
            abort(403);
        }

        if (! $depense->justification_data) {
            abort(404, 'Aucun justificatif trouvé.');
        }

        $content = base64_decode($depense->justification_data);
        $mime = $depense->justification_mime ?? 'application/octet-stream';
        $filename = $depense->justification_file ?? 'justificatif';

        return response($content, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function destroy(Depense $depense)
    {
        if ($depense->company_id !== auth()->user()->company_id) {
            abort(403);
        }

        DB::transaction(function () use ($depense) {
            CashMovement::where('depense_id', $depense->id)->delete();
            $depense->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Depense deleted successfully',
        ], Response::HTTP_OK);
    }

    public function restore($id)
    {
        $depense = Depense::withTrashed()
            ->where('company_id', auth()->user()->company_id)
            ->findOrFail($id);

        return DB::transaction(function () use ($depense) {
            $register = $this->openRegisterForSection($depense->hotel_section);
            $this->ensureSufficientBalance($register, (float) $depense->montant);

            $depense->restore();

            CashMovement::create([
                'cash_register_id' => $register->id,
                'depense_id' => $depense->id,
                'type' => 'expense',
                'amount' => $depense->montant,
                'description' => $depense->name,
                'reference' => 'DEP-'.$depense->id,
                'created_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Dépense restaurée et retirée de la caisse',
                'data' => $depense->load('cashMovement'),
            ], Response::HTTP_OK);
        });
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

        $csvFileName = 'depenses_export_'.date('Y-m-d_H-i-s').'.csv';
        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=$csvFileName",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($depenses) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Date', 'Libellé', 'Catégorie', 'Montant', 'Créé par']);

            foreach ($depenses as $row) {
                fputcsv($file, [
                    $row->id,
                    $row->created_at->format('Y-m-d H:i'),
                    $row->name,
                    $row->depenseCategory?->name ?? 'N/A',
                    $row->montant,
                    $row->user?->name ?? 'N/A',
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function openRegisterForSection(?string $hotelSection): CashRegister
    {
        $register = CashRegister::where('company_id', auth()->user()->company_id)
            ->where('status', 'open')
            ->when(
                $hotelSection,
                fn ($query) => $query->where('hotel_section', $hotelSection),
                fn ($query) => $query->whereNull('hotel_section')
            )
            ->lockForUpdate()
            ->first();

        if (! $register) {
            throw ValidationException::withMessages([
                'cash_register' => 'Ouvrez la caisse avant d’enregistrer une dépense.',
            ]);
        }

        return $register;
    }

    private function ensureSufficientBalance(
        CashRegister $register,
        float $amount,
        ?float $availableBalance = null
    ): void {
        $balance = $availableBalance ?? $register->calculateExpectedBalance();

        if ($amount > $balance) {
            throw ValidationException::withMessages([
                'montant' => 'Le montant de la dépense dépasse le solde actuel de la caisse.',
            ]);
        }
    }
}
