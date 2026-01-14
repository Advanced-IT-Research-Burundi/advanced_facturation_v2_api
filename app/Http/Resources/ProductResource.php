<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_code' => $this->item_code,
            'item_designation' => $this->item_designation,
            'item_measurement_unit' => $this->item_measurement_unit,
            'barcode' => $this->barcode,
            'vat_rate' => $this->vat_rate,
            'code_product' => $this->code_product,
            'marque' => $this->marque,
            'quantite' => $this->quantite,
            'quantite_alert' => $this->quantite_alert,
            'price' => $this->price,
            'price_ttc' => $this->price_ttc,
            'price_max' => $this->price_max,
            'price_min' => $this->price_min,
            'price_tvac' => $this->price_tvac,
            'item_ott_tax' => $this->item_ott_tax,
            'item_tsce_tax' => $this->item_tsce_tax,
            'date_expiration' => $this->date_expiration,
            'image' => $this->image,
            'type' => $this->type,
            'description' => $this->description,
            'company' => $this->whenLoaded('company'),
            'product_unit' => $this->whenLoaded('productUnit'),
            'category_product' => $this->whenLoaded('categoryProduct'),
            'user' => $this->whenLoaded('user'),
            'barcode_url' => url("/api/products/{$this->id}/barcode"),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}