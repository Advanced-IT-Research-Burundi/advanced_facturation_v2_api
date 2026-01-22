<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RestaurantOrder;
use App\Services\RestaurantOrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RestaurantOrderController extends Controller
{
    protected RestaurantOrderService $orderService;

    public function __construct(RestaurantOrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    public function index(Request $request)
    {
        $query = RestaurantOrder::where('company_id', auth()->user()->company_id)
            ->with(['table', 'server', 'items.product']);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($tableId = $request->input('table_id')) {
            $query->where('restaurant_table_id', $tableId);
        }

        $orders = $query->orderBy('opened_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'table_id' => 'required|exists:restaurant_tables,id',
            'server_id' => 'nullable|exists:users,id',
            'notes' => 'nullable|string',
        ]);

        try {
            $order = $this->orderService->openOrder(
                $validated['table_id'],
                $validated['server_id'] ?? auth()->id(),
                $validated['notes'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Commande ouverte',
                'data' => $order,
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function show(RestaurantOrder $restaurantOrder)
    {
        $restaurantOrder->load(['table', 'server', 'items.product']);

        return response()->json([
            'success' => true,
            'data' => $restaurantOrder,
        ]);
    }

    public function addItems(Request $request, RestaurantOrder $restaurantOrder)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.1',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.notes' => 'nullable|string',
        ]);

        try {
            $order = $this->orderService->addItems($restaurantOrder->id, $validated['items']);

            return response()->json([
                'success' => true,
                'message' => 'Articles ajoutés',
                'data' => $order,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function updateItemStatus(Request $request, int $itemId)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,preparing,ready,served,cancelled',
        ]);

        try {
            $item = $this->orderService->updateItemStatus($itemId, $validated['status']);

            return response()->json([
                'success' => true,
                'data' => $item,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function removeItem(int $itemId)
    {
        try {
            $this->orderService->removeItem($itemId);

            return response()->json([
                'success' => true,
                'message' => 'Article annulé',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function cancel(RestaurantOrder $restaurantOrder)
    {
        try {
            $order = $this->orderService->cancelOrder($restaurantOrder->id);

            return response()->json([
                'success' => true,
                'message' => 'Commande annulée',
                'data' => $order,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function markAsServed(RestaurantOrder $restaurantOrder)
    {
        try {
            $order = $this->orderService->markOrderAsServed($restaurantOrder->id);

            return response()->json([
                'success' => true,
                'message' => 'Commande marquée comme servie',
                'data' => $order,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function tableOrders(int $tableId)
    {
        $orders = $this->orderService->getTableOrders($tableId);

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }
}
