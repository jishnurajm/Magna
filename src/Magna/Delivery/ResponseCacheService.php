<?php

declare(strict_types=1);

namespace Magna\Delivery;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Full-response body cache for the delivery API with stampede protection.
 *
 * Stores the serialised JSON string keyed by the canonical request signature
 * and tagged with every surrogate key the response carries. Flushing a
 * surrogate-key tag (e.g. 'magna.delivery.type.article') invalidates all
 * cached responses that include data from that type.
 *
 * Stampede protection: on a cache miss the caller can acquire a rebuild lock
 * (tryLock). Concurrent requests that don't win the lock are served STALE
 * from the grace store (a secondary untagged key with a longer TTL) while
 * the winner rebuilds. This prevents all workers from hitting the DB
 * simultaneously when a hot key expires.
 */
final class ResponseCacheService
{
    /** Seconds to hold a cached response body. */
    private const TTL = 300;

    /** Seconds to hold the stale-grace copy for stampede serving. */
    private const GRACE_TTL = 600;

    /** Lock hold time — the rebuild window before the lock auto-releases. */
    private const LOCK_TTL = 10;

    /**
     * Build a stable cache key from the request path + sorted query params.
     * Identical to ETagService::cacheKey() so the two caches are co-located.
     */
    public function cacheKey(Request $request): string
    {
        /** @var array<string, mixed> $params */
        $params = $request->query();
        ksort($params);

        return 'magna.delivery.body.'.hash('sha256', $request->path().'?'.http_build_query($params));
    }

    /**
     * Retrieve a cached response body using the surrogate-key collector's tag set.
     * Returns null on miss; the caller should call tryLock() and, if it wins,
     * rebuild + put(). If it loses the lock, call getStale() for a grace copy.
     */
    public function get(string $cacheKey, SurrogateKeyCollector $keys): ?string
    {
        $value = Cache::tags($keys->cacheTagKeys())->get($cacheKey);

        return is_string($value) ? $value : null;
    }

    /**
     * Retrieve the stale grace copy (survives past normal TTL for stampede serving).
     */
    public function getStale(string $cacheKey): ?string
    {
        $value = Cache::get($cacheKey.':grace');

        return is_string($value) ? $value : null;
    }

    /**
     * Try to acquire the rebuild lock for this cache key.
     * Returns true if this worker won and must rebuild + put().
     * Returns false if another worker holds it — caller should serve stale.
     */
    public function tryLock(string $cacheKey): bool
    {
        return (bool) Cache::lock($cacheKey.':lock', self::LOCK_TTL)->get();
    }

    /**
     * Release the rebuild lock (called after put() to unblock waiters early).
     */
    public function releaseLock(string $cacheKey): void
    {
        Cache::lock($cacheKey.':lock', self::LOCK_TTL)->forceRelease();
    }

    /**
     * Store a response body tagged with the collector's full key set.
     * Also writes a separate untagged grace copy for stampede serving.
     */
    public function put(string $cacheKey, string $body, SurrogateKeyCollector $keys): void
    {
        Cache::tags($keys->cacheTagKeys())->put($cacheKey, $body, self::TTL);
        Cache::put($cacheKey.':grace', $body, self::GRACE_TTL);
    }

    /**
     * Flush all cached responses that carry the given type's surrogate key.
     */
    public function invalidateType(string $typeHandle): void
    {
        Cache::tags(['magna.delivery.type.'.$typeHandle])->flush();
    }

    /**
     * Flush the entire delivery body cache (used when media changes touch all types).
     */
    public function invalidateAll(): void
    {
        Cache::tags(['magna.delivery'])->flush();
    }
}
