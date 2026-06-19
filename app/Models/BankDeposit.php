<?php

namespace App\Models;

use App\Models\Traits\HasCompanyId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankDeposit extends Model
{
    use HasCompanyId, SoftDeletes;

    protected $fillable = [
        'company_id',
        'cash_register_id',
        'created_by',
        'amount',
        'deposit_date',
        'bank_name',
        'account_name',
        'account_number',
        'reference',
        'note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'deposit_date' => 'date',
        'created_by' => 'integer',
        'cash_register_id' => 'integer',
    ];

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cashMovement(): HasOne
    {
        return $this->hasOne(CashMovement::class);
    }
}
