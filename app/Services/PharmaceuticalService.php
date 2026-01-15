<?php

namespace App\Services;

use App\Models\ProductLot;
use App\Models\PatientHistory;
use App\Models\ExpirationAlert;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PharmaceuticalService
{
    /**
     * Vendre un produit pharmaceutique avec gestion FEFO (First Expired, First Out)
     */
    public function sellWithFEFO(
        int $productId,
        int $warehouseId,
        float $quantity,
        int $customerId,
        ?int $invoiceId = null,
        ?int $prescriptionId = null
    ): array {
        return DB::transaction(function () use ($productId, $warehouseId, $quantity, $customerId, $invoiceId, $prescriptionId) {
            $product = Product::findOrFail($productId);
            $remainingQuantity = $quantity;
            $soldLots = [];

            // Recuperer les lots actifs, tries par date d'expiration (FEFO)
            $lots = ProductLot::where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->where('status', 'active')
                ->where('current_quantity', '>', 0)
                ->where('expiration_date', '>', Carbon::now())
                ->orderBy('expiration_date', 'asc')
                ->get();

            foreach ($lots as $lot) {
                if ($remainingQuantity <= 0) break;

                $quantityFromLot = min($lot->current_quantity, $remainingQuantity);
                $lot->decrementQuantity($quantityFromLot);

                // Creer l'historique patient
                PatientHistory::create([
                    'customer_id' => $customerId,
                    'product_id' => $productId,
                    'product_lot_id' => $lot->id,
                    'invoice_id' => $invoiceId,
                    'prescription_id' => $prescriptionId,
                    'quantity' => $quantityFromLot,
                    'unit_price' => $product->price_ttc ?? $product->price,
                    'purchase_date' => now(),
                    'lot_number' => $lot->lot_number,
                    'lot_expiration' => $lot->expiration_date,
                ]);

                $soldLots[] = [
                    'lot_id' => $lot->id,
                    'lot_number' => $lot->lot_number,
                    'quantity' => $quantityFromLot,
                    'expiration_date' => $lot->expiration_date,
                ];

                $remainingQuantity -= $quantityFromLot;
            }

            if ($remainingQuantity > 0) {
                throw new \Exception(
                    "Stock insuffisant. Demande: {$quantity}, Disponible: " . ($quantity - $remainingQuantity)
                );
            }

            return [
                'success' => true,
                'sold_lots' => $soldLots,
                'total_quantity' => $quantity,
            ];
        });
    }

    /**
     * Verifier la disponibilite du stock pour un produit pharmaceutique
     */
    public function checkStockAvailability(int $productId, int $warehouseId, float $quantity): array
    {
        $availableQuantity = ProductLot::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('status', 'active')
            ->where('current_quantity', '>', 0)
            ->where('expiration_date', '>', Carbon::now())
            ->sum('current_quantity');

        return [
            'available' => $availableQuantity >= $quantity,
            'requested' => $quantity,
            'available_quantity' => $availableQuantity,
            'shortage' => max(0, $quantity - $availableQuantity),
        ];
    }

    /**
     * Generer les alertes d'expiration
     */
    public function generateExpirationAlerts(?int $companyId = null): array
    {
        $query = ProductLot::with(['product', 'warehouse'])
            ->where('status', 'active')
            ->where('current_quantity', '>', 0);

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        $lots = $query->get();
        $alertsCreated = [];

        foreach ($lots as $lot) {
            $daysUntilExpiration = $lot->days_until_expiration;
            $alertDays = $lot->product->delai_alerte_expiration ?? 90;

            // Determiner le niveau d'alerte
            $alertLevel = ExpirationAlert::determineAlertLevel($daysUntilExpiration, $alertDays);

            if ($alertLevel) {
                // Verifier si une alerte active existe deja
                $existingAlert = ExpirationAlert::where('product_lot_id', $lot->id)
                    ->where('status', 'active')
                    ->first();

                if (!$existingAlert) {
                    $alert = ExpirationAlert::create([
                        'product_id' => $lot->product_id,
                        'product_lot_id' => $lot->id,
                        'warehouse_id' => $lot->warehouse_id,
                        'expiration_date' => $lot->expiration_date,
                        'days_until_expiration' => $daysUntilExpiration,
                        'quantity_at_risk' => $lot->current_quantity,
                        'alert_level' => $alertLevel,
                        'status' => 'active',
                        'company_id' => $lot->company_id,
                    ]);

                    $alertsCreated[] = $alert;
                } else {
                    // Mettre a jour le niveau si necessaire
                    if ($this->shouldUpgradeAlert($existingAlert->alert_level, $alertLevel)) {
                        $existingAlert->update([
                            'alert_level' => $alertLevel,
                            'days_until_expiration' => $daysUntilExpiration,
                            'quantity_at_risk' => $lot->current_quantity,
                        ]);
                    }
                }
            }
        }

        // Marquer les lots expires
        ProductLot::where('expiration_date', '<', Carbon::now())
            ->where('status', 'active')
            ->update(['status' => 'expired']);

        return [
            'success' => true,
            'alerts_created' => count($alertsCreated),
            'alerts' => $alertsCreated,
        ];
    }

    /**
     * Obtenir les statistiques d'expiration
     */
    public function getExpirationStats(?int $companyId = null): array
    {
        $query = ProductLot::where('status', 'active')->where('current_quantity', '>', 0);

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        $expiredCount = (clone $query)->where('expiration_date', '<', Carbon::now())->count();
        $criticalCount = (clone $query)
            ->where('expiration_date', '>', Carbon::now())
            ->where('expiration_date', '<=', Carbon::now()->addDays(30))
            ->count();
        $warningCount = (clone $query)
            ->where('expiration_date', '>', Carbon::now()->addDays(30))
            ->where('expiration_date', '<=', Carbon::now()->addDays(90))
            ->count();

        $expiredValue = (clone $query)
            ->where('expiration_date', '<', Carbon::now())
            ->sum(DB::raw('current_quantity * purchase_price'));

        $criticalValue = (clone $query)
            ->where('expiration_date', '>', Carbon::now())
            ->where('expiration_date', '<=', Carbon::now()->addDays(30))
            ->sum(DB::raw('current_quantity * purchase_price'));

        return [
            'expired' => [
                'count' => $expiredCount,
                'value' => $expiredValue,
            ],
            'critical' => [
                'count' => $criticalCount,
                'value' => $criticalValue,
            ],
            'warning' => [
                'count' => $warningCount,
            ],
            'total_at_risk' => $expiredCount + $criticalCount + $warningCount,
        ];
    }

    /**
     * Obtenir les lots expirant bientot
     */
    public function getExpiringSoonLots(?int $companyId = null, int $days = 90, int $limit = 50): array
    {
        $query = ProductLot::with(['product', 'warehouse'])
            ->where('status', 'active')
            ->where('current_quantity', '>', 0)
            ->where('expiration_date', '<=', Carbon::now()->addDays($days))
            ->where('expiration_date', '>', Carbon::now())
            ->orderBy('expiration_date', 'asc');

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return $query->limit($limit)->get()->toArray();
    }

    /**
     * Verifier si le niveau d'alerte doit etre mis a jour
     */
    private function shouldUpgradeAlert(string $currentLevel, string $newLevel): bool
    {
        $levels = ['warning' => 1, 'critical' => 2, 'expired' => 3];
        return ($levels[$newLevel] ?? 0) > ($levels[$currentLevel] ?? 0);
    }

    /**
     * Obtenir l'historique d'achat d'un patient
     */
    public function getPatientPurchaseHistory(int $customerId, ?int $productId = null): array
    {
        $query = PatientHistory::with(['product', 'productLot', 'prescription'])
            ->where('customer_id', $customerId)
            ->orderBy('purchase_date', 'desc');

        if ($productId) {
            $query->where('product_id', $productId);
        }

        return $query->get()->toArray();
    }

    /**
     * Verifier les interactions medicamenteuses potentielles
     */
    public function checkRecentPurchases(int $customerId, int $productId, int $days = 30): array
    {
        $recentPurchases = PatientHistory::with('product')
            ->where('customer_id', $customerId)
            ->where('purchase_date', '>=', Carbon::now()->subDays($days))
            ->get();

        $product = Product::find($productId);

        return [
            'product' => $product ? $product->item_designation : null,
            'recent_purchases' => $recentPurchases->map(function ($history) {
                return [
                    'product_name' => $history->product->item_designation ?? 'N/A',
                    'quantity' => $history->quantity,
                    'purchase_date' => $history->purchase_date,
                    'classe_therapeutique' => $history->product->classe_therapeutique ?? null,
                ];
            })->toArray(),
            'warning' => $recentPurchases->isNotEmpty(),
        ];
    }
}
