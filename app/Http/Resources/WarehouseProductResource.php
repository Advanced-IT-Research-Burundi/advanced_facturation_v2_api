<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
          

        return [
            'id' => $this->product_id,
            'warehouse_product_id' => $this->id,
            'name' => $this->product?->item_designation,
            'price' => $this->product?->price ?: $this->unit_price,
            'unit_price' => $this->unit_price,
            'category' => $this->product?->categoryProduct?->name,
            'stock' => $this->quantity,
            'item_measurement_unit' => $this->product?->item_measurement_unit,
            'alert_threshold' => $this->alert_threshold,
            'is_alert' => $this->is_alert,
            'vat_rate' => $this->product?->vat_rate,
            'item_code' => $this->product?->item_code,
            'barcode' => $this->product?->barcode,
        ];
    }
}
