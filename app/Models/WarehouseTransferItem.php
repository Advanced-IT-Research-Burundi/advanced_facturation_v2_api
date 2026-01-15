<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class WarehouseTransferItem extends Model
{
    protected $fillable = [
        'transfer_id',
        'product_id',
        'quantity',
        'unit_price',
        'currency',
        'stock_movement_out_id',
        'stock_movement_in_id'
    ];

    public function transfer()
    {
        return $this->belongsTo(WarehouseTransfer::class, 'transfer_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function movementOut()
    {
        return $this->belongsTo(StockMovement::class, 'stock_movement_out_id');
    }

    public function movementIn()
    {
        return $this->belongsTo(StockMovement::class, 'stock_movement_in_id');
    }
}
