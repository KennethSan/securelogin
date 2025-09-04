<?php

use App\Http\Controllers\AuthController;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

// Apply security headers to remaining routes
Route::middleware([SecurityHeaders::class])->group(function () {
    // CSRF cookie route for SPA authentication
    Route::get('/sanctum/csrf-cookie', '\Laravel\Sanctum\Http\Controllers\CsrfCookieController@show');

    // Email verification routes - signed GET only
    Route::middleware(['signed', 'throttle:6,1'])->group(function () {
        Route::get('/api/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmailSigned'])
            ->name('verification.verify');
        Route::get('/dev/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmailSigned'])
            ->name('dev.verification.verify');
    });

    // Session-based routes (for password reset links from emails)
    Route::middleware(['web'])->group(function () {
        Route::get('/reset-password/{token}', function ($token) {
            return response()->json([
                'message' => 'Password reset page', 
                'token' => $token, 
                'redirect' => '/password-reset?token=' . $token
            ], 200);
        })->name('password.reset.form');
        
        Route::get('/forgot-password', function () {
            return response()->json([
                'message' => 'Forgot password page', 
                'redirect' => '/forgot-password'
            ], 200);
        });
    });

    // Return 404 for unmatched backend routes - let Caddy handle SPA routing
    Route::fallback(function () {
        return response()->json(['message' => 'Route not found'], 404);
    });
});
