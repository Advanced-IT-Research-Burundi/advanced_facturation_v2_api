<?php

namespace App\Models;

use App\Models\Traits\HasCompanyId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HotelConferenceBooking extends Model
{
    use HasCompanyId, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'hotel_conference_room_id',
        'guest_name',
        'guest_phone',
        'booking_date',
        'start_time',
        'end_time',
        'purpose',
        'advance_payment',
        'notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'booking_date' => 'date',
            'advance_payment' => 'decimal:2',
        ];
    }

    public function conferenceRoom(): BelongsTo
    {
        return $this->belongsTo(HotelConferenceRoom::class, 'hotel_conference_room_id');
    }
}
