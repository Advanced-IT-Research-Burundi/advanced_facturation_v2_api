<?php

namespace App\Models;

use App\Models\Traits\HasCompanyId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HotelRestaurantOrder extends Model
{
    use HasCompanyId, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'hotel_restaurant_table_id',
        'room_number',
        'is_room_service',
        'client_name',
        'total',
        'status',
        'notes',
        'served_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'is_room_service' => 'boolean',
            'served_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /** Libellé d'affichage : "Table X" ou "Chambre X" */
    public function getLocationLabelAttribute(): string
    {
        if ($this->is_room_service) {
            return 'Chambre '.($this->room_number ?? '—');
        }

        return 'Table '.($this->table?->number ?? '—');
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(HotelRestaurantTable::class, 'hotel_restaurant_table_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(HotelRestaurantOrderItem::class);
    }

    public function recalculateTotal(): void
    {
        $this->total = $this->items()->sum(\DB::raw('price * qty'));
        $this->save();
    }
}
