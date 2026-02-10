<?php

namespace App\Models;

use App\Models\Traits\HasCompanyId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WarehouseProduct extends Model
{
    use HasFactory, SoftDeletes, HasCompanyId;


    protected $fillable = [
        'product_id',
        'warehouse_id',
        'quantity',
        'unit_price',
        'currency',
        'last_stock_movement_id',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'product_id' => 'integer',
            'warehouse_id' => 'integer',
            'quantity' => 'double', // Crucial pour les stocks fractionnables
            'unit_price' => 'double',
            'last_stock_movement_id' => 'integer',
            'user_id' => 'integer',
        ];
    }

    public function product(): BelongsTo {
        return $this->belongsTo(Product::class, 'product_id'); 
    }

    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    
    public function lastStockMovement(): BelongsTo 
    { 
        return $this->belongsTo(StockMovement::class, 'last_stock_movement_id'); 
    }
}