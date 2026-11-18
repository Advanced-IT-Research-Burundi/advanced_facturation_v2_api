<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashMovement extends Model
{
    protected $fillable = [
        'cash_register_id',
        'invoice_id',
        'payment_id',
        'depense_id',
        'bank_deposit_id',
        'type',
        'amount',
        'description',
        'reference',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    const TYPES = [
        'income' => 'Entrée',
        'expense' => 'Sortie',
        'adjustment' => 'Ajustement',
    ];

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function depense(): BelongsTo
    {
        return $this->belongsTo(Depense::class);
    }

    public function bankDeposit(): BelongsTo
    {
        return $this->belongsTo(BankDeposit::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Montant caisse du jour d'un utilisateur sur la caisse principale (même calcul que le résumé journalier)
     */
    public static function userDailyBalance(int $companyId, int $userId): float
    {
        $registerIds = CashRegister::where('company_id', $companyId)
            ->whereNull('hotel_section')
            ->pluck('id');

        $row = static::whereIn('cash_register_id', $registerIds)
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
}
