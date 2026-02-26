<?php

namespace App\Models;

use App\Models\Traits\HasCompanyId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HotelReceptionBooking extends Model
{
    use HasCompanyId, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'hotel_reception_hall_id',
        'guest_name',
        'guest_phone',
        'booking_date',
        'start_time',
        'end_time',
        'purpose',
        'advance_payment',
        'total_amount',
        'notes',
        'status',
        'invoice_id',
    ];

    protected function casts(): array
    {
        return [
            'booking_date' => 'date',
            'advance_payment' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function receptionHall(): BelongsTo
    {
        return $this->belongsTo(HotelReceptionHall::class, 'hotel_reception_hall_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
