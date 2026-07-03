<?php

declare(strict_types=1);

namespace Magna\Plugins;

use JsonException;
use Magna\Plugins\Exceptions\InvalidManifestException;

/**
 * Locates Magna plugins from two sources:
 *   1. vendor/composer/installed.json — packages with "type": "magna-plugin"
 *   2. plugins-dev/{vendor}/{package}/magna.json — local development path repositories
 */
final class PluginDiscovery
{
    public function __construct(private readonly string $basePath) {}

    /**
     * @return list<PluginInfo>
     */
    public function discover(): array
    {
        $vendor = $this->discoverFromVendor();
        $dev = $this->discoverFromDev();

        // Merge; plugins-dev/ takes precedence over vendor (same name wins for dev).
        $byName = [];
        foreach ([...$vendor, ...$dev] as $info) {
            $byName[$info->manifest->name] = $info;
        }

        return array_values($byName);
    }

    public function find(string $name): ?PluginInfo
    {
        foreach ($this->discover() as $info) {
            if ($info->manifest->name === $name) {
                return $info;
            }
        }

        return null;
    }

    /**
     * @return list<PluginInfo>
     */
    private function discoverFromVendor(): array
    {
        $installedJson = $this->basePath.'/vendor/composer/installed.json';
        if (! file_exists($installedJson)) {
            return [];
        }

        $raw = file_get_contents($installedJson);
        if ($raw === false) {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        $decoded = is_array($decoded) ? $decoded : [];
        $packagesNode = $decoded['packages'] ?? $decoded;
        $packages = is_array($packagesNode) ? $packagesNode : [];

        $result = [];
        foreach ($packages as $package) {
            if (! is_array($package)) {
                continue;
            }
            if (($package['type'] ?? '') !== 'magna-plugin') {
                continue;
            }

            $pkgName = is_string($package['name'] ?? null) ? $package['name'] : null;
            if ($pkgName === null) {
                continue;
            }

            $pkgPath = $this->basePath.'/vendor/'.$pkgName;
            $manifestPath = $pkgPath.'/magna.json';
            if (! file_exists($manifestPath)) {
                continue;
            }

            try {
                $info = $this->loadFrom($manifestPath, $pkgPath);
                $result[] = $info;
            } catch (InvalidManifestException) {
                // Silently skip malformed manifests during discovery.
            }
        }

        return $result;
    }

    /**
     * @return list<PluginInfo>
     */
    private function discoverFromDev(): array
    {
        $devDir = $this->basePath.'/plugins-dev';
        if (! is_dir($devDir)) {
            return [];
        }

        $result = [];
        foreach (glob($devDir.'/*/*/magna.json') ?: [] as $manifestPath) {
            $pkgPath = dirname($manifestPath);
            try {
                $result[] = $this->loadFrom($manifestPath, $pkgPath);
            } catch (InvalidManifestException) {
                // Skip malformed manifests during discovery.
            }
        }

        return $result;
    }

    /**
     * @throws InvalidManifestException
     */
    private function loadFrom(string $manifestPath, string $pkgPath): PluginInfo
    {
        $manifest = Manifest::loadFromFile($manifestPath);

        return new PluginInfo($manifest, realpath($pkgPath) ?: $pkgPath);
    }
}
