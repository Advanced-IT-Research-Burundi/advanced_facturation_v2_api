<?php

namespace App\Models;

use App\Models\Traits\HasCompanyId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HotelDish extends Model
{
    use HasCompanyId, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'category',
        'price',
        'prep_time',
        'ingredients',
        'description',
        'available',
        'kitchen_stock_id',
        'stock_per_serving',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'prep_time' => 'integer',
            'available' => 'boolean',
            'stock_per_serving' => 'decimal:3',
        ];
    }

    public function kitchenStock(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(HotelKitchenStock::class, 'kitchen_stock_id');
    }
}
