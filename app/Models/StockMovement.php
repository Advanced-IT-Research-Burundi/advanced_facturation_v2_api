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
    protected $fillable = [
        'system_or_device_id',
        'item_code',
        'item_designation',
        'item_quantity',
        'item_measurement_unit',
        'item_purchase_or_sale_price',
        'item_purchase_or_sale_currency',
        'item_movement_type',
        'item_movement_invoice_ref',
        'item_movement_description',
        'item_movement_date',
        'obr_submission_status',
        'obr_response_message',
        'obr_sent_at',
        'company_id',
        'product_id',
        'warehouse_id',
        'created_by',
        'user_id',
        'created_by_id',
    ];

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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
