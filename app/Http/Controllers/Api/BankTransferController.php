<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BankTransfer;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashTransfer;
use App\Models\Invoice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Caisse Banque : reçoit l'argent des caisses utilisateurs, de la caisse principale
 * et des comptes électroniques. Le montant ne bouge qu'à la validation par un admin.
 */
class BankTransferController extends Controller
{
    /**
     * Solde de la caisse Banque, disponibles par source et liste des transferts
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'status' => 'nullable|in:pending,accepted,rejected,cancelled',
            'source' => 'nullable|in:user_cash,main_register,electronic',
            'per_page' => 'nullable|integer',
        ]);

        $user = $request->user();
        $companyId = $user->company_id;
        $hasFullAccess = $this->hasFullCashAccess($user);
        $perPage = max(1, min((int) $request->get('per_page', 25), 100));

        $transfers = BankTransfer::with(['fromUser:id,name', 'createdBy:id,name', 'validatedBy:id,name'])
            ->where('company_id', $companyId)
            ->when(! $hasFullAccess, function ($query) use ($user) {
                $query->where(fn ($q) => $q->where('from_user_id', $user->id)->orWhere('created_by', $user->id));
            })
            ->when($request->filled('start_date'), fn ($q) => $q->whereDate('transfer_date', '>=', $request->start_date))
            ->when($request->filled('end_date'), fn ($q) => $q->whereDate('transfer_date', '<=', $request->end_date))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->source))
            ->orderByDesc('transfer_date')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $acceptedBySource = BankTransfer::where('company_id', $companyId)
            ->where('status', 'accepted')
            ->selectRaw('source, COALESCE(SUM(amount), 0) as total')
            ->groupBy('source')
            ->pluck('total', 'source');

        $pending = BankTransfer::where('company_id', $companyId)
            ->where('status', 'pending')
            ->where(fn ($q) => $q->where('source', 'electronic')->orWhereDate('transfer_date', Carbon::today()))
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(amount), 0) as total')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'today' => Carbon::today()->format('Y-m-d'),
                'has_full_access' => $hasFullAccess,
                'can_validate' => $this->isAdminUser($user),
                'summary' => [
                    'balance' => $hasFullAccess ? (float) $acceptedBySource->sum() : null,
                    'by_source' => $hasFullAccess
                        ? collect(BankTransfer::SOURCES)->map(fn ($label, $source) => (float) $acceptedBySource->get($source, 0))
                        : null,
                    'pending_count' => $hasFullAccess ? (int) $pending->count : null,
                    'pending_total' => $hasFullAccess ? (float) $pending->total : null,
                ],
                'available' => [
                    'user_cash' => $this->availableUserCash($companyId, $user->id),
                    'main_register' => $hasFullAccess ? $this->availableMainRegister($companyId) : null,
                    'electronic' => $hasFullAccess ? $this->availableElectronic($companyId) : null,
                ],
                'transfers' => $transfers,
            ],
        ]);
    }

    /**
     * Demande de transfert vers la Banque (en attente de validation par un admin)
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'source' => 'required|in:user_cash,main_register,electronic',
            'amount' => 'required|numeric|min:0.01',
            'reference' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:255',
        ]);

        $user = $request->user();
        $companyId = $user->company_id;
        $source = $request->source;

        if ($source !== 'user_cash' && ! $this->hasFullCashAccess($user)) {
            return $this->error("Vous n'avez pas l'accès complet à la gestion de caisse.", 403);
        }

        $register = null;
        if ($source !== 'electronic') {
            $register = $this->openRegister($companyId);
            if (! $register) {
                return $this->error('Aucune caisse ouverte.', 422);
            }
        }

        return DB::transaction(function () use ($request, $user, $companyId, $source, $register) {
            // Verrouiller les transferts en attente de la société pour éviter un double envoi
            BankTransfer::where('company_id', $companyId)->where('status', 'pending')->lockForUpdate()->get();

            $available = $this->availableFor($source, $companyId, $user->id);
            if ((float) $request->amount > $available) {
                return $this->error('Montant supérieur au disponible ('.number_format($available, 2, ',', ' ').' FBU).', 422);
            }

            $transfer = BankTransfer::create([
                'company_id' => $companyId,
                'source' => $source,
                'from_user_id' => $source === 'user_cash' ? $user->id : null,
                'cash_register_id' => $register?->id,
                'created_by' => $user->id,
                'amount' => $request->amount,
                'transfer_date' => Carbon::today(),
                'reference' => $request->reference,
                'note' => $request->note,
                'status' => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'message' => "Transfert vers la Banque envoyé, en attente de validation par l'administrateur",
                'data' => $transfer->load(['fromUser:id,name', 'createdBy:id,name']),
            ], 201);
        });
    }

    /**
     * L'admin valide : le montant sort de la caisse source et entre en Banque
     */
    public function accept(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        if (! $this->isAdminUser($user)) {
            return $this->error("Seul l'administrateur peut valider un transfert vers la Banque.", 403);
        }

        return DB::transaction(function () use ($user, $id) {
            $transfer = BankTransfer::where('company_id', $user->company_id)
                ->lockForUpdate()
                ->findOrFail($id);

            if ($check = $this->checkPending($transfer)) {
                return $check;
            }

            $available = $this->availableFor($transfer->source, $user->company_id, (int) $transfer->from_user_id, $transfer->id);
            if ((float) $transfer->amount > $available) {
                return $this->error("La source n'a plus assez de fonds pour ce transfert.", 422);
            }

            $movement = null;
            if ($transfer->source !== 'electronic') {
                $register = $this->openRegister($user->company_id);
                if (! $register) {
                    return $this->error('Aucune caisse ouverte.', 422);
                }

                $movement = CashMovement::create([
                    'cash_register_id' => $register->id,
                    'type' => 'adjustment',
                    'amount' => -1 * (float) $transfer->amount,
                    'description' => 'Transfert vers la Banque'.($transfer->reference ? ' - '.$transfer->reference : ''),
                    'reference' => 'BNK-'.$transfer->id,
                    'created_by' => $transfer->source === 'user_cash' ? $transfer->from_user_id : $transfer->created_by,
                ]);
            }

            $transfer->update([
                'status' => 'accepted',
                'validated_by' => $user->id,
                'validated_at' => now(),
                'cash_movement_id' => $movement?->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Transfert validé, montant ajouté à la Banque',
                'data' => $transfer->fresh(['fromUser:id,name', 'createdBy:id,name', 'validatedBy:id,name']),
            ]);
        });
    }

