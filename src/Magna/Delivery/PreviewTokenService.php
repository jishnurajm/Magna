<?php

declare(strict_types=1);

namespace Magna\Delivery;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Magna\Delivery\Exceptions\DeliveryException;

/**
 * Stateless HMAC-signed preview tokens scoped to a single entry.
 *
 * Format: base64(json_payload) . "." . hmac_sha256
 * Payload: { entry_id, entry_type, exp }
 */
final class PreviewTokenService
{
    public function mint(string $entryId, string $typeHandle, int $ttlSeconds = 3600): string
    {
        $payload = json_encode([
            'entry_id' => $entryId,
            'entry_type' => $typeHandle,
            'exp' => Carbon::now()->getTimestamp() + $ttlSeconds,
        ]);

        if ($payload === false) {
            throw new DeliveryException('Failed to encode preview token payload.');
        }

        $encoded = base64_encode($payload);
        $key = Config::string('app.key');
        $sig = hash_hmac('sha256', $encoded, $key);

        return $encoded.'.'.$sig;
    }

    public function validate(string $token, string $expectedEntryId, string $expectedType): bool
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return false;
        }

        [$encoded, $sig] = $parts;
        $key = Config::string('app.key');
        $expected = hash_hmac('sha256', $encoded, $key);

        if (! hash_equals($expected, $sig)) {
            return false;
        }

        $decoded = base64_decode($encoded, strict: true);
        if ($decoded === false) {
            return false;
        }

        $payload = json_decode($decoded, true);
        if (! is_array($payload)) {
            return false;
        }

        $exp = $payload['exp'] ?? null;
        if (! is_int($exp) || $exp < Carbon::now()->getTimestamp()) {
            return false;
        }

        return $payload['entry_id'] === $expectedEntryId
            && $payload['entry_type'] === $expectedType;
    }
}
