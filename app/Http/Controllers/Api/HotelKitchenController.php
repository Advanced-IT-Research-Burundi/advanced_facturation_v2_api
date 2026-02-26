<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HotelRestaurantOrder;
use Illuminate\Http\JsonResponse;

class HotelKitchenController extends Controller
{
    public function orders(): JsonResponse
    {
        $orders = HotelRestaurantOrder::with(['restaurantTable', 'items'])
            ->whereIn('status', ['pending', 'preparing', 'ready'])
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($order) {
                $order->time = $order->created_at->format('H\hi');

                return $order;
            });

        return response()->json(['success' => true, 'data' => $orders]);
    }
}
