<?php

declare(strict_types=1);

namespace Magna\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Magna\Auth\ApiKeyService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticate requests using an X-Magna-Key / X-Magna-Secret header pair.
 *
 * Usage as an alias: `magna.api.key` (registered in AuthServiceProvider).
 * Scope enforcement: `magna.api.key:management` rejects delivery keys.
 *
 * Headers:
 *   X-Magna-Key:    mag_del_<hex>   (or mag_mgt_<hex>)
 *   X-Magna-Secret: <64-hex-char secret>
 */
class ApiKeyMiddleware
{
    public function __construct(private readonly ApiKeyService $service) {}

    /**
     * @param  Closure(Request): Response  $next
     * @param  string  $requiredScope  'delivery' (default) | 'management'
     */
    public function handle(Request $request, Closure $next, string $requiredScope = 'delivery'): Response
    {
        $key = $request->header('X-Magna-Key');
        $secret = $request->header('X-Magna-Secret');

        if (! $key || ! $secret) {
            return $this->unauthorized('Missing API credentials. Provide X-Magna-Key and X-Magna-Secret headers.');
        }

        $apiKey = $this->service->verify((string) $key, (string) $secret);

        if ($apiKey === null) {
            return $this->unauthorized('Invalid or revoked API credentials.');
        }

        if ($requiredScope === 'management' && $apiKey->isDelivery()) {
            return $this->forbidden('A management-scope key is required for this endpoint.');
        }

        $rateLimitKey = 'api_key:'.$apiKey->id;
        $limit = $apiKey->effectiveRateLimit();

        if (RateLimiter::tooManyAttempts($rateLimitKey, $limit)) {
            $retryAfter = RateLimiter::availableIn($rateLimitKey);

            return response()->json(
                ['message' => 'Too many requests.'],
                Response::HTTP_TOO_MANY_REQUESTS,
                ['Retry-After' => $retryAfter],
            );
        }

        RateLimiter::hit($rateLimitKey, 60);

        $apiKey->forceFill(['last_used_at' => now()])->save();

        // Attach the resolved key to the request for downstream use.
        $request->attributes->set('magna_api_key', $apiKey);

        return $next($request);
    }

    private function unauthorized(string $message): JsonResponse
    {
        return response()->json(['message' => $message], Response::HTTP_UNAUTHORIZED);
    }

    private function forbidden(string $message): JsonResponse
    {
        return response()->json(['message' => $message], Response::HTTP_FORBIDDEN);
    }
}
