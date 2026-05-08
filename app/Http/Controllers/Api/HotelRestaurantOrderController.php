<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HotelDish;
use App\Models\HotelMenuItem;
use App\Models\HotelRestaurantOrder;
use App\Models\HotelRestaurantTable;
use App\Services\HotelInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class HotelRestaurantOrderController extends Controller
{
    public function __construct(protected HotelInvoiceService $hotelInvoiceService) {}

    public function index(Request $request): JsonResponse
    {
        $query = HotelRestaurantOrder::with(['restaurantTable', 'items.menuItem:id,category'])
            ->orderBy('created_at', 'desc');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($request->boolean('active_only')) {
            $query->whereNotIn('status', ['paid', 'cancelled']);
        }

        if ($request->boolean('today')) {
            $query->whereDate('created_at', today());
        }

        if ($request->boolean('room_service_only')) {
            $query->where('is_room_service', true);
        }

        $orders = $query->get()->map(function ($order) {
            $order->time = $order->created_at->format('H\hi');
            $order->location_label = $order->getLocationLabelAttribute();

            $order->items->each(function ($item) {
                $item->category = $item->menuItem?->category ?? ($item->item_type === 'dish' ? 'Cuisine' : null);
            });

            return $order;
        });

        return response()->json(['success' => true, 'data' => $orders]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hotel_restaurant_table_id' => 'nullable|exists:hotel_restaurant_tables,id',
            'room_number' => 'nullable|string|max:50',
            'client_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.hotel_menu_item_id' => 'nullable|exists:hotel_menu_items,id',
            'items.*.hotel_dish_id' => 'nullable|exists:hotel_dishes,id',
            'items.*.qty' => 'required|integer|min:1',
        ]);

        if (empty($validated['hotel_restaurant_table_id']) && empty($validated['room_number'])) {
            return response()->json([
                'success' => false,
                'message' => 'Une table ou un numéro de chambre est requis.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $isRoomService = ! empty($validated['room_number']);

            $order = HotelRestaurantOrder::create([
                'hotel_restaurant_table_id' => $isRoomService ? null : ($validated['hotel_restaurant_table_id'] ?? null),
                'room_number' => $isRoomService ? $validated['room_number'] : null,
                'is_room_service' => $isRoomService,
                'client_name' => $validated['client_name'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => 'pending',
                'total' => 0,
            ]);

            $total = 0;

            foreach ($validated['items'] as $item) {
                $qty = $item['qty'];

                if (! empty($item['hotel_dish_id'])) {
                    $dish = HotelDish::with('kitchenStock')->findOrFail($item['hotel_dish_id']);
                    $lineTotal = $dish->price * $qty;
                    $total += $lineTotal;

                    $order->items()->create([
                        'hotel_menu_item_id' => null,
                        'hotel_dish_id' => $dish->id,
                        'item_type' => 'dish',
                        'name' => $dish->name,
                        'price' => $dish->price,
                        'qty' => $qty,
                    ]);

                    if ($dish->kitchenStock && $dish->stock_per_serving > 0) {
                        $consumed = $dish->stock_per_serving * $qty;
                        $dish->kitchenStock->decrement('quantity', $consumed);
                    }
                } else {
                    $menuItem = HotelMenuItem::with('barStock')->findOrFail($item['hotel_menu_item_id']);
                    $lineTotal = $menuItem->price * $qty;
                    $total += $lineTotal;

                    $order->items()->create([
                        'hotel_menu_item_id' => $menuItem->id,
                        'hotel_dish_id' => null,
                        'item_type' => 'menu',
                        'name' => $menuItem->name,
                        'price' => $menuItem->price,
                        'qty' => $qty,
                    ]);

                    if ($menuItem->barStock && $menuItem->stock_per_serving > 0) {
                        $consumed = $menuItem->stock_per_serving * $qty;
                        $menuItem->barStock->decrement('quantity', $consumed);
                    }
                }
            }

            $order->update(['total' => $total]);

            if (! $isRoomService && ! empty($validated['hotel_restaurant_table_id'])) {
                HotelRestaurantTable::find($validated['hotel_restaurant_table_id'])
                    ->update(['status' => 'occupied']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Commande créée avec succès',
                'data' => $order->load(['restaurantTable', 'items']),
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la commande',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function updateStatus(Request $request, HotelRestaurantOrder $hotelRestaurantOrder): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,preparing,ready,served,paid,cancelled',
        ]);

        $hotelRestaurantOrder->update([
            'status' => $validated['status'],
            'served_at' => $validated['status'] === 'served' ? now() : $hotelRestaurantOrder->served_at,
            'paid_at' => $validated['status'] === 'paid' ? now() : $hotelRestaurantOrder->paid_at,
        ]);

        if ($validated['status'] === 'paid' || $validated['status'] === 'cancelled') {
            $table = $hotelRestaurantOrder->restaurantTable;
            if ($table && ! $table->activeOrders()->exists()) {
                $table->update(['status' => 'free']);
            }
        }

        $invoice = null;
        if ($validated['status'] === 'paid' && ! $hotelRestaurantOrder->invoice_id) {
            try {
                $invoice = $this->hotelInvoiceService->generateRestaurantOrderInvoice(
                    $hotelRestaurantOrder->fresh(['items', 'restaurantTable'])
                );
            } catch (\Exception $e) {
                \Log::warning('Impossible de créer la facture pour la commande '.$hotelRestaurantOrder->id.': '.$e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Statut mis à jour',
            'data' => $hotelRestaurantOrder->load(['restaurantTable', 'items', 'invoice']),
            'invoice' => $invoice,
        ]);
    }
}
