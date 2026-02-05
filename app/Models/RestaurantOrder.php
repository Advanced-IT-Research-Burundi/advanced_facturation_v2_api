<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RestaurantOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'restaurant_table_id',
        'server_id',
        'order_number',
        'status',
        'total_amount',
        'opened_at',
        'closed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'restaurant_table_id');
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(User::class, 'server_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RestaurantOrderItem::class);
    }

    public function calculateTotal(): void
    {
        $this->total_amount = $this->items()->where('status', '!=', 'cancelled')->sum('total_price');
        $this->saveQuietly();
    }

    public function markAsInvoiced(): void
    {
        $this->update([
            'status' => 'invoiced',
            'closed_at' => now(),
        ]);
    }

    public static function generateOrderNumber(int $companyId): string
    {
        $today = now()->format('Ymd');
        $count = self::where('company_id', $companyId)
            ->whereDate('created_at', now())
            ->count() + 1;
        return "CMD-{$today}-" . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}
