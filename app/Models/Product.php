<?php

namespace App\Models;

use App\Models\Traits\AddUserId;
use App\Models\Traits\HasCompanyId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use AddUserId, HasCompanyId, HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'vat_rate' => 'double',
            'quantite' => 'double',
            'quantite_alert' => 'double',
            'price' => 'double',
            'price_ttc' => 'double',
            'price_max' => 'double',
            'price_min' => 'double',
            'price_tvac' => 'double',
            'item_ott_tax' => 'double',
            'item_tsce_tax' => 'double',
            'company_id' => 'integer',
            'product_unit_id' => 'integer',
            'product_category_id' => 'integer',
            'user_id' => 'integer',
            'date_expiration' => 'date',
            // Casts pharmaceutiques
            'is_production' => 'boolean',
            'is_pharmaceutical' => 'boolean',
            'requires_prescription' => 'boolean',
            'is_controlled_substance' => 'boolean',
            'delai_alerte_expiration' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class);
    }

    public function categoryProduct(): BelongsTo
    {
        return $this->belongsTo(CategoryProduct::class, 'product_category_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function warehouseProducts(): HasMany
    {
        return $this->hasMany(WarehouseProduct::class);
    }

    // Relations pharmaceutiques
    public function lots(): HasMany
    {
        return $this->hasMany(ProductLot::class);
    }

    public function activeLots(): HasMany
    {
        return $this->hasMany(ProductLot::class)->where('status', 'active')->where('current_quantity', '>', 0);
    }

    public function patientHistories(): HasMany
    {
        return $this->hasMany(PatientHistory::class);
    }

    public function expirationAlerts(): HasMany
    {
        return $this->hasMany(ExpirationAlert::class);
    }

    // Scopes pharmaceutiques
    public function scopePharmaceutical($query)
    {
        return $query->where('is_pharmaceutical', true);
    }

    public function scopeNonPharmaceutical($query)
    {
        return $query->where('is_pharmaceutical', false);
    }

    public function scopeRequiresPrescription($query)
    {
        return $query->where('requires_prescription', true);
    }

    public function scopeControlledSubstance($query)
    {
        return $query->where('is_controlled_substance', true);
    }

    // Accessors pharmaceutiques
    public function getTotalLotQuantityAttribute(): float
    {
        return $this->activeLots()->sum('current_quantity');
    }

    public function getHasExpiringLotsAttribute(): bool
    {
        $alertDays = $this->delai_alerte_expiration ?? 90;

        return $this->activeLots()
            ->where('expiration_date', '<=', now()->addDays($alertDays))
            ->exists();
    }
}
