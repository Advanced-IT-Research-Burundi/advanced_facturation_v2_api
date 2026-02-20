<?php

namespace App\Models;

use App\Models\Traits\HasCompanyId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HotelRoom extends Model
{
    use HasCompanyId, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'room_number',
        'name',
        'type',
        'floor',
        'capacity',
        'price_per_night',
        'status',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'price_per_night' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(HotelReservation::class);
    }

    public function activeReservations(): HasMany
    {
        return $this->reservations()->whereIn('status', ['confirmed', 'checked_in']);
    }

    public function updateStatusFromReservations(): void
    {
        $hasCheckedIn = $this->reservations()->where('status', 'checked_in')->exists();
        $hasConfirmed = $this->reservations()
            ->where('status', 'confirmed')
            ->whereDate('check_in_date', '<=', now())
            ->exists();

        if ($hasCheckedIn) {
            $this->update(['status' => 'occupied']);
        } elseif ($hasConfirmed) {
            $this->update(['status' => 'reserved']);
        } else {
            $this->update(['status' => 'available']);
        }
    }

    public function isAvailableForDates(string $checkIn, string $checkOut, ?int $excludeReservationId = null): bool
    {
        $query = $this->reservations()
            ->whereNotIn('status', ['cancelled', 'checked_out'])
            ->where(function ($q) use ($checkIn, $checkOut) {
                $q->whereBetween('check_in_date', [$checkIn, $checkOut])
                    ->orWhereBetween('check_out_date', [$checkIn, $checkOut])
                    ->orWhere(function ($q2) use ($checkIn, $checkOut) {
                        $q2->where('check_in_date', '<=', $checkIn)
                            ->where('check_out_date', '>=', $checkOut);
                    });
            });

        if ($excludeReservationId) {
            $query->where('id', '!=', $excludeReservationId);
        }

        return ! $query->exists();
    }
}
