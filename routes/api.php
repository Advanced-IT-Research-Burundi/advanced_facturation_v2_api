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

    Route::get('checkTIN/{tp_TIN}', [CustomerController::class,'checkTin']);

    // Invoices
    Route::apiResource('invoices', InvoiceController::class);
    Route::post('invoices/{id}/restore', [InvoiceController::class, 'restore']);

    Route::apiResource('stocks', WarehouseController::class);

    Route::get('stocks/{id}/products', [WarehouseController::class,'warehouseProducts']);
    Route::get('stocks/{id}/notproducts', [WarehouseController::class,'warehouseNotProducts']);

    // Invoice Items
    Route::apiResource('invoice-items', InvoiceItemController::class);
    Route::post('invoice-items/{id}/restore', [InvoiceItemController::class, 'restore']);

    // Stock Movements
    Route::apiResource('stock-movements', StockMovementController::class);
    Route::post('stock-movements/{id}/restore', [StockMovementController::class, 'restore']);

    // Stock Movement Operations
    Route::get('warehouse-stock/{warehouseId}', [StockMovementController::class, 'warehouseStock']);
    Route::post('stock-entries', [StockMovementController::class, 'createEntry']);
    Route::post('stock-exits', [StockMovementController::class, 'createExit']);
    Route::get('movement-types', [StockMovementController::class, 'getMovementTypes']);
    Route::get('warehouses/{warehouseId}/movements', [StockMovementController::class, 'byWarehouse']);

    // Warehouse Transfers
    Route::get('warehouse-transfers/pending', [WarehouseTransferController::class, 'pending']);
    Route::apiResource('warehouse-transfers', WarehouseTransferController::class);
    Route::post('warehouse-transfers/{warehouseTransfer}/approve', [WarehouseTransferController::class, 'approve']);
    Route::post('warehouse-transfers/{warehouseTransfer}/reject', [WarehouseTransferController::class, 'reject']);

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

});

Route::get('/products/{product}/barcode', [ProductController::class, 'generatebarcode'])->name('api.products.barcode');
Route::get('/products/{product}/qrcode', [ProductController::class, 'generateqrcode'])->name('api.products.qrcode');
Route::get('/products-print-labels', [ProductController::class, 'printLabels'])->name('api.products.print');

