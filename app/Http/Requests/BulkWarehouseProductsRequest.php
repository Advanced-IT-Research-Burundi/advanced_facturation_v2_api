<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BulkWarehouseProductsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_ids' => ['required', 'array', 'min:1', 'max:1000'],
            'product_ids.*' => ['integer', 'distinct', 'exists:products,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_ids.required' => 'Sélectionnez au moins un produit.',
            'product_ids.array' => 'La liste des produits est invalide.',
            'product_ids.min' => 'Sélectionnez au moins un produit.',
            'product_ids.max' => 'Vous ne pouvez pas traiter plus de 1000 produits à la fois.',
            'product_ids.*.integer' => 'Identifiant de produit invalide.',
            'product_ids.*.distinct' => 'Un produit est sélectionné plusieurs fois.',
            'product_ids.*.exists' => 'Un des produits sélectionnés n\'existe pas.',
        ];
    }
}
