<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelRestaurantOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_restaurant_order_id',
        'hotel_menu_item_id',
        'hotel_dish_id',
        'item_type',
        'name',
        'price',
        'qty',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'qty' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(HotelRestaurantOrder::class, 'hotel_restaurant_order_id');
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(HotelMenuItem::class, 'hotel_menu_item_id');
    }
}
