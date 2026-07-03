<?php

declare(strict_types=1);

namespace Magna\Plugins;

use Magna\Plugins\Exceptions\InvalidManifestException;

final class ManifestValidator
{
    private const REQUIRED_FIELDS = ['name', 'displayName', 'description', 'version', 'author', 'license', 'compat', 'entry', 'permissions'];

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidManifestException
     */
    public static function validate(array $data): void
    {
        foreach (self::REQUIRED_FIELDS as $field) {
            if (! array_key_exists($field, $data)) {
                throw new InvalidManifestException("Missing required field in magna.json: {$field}");
            }
        }

        $name = $data['name'];
        if (! is_string($name) || ! preg_match('/^[a-z0-9]([a-z0-9_-]*[a-z0-9])?\/[a-z0-9]([a-z0-9_-]*[a-z0-9])?$/', $name)) {
            throw new InvalidManifestException('The "name" field must be in vendor/package format (lowercase, hyphens/underscores allowed).');
        }

        $compat = $data['compat'];
        if (! is_array($compat)) {
            throw new InvalidManifestException('The "compat" field must be an object.');
        }

        if (! isset($compat['magna']) || ! is_string($compat['magna'])) {
            throw new InvalidManifestException('The "compat.magna" field is required and must be a string semver constraint.');
        }

        $permissions = $data['permissions'];
        if (! is_array($permissions)) {
            throw new InvalidManifestException('The "permissions" field must be an array.');
        }

        foreach ($permissions as $key => $perm) {
            if (! is_string($perm)) {
                throw new InvalidManifestException("Permission at index {$key} must be a string.");
            }
            if (! preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)+$/', $perm)) {
                throw new InvalidManifestException("Permission \"{$perm}\" must be a dot-separated key (e.g. \"plugin.resource.action\").");
            }
        }

        if (! is_string($data['entry'])) {
            throw new InvalidManifestException('The "entry" field must be a fully-qualified class name string.');
        }

        $version = $data['version'];
        if (! is_string($version) || ! preg_match('/^\d+\.\d+\.\d+/', $version)) {
            throw new InvalidManifestException('The "version" field must be a semver version string (e.g. "1.2.0").');
        }

        if (isset($data['uninstall'])) {
            $uninstall = $data['uninstall'];
            if (! is_array($uninstall)) {
                throw new InvalidManifestException('The "uninstall" field must be an object when present.');
            }
        }
    }
}
