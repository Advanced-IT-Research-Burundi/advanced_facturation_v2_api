<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ObrLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'log_type',
        'invoice_id',
        'stock_movement_id',
        'invoice_identifier',
        'invoice_number',
        'success',
        'status',
        'obr_message',
        'obr_response',
        'electronic_signature',
        'invoice_registered_number',
        'invoice_registered_date',
        'request_body',
        'retry_count',
        'last_retry_at',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'request_body' => 'array',
            'invoice_registered_date' => 'datetime',
            'last_retry_at' => 'datetime',
            'retry_count' => 'integer',
        ];
    }

    // Constantes pour les types de log
    const TYPE_INVOICE = 'INVOICE';
    const TYPE_STOCK_MOVEMENT = 'STOCK_MOVEMENT';
    const TYPE_CANCEL = 'CANCEL';

    // Constantes pour les statuts
    const STATUS_PENDING = 'PENDING';
    const STATUS_ACCEPTED = 'ACCEPTED';
    const STATUS_REJECTED = 'REJECTED';

    /**
     * Relation vers la facture
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Relation vers le mouvement de stock
     */
    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }

    /**
     * Relation vers l'utilisateur
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Créer un log pour une facture envoyée
     */
    public static function logInvoiceSent(Invoice $invoice, array $obrResult, array $requestBody = []): self
    {
        return self::create([
            'log_type' => self::TYPE_INVOICE,
            'invoice_id' => $invoice->id,
            'invoice_identifier' => $obrResult['invoice_identifier'] ?? null,
            'invoice_number' => $invoice->invoice_number,
            'success' => $obrResult['success'] ?? false,
            'status' => ($obrResult['success'] ?? false) ? self::STATUS_ACCEPTED : self::STATUS_REJECTED,
            'obr_message' => $obrResult['message'] ?? null,
            'obr_response' => json_encode($obrResult),
            'electronic_signature' => $obrResult['electronic_signature'] ?? null,
            'invoice_registered_number' => $obrResult['invoice_registered_number'] ?? null,
            'invoice_registered_date' => $obrResult['invoice_registered_date'] ?? null,
            'request_body' => $requestBody,
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Créer un log pour une annulation de facture
     */
    public static function logInvoiceCancelled(Invoice $invoice, array $obrResult, string $motif): self
    {
        return self::create([
            'log_type' => self::TYPE_CANCEL,
            'invoice_id' => $invoice->id,
            'invoice_identifier' => $invoice->obr_invoice_identifier,
            'invoice_number' => $invoice->invoice_number,
            'success' => $obrResult['success'] ?? false,
            'status' => ($obrResult['success'] ?? false) ? self::STATUS_ACCEPTED : self::STATUS_REJECTED,
            'obr_message' => $obrResult['message'] ?? null,
            'obr_response' => json_encode($obrResult),
            'request_body' => ['motif' => $motif],
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Créer un log pour un mouvement de stock
     */
    public static function logStockMovement(StockMovement $movement, array $obrResult): self
    {
        return self::create([
            'log_type' => self::TYPE_STOCK_MOVEMENT,
            'stock_movement_id' => $movement->id,
            'success' => $obrResult['success'] ?? false,
            'status' => ($obrResult['success'] ?? false) ? self::STATUS_ACCEPTED : self::STATUS_REJECTED,
            'obr_message' => $obrResult['message'] ?? null,
            'obr_response' => json_encode($obrResult),
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Scope pour les logs en attente
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope pour les logs acceptés
     */
    public function scopeAccepted($query)
    {
        return $query->where('status', self::STATUS_ACCEPTED);
    }

    /**
     * Scope pour les logs rejetés
     */
    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    /**
     * Scope pour les factures
     */
    public function scopeInvoices($query)
    {
        return $query->where('log_type', self::TYPE_INVOICE);
    }

    /**
     * Scope pour les annulations
     */
    public function scopeCancellations($query)
    {
        return $query->where('log_type', self::TYPE_CANCEL);
    }
}
