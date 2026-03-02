<?php

namespace App\Models;

use App\Models\Traits\HasCompanyId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HotelBarStock extends Model
{
    use HasCompanyId, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'quantity',
        'unit',
        'alert_threshold',
        'purchase_price',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'alert_threshold' => 'decimal:3',
            'purchase_price' => 'decimal:2',
        ];
    }

    public function isLowStock(): bool
    {
        return $this->quantity <= $this->alert_threshold;
    }
}
