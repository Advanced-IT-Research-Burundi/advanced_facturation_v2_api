<?php

namespace App\Models;

use App\Models\Traits\HasCompanyId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelStockMovement extends Model
{
    use HasCompanyId, HasFactory;

    protected $fillable = [
        'company_id',
        'stock_type',
        'stock_item_id',
        'stock_item_name',
        'movement_type',
        'quantity',
        'quantity_before',
        'quantity_after',
        'reason',
        'reference',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'quantity_before' => 'decimal:3',
            'quantity_after' => 'decimal:3',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
