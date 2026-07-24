<?php

declare(strict_types=1);

namespace Magna\Delivery\EdgeCache\Drivers;

use Illuminate\Http\Client\Factory as Http;
use Magna\Delivery\EdgeCache\Contracts\PurgesEdgeCache;
use RuntimeException;

/**
 * Varnish BAN-request purge driver.
 *
 * Required config keys:
 *   VARNISH_HOST    e.g. http://varnish:6081
 *   VARNISH_SECRET  optional — sent as X-Varnish-Secret for ACL auth
 *
 * Issues one BAN per surrogate key via the custom X-Surrogate-Key
 * header: `BAN /` with `X-Surrogate-Key: {key}`. Requires a Varnish
 * VCL rule that honours this header to perform the actual ban.
 */
final class VarnishEdgeCacheDriver implements PurgesEdgeCache
{
    public function __construct(
        private readonly Http $http,
        private readonly string $host,
        private readonly string $secret = '',
    ) {}

    public function purge(array $keys): void
    {
        if ($keys === [] || $this->host === '') {
            return;
        }

        $headers = ['X-Surrogate-Key' => ''];
        if ($this->secret !== '') {
            $headers['X-Varnish-Secret'] = $this->secret;
        }

        foreach ($keys as $key) {
            $response = $this->http
                ->withHeaders([...$headers, 'X-Surrogate-Key' => $key])
                ->send('BAN', rtrim($this->host, '/').'/');

            if (! $response->successful()) {
                throw new RuntimeException(
                    "Varnish BAN failed for key '{$key}' ({$response->status()}): {$response->body()}"
                );
            }
        }
    }
}
