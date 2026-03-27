<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseTransferItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'transfer_id',
        'product_id',
        'quantity',
        'unit_price',
        'currency',
        'stock_movement_out_id',
        'stock_movement_in_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
        ];
    }

    public function warehouseTransfer(): BelongsTo
    {
        return $this->belongsTo(WarehouseTransfer::class, 'transfer_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
