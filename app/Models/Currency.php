<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Currency extends Model
{
    protected $fillable = [
        'code',
        'name',
        'symbol',
        'exchange_rate',
        'is_base',
        'is_active',
        'decimal_places',
    ];

    protected $casts = [
        'exchange_rate' => 'decimal:6',
        'is_base' => 'boolean',
        'is_active' => 'boolean',
        'decimal_places' => 'integer',
    ];

    public function exchangeRateHistory(): HasMany
    {
        return $this->hasMany(ExchangeRateHistory::class);
    }

    public static function getBaseCurrency(): ?Currency
    {
        return self::where('is_base', true)->first();
    }

    public static function getActiveCurrencies()
    {
        return self::where('is_active', true)->get();
    }

    public function convertToBase(float $amount): float
    {
        return $amount * $this->exchange_rate;
    }

    public function convertFromBase(float $amount): float
    {
        return $amount / $this->exchange_rate;
    }

    public function format(float $amount): string
    {
        return number_format($amount, $this->decimal_places, ',', ' ') . ' ' . $this->symbol;
    }
}
