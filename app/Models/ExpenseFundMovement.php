<?php

namespace App\Models;

use App\Models\Traits\HasCompanyId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseFundMovement extends Model
{
    use HasCompanyId;

    const TYPE_DEPOSIT = 'deposit';

    const TYPE_EXPENSE = 'expense';

    protected $fillable = [
        'company_id',
        'depense_id',
        'type',
        'amount',
        'description',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function depense(): BelongsTo
    {
        return $this->belongsTo(Depense::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Solde de la caisse des dépenses.
     */
    public static function balanceForCompany(int $companyId): float
    {
        $movements = self::where('company_id', $companyId);

        return (float) (clone $movements)->where('type', self::TYPE_DEPOSIT)->sum('amount')
            - (float) (clone $movements)->where('type', self::TYPE_EXPENSE)->sum('amount');
    }
}
