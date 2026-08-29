<?php

namespace App\Services;

use App\Models\WarehouseProduct;
use App\Models\StockMovement;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Exception;
use App\Models\AppConfig;

class StockService
{
    /**
     * Traiter une sortie de stock pour une vente POS
     *
     * @param array $items - Items de la facture
     * @param int $invoiceId - ID de la facture
     * @param string $invoiceNumber - Numéro de la facture
     * @param int|null $warehouseId - ID de l'entrepôt (null = entrepôt par défaut)
     * @return array
     * @throws Exception
     */
    public function processSaleStockMovement(
        array $items,
        int $invoiceId,
        string $invoiceNumber,
        ?int $warehouseId = null
    ): array {
        $movements = [];
        $errors = [];

        foreach ($items as $item) {
            try {
                // Vérifier si c'est un produit (pas un service)
                if (!isset($item['product_id'])) {
                    continue;
                }

                $product = Product::findOrFail($item['product_id']);

                // Récupérer la ligne exacte du stock vendue.
                $warehouseProduct = $this->findWarehouseProductForSale($item, $warehouseId, true);
                if (! $warehouseProduct) {
                    throw new Exception("Stock introuvable pour {$product->item_designation}");
                }

                // Vérifier la disponibilité
                if ($warehouseProduct->quantity < $item['item_quantity']) {
                    throw new Exception(
                        "Stock insuffisant pour {$product->name}. " .
                        "Disponible: {$warehouseProduct->quantity}, " .
                        "Demandé: {$item['item_quantity']}"
                    );
                }

                // Créer le mouvement de stock
                $movement = $this->createStockMovement([
                    'product' => $product,
                    'quantity' => $item['item_quantity'],
                    'price' => $item['item_price'],
                    'currency' => 'BIF',
                    'invoice_number' => $invoiceNumber,
                    'warehouse_id' => $warehouseId,
                    'movement_type' => 'SN',
                ]);

                // Réduire la quantité en stock
                $warehouseProduct->decrement('quantity', $item['item_quantity']);
                $warehouseProduct->update(['last_stock_movement_id' => $movement->id]);
                $movements[] = $movement;

            } catch (Exception $e) {
                $errors[] = [
                    'product_id' => $item['product_id'] ?? null,
                    'error' => $e->getMessage()
                ];
            }
        }

        if (!empty($errors)) {
            throw new Exception('Erreurs lors du traitement du stock: ' . json_encode($errors));
        }

        return [
            'success' => true,
            'movements' => $movements,
            'total_items' => count($movements)
        ];
    }

    /**
     * Créer un mouvement de stock
     */
    private function createStockMovement(array $data): StockMovement
    {
        return StockMovement::create([
            'system_or_device_id' => AppConfig::getConfigKey('OBR_USERNAME') ?? "-",
            'item_code' => $data['product']->id,
            'item_designation' => $data['product']->item_designation,
            'item_quantity' => $data['quantity'],
            'item_measurement_unit' => $data['product']->item_measurement_unit ?? 'UNITE',
            'item_purchase_or_sale_price' => $data['price'],
            'item_purchase_or_sale_currency' => $data['currency'],
            'item_movement_type' => $data['movement_type'],
            'item_movement_invoice_ref' => $data['invoice_number'],
            'item_movement_description' => "Vente - Facture {$data['invoice_number']}",
            'item_movement_date' => now(),
            'obr_submission_status' => 'PENDING',
            'company_id' => auth()->user()->company_id,
            'product_id' => $data['product']->id,
            'warehouse_id' => $data['warehouse_id'],
            'created_by' => auth()->id(),
            'user_id' => auth()->id(),
            'created_by_id' => auth()->id(),
        ]);
    }

    /**
     * Annuler un mouvement de stock (pour facture d'avoir)
     */
    public function cancelSaleStockMovement(int $invoiceId, string $invoiceNumber): array
    {
        $movements = StockMovement::where('item_movement_invoice_ref', $invoiceNumber)
            ->where('item_movement_type', 'VENTE')
            ->get();

        $cancelled = [];

        foreach ($movements as $movement) {
            // Remettre le stock
            $warehouseProduct = WarehouseProduct::where('product_id', $movement->product_id)
                ->where('warehouse_id', $movement->warehouse_id)
                ->first();

            if ($warehouseProduct) {
                $warehouseProduct->increment('quantity', $movement->item_quantity);
            }

            // Créer un mouvement d'annulation
            $cancelMovement = $this->createStockMovement([
                'product' => $movement->product,
                'quantity' => $movement->item_quantity,
                'price' => $movement->item_purchase_or_sale_price,
                'currency' => $movement->item_purchase_or_sale_currency,
                'invoice_number' => $invoiceNumber,
                'warehouse_id' => $movement->warehouse_id,
                'movement_type' => 'RETOUR',
            ]);

            $cancelled[] = $cancelMovement;
        }

        return [
            'success' => true,
            'cancelled_movements' => $cancelled,
            'total_cancelled' => count($cancelled)
        ];
    }

    /**
     * Vérifier la disponibilité du stock avant vente
     */
    public function checkStockAvailability(array $items, ?int $warehouseId = null): array
    {
        $availability = [];
        $hasError = false;

        foreach ($items as $item) {
            if (!isset($item['product_id'])) {
                continue;
            }

            $warehouseProduct = $this->findWarehouseProductForSale($item, $warehouseId);

            $available = $warehouseProduct ? $warehouseProduct->quantity : 0;
            $requested = $item['item_quantity'];
            $isAvailable = $available >= $requested;

            if (!$isAvailable) {
                $hasError = true;
            }

            $availability[] = [
                'product_id' => $item['product_id'],
                'requested' => $requested,
                'available' => $available,
                'is_available' => $isAvailable,
            ];
        }

        return [
            'has_error' => $hasError,
            'items' => $availability
        ];
    }

    private function findWarehouseProductForSale(
        array $item,
        ?int $warehouseId = null,
        bool $lockForUpdate = false
    ): ?WarehouseProduct {
        $query = WarehouseProduct::query();

        if (!empty($item['warehouse_product_id'])) {
            $query->where('id', $item['warehouse_product_id']);
        } else {
            $query->where('product_id', $item['product_id']);
        }

        if (!empty($item['product_id'])) {
            $query->where('product_id', $item['product_id']);
        }

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }
}
