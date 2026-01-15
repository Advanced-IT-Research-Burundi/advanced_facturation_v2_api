<?php

namespace App\Models;

use App\Models\Traits\AddUserId;
use App\Models\Traits\HasCompanyId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Prescription extends Model
{
    use HasFactory, SoftDeletes, AddUserId, HasCompanyId;

    protected $fillable = [
        'prescription_number',
        'customer_id',
        'invoice_id',
        'patient_name',
        'patient_birthdate',
        'patient_phone',
        'prescriber_name',
        'prescriber_registration',
        'prescriber_phone',
        'prescriber_address',
        'prescription_date',
        'validity_date',
        'diagnosis',
        'notes',
        'status',
        'prescription_image',
        'company_id',
        'user_id',
        'dispensed_by',
        'dispensed_at',
    ];

    protected function casts(): array
    {
        return [
            'patient_birthdate' => 'date',
            'prescription_date' => 'date',
            'validity_date' => 'date',
            'dispensed_at' => 'datetime',
        ];
    }

    // Relations
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class);
    }

    public function dispensedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispensed_by');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function patientHistories(): HasMany
    {
        return $this->hasMany(PatientHistory::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeValid($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('validity_date')
              ->orWhere('validity_date', '>=', now());
        });
    }

    public function scopeExpired($query)
    {
        return $query->where('validity_date', '<', now());
    }

    // Methods
    public function updateStatus(): void
    {
        $items = $this->items;

        if ($items->isEmpty()) {
            return;
        }

        $allDispensed = $items->every(fn ($item) => $item->status === 'fully_dispensed');
        $someDispensed = $items->contains(fn ($item) => $item->dispensed_quantity > 0);

        if ($allDispensed) {
            $this->status = 'fully_dispensed';
        } elseif ($someDispensed) {
            $this->status = 'partially_dispensed';
        }

        $this->save();
    }

    public static function generateNumber(): string
    {
        $prefix = 'ORD';
        $date = now()->format('Ymd');
        $last = static::whereDate('created_at', today())->count() + 1;
        return sprintf('%s-%s-%04d', $prefix, $date, $last);
    }

    public function isValid(): bool
    {
        if ($this->validity_date === null) {
            return true;
        }
        return $this->validity_date >= now();
    }
}
