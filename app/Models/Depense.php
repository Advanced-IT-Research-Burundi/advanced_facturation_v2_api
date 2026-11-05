<?php

namespace App\Models;

use App\Models\Traits\HasCompanyId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Depense extends Model
{
    use HasCompanyId, HasFactory, SoftDeletes;

    const STATUS_PENDING = 'pending';

    const STATUS_APPROVED = 'approved';

    const STATUS_REJECTED = 'rejected';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'montant',
        'depense_category_id',
        'company_id',
        'justification_file',
        'justification_data',
        'justification_mime',
        'user_id',
        'hotel_section',
        'status',
        'validated_by',
        'validated_at',
        'rejection_reason',
    ];

    /**
     * @var array<int, string>
     */
    protected $hidden = [
        'justification_data',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'montant' => 'float',
            'depense_category_id' => 'integer',
            'user_id' => 'integer',
            'validated_by' => 'integer',
            'validated_at' => 'datetime',
        ];
    }

    /**
     * Get the depense category for this depense.
     */
    public function depenseCategory(): BelongsTo
    {
        return $this->belongsTo(DepenseCategory::class);
    }

    /**
     * Get the company that owns this depense.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the user that created this depense.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function cashMovement(): HasOne
    {
        return $this->hasOne(CashMovement::class);
    }

    public function expenseFundMovement(): HasOne
    {
        return $this->hasOne(ExpenseFundMovement::class);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }
}
