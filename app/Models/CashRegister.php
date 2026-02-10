<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Traits\HasCompanyId;

class CashRegister extends Model
{
    use SoftDeletes, HasCompanyId;


    protected $fillable = [
        'company_id',
        'warehouse_id',
        'opened_by',
        'closed_by',
        'opening_balance',
        'closing_balance',
        'expected_balance',
        'difference',
        'opened_at',
        'closed_at',
        'status',
        'opening_note',
        'closing_note',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'closing_balance' => 'decimal:2',
        'expected_balance' => 'decimal:2',
        'difference' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function calculateExpectedBalance(): float
    {
        $income = $this->movements()->where('type', 'income')->sum('amount');
        $expense = $this->movements()->where('type', 'expense')->sum('amount');
        $adjustments = $this->movements()->where('type', 'adjustment')->sum('amount');

        return $this->opening_balance + $income - $expense + $adjustments;
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
