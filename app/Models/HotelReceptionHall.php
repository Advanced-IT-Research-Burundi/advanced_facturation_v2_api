<?php

namespace App\Models;

use App\Models\Traits\HasCompanyId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HotelReceptionHall extends Model
{
    use HasCompanyId, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'floor',
        'capacity',
        'price_per_hour',
        'status',
        'equipment',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'price_per_hour' => 'decimal:2',
            'capacity' => 'integer',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(HotelReceptionBooking::class);
    }

    public function activeBookings(): HasMany
    {
        return $this->hasMany(HotelReceptionBooking::class)
            ->whereIn('status', ['confirmed']);
    }
}
