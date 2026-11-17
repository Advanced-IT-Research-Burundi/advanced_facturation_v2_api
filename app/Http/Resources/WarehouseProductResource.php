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
        $promoPrice = (float) ($this->price_promo ?? $this->product?->price_promo ?? 0);
        $basePrice = $this->product?->price ?: $this->unit_price;
        // Le bénéfice se calcule sur le prix de vente HTVA du stock et son prix d'achat réel.
        $sellingPrice = (float) ($this->unit_price ?? 0);
        $purchasePrice = (float) ($this->purchase_price ?? 0);
        $unitProfit = $sellingPrice - $purchasePrice;
        $roles = $request->user()?->roles ?? collect();
        $isSuperAdmin = $roles->contains(function ($role) {
            return in_array(strtolower($role->name ?? ''), ['super_admin', 'superadmin'], true)
                || strtolower($role->label ?? '') === 'super administrateur';
        });

        return [
            'id' => $this->product_id,
            'warehouse_product_id' => $this->id,
            'name' => $this->product?->item_designation,
            'price' => $isSuperAdmin && $promoPrice > 0 ? $promoPrice : $basePrice,
            'unit_price' => $this->unit_price,
            'purchase_price' => $purchasePrice,
            'profit' => round($unitProfit, 2),
            'profit_margin' => $sellingPrice > 0 ? round(($unitProfit / $sellingPrice) * 100, 2) : 0,
            'total_profit' => round($unitProfit * (float) ($this->quantity ?? 0), 2),
            'price_promo' => $promoPrice,
            'category' => $this->product?->categoryProduct?->name,
            'libelle' => $this->product?->libelle?->name,
            'libelle_price' => $this->product?->libelle?->price,
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
