<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseResource extends JsonResource
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
            'name' => $this->name,
            'location' => $this->location,
            'is_production' => (bool) $this->is_production,
            'company_id' => $this->company_id,
            'users_count' => $this->users()->count(),
            'company' => $this->whenLoaded('company')->name ?? '',
        ];
    }
}
