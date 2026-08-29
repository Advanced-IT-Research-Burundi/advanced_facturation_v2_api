<?php

namespace App\Models;

use App\Models\Traits\HasCompanyId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Services\ObrService;

class Invoice extends Model
{
    use HasCompanyId, HasFactory, SoftDeletes;

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
        'payment_type',
        'payment_method_id',
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
        'warehouse_id',
        'created_by',
        'user_id',
        'created_by_id',
        'payment_status',
        'total_paid',
        'due_date',
        'restaurant_table_id',
        'server_id',
        'is_restaurant',
        'restaurant_order_ids',
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
            'warehouse_id' => 'integer',
            'payment_method_id' => 'integer',
            'created_by' => 'integer',
            'user_id' => 'integer',
            'created_by_id' => 'integer',
            'cancelled_by' => 'integer',
            'total_paid' => 'decimal:2',
            'due_date' => 'date',
            'is_restaurant' => 'boolean',
            'restaurant_order_ids' => 'array',
        ];
    }

    public static function booting()
    {
        static::created(function ($invoice) {
            $invoice->invoice_number = self::getInvoiceNumber($invoice->id);
            $obr = new ObrService();
            $invoice->electronic_signature = $obr->generateInvoiceIdentifier( $invoice->invoice_number, $invoice->invoice_date);
            $invoice->obr_submission_status = 'PENDING';
            $invoice->saveQuietly();
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
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

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function restaurantTable(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(User::class, 'server_id');
    }

    public function hotelReservation(): HasOne
    {
        return $this->hasOne(HotelReservation::class);
    }

    public function hotelReceptionBooking(): HasOne
    {
        return $this->hasOne(HotelReceptionBooking::class);
    }

    public function hotelConferenceBooking(): HasOne
    {
        return $this->hasOne(HotelConferenceBooking::class);
    }

    /**
     * Générer le numéro de facture à partir de l'ID (format OBR demo).
     * Compteur global séquentiel, 6 chiffres zero-padded.
     */
    public static function getInvoiceNumber(int $invoiceId): string
    {
        return str_pad($invoiceId, 6, '0', STR_PAD_LEFT);
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
