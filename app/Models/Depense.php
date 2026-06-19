<?php

namespace App\Models;

use App\Models\Traits\HasCompanyId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Depense extends Model
{
    use HasCompanyId, HasFactory, SoftDeletes;

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

    public function cashMovement(): HasOne
    {
        return $this->hasOne(CashMovement::class);
    }
}
