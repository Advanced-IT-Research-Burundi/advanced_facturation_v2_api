<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HotelReservation;
use App\Models\HotelRoom;
use Illuminate\Http\JsonResponse;

class HotelDashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $companyId = auth()->user()->company_id;

        $totalRooms = HotelRoom::where('company_id', $companyId)->count();
        $availableRooms = HotelRoom::where('company_id', $companyId)->where('status', 'available')->count();
        $occupiedRooms = HotelRoom::where('company_id', $companyId)->where('status', 'occupied')->count();
        $reservedRooms = HotelRoom::where('company_id', $companyId)->where('status', 'reserved')->count();
        $maintenanceRooms = HotelRoom::where('company_id', $companyId)->where('status', 'maintenance')->count();

        $activeReservations = HotelReservation::where('company_id', $companyId)
            ->whereIn('status', ['confirmed', 'checked_in'])
            ->count();

        $todayCheckIns = HotelReservation::where('company_id', $companyId)
            ->where('status', 'confirmed')
            ->whereDate('check_in_date', today())
            ->count();

        $todayCheckOuts = HotelReservation::where('company_id', $companyId)
            ->where('status', 'checked_in')
            ->whereDate('check_out_date', today())
            ->count();

        $monthRevenue = HotelReservation::where('company_id', $companyId)
            ->whereIn('status', ['checked_out', 'checked_in'])
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('total_amount');

        $occupancyRate = $totalRooms > 0
            ? round(($occupiedRooms / $totalRooms) * 100, 1)
            : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'rooms' => [
                    'total' => $totalRooms,
                    'available' => $availableRooms,
                    'occupied' => $occupiedRooms,
                    'reserved' => $reservedRooms,
                    'maintenance' => $maintenanceRooms,
                    'occupancy_rate' => $occupancyRate,
                ],
                'reservations' => [
                    'active' => $activeReservations,
                    'today_check_ins' => $todayCheckIns,
                    'today_check_outs' => $todayCheckOuts,
                ],
                'revenue' => [
                    'month' => $monthRevenue,
                ],
            ],
        ]);
    }
}
