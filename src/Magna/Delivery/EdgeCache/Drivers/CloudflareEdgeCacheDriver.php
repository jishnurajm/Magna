<?php

declare(strict_types=1);

namespace Magna\Delivery\EdgeCache\Drivers;

use Illuminate\Http\Client\Factory as Http;
use Magna\Delivery\EdgeCache\Contracts\PurgesEdgeCache;
use RuntimeException;

/**
 * Cloudflare Cache Tag purge driver.
 *
 * Required config keys (config/magna.php or env):
 *   CLOUDFLARE_ZONE_ID
 *   CLOUDFLARE_API_TOKEN   (needs Cache Purge permission on the zone)
 *
 * Cloudflare accepts up to 30 tags per API call.
 */
final class CloudflareEdgeCacheDriver implements PurgesEdgeCache
{
    private const BATCH_SIZE = 30;

    public function __construct(
        private readonly Http $http,
        private readonly string $zoneId,
        private readonly string $apiToken,
    ) {}

    public function purge(array $keys): void
    {
        if ($keys === [] || $this->zoneId === '' || $this->apiToken === '') {
            return;
        }

        foreach (array_chunk($keys, self::BATCH_SIZE) as $batch) {
            $response = $this->http
                ->withToken($this->apiToken)
                ->post("https://api.cloudflare.com/client/v4/zones/{$this->zoneId}/purge_cache", [
                    'tags' => $batch,
                ]);

            if (! $response->successful()) {
                throw new RuntimeException(
                    "Cloudflare purge failed ({$response->status()}): {$response->body()}"
                );
            }
        }
    }
}
