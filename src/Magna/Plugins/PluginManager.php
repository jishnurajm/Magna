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
use Magna\Content\Models\ContentTypeRecord;
use Magna\Content\SchemaRegistry;
use Magna\Content\SchemaSyncer;
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
        $this->syncPluginSchemas($plugin);
        $this->persistPluginContentTypes($plugin);

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

        // A disabled plugin must not leave its content types active — otherwise
        // SchemaRegistry::loadFromDatabase() keeps re-registering them and their
        // admin navigation lingers. Data tables are preserved (disable never
        // destroys data); re-enable restores the content_types records.
        $this->deregisterContentTypes($record, dropTables: false);

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

        // Remove the plugin's content types. Without --purge the entries data
        // tables are kept (data preserved); with --purge they are dropped along
        // with any tables the manifest declares.
        $this->deregisterContentTypes($record, dropTables: $purge);

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

    /**
     * Remove the content types a plugin owns from the registry and the
     * content_types table so their navigation and resources disappear.
     * When $dropTables is true, the physical magna_entries_* tables are
     * dropped too (destructive — purge only).
     */
    private function deregisterContentTypes(PluginRecord $record, bool $dropTables): void
    {
        if (! Schema::hasTable('content_types')) {
            return;
        }

        $registry = $this->app->bound(SchemaRegistry::class)
            ? $this->app->make(SchemaRegistry::class)
            : null;

        foreach ($this->ownedContentTypeHandles($record) as $handle) {
            ContentTypeRecord::query()->where('handle', $handle)->delete();
            $registry?->forget($handle);

            if ($dropTables) {
                Schema::dropIfExists('magna_entries_'.$handle);
            }
        }
    }

    /**
     * Re-assert content_types records for a plugin's types on enable. Needed
     * because SchemaSyncer skips the upsert when the physical table already
     * exists (e.g. re-enabling after a non-purge uninstall), which would
     * otherwise leave the record missing.
     */
    private function persistPluginContentTypes(Plugin $plugin): void
    {
        if (! Schema::hasTable('content_types') || ! $this->app->bound(SchemaRegistry::class)) {
            return;
        }

        /** @var SchemaRegistry $registry */
        $registry = $this->app->make(SchemaRegistry::class);

        $handles = $this->ownedContentTypeHandles(
            $plugin->getBasePath(),
            $plugin->getManifest()->toArray(),
        );

        foreach ($handles as $handle) {
            $type = $registry->get($handle);
            if ($type === null) {
                continue;
            }

            ContentTypeRecord::updateOrCreate(
                ['handle' => $type->handle],
                [
                    'display_name' => $type->displayName,
                    'is_database_defined' => false,
                    'schema' => $type->toArray(),
                ],
            );
        }
    }

    /**
     * The content type handles a plugin owns. Authoritative source is the
     * plugin's schemas/ directory; the manifest's provides.contentTypes and
     * uninstall.contentTypes are merged in as a fallback so a plugin can list
     * types it registers programmatically rather than via schema files.
     *
     * @param  array<string, mixed>|null  $manifest
     * @return list<string>
     */
    private function ownedContentTypeHandles(PluginRecord|string $recordOrBasePath, ?array $manifest = null): array
    {
        if ($recordOrBasePath instanceof PluginRecord) {
            $basePath = $recordOrBasePath->base_path;
            $manifest = $recordOrBasePath->manifest;
        } else {
            $basePath = $recordOrBasePath;
        }

        $handles = [];

        foreach (glob($basePath.'/schemas/*.json') ?: [] as $file) {
            $decoded = json_decode((string) file_get_contents($file), true);
            if (is_array($decoded) && isset($decoded['handle']) && is_string($decoded['handle'])) {
                $handles[] = $decoded['handle'];
            }
        }

        $manifest ??= [];
        $provides = $manifest['provides'] ?? [];
        if (is_array($provides) && is_array($provides['contentTypes'] ?? null)) {
            foreach ($provides['contentTypes'] as $handle) {
                if (is_string($handle)) {
                    $handles[] = $handle;
                }
            }
        }

        $uninstall = $manifest['uninstall'] ?? [];
        if (is_array($uninstall) && is_array($uninstall['contentTypes'] ?? null)) {
            foreach ($uninstall['contentTypes'] as $handle) {
                if (is_string($handle)) {
                    $handles[] = $handle;
                }
            }
        }

        return array_values(array_unique($handles));
    }

    /**
     * Create (or update) the magna_entries_* table for every content type that
     * the plugin declares in its schemas/ directory. Called after enable() loads
     * those schemas into the SchemaRegistry so SchemaSyncer sees them.
     */
    private function syncPluginSchemas(Plugin $plugin): void
    {
        if (! is_dir($plugin->getBasePath().'/schemas')) {
            return;
        }

        if (! $this->app->bound(SchemaSyncer::class)) {
            return;
        }

        /** @var SchemaRegistry $registry */
        $registry = $this->app->make(SchemaRegistry::class);
        /** @var SchemaSyncer $syncer */
        $syncer = $this->app->make(SchemaSyncer::class);
        $syncer->syncAll($registry, allowDestructive: false);
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

        // Load plugin content type schemas from schemas/ directory.
        $schemasDir = $plugin->getBasePath().'/schemas';
        if (is_dir($schemasDir)) {
            /** @var SchemaRegistry $schemaRegistry */
            $schemaRegistry = $this->app->make(SchemaRegistry::class);
            $schemaRegistry->loadFromDirectory($schemasDir);
        }

        // TODO Stage 10: RegistersDashboardWidgets
        // TODO Stage 10: RegistersSettingsPages
        // TODO Stage 11: RegistersBlocks
        // TODO Stage 10: ExtendsEntryForm
        // TODO Stage 8:  FiltersApiQuery
        // TODO Stage 9:  RegistersWebhookEvents
    }
}
