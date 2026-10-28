<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashTransfer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Transferts de montant entre les caisses de deux utilisateurs (journalier).
 * Le montant ne bouge qu'à la validation par le destinataire.
 */
class CashTransferController extends Controller
{
    /**
     * Liste des transferts d'une journée
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $date = $request->get('date', Carbon::today()->format('Y-m-d'));
        $isAdmin = $this->isAdminUser($user);

        $transfers = CashTransfer::with(['fromUser:id,name', 'toUser:id,name', 'validatedBy:id,name'])
            ->where('company_id', $user->company_id)
            ->whereDate('transfer_date', $date)
            ->when(! $isAdmin, function ($query) use ($user) {
                $query->where(fn ($q) => $q->where('from_user_id', $user->id)->orWhere('to_user_id', $user->id));
            })
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $date,
                'is_today' => Carbon::parse($date)->isToday(),
                'transfers' => $transfers,
            ],
        ]);
    }

    /**
     * Utilisateurs pouvant recevoir un transfert
     */
    public function recipients(Request $request)
    {
        $user = $request->user();

        $users = User::where('company_id', $user->company_id)
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * Montant disponible dans la caisse d'un utilisateur aujourd'hui
     */
    public function available(Request $request, $userId)
    {
        $user = $request->user();
        $fromUser = User::where('company_id', $user->company_id)->findOrFail($userId);

        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => $fromUser->id,
                'balance' => $this->userDailyBalance($user->company_id, $fromUser->id),
                'pending_out' => $this->pendingOutgoing($user->company_id, $fromUser->id),
                'available' => $this->availableAmount($user->company_id, $fromUser->id),
            ],
        ]);
    }

    /**
     * Créer un transfert (en attente de validation par le destinataire)
     */
    public function store(Request $request)
    {
        $request->validate([
            'from_user_id' => 'required|integer|exists:users,id',
            'to_user_id' => 'required|integer|exists:users,id|different:from_user_id',
            'amount' => 'required|numeric|min:0.01',
            'note' => 'nullable|string|max:255',
        ]);

        $user = $request->user();
        $companyId = $user->company_id;

        if ((int) $request->from_user_id !== $user->id && ! $this->isAdminUser($user)) {
            return $this->error('Vous ne pouvez transférer que depuis votre propre caisse.', 403);
        }

        $fromUser = User::where('company_id', $companyId)->find($request->from_user_id);
        $toUser = User::where('company_id', $companyId)->find($request->to_user_id);
        if (! $fromUser || ! $toUser) {
            return $this->error('Utilisateur introuvable.', 404);
        }

        $register = $this->openRegister($companyId);
        if (! $register) {
            return $this->error('Aucune caisse ouverte.', 422);
        }

        return DB::transaction(function () use ($request, $user, $companyId, $register) {
            // Verrouiller les transferts en attente de l'expéditeur pour éviter un double envoi
            CashTransfer::where('company_id', $companyId)
                ->where('from_user_id', $request->from_user_id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->get();

            $available = $this->availableAmount($companyId, (int) $request->from_user_id);
            if ((float) $request->amount > $available) {
                return $this->error('Montant supérieur au disponible en caisse ('.number_format($available, 2, ',', ' ').' FBU).', 422);
            }

            $transfer = CashTransfer::create([
                'company_id' => $companyId,
                'cash_register_id' => $register->id,
                'from_user_id' => $request->from_user_id,
                'to_user_id' => $request->to_user_id,
                'created_by' => $user->id,
                'amount' => $request->amount,
                'transfer_date' => Carbon::today(),
                'status' => 'pending',
                'note' => $request->note,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Transfert envoyé, en attente de validation par le destinataire',
                'data' => $transfer->load(['fromUser:id,name', 'toUser:id,name']),
            ], 201);
        });
    }

    /**
     * Le destinataire valide l'arrivée du montant dans sa caisse
     */
    public function accept(Request $request, $id)
    {
        $user = $request->user();

        return DB::transaction(function () use ($user, $id) {
            $transfer = CashTransfer::where('company_id', $user->company_id)
                ->lockForUpdate()
                ->findOrFail($id);

            if ($check = $this->checkPendingForRecipient($transfer, $user)) {
                return $check;
            }

            $register = $this->openRegister($user->company_id);
            if (! $register) {
                return $this->error('Aucune caisse ouverte.', 422);
            }

            // Le disponible exclut ce transfert-ci des montants réservés
            $available = $this->userDailyBalance($user->company_id, $transfer->from_user_id)
                - $this->pendingOutgoing($user->company_id, $transfer->from_user_id, $transfer->id);
            if ((float) $transfer->amount > $available) {
                return $this->error("La caisse de l'expéditeur n'a plus assez de fonds.", 422);
            }

            $fromName = $transfer->fromUser?->name ?? 'Utilisateur';
            $toName = $transfer->toUser?->name ?? 'Utilisateur';
            $reference = 'TRF-'.$transfer->id;

            $out = CashMovement::create([
                'cash_register_id' => $register->id,
                'type' => 'adjustment',
                'amount' => -1 * (float) $transfer->amount,
                'description' => "Transfert vers {$toName}",
                'reference' => $reference,
                'created_by' => $transfer->from_user_id,
            ]);

            $in = CashMovement::create([
                'cash_register_id' => $register->id,
                'type' => 'adjustment',
                'amount' => (float) $transfer->amount,
                'description' => "Transfert reçu de {$fromName}",
                'reference' => $reference,
                'created_by' => $transfer->to_user_id,
            ]);

            $transfer->update([
                'status' => 'accepted',
                'validated_by' => $user->id,
                'validated_at' => now(),
                'out_movement_id' => $out->id,
                'in_movement_id' => $in->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Transfert validé, montant ajouté à votre caisse',
                'data' => $transfer->fresh(['fromUser:id,name', 'toUser:id,name', 'validatedBy:id,name']),
            ]);
        });
    }

    /**
     * Le destinataire refuse le transfert
     */
    public function reject(Request $request, $id)
    {
        $user = $request->user();
        $transfer = CashTransfer::where('company_id', $user->company_id)->findOrFail($id);

        if ($check = $this->checkPendingForRecipient($transfer, $user)) {
            return $check;
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
    }

    /**
     * L'expéditeur (ou un admin) annule un transfert en attente
     */
    public function cancel(Request $request, $id)
    {
        $user = $request->user();
        $transfer = CashTransfer::where('company_id', $user->company_id)->findOrFail($id);

        if ($transfer->from_user_id !== $user->id && ! $this->isAdminUser($user)) {
            return $this->error('Seul l\'expéditeur peut annuler ce transfert.', 403);
        }
        if ($transfer->status !== 'pending') {
            return $this->error('Ce transfert n\'est plus en attente.', 422);
        }

        $transfer->update(['status' => 'cancelled']);

        return response()->json([
            'success' => true,
            'message' => 'Transfert annulé',
        ]);
    }

    private function checkPendingForRecipient(CashTransfer $transfer, User $user)
    {
        if ($transfer->to_user_id !== $user->id) {
            return $this->error('Seul le destinataire peut valider ou refuser ce transfert.', 403);
        }
        if ($transfer->status !== 'pending') {
            return $this->error('Ce transfert n\'est plus en attente.', 422);
        }
        if (! $transfer->transfer_date->isToday()) {
            return $this->error('Ce transfert a expiré (transferts journaliers uniquement).', 422);
        }

        return null;
    }

    private function openRegister(int $companyId): ?CashRegister
    {
        return CashRegister::where('company_id', $companyId)
            ->where('status', 'open')
            ->whereNull('hotel_section')
            ->first();
    }

    /**
     * Montant caisse du jour d'un utilisateur (même calcul que le résumé journalier)
     */
    private function userDailyBalance(int $companyId, int $userId): float
    {
        $registerIds = CashRegister::where('company_id', $companyId)
            ->whereNull('hotel_section')
            ->pluck('id');

        $row = CashMovement::whereIn('cash_register_id', $registerIds)
            ->where('created_by', $userId)
            ->whereBetween('created_at', [Carbon::today()->startOfDay(), Carbon::today()->endOfDay()])
            ->selectRaw("
                COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as total_income,
                COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as total_expense,
                COALESCE(SUM(CASE WHEN type = 'adjustment' THEN amount ELSE 0 END), 0) as total_adjustment
            ")
            ->first();

        return (float) $row->total_income - (float) $row->total_expense + (float) $row->total_adjustment;
    }

    private function pendingOutgoing(int $companyId, int $userId, ?int $exceptId = null): float
    {
        return (float) CashTransfer::where('company_id', $companyId)
            ->where('from_user_id', $userId)
            ->where('status', 'pending')
            ->whereDate('transfer_date', Carbon::today())
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->sum('amount');
    }

    private function availableAmount(int $companyId, int $userId): float
    {
        return max(0, $this->userDailyBalance($companyId, $userId) - $this->pendingOutgoing($companyId, $userId));
    }

    private function error(string $message, int $status)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }

    /**
     * Accès complet à la caisse : admin ou permission "finance"
     */
    private function isAdminUser(User $user): bool
    {
        return $user->roles->contains(function ($role) {
            if (in_array('finance', $role->permissions ?? [])) {
                return true;
            }

            $name = strtolower($role->name ?? '');
            $label = strtolower($role->label ?? '');

            return in_array($name, ['admin', 'super_admin'])
                || in_array($label, ['administrateur', 'super administrateur']);
        });
    }
}
