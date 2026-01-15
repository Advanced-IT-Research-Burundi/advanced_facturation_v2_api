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

});

Route::get('/products/{product}/barcode', [ProductController::class, 'generatebarcode'])->name('api.products.barcode');
Route::get('/products/{product}/qrcode', [ProductController::class, 'generateqrcode'])->name('api.products.qrcode');
Route::get('/products-print-labels', [ProductController::class, 'printLabels'])->name('api.products.print');

