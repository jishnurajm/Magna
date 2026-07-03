<?php

declare(strict_types=1);

namespace Magna\Plugins;

use Illuminate\Contracts\Foundation\Application;

/**
 * Base class every Magna plugin entry class must extend.
 *
 * Lifecycle (managed by PluginManager):
 *   - register() — bind services; called once per request bootstrap, before boot()
 *   - boot()     — load routes, listeners, etc.; called after all plugins have registered
 *   - enable()   — one-time: called when an admin enables the plugin
 *   - disable()  — one-time: called when an admin disables the plugin
 */
abstract class Plugin
{
    public function __construct(
        protected readonly Application $app,
        protected readonly string $basePath,
        protected readonly Manifest $manifest,
    ) {}

    public function register(): void {}

    public function boot(): void {}

    public function enable(): void {}

    public function disable(): void {}

    public function getManifest(): Manifest
    {
        return $this->manifest;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    protected function routesPath(string $file = 'api.php'): string
    {
        return $this->basePath.'/routes/'.$file;
    }

    protected function migrationsPath(): string
    {
        return $this->basePath.'/database/migrations';
    }
}
