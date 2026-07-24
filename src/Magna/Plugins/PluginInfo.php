<?php

declare(strict_types=1);

namespace Magna\Plugins;

/**
 * Bundles a validated Manifest with the absolute path to the plugin's root directory.
 */
final class PluginInfo
{
    public function __construct(
        public readonly Manifest $manifest,
        public readonly string $basePath,
    ) {}
}
