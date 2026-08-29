<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\BankDepositController;
use App\Http\Controllers\Api\CashRegisterController;
use App\Http\Controllers\Api\CurrencyController;
use App\Http\Controllers\Api\CustomerReminderController;
use App\Http\Controllers\Api\ExpirationAlertController;
use App\Http\Controllers\Api\ImportExportController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\PatientHistoryController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PaymentMethodController;
use App\Http\Controllers\Api\PharmaceuticalDashboardController;
use App\Http\Controllers\Api\PrescriptionController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductLotController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SavedPosCartController;
use App\Http\Controllers\Api\WarehouseController;
use App\Http\Controllers\Api\WarehouseUserController;
use Illuminate\Support\Facades\Route;

Route::get('warehouses/{warehouse}/users', [WarehouseUserController::class, 'getAssignedUsers']);

// Récupérer les utilisateurs disponibles (non assignés)
Route::get('warehouses/{warehouse}/available-users', [WarehouseUserController::class, 'getAvailableUsers']);
Route::post('warehouses/{warehouse}/assign-user', [WarehouseUserController::class, 'assignUser']);
Route::post('warehouses/{warehouse}/unassign-user', [WarehouseUserController::class, 'unassignUser']);
Route::post('warehouses/{warehouse}/assign-multiple-users', [WarehouseUserController::class, 'assignMultipleUsers']);

// =============================================
// ROUTES PHARMACEUTIQUES
// =============================================

// Produits pharmaceutiques (déplacé avant apiResource 'products' - voir ligne ~78)

// Product Lots (Gestion des lots)
Route::apiResource('product-lots', ProductLotController::class);
Route::post('product-lots/{id}/restore', [ProductLotController::class, 'restore']);
Route::get('products/{product}/lots', [ProductLotController::class, 'byProduct']);
Route::post('product-lots/{lot}/adjust', [ProductLotController::class, 'adjustQuantity']);

// Prescriptions (Ordonnances)
Route::apiResource('prescriptions', PrescriptionController::class);
Route::post('prescriptions/{id}/restore', [PrescriptionController::class, 'restore']);
Route::post('prescriptions/{prescription}/dispense', [PrescriptionController::class, 'dispense']);

// Patient History (Historique patient)
Route::apiResource('patient-histories', PatientHistoryController::class);
Route::get('customers/{customer}/history', [PatientHistoryController::class, 'byCustomer']);
Route::post('patient-histories/check-interactions', [PatientHistoryController::class, 'checkInteractions']);
Route::get('patient-histories/followups', [PatientHistoryController::class, 'followups']);

// Expiration Alerts (Alertes d'expiration)
Route::get('expiration-alerts', [ExpirationAlertController::class, 'index']);
Route::get('expiration-alerts/stats', [ExpirationAlertController::class, 'stats']);
Route::get('expiration-alerts/{expirationAlert}', [ExpirationAlertController::class, 'show']);
Route::post('expiration-alerts/{alert}/acknowledge', [ExpirationAlertController::class, 'acknowledge']);
Route::post('expiration-alerts/{alert}/resolve', [ExpirationAlertController::class, 'resolve']);
Route::post('expiration-alerts/generate', [ExpirationAlertController::class, 'generate']);
Route::post('expiration-alerts/bulk-acknowledge', [ExpirationAlertController::class, 'bulkAcknowledge']);

// Pharmaceutical Dashboard
Route::get('pharmaceutical/dashboard', [PharmaceuticalDashboardController::class, 'index']);
Route::get('pharmaceutical/expiring-soon', [PharmaceuticalDashboardController::class, 'expiringSoon']);
Route::get('pharmaceutical/recent-prescriptions', [PharmaceuticalDashboardController::class, 'recentPrescriptions']);
Route::get('pharmaceutical/critical-alerts', [PharmaceuticalDashboardController::class, 'criticalAlerts']);
Route::get('pharmaceutical/therapeutic-classes', [PharmaceuticalDashboardController::class, 'therapeuticClasses']);
Route::get('pharmaceutical/low-stock', [PharmaceuticalDashboardController::class, 'lowStock']);
Route::get('/products/{product}/barcode', [ProductController::class, 'generatebarcode'])->name('api.products.barcode');
Route::get('/products/{product}/qrcode', [ProductController::class, 'generateqrcode'])->name('api.products.qrcode');
Route::get('/products-print-labels', [ProductController::class, 'printLabels'])->name('api.products.print');

// Get Mes stock
Route::get('mes_stock', [WarehouseController::class, 'mesStock']);
Route::get('pos-products', [ProductController::class, 'posProducts']);
Route::apiResource('saved-pos-carts', SavedPosCartController::class)->only(['index', 'store', 'destroy']);

