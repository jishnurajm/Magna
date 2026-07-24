<?php

declare(strict_types=1);

namespace Magna\Delivery\EdgeCache\Drivers;

use Magna\Delivery\EdgeCache\Contracts\PurgesEdgeCache;

/**
 * No-op edge cache driver — the default when no CDN is configured.
 * Safe for local development and test environments.
 */
final class NullEdgeCacheDriver implements PurgesEdgeCache
{
    public function purge(array $keys): void
    {
        // Intentional no-op.
    }
}
