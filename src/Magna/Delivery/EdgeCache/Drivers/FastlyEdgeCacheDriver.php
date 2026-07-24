<?php

declare(strict_types=1);

namespace Magna\Delivery\EdgeCache\Drivers;

use Illuminate\Http\Client\Factory as Http;
use Magna\Delivery\EdgeCache\Contracts\PurgesEdgeCache;
use RuntimeException;

/**
 * Fastly Surrogate-Key purge driver.
 *
 * Required config keys:
 *   FASTLY_SERVICE_ID
 *   FASTLY_API_TOKEN   (needs purge permission)
 *
 * Fastly accepts a space-separated list of surrogate keys per call
 * (documented limit ~32 KB URL, so we batch at 100 keys to stay safe).
 */
final class FastlyEdgeCacheDriver implements PurgesEdgeCache
{
    private const BATCH_SIZE = 100;

    public function __construct(
        private readonly Http $http,
        private readonly string $serviceId,
        private readonly string $apiToken,
    ) {}

    public function purge(array $keys): void
    {
        if ($keys === [] || $this->serviceId === '' || $this->apiToken === '') {
            return;
        }

        foreach (array_chunk($keys, self::BATCH_SIZE) as $batch) {
            $response = $this->http
                ->withHeaders([
                    'Fastly-Key' => $this->apiToken,
                    'Surrogate-Key' => implode(' ', $batch),
                ])
                ->post("https://api.fastly.com/service/{$this->serviceId}/purge");

            if (! $response->successful()) {
                throw new RuntimeException(
                    "Fastly purge failed ({$response->status()}): {$response->body()}"
                );
            }
        }
    }
}
