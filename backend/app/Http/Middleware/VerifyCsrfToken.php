<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        // CSRF bootstrap endpoint
        'sanctum/csrf-cookie',

        // Development-only utilities (keep minimal)
        'dev/*',
        '/dev/*',

        // Optionally keep direct debug helpers (non-production)
        'test-login',
        '/test-login',
        'debug-login',
        '/debug-login',
    ];
}