<?php

declare(strict_types=1);

namespace Magna\Auth;

use Illuminate\Support\Carbon;
use Magna\Audit\AuditLog;
use Magna\Users\User;

class ApiKeyService
{
    /**
     * Generate a new key+secret pair, persist the key (with hashed secret),
     * and return the plaintext credentials for one-time display.
     *
     * @param  array{
     *   name: string,
     *   scope: string,
     *   rate_limit_per_minute?: int|null,
     *   expires_at?: Carbon|string|null,
     * }  $data
     * @return array{key: string, secret: string, record: ApiKey}
     */
    public function generate(array $data, User $creator): array
    {
        $scope = $data['scope'];
        $prefix = $scope === 'management' ? 'mag_mgt_' : 'mag_del_';

        // 128-bit public key, prefixed for at-a-glance scope identification
        $key = $prefix.bin2hex(random_bytes(16));

        // 256-bit secret — shown once, then hashed
        $secret = bin2hex(random_bytes(32));

        $expiresAt = isset($data['expires_at']) && $data['expires_at']
            ? Carbon::parse($data['expires_at'])
            : null;

        /** @var string|int $creatorKey */
        $creatorKey = $creator->getKey();

        $record = ApiKey::create([
            'name' => $data['name'],
            'key' => $key,
            'secret_hash' => hash('sha256', $secret),
            'scope' => $scope,
            'rate_limit_per_minute' => $data['rate_limit_per_minute'] ?? null,
            'expires_at' => $expiresAt,
            'is_active' => true,
            'created_by_id' => (string) $creatorKey,
        ]);

        AuditLog::record(
            action: 'api_keys.created',
            actorId: (string) $creatorKey,
            actorType: 'user',
            after: ['name' => $data['name'], 'scope' => $scope, 'key_prefix' => substr($key, 0, 12).'...'],
        );

        return ['key' => $key, 'secret' => $secret, 'record' => $record];
    }

    /**
     * Validate a key+secret pair from an inbound request.
     * Returns the matching ApiKey on success, null on failure.
     */
    public function verify(string $key, string $secret): ?ApiKey
    {
        $record = ApiKey::where('key', $key)
            ->where('is_active', true)
            ->first();

        if ($record === null) {
            return null;
        }

        if ($record->isExpired()) {
            return null;
        }

        if (! $record->verifySecret($secret)) {
            return null;
        }

        return $record;
    }

    /**
     * Revoke (soft-disable) a key and log the action.
     */
    public function revoke(ApiKey $record, User $actor): void
    {
        $record->update(['is_active' => false]);

        /** @var string|int $actorKey */
        $actorKey = $actor->getKey();

        AuditLog::record(
            action: 'api_keys.revoked',
            actorId: (string) $actorKey,
            actorType: 'user',
            before: ['name' => $record->name, 'key_prefix' => substr($record->key, 0, 12).'...'],
        );
    }
}
