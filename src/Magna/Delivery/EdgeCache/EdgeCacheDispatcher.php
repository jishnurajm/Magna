<?php

declare(strict_types=1);

namespace Magna\Delivery\EdgeCache;

use Magna\Delivery\EdgeCache\Jobs\PurgeEdgeCacheJob;

/**
 * Dispatches surrogate-key purge jobs to the queue so edge-cache
 * invalidation is asynchronous and retriable.
 */
final class EdgeCacheDispatcher
{
    /**
     * Queue a purge for the given surrogate keys.
     *
     * @param  list<string>  $keys
     */
    public function dispatch(array $keys): void
    {
        if ($keys !== []) {
            PurgeEdgeCacheJob::dispatch($keys);
        }
    }
}
