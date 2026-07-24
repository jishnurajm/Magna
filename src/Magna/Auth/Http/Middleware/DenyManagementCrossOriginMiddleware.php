<?php

declare(strict_types=1);

namespace Magna\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Explicitly deny cross-origin browser requests to the management API.
 *
 * The browser same-origin policy already blocks responses without CORS headers,
 * but an explicit 403 here adds defence-in-depth: it catches misconfigured
 * clients and makes the policy visible in logs and tests.
 *
 * Delivery routes have their own CORS policy (config/cors.php) and must NOT
 * use this middleware.
 */
final class DenyManagementCrossOriginMiddleware
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->headers->get('Origin');

        if ($origin !== null && ! $this->isSameOrigin($origin)) {
            return response()->json(
                ['message' => 'Cross-origin requests are not permitted on the management API.'],
                403,
            );
        }

        return $next($request);
    }

    private function isSameOrigin(string $origin): bool
    {
        $appUrl = config('app.url', '');

        if (! is_string($appUrl) || $appUrl === '') {
            return false;
        }

        // Extract only scheme+host+port from APP_URL. Browsers always send the
        // bare origin (no path), so "https://example.com/cms" must match
        // "https://example.com", not require the /cms suffix in the header.
        $parsed = parse_url($appUrl);
        if (! is_array($parsed) || ! isset($parsed['host'])) {
            return false;
        }

        $appOrigin = ($parsed['scheme'] ?? 'http').'://'.$parsed['host'];
        if (isset($parsed['port'])) {
            $appOrigin .= ':'.$parsed['port'];
        }

        return rtrim($origin, '/') === $appOrigin;
    }
}
