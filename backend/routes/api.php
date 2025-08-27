<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// CSRF-exempt API routes with rate limiting
Route::prefix('v1')->group(function () {
    // Rate limited authentication endpoints (5 attempts per minute)
    Route::middleware('throttle:5,1')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    });
    
    // Public registration endpoint
    Route::post('/register', [AuthController::class, 'register']);
    
    // Logout endpoint - should be protected
    Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);
    
    // Protected routes - require authentication
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', [AuthController::class, 'user']);
        Route::get('/me', [AuthController::class, 'user']);
    });
});