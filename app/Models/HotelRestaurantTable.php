<?php

namespace App\Models;

use App\Models\Traits\HasCompanyId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HotelRestaurantTable extends Model
{
    use HasCompanyId, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'number',
        'seats',
        'location',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'seats' => 'integer',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(HotelRestaurantOrder::class);
    }

    public function activeOrders(): HasMany
    {
        return $this->orders()->whereNotIn('status', ['paid', 'cancelled']);
    }
}
