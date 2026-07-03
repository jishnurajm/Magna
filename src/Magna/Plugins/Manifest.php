<?php

declare(strict_types=1);

namespace Magna\Plugins;

use Composer\Semver\Semver;
use Magna\Plugins\Exceptions\InvalidManifestException;

/**
 * Immutable value object representing a validated magna.json manifest.
 */
final class Manifest
{
    /**
     * @param  list<string>  $permissions
     * @param  array<string, mixed>  $provides
     * @param  array<mixed, mixed>|null  $uninstall
     */
    public function __construct(
        public readonly string $name,
        public readonly string $displayName,
        public readonly string $description,
        public readonly string $version,
        public readonly string $author,
        public readonly string $license,
        public readonly string $magnaCompat,
        public readonly string $phpCompat,
        public readonly string $entryClass,
        public readonly array $provides,
        public readonly array $permissions,
        public readonly ?array $uninstall,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        ManifestValidator::validate($data);

        $compat = $data['compat'];
        $compatMagna = is_array($compat) && is_string($compat['magna'] ?? null) ? (string) $compat['magna'] : '^1.0';
        $compatPhp = is_array($compat) && is_string($compat['php'] ?? null) ? (string) $compat['php'] : '^8.3';

        $permissions = is_array($data['permissions'])
            ? array_values(array_filter($data['permissions'], 'is_string'))
            : [];

        $uninstall = isset($data['uninstall']) && is_array($data['uninstall']) ? $data['uninstall'] : null;
        $provides = isset($data['provides']) && is_array($data['provides']) ? $data['provides'] : [];

        return new self(
            name: self::requireString($data, 'name'),
            displayName: self::requireString($data, 'displayName'),
            description: self::requireString($data, 'description'),
            version: self::requireString($data, 'version'),
            author: self::requireString($data, 'author'),
            license: self::requireString($data, 'license'),
            magnaCompat: $compatMagna,
            phpCompat: $compatPhp,
            entryClass: self::requireString($data, 'entry'),
            provides: $provides,
            permissions: $permissions,
            uninstall: $uninstall,
        );
    }

    public function isCompatibleWith(string $coreVersion): bool
    {
        return Semver::satisfies($coreVersion, $this->magnaCompat);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'displayName' => $this->displayName,
            'description' => $this->description,
            'version' => $this->version,
            'author' => $this->author,
            'license' => $this->license,
            'compat' => ['magna' => $this->magnaCompat, 'php' => $this->phpCompat],
            'entry' => $this->entryClass,
            'provides' => $this->provides,
            'permissions' => $this->permissions,
            'uninstall' => $this->uninstall,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidManifestException
     */
    private static function requireString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (! is_string($value)) {
            throw new InvalidManifestException("Field \"{$key}\" must be a string.");
        }

        return $value;
    }

    /**
     * @throws InvalidManifestException
     */
    public static function loadFromFile(string $path): self
    {
        if (! file_exists($path)) {
            throw new InvalidManifestException("Manifest file not found: {$path}");
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new InvalidManifestException("Cannot read manifest: {$path}");
        }

        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($data)) {
            throw new InvalidManifestException("Manifest is not a JSON object: {$path}");
        }

        /** @var array<string, mixed> $data */
        return self::fromArray($data);
    }
}
