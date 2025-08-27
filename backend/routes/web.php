<?php

use App\Http\Controllers\AuthController;
use App\Http\Middleware\LoginRateLimiter;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\ApiAuth;
use Illuminate\Support\Facades\Route;

// Apply security headers to all routes
Route::middleware([SecurityHeaders::class])->group(function () {
    Route::get('/', function () {
        return view('welcome');
    });

    // CSRF cookie route for SPA authentication - use Laravel Sanctum's built-in functionality
    Route::get('/sanctum/csrf-cookie', '\Laravel\Sanctum\Http\Controllers\CsrfCookieController@show');

    // Session-based authentication routes - use web middleware for proper CSRF handling
    Route::middleware(['web'])->group(function () {
        // Login routes with rate limiting
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
    });

    // API routes for authenticated users
    Route::prefix('api')->middleware(['api'])->group(function () {
        Route::get('/me', [AuthController::class, 'user'])->middleware('auth:sanctum');
    });
});

// Separate logout route with minimal middleware to test authentication logic
Route::post('/logout', [AuthController::class, 'logout'])->middleware([
    \App\Http\Middleware\SecurityHeaders::class,
    \Illuminate\Cookie\Middleware\EncryptCookies::class,
    \Illuminate\Session\Middleware\StartSession::class,
]);
