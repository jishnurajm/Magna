<?php

declare(strict_types=1);

namespace Magna\Delivery\EdgeCache\Contracts;

/**
 * Contract for CDN edge-cache purge drivers.
 *
 * Implementations receive a list of surrogate keys and must dispatch purge
 * requests to the upstream CDN. The call is fire-and-forget from the
 * application's perspective — failures are queued for retry by the driver.
 */
interface PurgesEdgeCache
{
    /**
     * Purge all cached objects tagged with any of the given surrogate keys.
     *
     * @param  list<string>  $keys  e.g. ['type:article', 'entry:01ABC123']
     */
    public function purge(array $keys): void;
}
