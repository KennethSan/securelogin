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
        'api/*',  // Exclude all API routes from CSRF verification
        'logout', // Exclude logout for session-based auth without CSRF
        '/logout', // Also exclude with leading slash
        '*/logout', // Wildcard pattern for logout
    ];

    /**
     * Determine if the request has a URI that should pass through CSRF verification.
     */
    protected function inExceptArray($request)
    {
        // Check if this is a logout request - bypass CSRF completely
        if ($request->is('logout') || $request->path() === 'logout') {
            return true;
        }

        return parent::inExceptArray($request);
    }
}