    /**
     * L'admin refuse le transfert
     */
    public function reject(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        if (! $this->isAdminUser($user)) {
            return $this->error("Seul l'administrateur peut refuser un transfert vers la Banque.", 403);
        }

        return DB::transaction(function () use ($user, $id) {
            $transfer = BankTransfer::where('company_id', $user->company_id)
                ->lockForUpdate()
                ->findOrFail($id);

            if ($transfer->status !== 'pending') {
                return $this->error("Ce transfert n'est plus en attente.", 422);
            }

            $transfer->update([
                'status' => 'rejected',
                'validated_by' => $user->id,
                'validated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Transfert refusé',
            ]);
        });
    }

    /**
     * Le demandeur (ou un admin) annule un transfert en attente
     */
    public function cancel(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        return DB::transaction(function () use ($user, $id) {
            $transfer = BankTransfer::where('company_id', $user->company_id)
                ->lockForUpdate()
                ->findOrFail($id);

            if ($transfer->created_by !== $user->id && ! $this->isAdminUser($user)) {
                return $this->error('Seul le demandeur peut annuler ce transfert.', 403);
            }
            if ($transfer->status !== 'pending') {
                return $this->error("Ce transfert n'est plus en attente.", 422);
            }

            $transfer->update(['status' => 'cancelled']);

            return response()->json([
                'success' => true,
                'message' => 'Transfert annulé',
            ]);
        });
    }

    /**
     * Les transferts depuis une caisse sont journaliers ; ceux de l'argent électronique n'expirent pas
     */
    private function checkPending(BankTransfer $transfer): ?JsonResponse
    {
        if ($transfer->status !== 'pending') {
            return $this->error("Ce transfert n'est plus en attente.", 422);
        }
        if ($transfer->source !== 'electronic' && ! $transfer->transfer_date->isToday()) {
            return $this->error('Ce transfert a expiré (transferts de caisse journaliers uniquement).', 422);
        }

        return null;
    }

    private function availableFor(string $source, int $companyId, int $userId, ?int $exceptId = null): float
    {
        return match ($source) {
            'user_cash' => $this->availableUserCash($companyId, $userId, $exceptId),
            'main_register' => $this->availableMainRegister($companyId, $exceptId),
            'electronic' => $this->availableElectronic($companyId, $exceptId),
        };
    }

    /**
     * Montant caisse du jour de l'utilisateur, moins les transferts en attente (vers un collègue ou la Banque)
     */
    private function availableUserCash(int $companyId, int $userId, ?int $exceptId = null): float
    {
        $pendingCashTransfers = (float) CashTransfer::where('company_id', $companyId)
            ->where('from_user_id', $userId)
            ->where('status', 'pending')
            ->whereDate('transfer_date', Carbon::today())
            ->sum('amount');

        return max(0, CashMovement::userDailyBalance($companyId, $userId)
            - $pendingCashTransfers
            - BankTransfer::pendingFromUserToday($companyId, $userId, $exceptId));
    }

    /**
     * Solde de la caisse principale ouverte, moins tous les transferts Banque en attente qui en sortiront
     */
    private function availableMainRegister(int $companyId, ?int $exceptId = null): float
    {
        $register = $this->openRegister($companyId);
        if (! $register) {
            return 0;
        }

        $pending = (float) BankTransfer::where('company_id', $companyId)
            ->whereIn('source', ['user_cash', 'main_register'])
            ->where('status', 'pending')
            ->whereDate('transfer_date', Carbon::today())
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->sum('amount');

        return max(0, $register->calculateExpectedBalance() - $pending);
    }

    /**
     * Ventes par argent électronique cumulées, moins ce qui a déjà été transféré ou demandé vers la Banque
     */
    private function availableElectronic(int $companyId, ?int $exceptId = null): float
    {
        $sales = (float) Invoice::where('company_id', $companyId)
            ->electronicMoneySales()
            ->sum('invoice_total_amount');

        $transferred = (float) BankTransfer::where('company_id', $companyId)
            ->where('source', 'electronic')
            ->whereIn('status', ['pending', 'accepted'])
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->sum('amount');

        return max(0, $sales - $transferred);
    }

    private function openRegister(int $companyId): ?CashRegister
    {
        return CashRegister::where('company_id', $companyId)
            ->where('status', 'open')
            ->whereNull('hotel_section')
            ->first();
    }

    private function error(string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }

    /**
     * Accès complet à la caisse : admin ou permission "finance"
     */
    private function hasFullCashAccess(User $user): bool
    {
        return $this->isAdminUser($user)
            || $user->roles->contains(fn ($role) => in_array('finance', $role->permissions ?? []));
    }

    private function isAdminUser(User $user): bool
    {
        return $user->roles->contains(function ($role) {
            $name = strtolower($role->name ?? '');
            $label = strtolower($role->label ?? '');

            return in_array($name, ['admin', 'super_admin'])
                || in_array($label, ['administrateur', 'super administrateur']);
        });
    }
}
