<?php

namespace App\Models;

use App\Models\Traits\AddUserId;
use App\Models\Traits\HasCompanyId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PatientHistory extends Model
{
    use HasFactory, SoftDeletes, AddUserId, HasCompanyId;

    protected $fillable = [
        'customer_id',
        'product_id',
        'product_lot_id',
        'invoice_id',
        'prescription_id',
        'quantity',
        'unit_price',
        'purchase_date',
        'lot_number',
        'lot_expiration',
        'notes',
        'requires_followup',
        'followup_date',
        'company_id',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'double',
            'unit_price' => 'double',
            'purchase_date' => 'date',
            'lot_expiration' => 'date',
            'followup_date' => 'date',
            'requires_followup' => 'boolean',
        ];
    }

    // Relations
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productLot(): BelongsTo
    {
        return $this->belongsTo(ProductLot::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeRequiringFollowup($query)
    {
        return $query->where('requires_followup', true)
                     ->where('followup_date', '<=', now()->addDays(7));
    }

    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('purchase_date', '>=', now()->subDays($days));
    }

    // Accessors
    public function getTotalAmountAttribute(): float
    {
        return $this->quantity * $this->unit_price;
    }
}
