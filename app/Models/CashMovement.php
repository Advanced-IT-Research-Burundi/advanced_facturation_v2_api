<?php

namespace App\Models;

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
}
