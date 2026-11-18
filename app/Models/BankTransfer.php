<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Transfert vers la caisse Banque (unique par société).
 * Le montant ne sort de la caisse source qu'à la validation par un admin.
 */
class BankTransfer extends Model
{
    protected $fillable = [
        'company_id',
        'source',
        'from_user_id',
        'cash_register_id',
        'created_by',
        'amount',
        'transfer_date',
        'reference',
        'note',
        'status',
        'validated_by',
        'validated_at',
        'cash_movement_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transfer_date' => 'date:Y-m-d',
        'validated_at' => 'datetime',
    ];

    const SOURCES = [
        'user_cash' => 'Caisse utilisateur',
        'main_register' => 'Caisse principale',
        'electronic' => 'Argent électronique',
    ];

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    /**
     * Montant réservé aujourd'hui par les transferts banque en attente depuis la caisse d'un utilisateur
     */
    public static function pendingFromUserToday(int $companyId, int $userId, ?int $exceptId = null): float
    {
        return (float) static::where('company_id', $companyId)
            ->where('source', 'user_cash')
            ->where('from_user_id', $userId)
            ->where('status', 'pending')
            ->whereDate('transfer_date', Carbon::today())
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->sum('amount');
    }
}
