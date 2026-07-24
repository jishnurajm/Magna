<?php

declare(strict_types=1);

namespace Magna\Delivery\EdgeCache\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Magna\Delivery\EdgeCache\Contracts\PurgesEdgeCache;

/**
 * Queued job that delegates a batch of surrogate-key purges to the
 * configured edge-cache driver.  Retried automatically by the queue
 * worker on failure (3 attempts, 10-second back-off).
 */
final class PurgeEdgeCacheJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    /**
     * @param  list<string>  $keys
     */
    public function __construct(private readonly array $keys) {}

    public function handle(PurgesEdgeCache $driver): void
    {
        $driver->purge($this->keys);
    }
}
