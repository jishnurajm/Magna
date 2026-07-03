<?php

declare(strict_types=1);

namespace Magna\Plugins;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Magna\Auth\PermissionRegistry;
use Magna\Contracts\RegistersAdminNavigation;
use Magna\MagnaServiceProvider;
use Magna\Plugins\Exceptions\PluginCompatibilityException;
use Magna\Plugins\Exceptions\PluginNotFoundException;

class PluginManager
{
    /** @var array<string, Plugin> */
    private array $booted = [];

    public function __construct(
        private readonly Application $app,
        private readonly PluginDiscovery $discovery,
    ) {}

    /**
     * Called from PluginsServiceProvider::boot(). Loads all enabled plugins and
     * runs register() then boot() on each, registering their routes and permissions.
     */
    public function bootEnabledPlugins(): void
    {
        if (! Schema::hasTable('plugins')) {
            return;
        }

        /** @var Collection<int, PluginRecord> $records */
        $records = PluginRecord::query()->where('enabled', true)->get();

        // First pass — register() on every plugin (so container bindings are in place for boot()).
        foreach ($records as $record) {
            $plugin = $this->instantiate($record->manifest, $record->base_path);
            $plugin->register();
            $this->booted[$record->name] = $plugin;
        }

        // Second pass — boot() once all plugins have had a chance to register.
        foreach ($this->booted as $plugin) {
            $plugin->boot();
            $this->loadRoutes($plugin);
            $this->registerPermissions($plugin->getManifest());
        }

        // Dispatch typed contracts.
        $this->dispatchContracts();
    }

    /**
     * Validate, install, and enable a plugin.
     *
     * @throws PluginNotFoundException
     * @throws PluginCompatibilityException
     */
    public function enable(string $name): void
    {
        $info = $this->discovery->find($name);
        if ($info === null) {
            throw new PluginNotFoundException($name);
        }

        if (! $info->manifest->isCompatibleWith(MagnaServiceProvider::VERSION)) {
            throw new PluginCompatibilityException(
                "Plugin [{$name}] requires magna {$info->manifest->magnaCompat} "
                .'but the installed core is '.MagnaServiceProvider::VERSION.'.'
            );
        }

        $this->runMigrations($info->basePath);

        $record = PluginRecord::updateOrCreate(
            ['name' => $name],
            [
                'display_name' => $info->manifest->displayName,
                'version' => $info->manifest->version,
                'enabled' => true,
                'base_path' => $info->basePath,
                'enabled_at' => now(),
                'disabled_at' => null,
                'manifest' => $info->manifest->toArray(),
            ]
        );

        $plugin = $this->instantiate($record->manifest, $record->base_path);
        $plugin->register();
        $plugin->enable();
        $plugin->boot();
        $this->loadRoutes($plugin);
        $this->registerPermissions($info->manifest);
        $this->dispatchContractsFor($plugin);

        $this->booted[$name] = $plugin;
    }

    /**
     * Disable a plugin (data is preserved; no DB drops).
     *
     * @throws PluginNotFoundException
     */
    public function disable(string $name): void
    {
        /** @var PluginRecord|null $record */
        $record = PluginRecord::query()->where('name', $name)->first();
        if ($record === null) {
            throw new PluginNotFoundException($name);
        }

        if (isset($this->booted[$name])) {
            $this->booted[$name]->disable();
            unset($this->booted[$name]);
        } else {
            $plugin = $this->instantiate($record->manifest, $record->base_path);
            $plugin->disable();
        }

        $record->update(['enabled' => false, 'disabled_at' => now()]);
    }

    /**
     * Disable and remove a plugin's DB record. With --purge, also drops its declared tables.
     *
     * @throws PluginNotFoundException
     */
    public function uninstall(string $name, bool $purge = false): void
    {
        /** @var PluginRecord|null $record */
        $record = PluginRecord::query()->where('name', $name)->first();
        if ($record === null) {
            throw new PluginNotFoundException($name);
        }

        if ($record->enabled) {
            $this->disable($name);
        }

        if ($purge) {
            $this->purge($record);
        }

        $record->delete();
    }

    /**
     * @return array<string, Plugin>
     */
    public function getEnabled(): array
    {
        return $this->booted;
    }

    /**
     * @return list<PluginInfo>
     */
    public function discover(): array
    {
        return $this->discovery->discover();
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function instantiate(array $manifest, string $basePath): Plugin
    {
        $manifestObj = Manifest::fromArray($manifest);
        $class = $manifestObj->entryClass;

        /** @var Plugin */
        return $this->app->make($class, [
            'app' => $this->app,
            'basePath' => $basePath,
            'manifest' => $manifestObj,
        ]);
    }

    private function loadRoutes(Plugin $plugin): void
    {
        $routesFile = $plugin->getBasePath().'/routes/api.php';
        if (! file_exists($routesFile)) {
            return;
        }

        $slug = Arr::last(explode('/', $plugin->getManifest()->name));

        Route::middleware('api')
            ->prefix('api/v1/'.$slug)
            ->group($routesFile);
    }

    private function registerPermissions(Manifest $manifest): void
    {
        if ($manifest->permissions === []) {
            return;
        }

        /** @var PermissionRegistry $registry */
        $registry = $this->app->make(PermissionRegistry::class);
        foreach ($manifest->permissions as $permission) {
            $registry->register($permission);
        }
    }

    private function runMigrations(string $basePath): void
    {
        $migrationsPath = $basePath.'/database/migrations';
        if (! is_dir($migrationsPath)) {
            return;
        }

        Artisan::call('migrate', [
            '--path' => $migrationsPath,
            '--realpath' => true,
            '--force' => true,
        ]);
    }

    private function purge(PluginRecord $record): void
    {
        /** @var array<string, mixed> $manifest */
        $manifest = $record->manifest;
        $uninstall = $manifest['uninstall'] ?? null;
        if (! is_array($uninstall)) {
            return;
        }

        $tables = $uninstall['tables'] ?? [];
        if (is_array($tables)) {
            foreach ($tables as $table) {
                if (is_string($table)) {
                    Schema::dropIfExists($table);
                }
            }
        }
    }

    private function dispatchContracts(): void
    {
        foreach ($this->booted as $plugin) {
            $this->dispatchContractsFor($plugin);
        }
    }

    private function dispatchContractsFor(Plugin $plugin): void
    {
        if ($plugin instanceof RegistersAdminNavigation) {
            $this->app->instance(
                'magna.nav.'.$plugin->getManifest()->name,
                $plugin->adminNavigation(),
            );
        }

        // TODO Stage 10: RegistersDashboardWidgets
        // TODO Stage 10: RegistersSettingsPages
        // TODO Stage 11: RegistersBlocks
        // TODO Stage 10: ExtendsEntryForm
        // TODO Stage 8:  FiltersApiQuery
        // TODO Stage 9:  RegistersWebhookEvents
    }
}
