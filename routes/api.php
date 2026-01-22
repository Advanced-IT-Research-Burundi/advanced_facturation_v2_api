<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductUnitController;
use App\Http\Controllers\Api\WarehouseController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\InvoiceItemController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\WarehouseProductController;
use App\Http\Controllers\Api\RoleUserController;
use App\Http\Controllers\Api\AppConfigController;
use App\Http\Controllers\Api\CategoryProductController;
use App\Http\Controllers\Api\DepenseCategoryController;
use App\Http\Controllers\Api\DepenseController;
use App\Http\Controllers\Api\FourinsseurController;
use App\Http\Controllers\Api\WarehouseUserController;
use App\Http\Controllers\Api\ProductLotController;
use App\Http\Controllers\Api\PrescriptionController;
use App\Http\Controllers\Api\PatientHistoryController;
use App\Http\Controllers\Api\ExpirationAlertController;
use App\Http\Controllers\Api\PharmaceuticalDashboardController;
use App\Http\Controllers\Api\WarehouseTransferController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\CashRegisterController;
use App\Http\Controllers\Api\CustomerReminderController;
use App\Http\Controllers\Api\CurrencyController;
use App\Http\Controllers\Api\BakeryProductionController;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Logout
    Route::post('/logout', [AuthController::class, 'logout']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Companies
    Route::apiResource('companies', CompanyController::class);
    Route::post('companies/{id}/restore', [CompanyController::class, 'restore']);

    // Roles
    Route::apiResource('roles', RoleController::class);
    Route::post('roles/{id}/restore', [RoleController::class, 'restore']);

    // Role Users
    Route::apiResource('role-users', RoleUserController::class);
    Route::post('role-users/{id}/restore', [RoleUserController::class, 'restore']);

    // Users
    Route::apiResource('users', UserController::class);
        // Get all roles
    Route::get('/get-roles/all', [UserController::class, 'getRoles']);
    Route::post('users/{id}/restore', [UserController::class, 'restore']);

    // Products
    Route::apiResource('products', ProductController::class);
    Route::post('products/{id}/restore', [ProductController::class, 'restore']);

    // Product Units
    Route::apiResource('product-units', ProductUnitController::class);
    Route::post('product-units/{id}/restore', [ProductUnitController::class, 'restore']);

    // Category Products
    Route::apiResource('category-products', CategoryProductController::class);
    Route::post('category-products/{id}/restore', [CategoryProductController::class, 'restore']);

    Route::post('warehouses/{id}/products/{product_id}', [WarehouseController::class,'addProduct']);

    // Warehouses
    Route::apiResource('warehouses', WarehouseController::class);
    Route::post('warehouses/{id}/restore', [WarehouseController::class, 'restore']);
    Route::get('product_not_stock/{stock_id}', [WarehouseController::class, 'product_not_stock']);

    Route::get('product_in_stock/{stock_id}', [WarehouseController::class, 'product_in_stock']);

    // Customers
    Route::apiResource('customers', CustomerController::class);
    Route::post('customers/{id}/restore', [CustomerController::class, 'restore']);
    Route::get('customers/{customer}/deposits', [CustomerController::class, 'deposits']);

    Route::get('checkTIN/{tp_TIN}', [CustomerController::class,'checkTin']);

    // Invoices
    Route::post('invoices/sync-obr', [InvoiceController::class, 'syncPendingInvoices']);
    Route::get('invoices/obr-stats', [InvoiceController::class, 'obrStats']);
    Route::apiResource('invoices', InvoiceController::class);
    Route::post('invoices/{id}/restore', [InvoiceController::class, 'restore']);
    Route::post('invoices/{invoice}/resend-obr', [InvoiceController::class, 'resendToObr']);

    Route::apiResource('stocks', WarehouseController::class);

    Route::get('stocks/{id}/products', [WarehouseController::class,'warehouseProducts']);
    Route::get('stocks/{id}/notproducts', [WarehouseController::class,'warehouseNotProducts']);

    // Invoice Items
    Route::apiResource('invoice-items', InvoiceItemController::class);
    Route::post('invoice-items/{id}/restore', [InvoiceItemController::class, 'restore']);

     // Stock Movements
    Route::get('warehouses/{warehouseId}/dashboard', [StockMovementController::class, 'dashboard']);
    Route::get('warehouses/{warehouseId}/movements', [StockMovementController::class, 'movements']);
    Route::post('warehouses/{warehouseId}/quick-entry', [StockMovementController::class, 'quickEntry']);
    Route::post('warehouses/{warehouseId}/quick-exit', [StockMovementController::class, 'quickExit']);
    Route::post('warehouses/{warehouseId}/bulk-entry', [StockMovementController::class, 'bulkEntry']);
    Route::post('warehouses/{warehouseId}/bulk-exit', [StockMovementController::class, 'bulkExit']);
    Route::post('warehouses/{warehouseId}/transfers', [StockMovementController::class, 'createTransfer']);
    Route::post('warehouses/{warehouseId}/transfers/{transferId}/approve', [StockMovementController::class, 'approveTransfer']);
    Route::post('warehouses/{warehouseId}/transfers/{transferId}/reject', [StockMovementController::class, 'rejectTransfer']);
    Route::get('warehouses/{warehouseId}/available', [StockMovementController::class, 'availableWarehouses']);

    // Bakery Production
    //  Route::prefix('bakery/production')->group(function () {
    //     Route::get('/dashboard', [BakeryProductionController::class, 'dashboard']);
    //     Route::post('/quick-entry', [BakeryProductionController::class, 'quickEntry']);
    //     Route::post('/quick-exit', [BakeryProductionController::class, 'quickExit']);
    //     Route::get('/finished-products', [BakeryProductionController::class, 'finishedProducts']);
    //     Route::post('/record', [BakeryProductionController::class, 'recordProduction']);
    //     Route::get('/transfer-data', [BakeryProductionController::class, 'transferData']);
    //     Route::post('/transfer', [BakeryProductionController::class, 'transferToSales']);
    //     Route::get('/history', [BakeryProductionController::class, 'productionHistory']);

    //     // Route::post('/report', [BakeryProductionController::class, 'productionReport']);
    // });
    Route::prefix('bakery/production')->group(function () {
        Route::get('/dashboard', [BakeryProductionController::class, 'dashboard']);
        Route::post('/change-status', [BakeryProductionController::class, 'changeStatus']);
        Route::post('/quick-entry', [BakeryProductionController::class, 'quickEntry']);
        Route::post('/quick-exit', [BakeryProductionController::class, 'quickExit']);
        Route::post('/quick-transfer', [BakeryProductionController::class, 'quickTransfer']);
        Route::get('/finished-products', [BakeryProductionController::class, 'finishedProducts']);
        Route::post('/record', [BakeryProductionController::class, 'recordProduction']);
        Route::get('/transfer-data', [BakeryProductionController::class, 'transferData']);
        Route::post('/transfer', [BakeryProductionController::class, 'transferToSales']);
        Route::get('/history', [BakeryProductionController::class, 'productionHistory']);
    });

    // Warehouse Products
    Route::apiResource('warehouse-products', WarehouseProductController::class);
    Route::post('warehouse-products/{id}/restore', [WarehouseProductController::class, 'restore']);

    // App Configs
    Route::apiResource('app-configs', AppConfigController::class);
    Route::post('app-configs/{id}/restore', [AppConfigController::class, 'restore']);
    Route::get('app-configs-by-key/{key}', [AppConfigController::class, 'getByKey']);

    // Depense Categories
    Route::apiResource('depense-categories', DepenseCategoryController::class);
    Route::post('depense-categories/{id}/restore', [DepenseCategoryController::class, 'restore']);

    // Depenses
    Route::apiResource('depenses', DepenseController::class);
    Route::post('depenses/{id}/restore', [DepenseController::class, 'restore']);

    // Fourinsseurs
    Route::apiResource('fournisseurs', FourinsseurController::class);

    Route::get('warehouses/{warehouse}/users', [WarehouseUserController::class, 'getAssignedUsers']);

    // Récupérer les utilisateurs disponibles (non assignés)
    Route::get('warehouses/{warehouse}/available-users', [WarehouseUserController::class, 'getAvailableUsers']);
    Route::post('warehouses/{warehouse}/assign-user', [WarehouseUserController::class, 'assignUser']);
    Route::post('warehouses/{warehouse}/unassign-user', [WarehouseUserController::class, 'unassignUser']);
    Route::post('warehouses/{warehouse}/assign-multiple-users', [WarehouseUserController::class, 'assignMultipleUsers']);

    // =============================================
    // ROUTES PHARMACEUTIQUES
    // =============================================

    // Produits pharmaceutiques
    Route::get('products/pharmaceutical', [PharmaceuticalDashboardController::class, 'products']);

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

    // Analytics
    Route::get('analytics/sales-chart', [AnalyticsController::class, 'salesChart']);
    Route::get('analytics/top-products', [AnalyticsController::class, 'topProducts']);
    Route::get('analytics/top-customers', [AnalyticsController::class, 'topCustomers']);
    Route::get('analytics/low-stock', [AnalyticsController::class, 'lowStockAlerts']);
    Route::get('analytics/dashboard-stats', [AnalyticsController::class, 'dashboardStats']);

    // Reports
    Route::get('reports/sales', [App\Http\Controllers\Api\ReportController::class, 'sales']);
    Route::get('reports/invoices-history', [App\Http\Controllers\Api\ReportController::class, 'invoicesHistory']);
    Route::get('reports/stock-sheet', [App\Http\Controllers\Api\ReportController::class, 'stockSheet']);
    Route::get('reports/stock-movements', [App\Http\Controllers\Api\ReportController::class, 'stockMovements']);
    Route::get('reports/stock-entries', [App\Http\Controllers\Api\ReportController::class, 'stockEntries']);
    Route::get('reports/credit-invoices', [App\Http\Controllers\Api\ReportController::class, 'creditInvoices']);
    Route::get('reports/proformas', [App\Http\Controllers\Api\ReportController::class, 'proformas']);
    Route::get('reports/invoices-print', [App\Http\Controllers\Api\ReportController::class, 'invoicesForPrint']);

    // =============================================
    // GESTION FINANCIÈRE
    // =============================================

    // Payments (Paiements partiels)
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
    Route::get('purchase-orders/stats', [App\Http\Controllers\Api\PurchaseOrderController::class, 'stats']);
    Route::patch('purchase-orders/{purchaseOrder}/status', [App\Http\Controllers\Api\PurchaseOrderController::class, 'updateStatus']);
    Route::apiResource('purchase-orders', App\Http\Controllers\Api\PurchaseOrderController::class);

    // =============================================
    // JOURNAL D'ACTIVITÉS (ACTIVITY LOGS)
    // =============================================
    Route::get('activity-logs/stats', [App\Http\Controllers\Api\ActivityLogController::class, 'stats']);
    Route::get('activity-logs/types', [App\Http\Controllers\Api\ActivityLogController::class, 'types']);
    Route::get('activity-logs/actions', [App\Http\Controllers\Api\ActivityLogController::class, 'actions']);
    Route::get('activity-logs/export', [App\Http\Controllers\Api\ActivityLogController::class, 'export']);
    Route::apiResource('activity-logs', App\Http\Controllers\Api\ActivityLogController::class)->only(['index', 'show']);

});

