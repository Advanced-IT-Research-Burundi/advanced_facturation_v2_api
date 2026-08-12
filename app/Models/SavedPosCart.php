<?php

namespace App\Models;

use App\Models\Traits\HasCompanyId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedPosCart extends Model
{
    use HasCompanyId, HasFactory;

    protected $fillable = [
        'local_id',
        'identifier',
        'company_id',
        'user_id',
        'customer_id',
        'warehouse_id',
        'currency',
        'payment_type',
        'total_ht',
        'total_tva',
        'total_ttc',
        'customer_snapshot',
        'items',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'user_id' => 'integer',
            'customer_id' => 'integer',
            'warehouse_id' => 'integer',
            'total_ht' => 'decimal:2',
            'total_tva' => 'decimal:2',
            'total_ttc' => 'decimal:2',
            'customer_snapshot' => 'array',
            'items' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
