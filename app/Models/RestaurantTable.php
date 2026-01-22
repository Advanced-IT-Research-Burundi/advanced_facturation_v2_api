<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RestaurantTable extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'table_number',
        'capacity',
        'status',
        'location',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(RestaurantOrder::class);
    }

    public function activeOrders(): HasMany
    {
        return $this->orders()->whereIn('status', ['open', 'served']);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function updateStatus(): void
    {
        $hasActiveOrders = $this->activeOrders()->exists();
        $this->update(['status' => $hasActiveOrders ? 'occupied' : 'free']);
    }
}