// Analytics
Route::get('analytics/sales-chart', [AnalyticsController::class, 'salesChart']);
Route::get('analytics/top-products', [AnalyticsController::class, 'topProducts']);
Route::get('analytics/top-customers', [AnalyticsController::class, 'topCustomers']);
Route::get('analytics/low-stock', [AnalyticsController::class, 'lowStockAlerts']);
Route::get('analytics/dashboard-stats', [AnalyticsController::class, 'dashboardStats']);

// Reports
Route::get('reports/sales', [ReportController::class, 'sales']);
Route::get('reports/invoices-history', [ReportController::class, 'invoicesHistory']);
Route::get('reports/stock-sheet', [ReportController::class, 'stockSheet']);
Route::get('reports/stock-movements', [ReportController::class, 'stockMovements']);
Route::get('reports/stock-entries', [ReportController::class, 'stockEntries']);
Route::get('reports/credit-invoices', [ReportController::class, 'creditInvoices']);
Route::get('reports/proformas', [ReportController::class, 'proformas']);
Route::get('reports/invoices-print', [ReportController::class, 'invoicesForPrint']);
Route::get('reports/cash-balance', [ReportController::class, 'cashBalance']);

// =============================================
// GESTION FINANCIÈRE
// =============================================

// Payments (Paiements partiels)
Route::apiResource('payment-methods', PaymentMethodController::class)->except(['show']);
Route::get('payments/methods', [PaymentController::class, 'paymentMethods']);
Route::get('invoices/{invoice}/payments', [PaymentController::class, 'invoicePayments']);
Route::apiResource('payments', PaymentController::class)->except(['update']);

// Cash Register (Caisse journalière)
Route::get('cash-registers/current', [CashRegisterController::class, 'current']);
Route::get('cash-registers/daily-summary', [CashRegisterController::class, 'dailySummary']);
Route::post('cash-registers/open', [CashRegisterController::class, 'open']);
Route::post('cash-registers/{id}/close', [CashRegisterController::class, 'close']);
Route::post('cash-registers/{id}/movements', [CashRegisterController::class, 'addMovement']);
Route::get('cash-registers/{id}/movements', [CashRegisterController::class, 'movements']);
Route::apiResource('cash-registers', CashRegisterController::class)->only(['index', 'show']);

// Bank Deposits (Versements sur banque)
Route::get('bank-deposits/summary', [BankDepositController::class, 'summary']);
Route::apiResource('bank-deposits', BankDepositController::class)->only(['index', 'store', 'show', 'destroy']);

// Customer Reminders (Relances clients)
Route::get('reminders/unpaid-invoices', [CustomerReminderController::class, 'unpaidInvoices']);
Route::get('reminders/stats', [CustomerReminderController::class, 'stats']);
Route::post('reminders/{id}/mark-sent', [CustomerReminderController::class, 'markAsSent']);
Route::post('reminders/{id}/mark-paid', [CustomerReminderController::class, 'markAsPaid']);
Route::apiResource('reminders', CustomerReminderController::class);

// Currencies (Multi-devises)
Route::get('currencies/convert', [CurrencyController::class, 'convert']);
Route::get('currencies/{id}/history', [CurrencyController::class, 'rateHistory']);
Route::post('currencies/{id}/rate', [CurrencyController::class, 'updateRate']);
Route::apiResource('currencies', CurrencyController::class);

// =============================================
// BONS DE COMMANDE (PURCHASE ORDERS)
// =============================================
Route::get('purchase-orders/stats', [PurchaseOrderController::class, 'stats']);
Route::patch('purchase-orders/{purchaseOrder}/status', [PurchaseOrderController::class, 'updateStatus']);
Route::apiResource('purchase-orders', PurchaseOrderController::class);

// =============================================
// JOURNAL D'ACTIVITÉS (ACTIVITY LOGS)
// =============================================
Route::get('activity-logs/stats', [ActivityLogController::class, 'stats']);
Route::get('activity-logs/types', [ActivityLogController::class, 'types']);
Route::get('activity-logs/actions', [ActivityLogController::class, 'actions']);
Route::get('activity-logs/export', [ActivityLogController::class, 'export']);
Route::apiResource('activity-logs', ActivityLogController::class)->only(['index', 'show']);

// =============================================
// IMPORT / EXPORT DE DONNÉES
// =============================================
Route::get('export/products-template', [ImportExportController::class, 'downloadProductTemplate']);
Route::get('export/products', [ImportExportController::class, 'exportProducts']);
Route::post('import/products/preview', [ImportExportController::class, 'previewProductImport']);
Route::post('import/products', [ImportExportController::class, 'importProducts']);

// =============================================
// ANNULATION DE FACTURES
// =============================================
Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancelInvoice']);
