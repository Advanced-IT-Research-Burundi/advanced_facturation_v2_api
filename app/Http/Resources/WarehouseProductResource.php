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
            'id' => $this->id,
            'name' => $this->product?->item_designation,
            'price' => $this->product?->price,
            'category' => $this->product?->categoryProduct?->name,
            'stock' => $this->quantity,
            'vat_rate' => $this->product?->vat_rate,
            'item_code' => $this->product?->item_code,
        ];
    }
}
