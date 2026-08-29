<?php

use App\Http\Controllers\Api\AppConfigController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BakeryProductionController;
use App\Http\Controllers\Api\CategoryProductController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DepenseCategoryController;
use App\Http\Controllers\Api\DepenseController;
use App\Http\Controllers\Api\FourinsseurController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\InvoiceItemController;
use App\Http\Controllers\Api\PharmaceuticalDashboardController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductUnitController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\RoleUserController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WarehouseController;
use App\Http\Controllers\Api\WarehouseProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
});

// Logout
Route::post('/logout', [AuthController::class, 'logout']);

// Création d'utilisateur (même entreprise que l'admin connecté uniquement)
Route::post('/register', [AuthController::class, 'register']);

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
// Route spécifique pour les produits pharmaceutiques (DOIT être avant apiResource)
Route::get('products/pharmaceutical', [PharmaceuticalDashboardController::class, 'products']);

Route::apiResource('products', ProductController::class);
Route::post('products/{id}/restore', [ProductController::class, 'restore']);

// Product Units
Route::apiResource('product-units', ProductUnitController::class);
Route::post('product-units/{id}/restore', [ProductUnitController::class, 'restore']);

// Category Products
Route::apiResource('category-products', CategoryProductController::class);
Route::post('category-products/{id}/restore', [CategoryProductController::class, 'restore']);

Route::post('warehouses/{id}/products/{product_id}', [WarehouseController::class, 'addProduct']);
Route::delete('warehouses/{id}/products/{product_id}', [WarehouseController::class, 'removeProduct']);

// Warehouses
Route::apiResource('warehouses', WarehouseController::class);
Route::post('warehouses/{id}/restore', [WarehouseController::class, 'restore']);
Route::get('product_not_stock/{stock_id}', [WarehouseController::class, 'product_not_stock']);

Route::get('product_in_stock/{stock_id}', [WarehouseController::class, 'product_in_stock']);

// Customers
Route::apiResource('customers', CustomerController::class);
Route::post('customers/{id}/restore', [CustomerController::class, 'restore']);
Route::get('customers/{customer}/deposits', [CustomerController::class, 'deposits']);

Route::post('customers/checkTIN', [CustomerController::class, 'checkTin']);
Route::get('checkTIN/{tp_TIN}', [CustomerController::class, 'checkTin']);

// Invoices
Route::post('invoices/sync-obr', [InvoiceController::class, 'syncPendingInvoices']);
Route::get('invoices/obr-stats', [InvoiceController::class, 'obrStats']);
Route::apiResource('invoices', InvoiceController::class);
Route::post('invoices/{id}/restore', [InvoiceController::class, 'restore']);
Route::post('invoices/{invoice}/resend-obr', [InvoiceController::class, 'resendToObr']);

Route::apiResource('stocks', WarehouseController::class);

Route::get('stocks/{id}/products', [WarehouseController::class, 'warehouseProducts']);
Route::get('stocks/{id}/notproducts', [WarehouseController::class, 'warehouseNotProducts']);

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
    Route::post('/mark-as-finished', [BakeryProductionController::class, 'markAsFinished']);
    Route::post('/mark-as-raw', [BakeryProductionController::class, 'markAsRaw']);
    Route::post('/quick-entry', [BakeryProductionController::class, 'quickEntry']);
    Route::post('/quick-exit', [BakeryProductionController::class, 'quickExit']);
    Route::post('/quick-transfer', [BakeryProductionController::class, 'quickTransfer']);
    Route::get('/finished-products', [BakeryProductionController::class, 'finishedProducts']);
    Route::post('/record', [BakeryProductionController::class, 'recordProduction']);
    Route::get('/transfer-data', [BakeryProductionController::class, 'transferData']);
    Route::post('/transfer', [BakeryProductionController::class, 'transferToSales']);
    Route::get('/history', [BakeryProductionController::class, 'productionHistory']);
    Route::get('/rapports', [BakeryProductionController::class, 'rapportsSummary']);
    Route::post('/report', [BakeryProductionController::class, 'productionReport']);
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
Route::get('depenses/{depense}/justification', [DepenseController::class, 'justification']);
Route::post('depenses/{id}/restore', [DepenseController::class, 'restore']);

// Fourinsseurs
Route::apiResource('fournisseurs', FourinsseurController::class);
