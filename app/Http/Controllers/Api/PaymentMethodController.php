<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    public function index()
    {
        return response()->json(['success' => true, 'data' => PaymentMethod::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $method = PaymentMethod::create($this->validated($request));

        return response()->json(['success' => true, 'message' => 'Méthode de paiement créée avec succès.', 'data' => $method], 201);
    }

    public function update(Request $request, PaymentMethod $paymentMethod)
    {
        $paymentMethod->update($this->validated($request, $paymentMethod));

        return response()->json(['success' => true, 'message' => 'Méthode de paiement modifiée avec succès.', 'data' => $paymentMethod]);
    }

    public function destroy(PaymentMethod $paymentMethod)
    {
        $paymentMethod->delete();

        return response()->json(['success' => true, 'message' => 'Méthode de paiement supprimée avec succès.']);
    }

    private function validated(Request $request, ?PaymentMethod $paymentMethod = null): array
    {
        return $request->validate([
            'name' => [$paymentMethod ? 'sometimes' : 'required', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:255'],
            'account_name' => ['nullable', 'string', 'max:255'],
            'method_type' => [$paymentMethod ? 'sometimes' : 'required', 'in:bank,mobile_money,other'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
