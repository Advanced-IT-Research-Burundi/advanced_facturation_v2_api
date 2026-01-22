<?php

namespace App\Services;

use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Models\RestaurantTable;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class RestaurantOrderService
{
    public function openOrder(int $tableId, int $serverId, ?string $notes = null): RestaurantOrder
    {
        $table = RestaurantTable::findOrFail($tableId);
        $companyId = auth()->user()->company_id;

        $order = RestaurantOrder::create([
            'company_id' => $companyId,
            'restaurant_table_id' => $tableId,
            'server_id' => $serverId,
            'order_number' => RestaurantOrder::generateOrderNumber($companyId),
            'status' => 'open',
            'opened_at' => now(),
            'notes' => $notes,
        ]);

        $table->updateStatus();

        return $order->load(['table', 'server', 'items.product']);
    }

    public function addItems(int $orderId, array $items): RestaurantOrder
    {
        $order = RestaurantOrder::findOrFail($orderId);

        if ($order->status === 'invoiced') {
            throw new \Exception('Cette commande a déjà été facturée');
        }

        DB::transaction(function () use ($order, $items) {
            foreach ($items as $item) {
                $product = Product::findOrFail($item['product_id']);

                RestaurantOrderItem::create([
                    'company_id' => $order->company_id,
                    'restaurant_order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'] ?? $product->selling_price ?? 0,
                    'total_price' => ($item['quantity']) * ($item['unit_price'] ?? $product->selling_price ?? 0),
                    'status' => 'pending',
                    'notes' => $item['notes'] ?? null,
                ]);
            }
        });

        return $order->fresh(['table', 'server', 'items.product']);
    }

    public function updateItemStatus(int $itemId, string $status): RestaurantOrderItem
    {
        $item = RestaurantOrderItem::findOrFail($itemId);
        $item->update(['status' => $status]);
        return $item->fresh(['product']);
    }

    public function removeItem(int $itemId): void
    {
        $item = RestaurantOrderItem::findOrFail($itemId);

        if ($item->order->status === 'invoiced') {
            throw new \Exception('Impossible de modifier une commande facturée');
        }

        $item->update(['status' => 'cancelled']);
    }

    public function cancelOrder(int $orderId): RestaurantOrder
    {
        $order = RestaurantOrder::findOrFail($orderId);

        if ($order->status === 'invoiced') {
            throw new \Exception('Impossible d\'annuler une commande facturée');
        }

        $order->update([
            'status' => 'cancelled',
            'closed_at' => now(),
        ]);

        $order->table->updateStatus();

        return $order->fresh(['table', 'server']);
    }

    public function getTableOrders(int $tableId, bool $activeOnly = true): \Illuminate\Database\Eloquent\Collection
    {
        $query = RestaurantOrder::where('restaurant_table_id', $tableId)
            ->with(['server', 'items.product']);

        if ($activeOnly) {
            $query->whereIn('status', ['open', 'served']);
        }

        return $query->orderBy('opened_at', 'desc')->get();
    }

    public function getOrdersByStatus(string $status): \Illuminate\Database\Eloquent\Collection
    {
        return RestaurantOrder::where('company_id', auth()->user()->company_id)
            ->where('status', $status)
            ->with(['table', 'server', 'items.product'])
            ->orderBy('opened_at', 'desc')
            ->get();
    }

    public function markOrderAsServed(int $orderId): RestaurantOrder
    {
        $order = RestaurantOrder::findOrFail($orderId);
        $order->update(['status' => 'served']);
        $order->items()->where('status', 'ready')->update(['status' => 'served']);
        return $order->fresh(['table', 'server', 'items.product']);
    }
}
