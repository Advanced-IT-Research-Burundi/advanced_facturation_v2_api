<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockMovement extends Model
{
    use HasFactory, SoftDeletes;

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
            'item_quantity' => 'double',
            'item_purchase_or_sale_price' => 'double',
            'item_movement_date' => 'datetime',
            'obr_sent_at' => 'datetime',
            'company_id' => 'integer',
            'product_id' => 'integer',
            'warehouse_id' => 'integer',
            'created_by' => 'integer',
            'user_id' => 'integer',
            'created_by_id' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }



    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

}
