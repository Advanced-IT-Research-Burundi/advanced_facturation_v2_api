<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\Company;
use App\Models\Depense;
use App\Models\ExpenseFundMovement;
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
        $query = Depense::with(['depenseCategory', 'company', 'user', 'validatedBy:id,name'])
            ->where('company_id', auth()->user()->company_id);

        if ($request->filled('hotel_section')) {
            $query->where('hotel_section', $request->hotel_section);
        } elseif ($request->has('hotel_section') && $request->hotel_section === '') {
            $query->whereNull('hotel_section');
        }

        if ($request->filled('category_id')) {
            $query->where('depense_category_id', $request->category_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
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
            'data' => (clone $query)->latest()->paginate(15),
            'total_approved' => (float) (clone $query)->approved()->sum('montant'),
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

        // Hors hôtel, aucun argent ne bouge avant la validation par un administrateur
        if (! $isHotelSection) {
            $validated['status'] = Depense::STATUS_PENDING;
            $depense = Depense::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Dépense enregistrée, en attente de validation',
                'data' => $depense->load(['depenseCategory', 'company', 'user']),
            ], Response::HTTP_CREATED);
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

        if (! $depense->hotel_section) {
            if (! $this->isAuthorOrAdmin($depense)) {
                return $this->errorResponse('Seul son auteur ou un administrateur peut modifier cette dépense.', Response::HTTP_FORBIDDEN);
            }
            if ($depense->status !== Depense::STATUS_PENDING) {
                return $this->errorResponse('Seule une dépense en attente peut être modifiée.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }
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

        if (! $depense->hotel_section) {
            $depense->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Dépense mise à jour',
                'data' => $depense->load(['depenseCategory', 'company', 'user']),
            ], Response::HTTP_OK);
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

        if (! $depense->hotel_section) {
            $canDelete = auth()->user()->isAdministrator()
                || ($depense->user_id === auth()->id() && $depense->status !== Depense::STATUS_APPROVED);

            if (! $canDelete) {
                return $this->errorResponse("Vous n'avez pas le droit de supprimer cette dépense.", Response::HTTP_FORBIDDEN);
            }
        }

        DB::transaction(function () use ($depense) {
            CashMovement::where('depense_id', $depense->id)->delete();
            ExpenseFundMovement::where('depense_id', $depense->id)->delete();
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

        if (! $depense->hotel_section) {
            if (! auth()->user()->isAdministrator()) {
                return $this->errorResponse('Réservé aux administrateurs.', Response::HTTP_FORBIDDEN);
            }
            if (! $depense->trashed()) {
                return $this->errorResponse("Cette dépense n'est pas supprimée.", Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $depense->restore();
            $depense->update([
                'status' => Depense::STATUS_PENDING,
                'validated_by' => null,
                'validated_at' => null,
                'rejection_reason' => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Dépense restaurée, en attente de validation',
                'data' => $depense,
            ], Response::HTTP_OK);
        }

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

    /**
     * L'administrateur valide la dépense : le montant sort de la caisse des dépenses.
     * La validation est refusée si le solde de cette caisse est insuffisant.
     */
    public function approve(Depense $depense)
    {
        $user = auth()->user();

        return DB::transaction(function () use ($depense, $user) {
            // Verrouiller l'entreprise pour que deux validations simultanées ne dépensent pas le même solde
            Company::whereKey($user->company_id)->lockForUpdate()->first();

            $depense = Depense::whereKey($depense->id)->lockForUpdate()->firstOrFail();

            if ($response = $this->denyDecision($depense)) {
                return $response;
            }

            $balance = ExpenseFundMovement::balanceForCompany($depense->company_id);
            if ($depense->montant > $balance) {
                return $this->errorResponse(
                    'Solde insuffisant dans la caisse des dépenses (disponible : '.number_format($balance, 2, ',', ' ').' FBU, demandé : '.number_format($depense->montant, 2, ',', ' ').' FBU).',
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            $depense->update([
                'status' => Depense::STATUS_APPROVED,
                'validated_by' => $user->id,
                'validated_at' => now(),
            ]);

            ExpenseFundMovement::create([
                'company_id' => $depense->company_id,
                'depense_id' => $depense->id,
                'type' => ExpenseFundMovement::TYPE_EXPENSE,
                'amount' => $depense->montant,
                'description' => $depense->name,
                'created_by' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Dépense validée et retirée de la caisse des dépenses',
                'data' => $depense->load(['depenseCategory', 'user', 'validatedBy:id,name']),
                'fund_balance' => ExpenseFundMovement::balanceForCompany($depense->company_id),
            ], Response::HTTP_OK);
        });
    }

    /**
     * L'administrateur refuse la dépense : aucun argent ne bouge.
     */
    public function reject(Request $request, Depense $depense)
    {
        $validated = $request->validate([
            'rejection_reason' => 'nullable|string|max:255',
        ]);

        return DB::transaction(function () use ($depense, $validated) {
            $depense = Depense::whereKey($depense->id)->lockForUpdate()->firstOrFail();

            if ($response = $this->denyDecision($depense)) {
                return $response;
            }

            $depense->update([
                'status' => Depense::STATUS_REJECTED,
                'validated_by' => auth()->id(),
                'validated_at' => now(),
                'rejection_reason' => $validated['rejection_reason'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Dépense refusée',
                'data' => $depense->load(['depenseCategory', 'user', 'validatedBy:id,name']),
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

    private function denyDecision(Depense $depense)
    {
        if ($depense->company_id !== auth()->user()->company_id) {
            abort(403);
        }
        if (! auth()->user()->isAdministrator()) {
            return $this->errorResponse('Seul un administrateur peut valider ou refuser une dépense.', Response::HTTP_FORBIDDEN);
        }
        if ($depense->status !== Depense::STATUS_PENDING) {
            return $this->errorResponse("Cette dépense n'est plus en attente.", Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return null;
    }

    private function isAuthorOrAdmin(Depense $depense): bool
    {
        return $depense->user_id === auth()->id() || auth()->user()->isAdministrator();
    }

    private function errorResponse(string $message, int $status)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
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
