<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\Depense;
use App\Models\HotelConferenceBooking;
use App\Models\HotelReceptionBooking;
use App\Models\HotelReservation;
use App\Models\HotelRestaurantOrder;
use App\Models\HotelRoom;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HotelReportController extends Controller
{
    /**
     * Comprehensive hotel director report combining all financial traces.
     */
    public function summary(Request $request): JsonResponse
    {
        $companyId = auth()->user()->company_id;
        $startDate = $request->filled('start_date')
            ? Carbon::parse($request->start_date)->startOfDay()
            : null;
        $endDate = $request->filled('end_date')
            ? Carbon::parse($request->end_date)->endOfDay()
            : null;

        return response()->json([
            'success' => true,
            'data' => [
                'rooms' => $this->roomStats($companyId),
                'reservations' => $this->reservationStats($companyId, $startDate, $endDate),
                'revenue' => $this->revenueBreakdown($companyId, $startDate, $endDate),
                'invoices' => $this->invoiceStats($companyId, $startDate, $endDate),
                'caisse' => $this->caisseStats($companyId, $startDate, $endDate),
                'expenses' => $this->expenseStats($companyId, $startDate, $endDate),
                'restaurant' => $this->restaurantStats($companyId, $startDate, $endDate),
                'conference' => $this->conferenceStats($companyId, $startDate, $endDate),
                'reception' => $this->receptionStats($companyId, $startDate, $endDate),
                'top_rooms' => $this->topRooms($companyId, $startDate, $endDate),
                'top_clients' => $this->topClients($companyId, $startDate, $endDate),
                'daily_revenue' => $this->dailyRevenue($companyId, $startDate, $endDate),
            ],
        ]);
    }

    private function roomStats(int $companyId): array
    {
        $rooms = HotelRoom::where('company_id', $companyId)->get();

        return [
            'total' => $rooms->count(),
            'available' => $rooms->where('status', 'available')->count(),
            'occupied' => $rooms->where('status', 'occupied')->count(),
            'reserved' => $rooms->where('status', 'reserved')->count(),
            'maintenance' => $rooms->where('status', 'maintenance')->count(),
            'occupancy_rate' => $rooms->count() > 0
                ? round(($rooms->where('status', 'occupied')->count() / $rooms->count()) * 100, 1)
                : 0,
        ];
    }

    private function reservationStats(int $companyId, ?Carbon $start, ?Carbon $end): array
    {
        $query = HotelReservation::where('company_id', $companyId);

        if ($start && $end) {
            $query->where(function ($q) use ($start, $end) {
                $q->whereBetween('created_at', [$start, $end]);
            });
        }

        $reservations = $query->get();

        $totalNights = $reservations->whereIn('status', ['checked_in', 'checked_out'])->sum('nights');
        $avgStay = $reservations->whereIn('status', ['checked_in', 'checked_out'])->avg('nights');

        return [
            'total' => $reservations->count(),
            'confirmed' => $reservations->where('status', 'confirmed')->count(),
            'checked_in' => $reservations->where('status', 'checked_in')->count(),
            'checked_out' => $reservations->where('status', 'checked_out')->count(),
            'cancelled' => $reservations->where('status', 'cancelled')->count(),
            'total_nights' => (int) $totalNights,
            'avg_stay' => round($avgStay ?? 0, 1),
            'total_guests' => $reservations->whereIn('status', ['confirmed', 'checked_in', 'checked_out'])->count(),
        ];
    }

    /**
     * Revenue breakdown by section: rooms, restaurant, conference, reception.
     */
    private function revenueBreakdown(int $companyId, ?Carbon $start, ?Carbon $end): array
    {
        $resQuery = HotelReservation::where('company_id', $companyId)
            ->whereIn('status', ['checked_in', 'checked_out']);
        $restQuery = HotelRestaurantOrder::where('company_id', $companyId)
            ->whereIn('status', ['paid', 'served']);
        $confQuery = HotelConferenceBooking::where('company_id', $companyId)
            ->where('status', '!=', 'cancelled');
        $recQuery = HotelReceptionBooking::where('company_id', $companyId)
            ->where('status', '!=', 'cancelled');

        if ($start && $end) {
            $resQuery->whereBetween('created_at', [$start, $end]);
            $restQuery->whereBetween('created_at', [$start, $end]);
            $confQuery->whereBetween('created_at', [$start, $end]);
            $recQuery->whereBetween('created_at', [$start, $end]);
        }

        $roomsRevenue = (float) $resQuery->sum('total_amount');
        $restaurantRevenue = (float) $restQuery->sum('total');
        $conferenceRevenue = (float) $confQuery->sum('total_amount');
        $receptionRevenue = (float) $recQuery->sum('total_amount');
        $totalRevenue = $roomsRevenue + $restaurantRevenue + $conferenceRevenue + $receptionRevenue;

        $roomsCollected = (float) HotelReservation::where('company_id', $companyId)
            ->whereIn('status', ['checked_in', 'checked_out'])
            ->when($start && $end, fn ($q) => $q->whereBetween('created_at', [$start, $end]))
            ->sum('advance_payment');

        $roomsBalance = (float) HotelReservation::where('company_id', $companyId)
            ->whereIn('status', ['checked_in', 'checked_out'])
            ->when($start && $end, fn ($q) => $q->whereBetween('created_at', [$start, $end]))
            ->sum('balance_due');

        return [
            'total' => $totalRevenue,
            'rooms' => $roomsRevenue,
            'rooms_collected' => $roomsCollected,
            'rooms_balance' => $roomsBalance,
            'restaurant' => $restaurantRevenue,
            'conference' => $conferenceRevenue,
            'reception' => $receptionRevenue,
            'sections' => [
                ['label' => 'Chambres', 'amount' => $roomsRevenue, 'icon' => 'door-closed'],
                ['label' => 'Restaurant-Bar', 'amount' => $restaurantRevenue, 'icon' => 'cup-straw'],
                ['label' => 'Salles de Conf.', 'amount' => $conferenceRevenue, 'icon' => 'camera-video'],
                ['label' => 'Salle Réception', 'amount' => $receptionRevenue, 'icon' => 'balloon-heart'],
            ],
        ];
    }

    private function invoiceStats(int $companyId, ?Carbon $start, ?Carbon $end): array
    {
        $query = Invoice::where('company_id', $companyId)
            ->whereIn('invoice_identifier', ['HOTEL', 'RESTAURANT']);

        if ($start && $end) {
            $query->whereBetween('created_at', [$start, $end]);
        }

        $invoices = $query->get();

        $totalAmount = (float) $invoices->sum('invoice_total_amount');
        $totalPaid = (float) $invoices->sum('total_paid');

        return [
            'total_count' => $invoices->count(),
            'total_amount' => $totalAmount,
            'total_paid' => $totalPaid,
            'total_outstanding' => $totalAmount - $totalPaid,
            'total_unpaid' => (float) $invoices->whereIn('payment_status', ['unpaid', 'partial'])
                ->sum(fn ($inv) => (float) $inv->invoice_total_amount - (float) $inv->total_paid),
            'paid_count' => $invoices->where('payment_status', 'paid')->count(),
            'partial_count' => $invoices->where('payment_status', 'partial')->count(),
            'unpaid_count' => $invoices->where('payment_status', 'unpaid')->count(),
        ];
    }

    /**
     * Cash register (caisse) stats across all hotel sections.
     */
    private function caisseStats(int $companyId, ?Carbon $start, ?Carbon $end): array
    {
        $hotelSections = ['restaurant', 'bar', 'rooms', 'conference', 'reception'];
        $isLoss = fn ($m) => str_contains(strtolower($m->description ?? ''), 'perte');

        $registers = CashRegister::where('company_id', $companyId)
            ->whereIn('hotel_section', $hotelSections)
            ->get();

        $registerIds = $registers->pluck('id');

        $movementsQuery = CashMovement::whereIn('cash_register_id', $registerIds);

        if ($start) {
            $movementsQuery->where('created_at', '>=', $start);
        }
        if ($end) {
            $movementsQuery->where('created_at', '<=', $end);
        }

        $movements = $movementsQuery->get();

        $allExpenses = $movements->where('type', 'expense');
        $totalIncome = (float) $movements->where('type', 'income')->sum('amount');
        $totalLosses = (float) $allExpenses->filter($isLoss)->sum('amount');
        $totalExpenseOnly = (float) $allExpenses->reject($isLoss)->sum('amount');

        $registersBySection = $registers->groupBy('hotel_section');
        $bySection = [];

        foreach ($hotelSections as $section) {
            $sectionRegisterIds = ($registersBySection[$section] ?? collect())->pluck('id');
            $sectionMovements = $movements->whereIn('cash_register_id', $sectionRegisterIds);
            $sectionExpenses = $sectionMovements->where('type', 'expense');
            $sectionIncome = (float) $sectionMovements->where('type', 'income')->sum('amount');
            $sectionLosses = (float) $sectionExpenses->filter($isLoss)->sum('amount');
            $sectionExpenseOnly = (float) $sectionExpenses->reject($isLoss)->sum('amount');

            if ($sectionIncome > 0 || $sectionExpenseOnly > 0 || $sectionLosses > 0) {
                $bySection[$section] = [
                    'income' => $sectionIncome,
                    'expense' => $sectionExpenseOnly,
                    'losses' => $sectionLosses,
                    'profit' => $sectionIncome - $sectionExpenseOnly - $sectionLosses,
                ];
            }
        }

        return [
            'total_income' => $totalIncome,
            'total_expense' => $totalExpenseOnly,
            'total_losses' => $totalLosses,
            'total_profit' => $totalIncome - $totalExpenseOnly - $totalLosses,
            'registers_count' => $registers->count(),
            'open_registers' => $registers->whereNull('closed_at')->count(),
            'by_section' => $bySection,
        ];
    }

    private function expenseStats(int $companyId, ?Carbon $start, ?Carbon $end): array
    {
        $query = Depense::where('company_id', $companyId);

        if ($start && $end) {
            $query->whereBetween('created_at', [$start, $end]);
        }

        $expenses = $query->get();

        $hotelExpenses = $expenses->whereNotNull('hotel_section');
        $generalExpenses = $expenses->whereNull('hotel_section');

        $bySection = $hotelExpenses->groupBy('hotel_section')->map(fn ($items) => [
            'count' => $items->count(),
            'total' => (float) $items->sum('montant'),
        ])->toArray();

        if ($generalExpenses->isNotEmpty()) {
            $bySection['general'] = [
                'count' => $generalExpenses->count(),
                'total' => (float) $generalExpenses->sum('montant'),
            ];
        }

        return [
            'total' => (float) $expenses->sum('montant'),
            'count' => $expenses->count(),
            'by_section' => $bySection,
        ];
    }

    private function restaurantStats(int $companyId, ?Carbon $start, ?Carbon $end): array
    {
        $query = HotelRestaurantOrder::where('company_id', $companyId);

        if ($start && $end) {
            $query->whereBetween('created_at', [$start, $end]);
        }

        $orders = $query->get();

        return [
            'total_orders' => $orders->count(),
            'total_revenue' => (float) $orders->whereIn('status', ['paid', 'served'])->sum('total'),
            'paid' => $orders->where('status', 'paid')->count(),
            'pending' => $orders->whereIn('status', ['pending', 'preparing', 'ready'])->count(),
            'room_service' => $orders->where('is_room_service', true)->count(),
        ];
    }

    private function conferenceStats(int $companyId, ?Carbon $start, ?Carbon $end): array
    {
        $query = HotelConferenceBooking::where('company_id', $companyId);

        if ($start && $end) {
            $query->whereBetween('created_at', [$start, $end]);
        }

        $bookings = $query->get();

        return [
            'total_bookings' => $bookings->count(),
            'total_revenue' => (float) $bookings->where('status', '!=', 'cancelled')->sum('total_amount'),
            'confirmed' => $bookings->where('status', 'confirmed')->count(),
            'cancelled' => $bookings->where('status', 'cancelled')->count(),
        ];
    }

    private function receptionStats(int $companyId, ?Carbon $start, ?Carbon $end): array
    {
        $query = HotelReceptionBooking::where('company_id', $companyId);

        if ($start && $end) {
            $query->whereBetween('created_at', [$start, $end]);
        }

        $bookings = $query->get();

        return [
            'total_bookings' => $bookings->count(),
            'total_revenue' => (float) $bookings->where('status', '!=', 'cancelled')->sum('total_amount'),
            'confirmed' => $bookings->where('status', 'confirmed')->count(),
            'cancelled' => $bookings->where('status', 'cancelled')->count(),
        ];
    }

    /**
     * @return array<int, array{room_number: string, type: string, reservations: int, revenue: float}>
     */
    private function topRooms(int $companyId, ?Carbon $start, ?Carbon $end): array
    {
        $query = HotelReservation::where('company_id', $companyId)
            ->whereIn('status', ['checked_in', 'checked_out']);

        if ($start && $end) {
            $query->whereBetween('created_at', [$start, $end]);
        }

        return $query->select('hotel_room_id', DB::raw('COUNT(*) as bookings'), DB::raw('SUM(total_amount) as revenue'))
            ->with('room:id,room_number,type')
            ->groupBy('hotel_room_id')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get()
            ->map(fn ($item) => [
                'room_number' => $item->room->room_number ?? '?',
                'type' => $item->room->type ?? '?',
                'bookings' => (int) $item->bookings,
                'revenue' => (float) $item->revenue,
            ])
            ->toArray();
    }

    /**
     * @return array<int, array{name: string, phone: ?string, stays: int, revenue: float}>
     */
    private function topClients(int $companyId, ?Carbon $start, ?Carbon $end): array
    {
        $query = HotelReservation::where('company_id', $companyId)
            ->whereIn('status', ['checked_in', 'checked_out']);

        if ($start && $end) {
            $query->whereBetween('created_at', [$start, $end]);
        }

        return $query->select('guest_name', 'guest_phone', DB::raw('COUNT(*) as stays'), DB::raw('SUM(total_amount) as revenue'))
            ->groupBy('guest_name', 'guest_phone')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get()
            ->map(fn ($item) => [
                'name' => $item->guest_name,
                'phone' => $item->guest_phone,
                'stays' => (int) $item->stays,
                'revenue' => (float) $item->revenue,
            ])
            ->toArray();
    }

    /**
     * @return array<int, array{date: string, rooms: float, restaurant: float, conference: float, reception: float, total: float}>
     */
    private function dailyRevenue(int $companyId, ?Carbon $start, ?Carbon $end): array
    {
        $effectiveStart = $start ?? Carbon::now()->startOfMonth();
        $effectiveEnd = $end ?? Carbon::now()->endOfDay();

        $roomsByDay = HotelReservation::where('company_id', $companyId)
            ->whereIn('status', ['checked_in', 'checked_out'])
            ->whereBetween('created_at', [$effectiveStart, $effectiveEnd])
            ->selectRaw('DATE(created_at) as date, SUM(total_amount) as total')
            ->groupBy('date')
            ->pluck('total', 'date')
            ->toArray();

        $restByDay = HotelRestaurantOrder::where('company_id', $companyId)
            ->whereIn('status', ['paid', 'served'])
            ->whereBetween('created_at', [$effectiveStart, $effectiveEnd])
            ->selectRaw('DATE(created_at) as date, SUM(total) as total')
            ->groupBy('date')
            ->pluck('total', 'date')
            ->toArray();

        $confByDay = HotelConferenceBooking::where('company_id', $companyId)
            ->where('status', 'confirmed')
            ->whereBetween('created_at', [$effectiveStart, $effectiveEnd])
            ->selectRaw('DATE(created_at) as date, SUM(total_amount) as total')
            ->groupBy('date')
            ->pluck('total', 'date')
            ->toArray();

        $recByDay = HotelReceptionBooking::where('company_id', $companyId)
            ->where('status', 'confirmed')
            ->whereBetween('created_at', [$effectiveStart, $effectiveEnd])
            ->selectRaw('DATE(created_at) as date, SUM(total_amount) as total')
            ->groupBy('date')
            ->pluck('total', 'date')
            ->toArray();

        $allDates = collect(array_merge(
            array_keys($roomsByDay),
            array_keys($restByDay),
            array_keys($confByDay),
            array_keys($recByDay),
        ))
            ->unique()
            ->sort()
            ->values();

        return $allDates->map(fn ($date) => [
            'date' => $date,
            'rooms' => (float) ($roomsByDay[$date] ?? 0),
            'restaurant' => (float) ($restByDay[$date] ?? 0),
            'conference' => (float) ($confByDay[$date] ?? 0),
            'reception' => (float) ($recByDay[$date] ?? 0),
            'total' => (float) ($roomsByDay[$date] ?? 0)
                + (float) ($restByDay[$date] ?? 0)
                + (float) ($confByDay[$date] ?? 0)
                + (float) ($recByDay[$date] ?? 0),
        ])->toArray();
    }
}
