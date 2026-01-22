<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'invoice_number',
        'invoice_date',
        'invoice_type',
        'invoice_identifier',
        'invoice_currency',
        'tp_type',
        'tp_name',
        'tp_TIN',
        'tp_trade_number',
        'tp_phone_number',
        'tp_fiscal_center',
        'vat_taxpayer',
        'ct_taxpayer',
        'tl_taxpayer',
        'customer_name',
        'customer_TIN',
        'customer_address',
        'vat_customer_payer',
        'invoice_amount_nvat',
        'invoice_vat_amount',
        'invoice_total_amount',
        'invoice_registered_number',
        'invoice_registered_date',
        'electronic_signature',
        'obr_submission_status',
        'obr_response_message',
        'obr_invoice_identifier',
        'obr_invoice_registered_number',
        'obr_invoice_registered_date',
        'obr_electronic_signature',
        'obr_sent_at',
        'is_cancelled',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
        'company_id',
        'customer_id',
        'created_by',
        'user_id',
        'created_by_id',
        'payment_status',
        'total_paid',
        'due_date',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'invoice_date' => 'datetime',
            'invoice_amount_nvat' => 'decimal:2',
            'invoice_vat_amount' => 'decimal:2',
            'invoice_total_amount' => 'decimal:2',
            'invoice_registered_date' => 'datetime',
            'obr_invoice_registered_date' => 'datetime',
            'obr_sent_at' => 'datetime',
            'is_cancelled' => 'boolean',
            'cancelled_at' => 'datetime',
            'company_id' => 'integer',
            'customer_id' => 'integer',
            'created_by' => 'integer',
            'user_id' => 'integer',
            'created_by_id' => 'integer',
            'cancelled_by' => 'integer',
            'total_paid' => 'decimal:2',
            'due_date' => 'date',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function updatePaymentStatus()
    {
        $totalPaid = $this->payments()->sum('amount');
        $this->total_paid = $totalPaid;

        if ($totalPaid >= $this->invoice_total_amount) {
            $this->payment_status = 'paid';
        } elseif ($totalPaid > 0) {
            $this->payment_status = 'partial';
        } else {
            $this->payment_status = 'unpaid';
        }

        $this->saveQuietly(); // Prevent triggering other events if not needed
    }
}
