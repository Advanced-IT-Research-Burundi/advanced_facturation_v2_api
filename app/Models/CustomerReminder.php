<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Traits\HasCompanyId;

class CustomerReminder extends Model
{
    use SoftDeletes, HasCompanyId;


    protected $fillable = [
        'invoice_id',
        'customer_id',
        'company_id',
        'type',
        'status',
        'reminder_level',
        'reminder_date',
        'next_reminder_date',
        'message',
        'response_note',
        'amount_due',
        'created_by',
        'acknowledged_by',
        'acknowledged_at',
    ];

    protected $casts = [
        'reminder_date' => 'date',
        'next_reminder_date' => 'date',
        'acknowledged_at' => 'datetime',
        'amount_due' => 'decimal:2',
        'reminder_level' => 'integer',
    ];

    const TYPES = [
        'email' => 'Email',
        'sms' => 'SMS',
        'phone' => 'Appel téléphonique',
        'letter' => 'Courrier',
        'other' => 'Autre',
    ];

    const STATUSES = [
        'pending' => 'En attente',
        'sent' => 'Envoyée',
        'acknowledged' => 'Accusée',
        'paid' => 'Payée',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }
}
