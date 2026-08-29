<?php

use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\CashRegisterController;
use App\Http\Controllers\Api\DepenseCategoryController;
use App\Http\Controllers\Api\DepenseController;
use App\Http\Controllers\Api\HotelBarStockController;
use App\Http\Controllers\Api\HotelConferenceBookingController;
use App\Http\Controllers\Api\HotelConferenceRoomController;
use App\Http\Controllers\Api\HotelDashboardController;
use App\Http\Controllers\Api\HotelDishController;
use App\Http\Controllers\Api\HotelInvoiceController;
use App\Http\Controllers\Api\HotelKitchenController;
use App\Http\Controllers\Api\HotelKitchenStockController;
use App\Http\Controllers\Api\HotelMenuItemController;
use App\Http\Controllers\Api\HotelReceptionBookingController;
use App\Http\Controllers\Api\HotelReceptionHallController;
use App\Http\Controllers\Api\HotelReportController;
use App\Http\Controllers\Api\HotelReservationController;
use App\Http\Controllers\Api\HotelRestaurantOrderController;
use App\Http\Controllers\Api\HotelRestaurantTableController;
use App\Http\Controllers\Api\HotelRoomController;
use App\Http\Controllers\Api\HotelStockMovementController;
use App\Http\Controllers\Api\ObrLogController;
use App\Http\Controllers\Api\RestaurantInvoiceController;
use App\Http\Controllers\Api\RestaurantOrderController;
use App\Http\Controllers\Api\RestaurantTableController;
use App\Http\Controllers\Api\WarehouseProductController;
use Illuminate\Support\Facades\Route;

// =============================================
// LOGS OBR
// =============================================
Route::get('obr-logs/stats', [ObrLogController::class, 'stats']);
Route::post('obr-logs/{obrLog}/retry', [ObrLogController::class, 'retry']);
Route::apiResource('obr-logs', ObrLogController::class)->only(['index', 'show']);

// =============================================
// BACKUP BASE DE DONNÉES
// =============================================
Route::get('backup/database', [BackupController::class, 'database']);
Route::get('backup/list', [BackupController::class, 'list']);

// =============================================
// MODULE RESTAURANT
// =============================================
Route::prefix('restaurant')->group(function () {
    // Dashboard
    Route::get('dashboard', [RestaurantInvoiceController::class, 'dashboard']);
    Route::get('servers', [RestaurantInvoiceController::class, 'servers']);

    // Warehouses & Products for restaurant
    Route::get('warehouses', [RestaurantInvoiceController::class, 'userWarehouses']);
    Route::get('warehouses/{warehouseId}/products', [RestaurantInvoiceController::class, 'warehouseProducts']);

    // Tables
    Route::apiResource('tables', RestaurantTableController::class)->parameters(['tables' => 'restaurantTable']);
    Route::patch('tables/{restaurantTable}/status', [RestaurantTableController::class, 'updateStatus']);
    Route::get('tables/{tableId}/orders', [RestaurantOrderController::class, 'tableOrders']);
    Route::get('tables/{tableId}/invoices', [RestaurantInvoiceController::class, 'tableInvoices']);

    // Orders
    Route::apiResource('orders', RestaurantOrderController::class)->parameters(['orders' => 'restaurantOrder']);
    Route::post('orders/{restaurantOrder}/items', [RestaurantOrderController::class, 'addItems']);
    Route::patch('orders/{restaurantOrder}/served', [RestaurantOrderController::class, 'markAsServed']);
    Route::post('orders/{restaurantOrder}/cancel', [RestaurantOrderController::class, 'cancel']);
    Route::patch('order-items/{itemId}/status', [RestaurantOrderController::class, 'updateItemStatus']);
    Route::delete('order-items/{itemId}', [RestaurantOrderController::class, 'removeItem']);

    // Invoices
    Route::get('invoices', [RestaurantInvoiceController::class, 'index']);
    Route::post('invoices/generate', [RestaurantInvoiceController::class, 'generate']);
    Route::get('invoices/{invoice}', [RestaurantInvoiceController::class, 'show']);
    Route::post('invoices/{invoice}/pay', [RestaurantInvoiceController::class, 'pay']);
    Route::post('invoices/{invoice}/send-obr', [RestaurantInvoiceController::class, 'sendToObr']);
    Route::get('invoices/{invoice}/obr-status', [RestaurantInvoiceController::class, 'obrStatus']);
});

