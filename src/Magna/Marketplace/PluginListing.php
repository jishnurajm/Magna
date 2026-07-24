<?php

declare(strict_types=1);

namespace Magna\Marketplace;

use Composer\Semver\Semver;

/**
 * An immutable catalog entry returned by the marketplace API. Mirrors the
 * PluginSummary shape in the v1 API contract.
 */
final class PluginListing
{
    /**
     * @param  list<string>  $categories
     * @param  list<string>  $permissions
     */
    public function __construct(
        public readonly string $package,
        public readonly string $name,
        public readonly string $shortDescription,
        public readonly string $version,
        public readonly string $compat,
        public readonly ?string $icon = null,
        public readonly array $categories = [],
        public readonly ?string $author = null,
        public readonly ?int $installs = null,
        public readonly array $permissions = [],
        public readonly ?float $rating = null,
        public readonly int $ratingsCount = 0,
        public readonly ?string $website = null,
    ) {}

    /**
     * Build a listing from a raw API entry. Returns null when required fields
     * are missing or malformed, so callers can skip bad catalog rows safely.
     *
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        $package = $data['package'] ?? null;
        $name = $data['name'] ?? null;
        $version = $data['version'] ?? null;
        $compat = $data['compat'] ?? null;

        if (! is_string($package) || ! is_string($name) || ! is_string($version) || ! is_string($compat)) {
            return null;
        }

        // Package must be a valid vendor/package identifier — this is the value
        // that would later be handed to `composer require`, so validate it hard.
        if (! preg_match('#^[a-z0-9]([a-z0-9_-]*[a-z0-9])?/[a-z0-9]([a-z0-9_-]*[a-z0-9])?$#', $package)) {
            return null;
        }

        $categories = [];
        if (is_array($data['categories'] ?? null)) {
            $categories = array_values(array_filter($data['categories'], 'is_string'));
        }

        $permissions = [];
        if (is_array($data['permissions'] ?? null)) {
            $permissions = array_values(array_filter($data['permissions'], 'is_string'));
        }

        $rating = $data['rating'] ?? null;

        return new self(
            package: $package,
            name: $name,
            shortDescription: is_string($data['shortDescription'] ?? null) ? $data['shortDescription'] : '',
            version: $version,
            compat: $compat,
            icon: is_string($data['icon'] ?? null) ? $data['icon'] : null,
            categories: $categories,
            author: is_string($data['author'] ?? null) ? $data['author'] : null,
            installs: is_int($data['installs'] ?? null) ? $data['installs'] : null,
            permissions: $permissions,
            rating: is_int($rating) || is_float($rating) ? (float) $rating : null,
            ratingsCount: is_int($data['ratingsCount'] ?? null) ? $data['ratingsCount'] : 0,
            website: is_string($data['website'] ?? null) ? $data['website'] : null,
        );
    }

    /** Whether this plugin's `compat` constraint is satisfied by the given core version. */
    public function isCompatibleWith(string $coreVersion): bool
    {
        try {
            return Semver::satisfies($coreVersion, $this->compat);
        } catch (\Throwable) {
            return false;
        }
    }
}
