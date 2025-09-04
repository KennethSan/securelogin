<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// Public authentication routes (no auth required)
Route::middleware('throttle:5,1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('api.password.reset');
});

// Development email verification (only in local environment)
Route::post('/dev/verify-email', [AuthController::class, 'devVerifyEmail']);
Route::match(['GET', 'POST'], '/dev/verify', [AuthController::class, 'devVerifyEmail']);

// Email verification notification - requires auth to resend
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/email/verification-notification', [AuthController::class, 'sendVerificationEmail']);
});

// Logout endpoint - should be protected
Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);

// Protected routes - require authentication
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::get('/me', [AuthController::class, 'user']);
});