// =============================================
// MODULE HOTEL
// =============================================
Route::prefix('hotel')->group(function () {
    // Dashboard
    Route::get('dashboard', [HotelDashboardController::class, 'index']);

    // Rapport directeur
    Route::get('reports/summary', [HotelReportController::class, 'summary']);

    // Rooms
    Route::apiResource('rooms', HotelRoomController::class)->parameters(['rooms' => 'hotelRoom']);

    // Reservations
    Route::post('reservations/walk-in', [HotelReservationController::class, 'walkIn']);
    Route::apiResource('reservations', HotelReservationController::class)->parameters(['reservations' => 'hotelReservation']);
    Route::post('reservations/{hotelReservation}/check-in', [HotelReservationController::class, 'checkIn']);
    Route::post('reservations/{hotelReservation}/check-out', [HotelReservationController::class, 'checkOut']);
    Route::post('reservations/{hotelReservation}/cancel', [HotelReservationController::class, 'cancel']);
    Route::post('reservations/{hotelReservation}/extend', [HotelReservationController::class, 'extend']);
    Route::post('reservations/{hotelReservation}/invoice', [HotelInvoiceController::class, 'generate']);

    // Hotel invoices
    Route::get('invoices', [HotelInvoiceController::class, 'index']);
    Route::get('invoices/{invoice}', [HotelInvoiceController::class, 'show']);

    // Conference rooms
    Route::apiResource('conference-rooms', HotelConferenceRoomController::class)
        ->parameters(['conference-rooms' => 'hotelConferenceRoom']);
    Route::get('conference-bookings', [HotelConferenceBookingController::class, 'index']);
    Route::post('conference-bookings', [HotelConferenceBookingController::class, 'store']);
    Route::post('conference-bookings/{hotelConferenceBooking}/cancel', [HotelConferenceBookingController::class, 'cancel']);
    Route::post('conference-bookings/{hotelConferenceBooking}/invoice', [HotelConferenceBookingController::class, 'generateInvoice']);
    Route::post('conference-bookings/{hotelConferenceBooking}/extend', [HotelConferenceBookingController::class, 'extend']);

    // Stock movements (bar + kitchen)
    Route::get('stock-movements', [HotelStockMovementController::class, 'index']);
    Route::post('stock-movements', [HotelStockMovementController::class, 'store']);

    // Reception Halls
    Route::apiResource('reception-halls', HotelReceptionHallController::class)
        ->parameters(['reception-halls' => 'hotelReceptionHall']);
    Route::get('reception-bookings', [HotelReceptionBookingController::class, 'index']);
    Route::post('reception-bookings', [HotelReceptionBookingController::class, 'store']);
    Route::post('reception-bookings/{hotelReceptionBooking}/cancel', [HotelReceptionBookingController::class, 'cancel']);
    Route::post('reception-bookings/{hotelReceptionBooking}/invoice', [HotelReceptionBookingController::class, 'generateInvoice']);
    Route::post('reception-bookings/{hotelReceptionBooking}/extend', [HotelReceptionBookingController::class, 'extend']);

    // Restaurant-Bar
    Route::apiResource('restaurant-tables', HotelRestaurantTableController::class)
        ->parameters(['restaurant-tables' => 'hotelRestaurantTable'])
        ->except(['show', 'create', 'edit']);
    Route::get('restaurant-orders', [HotelRestaurantOrderController::class, 'index']);
    Route::post('restaurant-orders', [HotelRestaurantOrderController::class, 'store']);
    Route::put('restaurant-orders/{hotelRestaurantOrder}/status', [HotelRestaurantOrderController::class, 'updateStatus']);
    Route::apiResource('menu-items', HotelMenuItemController::class)
        ->parameters(['menu-items' => 'hotelMenuItem'])
        ->except(['show', 'create', 'edit']);

    // Kitchen
    Route::get('kitchen/orders', [HotelKitchenController::class, 'orders']);
    Route::apiResource('dishes', HotelDishController::class)
        ->parameters(['dishes' => 'hotelDish'])
        ->except(['show', 'create', 'edit']);
    Route::apiResource('kitchen-stock', HotelKitchenStockController::class)
        ->parameters(['kitchen-stock' => 'hotelKitchenStock'])
        ->except(['show', 'create', 'edit']);
    Route::apiResource('bar-stock', HotelBarStockController::class)
        ->parameters(['bar-stock' => 'hotelBarStock'])
        ->except(['show', 'create', 'edit']);

    // Hotel Cash Register (reuse existing CashRegisterController with hotel_section filter)
    Route::get('caisse/global-summary', [CashRegisterController::class, 'hotelGlobalSummary']);
    Route::get('caisse/current', [CashRegisterController::class, 'current']);
    Route::post('caisse/open', [CashRegisterController::class, 'open']);
    Route::post('caisse/{id}/close', [CashRegisterController::class, 'close']);
    Route::post('caisse/{id}/movements', [CashRegisterController::class, 'addMovement']);
    Route::get('caisse/{id}/movements', [CashRegisterController::class, 'movements']);
    Route::get('caisse', [CashRegisterController::class, 'index']);

    // Hotel Depenses
    Route::get('depenses', [DepenseController::class, 'index']);
    Route::post('depenses', [DepenseController::class, 'store']);
    Route::get('depenses/{depense}/justification', [DepenseController::class, 'justification']);
    Route::delete('depenses/{depense}', [DepenseController::class, 'destroy']);
    Route::get('depense-categories', [DepenseCategoryController::class, 'index']);
});

Route::get('stock-mouvements', [WarehouseProductController::class, 'stockMouvementHistory']);

Route::get('historique-invoices', [WarehouseProductController::class, 'historiqueInvoices']);
