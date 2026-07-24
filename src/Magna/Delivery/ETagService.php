<?php

declare(strict_types=1);

namespace Magna\Delivery;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Manages ETag generation, caching, and conditional-request 304 detection.
 *
 * ETags are SHA-256 hashes of the response JSON, stored in the tagged cache.
 * The cache tag 'magna.delivery.type.{handle}' is flushed on content changes,
 * automatically invalidating all ETags for that type.
 */
final class ETagService
{
    /**
     * Build a stable cache key for a given request (path + sorted query params).
     */
    public function cacheKey(Request $request): string
    {
        /** @var array<string, mixed> $params */
        $params = $request->query();
        ksort($params);

        return 'magna.delivery.etag.'.hash('sha256', $request->path().'?'.http_build_query($params));
    }

    /**
     * Check whether the request's If-None-Match matches the cached ETag.
     * Returns the cached ETag string if the response is unmodified (caller
     * should return 304), or null if the content must be re-fetched.
     *
     * The typeHandle must match the one used in store() so the tag set is identical.
     */
    public function check(Request $request, string $cacheKey, string $typeHandle): ?string
    {
        $ifNoneMatch = $request->header('If-None-Match');
        if (! is_string($ifNoneMatch) || $ifNoneMatch === '') {
            return null;
        }

        $cached = Cache::tags(['magna.delivery', 'magna.delivery.type.'.$typeHandle])->get($cacheKey);
        if (! is_string($cached)) {
            return null;
        }

        return $ifNoneMatch === $cached ? $cached : null;
    }

    /**
     * Persist an ETag in the tagged cache for future conditional checks.
     */
    public function store(string $cacheKey, string $etag, string $typeHandle): void
    {
        Cache::tags(['magna.delivery', 'magna.delivery.type.'.$typeHandle])
            ->put($cacheKey, $etag, 3600);
    }

    /**
     * Flush all cached ETags for a content type (call after publish/update/delete).
     */
    public function invalidateType(string $typeHandle): void
    {
        Cache::tags(['magna.delivery.type.'.$typeHandle])->flush();
    }

    /**
     * Flush the entire delivery ETag cache (call when media is created/deleted,
     * since media can appear in any entry response regardless of type).
     */
    public function invalidateAllMedia(): void
    {
        Cache::tags(['magna.delivery'])->flush();
    }

    /**
     * Compute a quoted ETag from JSON-serialisable data.
     */
    public function compute(mixed $data): string
    {
        $json = json_encode($data);

        return '"'.hash('sha256', is_string($json) ? $json : '').'"';
    }
}
