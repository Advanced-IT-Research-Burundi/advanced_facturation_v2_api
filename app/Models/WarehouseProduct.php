<?php

namespace App\Models;

use App\Models\Traits\HasCompanyId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WarehouseProduct extends Model
{
    use HasCompanyId, HasFactory, SoftDeletes;

    protected $appends = [
        'alert_threshold',
        'is_alert',
    ];

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'company_id',
        'quantity',
        'unit_price',
        'price_promo',
        'currency',
        'production_status',
        'last_stock_movement_id',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'product_id' => 'integer',
            'warehouse_id' => 'integer',
            'company_id' => 'integer',
            'quantity' => 'double', // Crucial pour les stocks fractionnables
            'unit_price' => 'double',
            'price_promo' => 'double',
            'last_stock_movement_id' => 'integer',
            'user_id' => 'integer',
        ];
    }

    /**
     * Le prix unitaire du stock et products.price restent identiques :
     * - un stock sans prix reprend le prix du produit ;
     * - un nouveau prix saisi sur un stock devient le prix du produit
     *   et de tous ses autres stocks.
     */
    protected static function booted(): void
    {
        static::saving(function (WarehouseProduct $warehouseProduct): void {
            if ((float) $warehouseProduct->unit_price > 0) {
                return;
            }

            $productPrice = (float) $warehouseProduct->product?->price;

            if ($productPrice > 0) {
                $warehouseProduct->unit_price = $productPrice;
            }
        });

        static::saved(function (WarehouseProduct $warehouseProduct): void {
            if (! $warehouseProduct->wasRecentlyCreated && ! $warehouseProduct->wasChanged('unit_price')) {
                return;
            }

            $product = $warehouseProduct->product;
            $unitPrice = (float) $warehouseProduct->unit_price;

            if (! $product || $unitPrice <= 0 || (float) $product->price === $unitPrice) {
                return;
            }

            $product->price = $unitPrice;
            $product->price_ttc = round($unitPrice * (1 + (float) $product->vat_rate / 100), 2);
            $product->saveQuietly();

            static::query()
                ->where('product_id', $product->id)
                ->whereKeyNot($warehouseProduct->getKey())
                ->update(['unit_price' => $unitPrice]);
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lastStockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'last_stock_movement_id');
    }

    public function getAlertThresholdAttribute(): float
    {
        return (float) ($this->product?->quantite_alert ?? 0);
    }

    public function getIsAlertAttribute(): bool
    {
        return (float) $this->quantity <= $this->alert_threshold;
    }
}
