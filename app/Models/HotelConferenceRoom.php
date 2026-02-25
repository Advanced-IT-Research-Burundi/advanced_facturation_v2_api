<?php

namespace App\Models;

use App\Models\Traits\HasCompanyId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HotelConferenceRoom extends Model
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
            'capacity' => 'integer',
            'price_per_hour' => 'decimal:2',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(HotelConferenceBooking::class);
    }

    public function activeBookings(): HasMany
    {
        return $this->bookings()->where('status', 'confirmed');
    }
}
