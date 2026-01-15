<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PrescriptionItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'prescription_id',
        'product_id',
        'product_lot_id',
        'prescribed_quantity',
        'dispensed_quantity',
        'dosage_instructions',
        'treatment_duration',
        'notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'prescribed_quantity' => 'double',
            'dispensed_quantity' => 'double',
            'treatment_duration' => 'integer',
        ];
    }

    // Relations
    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productLot(): BelongsTo
    {
        return $this->belongsTo(ProductLot::class);
    }

    // Accessors
    public function getRemainingQuantityAttribute(): float
    {
        return $this->prescribed_quantity - $this->dispensed_quantity;
    }

    public function getIsFullyDispensedAttribute(): bool
    {
        return $this->dispensed_quantity >= $this->prescribed_quantity;
    }

    // Methods
    public function dispense(float $quantity, ?int $lotId = null): bool
    {
        if ($quantity > $this->remaining_quantity) {
            return false;
        }

        $this->dispensed_quantity += $quantity;

        if ($lotId) {
            $this->product_lot_id = $lotId;
        }

        if ($this->dispensed_quantity >= $this->prescribed_quantity) {
            $this->status = 'fully_dispensed';
        } else {
            $this->status = 'partially_dispensed';
        }

        return $this->save();
    }

    public function cancel(): bool
    {
        $this->status = 'cancelled';
        return $this->save();
    }
}
