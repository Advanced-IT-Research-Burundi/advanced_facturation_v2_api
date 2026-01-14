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
    use HasFactory, SoftDeletes, AddUserId, HasCompanyId;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'item_code',
        'item_designation',
        'item_measurement_unit',
        'barcode',
        'vat_rate',
        'company_id',
        'product_unit_id',
        'product_category_id',
        'user_id',
        'code_product',
        'marque',
        'quantite',
        'quantite_alert',
        'price',
        'price_ttc',
        'price_max',
        'price_min',
        'price_tvac',
        'item_ott_tax',
        'item_tsce_tax',
        'date_expiration',
        'image',
        'type',
        'description',
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
}
