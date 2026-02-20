<?php

namespace App\Models;

use App\Models\Traits\HasCompanyId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HotelReservation extends Model
{
    use HasCompanyId, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'hotel_room_id',
        'customer_id',
        'guest_name',
        'guest_phone',
        'guest_email',
        'check_in_date',
        'check_out_date',
        'actual_check_in_at',
        'actual_check_out_at',
        'nights',
        'price_per_night',
        'total_amount',
        'advance_payment',
        'balance_due',
        'status',
        'notes',
        'invoice_id',
    ];

    protected function casts(): array
    {
        return [
            'check_in_date' => 'date',
            'check_out_date' => 'date',
            'actual_check_in_at' => 'datetime',
            'actual_check_out_at' => 'datetime',
            'nights' => 'integer',
            'price_per_night' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'advance_payment' => 'decimal:2',
            'balance_due' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(HotelRoom::class, 'hotel_room_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function recalculateTotals(): void
    {
        $nights = (int) $this->check_in_date->diffInDays($this->check_out_date);
        $this->nights = max(1, $nights);
        $this->total_amount = $this->price_per_night * $this->nights;
        $this->balance_due = $this->total_amount - $this->advance_payment;
    }
}
