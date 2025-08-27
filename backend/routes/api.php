<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// Apply rate limiting to authentication endpoints
Route::middleware('throttle:5,1')->group(function () {
    // 5 attempts per minute
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// Regular public routes (no rate limiting)
Route::post('/register', [AuthController::class, 'register']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::get('/me', [AuthController::class, 'user']); // Add this route for the test
    Route::post('/logout', [AuthController::class, 'logout']);
});