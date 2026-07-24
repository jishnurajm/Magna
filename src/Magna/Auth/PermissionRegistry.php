<?php

declare(strict_types=1);

namespace Magna\Auth;

use InvalidArgumentException;

/**
 * Central in-memory registry of permission keys.
 *
 * Permissions are string keys registered in code at boot time (by the core
 * and by enabled plugins) — never database rows. Roles store *grants*
 * against these keys; grants may contain wildcards, registered keys may not.
 */
final class PermissionRegistry
{
    /**
     * Registered keys mapped to their human-readable descriptions.
     *
     * @var array<string, string|null>
     */
    private array $permissions = [];

    public function register(string $key, ?string $description = null): void
    {
        if (preg_match('/^[a-z0-9_-]+(\.[a-z0-9_-]+)+$/', $key) !== 1) {
            throw new InvalidArgumentException(
                "Invalid permission key [{$key}]: keys must be two or more lowercase dot-separated segments and may not contain wildcards.",
            );
        }

        $this->permissions[$key] = $description;
    }

    /**
     * Register several keys at once. Accepts a list of keys, a map of
     * key => description, or a mix of both.
     *
     * @param  array<int|string, string|null>  $permissions
     */
    public function registerMany(array $permissions): void
    {
        foreach ($permissions as $key => $description) {
            if (is_int($key)) {
                if (! is_string($description)) {
                    throw new InvalidArgumentException('List-style permission entries must be strings.');
                }

                $this->register($description);

                continue;
            }

            $this->register($key, $description);
        }
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->permissions);
    }

    /**
     * @return array<string, string|null>
     */
    public function all(): array
    {
        $permissions = $this->permissions;
        ksort($permissions);

        return $permissions;
    }
}
