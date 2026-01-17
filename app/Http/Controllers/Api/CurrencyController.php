<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\ExchangeRateHistory;
use Illuminate\Http\Request;
use Carbon\Carbon;

class CurrencyController extends Controller
{
    public function index(Request $request)
    {
        $query = Currency::query();

        if ($request->boolean('active_only', true)) {
            $query->where('is_active', true);
        }

        $currencies = $query->orderBy('is_base', 'desc')->orderBy('code')->get();

        return response()->json([
            'success' => true,
            'data' => $currencies
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:3|unique:currencies,code',
            'name' => 'required|string|max:100',
            'symbol' => 'required|string|max:10',
            'exchange_rate' => 'required|numeric|min:0.000001',
            'decimal_places' => 'sometimes|integer|min:0|max:6',
        ]);

        $currency = Currency::create([
            'code' => strtoupper($request->code),
            'name' => $request->name,
            'symbol' => $request->symbol,
            'exchange_rate' => $request->exchange_rate,
            'is_base' => false,
            'is_active' => true,
            'decimal_places' => $request->get('decimal_places', 2),
        ]);

        // Record initial rate in history
        ExchangeRateHistory::create([
            'currency_id' => $currency->id,
            'rate' => $currency->exchange_rate,
            'effective_date' => Carbon::today(),
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Devise créée avec succès',
            'data' => $currency
        ], 201);
    }

    public function show($id)
    {
        $currency = Currency::with(['exchangeRateHistory' => function ($query) {
            $query->orderBy('effective_date', 'desc')->limit(30);
        }])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $currency
        ]);
    }

    public function update(Request $request, $id)
    {
        $currency = Currency::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:100',
            'symbol' => 'sometimes|string|max:10',
            'exchange_rate' => 'sometimes|numeric|min:0.000001',
            'is_active' => 'sometimes|boolean',
            'decimal_places' => 'sometimes|integer|min:0|max:6',
        ]);

        // If exchange rate changed, record in history
        if ($request->has('exchange_rate') && $request->exchange_rate != $currency->exchange_rate) {
            ExchangeRateHistory::create([
                'currency_id' => $currency->id,
                'rate' => $request->exchange_rate,
                'effective_date' => Carbon::today(),
                'updated_by' => $request->user()->id,
            ]);
        }

        $currency->update($request->only(['name', 'symbol', 'exchange_rate', 'is_active', 'decimal_places']));

        return response()->json([
            'success' => true,
            'message' => 'Devise mise à jour',
            'data' => $currency
        ]);
    }

    public function destroy($id)
    {
        $currency = Currency::findOrFail($id);

        if ($currency->is_base) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de supprimer la devise de base'
            ], 422);
        }

        $currency->delete();

        return response()->json([
            'success' => true,
            'message' => 'Devise supprimée'
        ]);
    }

    public function convert(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric',
            'from' => 'required|string|size:3',
            'to' => 'required|string|size:3',
        ]);

        $fromCurrency = Currency::where('code', strtoupper($request->from))->firstOrFail();
        $toCurrency = Currency::where('code', strtoupper($request->to))->firstOrFail();

        // Convert to base first, then to target
        $baseAmount = $request->amount * $fromCurrency->exchange_rate;
        $convertedAmount = $baseAmount / $toCurrency->exchange_rate;

        return response()->json([
            'success' => true,
            'data' => [
                'original_amount' => $request->amount,
                'from_currency' => $fromCurrency->code,
                'to_currency' => $toCurrency->code,
                'converted_amount' => round($convertedAmount, $toCurrency->decimal_places),
                'exchange_rate' => $fromCurrency->exchange_rate / $toCurrency->exchange_rate,
                'formatted' => $toCurrency->format($convertedAmount),
            ]
        ]);
    }

    public function rateHistory(Request $request, $id)
    {
        $currency = Currency::findOrFail($id);

        $days = $request->get('days', 30);

        $history = ExchangeRateHistory::where('currency_id', $id)
            ->where('effective_date', '>=', Carbon::now()->subDays($days))
            ->orderBy('effective_date', 'desc')
            ->with('updatedBy:id,name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'currency' => $currency,
                'history' => $history
            ]
        ]);
    }

    public function updateRate(Request $request, $id)
    {
        $request->validate([
            'exchange_rate' => 'required|numeric|min:0.000001',
            'effective_date' => 'sometimes|date',
        ]);

        $currency = Currency::findOrFail($id);

        if ($currency->is_base) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de modifier le taux de la devise de base'
            ], 422);
        }

        $effectiveDate = $request->get('effective_date', Carbon::today());

        // Record in history
        ExchangeRateHistory::create([
            'currency_id' => $currency->id,
            'rate' => $request->exchange_rate,
            'effective_date' => Carbon::parse($effectiveDate),
            'updated_by' => $request->user()->id,
        ]);

        $currency->update(['exchange_rate' => $request->exchange_rate]);

        return response()->json([
            'success' => true,
            'message' => 'Taux de change mis à jour',
            'data' => $currency
        ]);
    }
}
