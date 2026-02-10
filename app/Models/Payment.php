<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Traits\HasCompanyId;

class Payment extends Model
{
    use SoftDeletes, HasCompanyId;



    protected $fillable = [
        'invoice_id',
        'amount',
        'payment_date',
        'payment_method',
        'reference',
        'note',
        'created_by',
        'company_id',
    ];

    const PAYMENT_METHODS = [
        'cash' => 'Espèces',
        'bank_transfer' => 'Virement bancaire',
        'mobile_money' => 'Mobile Money',
        'check' => 'Chèque',
        'card' => 'Carte bancaire',
        'other' => 'Autre',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'datetime',
        'created_by' => 'integer',
        'invoice_id' => 'integer',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function booted()
    {
        static::saved(function ($payment) {
            $payment->invoice->updatePaymentStatus();
        });

        static::deleted(function ($payment) {
            $payment->invoice->updatePaymentStatus();
        });
    }
}
