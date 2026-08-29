<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/register-company', [AuthController::class, 'registerCompany']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    require __DIR__.'/api/core.php';
    require __DIR__.'/api/operations.php';
    require __DIR__.'/api/verticals.php';
});
