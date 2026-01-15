<?php

namespace App\Models;

use App\Models\Traits\AddUserId;
use App\Models\Traits\HasCompanyId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class ProductLot extends Model
{
    use HasFactory, SoftDeletes, AddUserId, HasCompanyId;

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'lot_number',
        'manufacturing_date',
        'expiration_date',
        'initial_quantity',
        'current_quantity',
        'purchase_price',
        'supplier_reference',
        'fournisseur_id',
        'status',
        'notes',
        'company_id',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'manufacturing_date' => 'date',
            'expiration_date' => 'date',
            'initial_quantity' => 'double',
            'current_quantity' => 'double',
            'purchase_price' => 'double',
        ];
    }

    // Relations
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Fourinsseur::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function patientHistories(): HasMany
    {
        return $this->hasMany(PatientHistory::class);
    }

    public function prescriptionItems(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class);
    }

    public function expirationAlerts(): HasMany
    {
        return $this->hasMany(ExpirationAlert::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active')->where('current_quantity', '>', 0);
    }

    public function scopeExpiringSoon($query, int $days = 90)
    {
        return $query->where('expiration_date', '<=', Carbon::now()->addDays($days))
                     ->where('expiration_date', '>', Carbon::now())
                     ->where('status', 'active');
    }

    public function scopeExpired($query)
    {
        return $query->where('expiration_date', '<', Carbon::now());
    }

    public function scopeByWarehouse($query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeByProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    // Accessors
    public function getDaysUntilExpirationAttribute(): int
    {
        return Carbon::now()->diffInDays($this->expiration_date, false);
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expiration_date < Carbon::now();
    }

    public function getIsExpiringSoonAttribute(): bool
    {
        $alertDays = $this->product->delai_alerte_expiration ?? 90;
        return $this->days_until_expiration <= $alertDays && !$this->is_expired;
    }

    // Methods
    public function decrementQuantity(float $quantity): bool
    {
        if ($this->current_quantity < $quantity) {
            return false;
        }

        $this->current_quantity -= $quantity;

        if ($this->current_quantity <= 0) {
            $this->status = 'depleted';
        }

        return $this->save();
    }

    public function incrementQuantity(float $quantity): bool
    {
        $this->current_quantity += $quantity;

        if ($this->status === 'depleted' && $this->current_quantity > 0) {
            $this->status = 'active';
        }

        return $this->save();
    }
}
