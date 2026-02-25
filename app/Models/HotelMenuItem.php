<?php

namespace App\Models;

use App\Models\Traits\HasCompanyId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HotelMenuItem extends Model
{
    use HasCompanyId, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'category',
        'price',
        'available',
        'description',
        'bar_stock_id',
        'stock_per_serving',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'available' => 'boolean',
            'stock_per_serving' => 'decimal:3',
        ];
    }

    public function barStock(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(HotelBarStock::class, 'bar_stock_id');
    }
}
