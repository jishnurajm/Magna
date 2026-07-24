<?php

declare(strict_types=1);

namespace Magna\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Attaches hardened security headers to every response.
 * Admin-route CSP is handled by the AdminCspMiddleware stacked on top.
 */
class SecurityHeadersMiddleware
{
    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // HSTS — only meaningful over HTTPS; harmless over HTTP in dev.
        $response->headers->set(
            'Strict-Transport-Security',
            'max-age=31536000; includeSubDomains',
        );

        return $response;
    }
}
