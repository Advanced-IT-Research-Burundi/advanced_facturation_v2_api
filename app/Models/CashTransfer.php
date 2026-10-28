<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashTransfer extends Model
{
    protected $fillable = [
        'company_id',
        'cash_register_id',
        'from_user_id',
        'to_user_id',
        'created_by',
        'amount',
        'transfer_date',
        'status',
        'note',
        'validated_by',
        'validated_at',
        'out_movement_id',
        'in_movement_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transfer_date' => 'date:Y-m-d',
        'validated_at' => 'datetime',
    ];

    const STATUSES = [
        'pending' => 'En attente',
        'accepted' => 'Validé',
        'rejected' => 'Refusé',
        'cancelled' => 'Annulé',
    ];

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}
