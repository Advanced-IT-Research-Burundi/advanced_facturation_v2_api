<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\WarehouseController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\InvoiceItemController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\WarehouseProductController;
use App\Http\Controllers\Api\RoleUserController;
use App\Http\Controllers\Api\AppConfigController;

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

    // Warehouses
    Route::apiResource('warehouses', WarehouseController::class);
    Route::post('warehouses/{id}/restore', [WarehouseController::class, 'restore']);

    // Customers
    Route::apiResource('customers', CustomerController::class);
    Route::post('customers/{id}/restore', [CustomerController::class, 'restore']);

    // Invoices
    Route::apiResource('invoices', InvoiceController::class);
    Route::post('invoices/{id}/restore', [InvoiceController::class, 'restore']);

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
});
