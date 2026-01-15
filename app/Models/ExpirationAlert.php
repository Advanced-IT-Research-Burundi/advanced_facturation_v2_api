<?php

namespace App\Models;

use App\Models\Traits\HasCompanyId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpirationAlert extends Model
{
    use HasFactory, HasCompanyId;

    protected $fillable = [
        'product_id',
        'product_lot_id',
        'warehouse_id',
        'expiration_date',
        'days_until_expiration',
        'quantity_at_risk',
        'alert_level',
        'status',
        'acknowledged_by',
        'acknowledged_at',
        'action_taken',
        'company_id',
    ];

    protected function casts(): array
    {
        return [
            'expiration_date' => 'date',
            'days_until_expiration' => 'integer',
            'quantity_at_risk' => 'double',
            'acknowledged_at' => 'datetime',
        ];
    }

    // Relations
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productLot(): BelongsTo
    {
        return $this->belongsTo(ProductLot::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCritical($query)
    {
        return $query->whereIn('alert_level', ['critical', 'expired']);
    }

    public function scopeWarning($query)
    {
        return $query->where('alert_level', 'warning');
    }

    public function scopeUnacknowledged($query)
    {
        return $query->whereNull('acknowledged_by');
    }

    // Methods
    public function acknowledge(int $userId, ?string $action = null): bool
    {
        $this->acknowledged_by = $userId;
        $this->acknowledged_at = now();
        $this->status = 'acknowledged';
        $this->action_taken = $action;

        return $this->save();
    }

    public function resolve(?string $action = null): bool
    {
        $this->status = 'resolved';
        if ($action) {
            $this->action_taken = $action;
        }

        return $this->save();
    }

    // Static Methods
    public static function determineAlertLevel(int $daysUntilExpiration, int $alertThreshold = 90): ?string
    {
        if ($daysUntilExpiration < 0) {
            return 'expired';
        } elseif ($daysUntilExpiration <= 30) {
            return 'critical';
        } elseif ($daysUntilExpiration <= $alertThreshold) {
            return 'warning';
        }

        return null;
    }
}
