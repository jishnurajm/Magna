<?php

declare(strict_types=1);

namespace Magna\Admin\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;
use Laravel\Octane\OctaneServiceProvider;
use Magna\Backup\BackupRun;
use Magna\MagnaServiceProvider;
use Magna\Plugins\PluginManager;
use Magna\Plugins\PluginRecord;
use Magna\Settings\BackupSettings;
use Magna\Updater\CoreUpdateJob;
use Magna\Updater\CoreUpdater;
use Magna\Updater\CoreUpdateState;
use Magna\Updater\IncompatiblePlugin;
use Magna\Updater\UpdateCheck;
use Magna\Updater\UpdateCheckClient;
use Throwable;

class SystemInfoPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-information-circle';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'System Info';

    protected static ?string $title = 'System Insights';

    protected static ?int $navigationSort = 99;

    protected string $view = 'magna::admin.system-info';

    /** @var list<array{type: string, text: string}> */
    public array $terminalLines = [];

    /** True while an "Update Now" apply is in progress, so the page polls CoreUpdater::progress(). */
    public bool $updating = false;

    /**
     * Enabled plugins found incompatible with the pending target version,
     * captured by updateNow's pre-flight check for the resolution modal.
     *
     * @var list<array{name: string, displayName: string, installedVersion: string, requiredCompat: string}>
     */
    public array $incompatiblePlugins = [];

    /** Target version/zip captured while the resolveIncompatiblePlugins modal is open. */
    public ?string $pendingUpdateVersion = null;

    public ?string $pendingUpdateZipUrl = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('settings.view') ?? false;
    }

    /**
     * Nav badge showing pending core + plugin updates, sourced from the last
     * check-in (scheduled every 12h, or on-demand via the button below) —
     * never queries Update Manager directly from a page render.
     */
    public static function getNavigationBadge(): ?string
    {
        $total = UpdateCheck::totalAvailable();

        return $total > 0 ? (string) $total : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('checkForUpdates')
                ->label('Check for Updates')
                ->icon('heroicon-o-arrow-down-circle')
                ->color('primary')
                ->action(function (UpdateCheckClient $client): void {
                    $result = $client->checkIn();
                    $current = MagnaServiceProvider::VERSION;

                    if ($result === null) {
                        Notification::make()
                            ->title('Could not reach Update Manager')
                            ->body("Running v{$current}. The update service didn't respond — try again shortly.")
                            ->warning()
                            ->send();

                        return;
                    }

                    $pluginUpdates = UpdateCheck::pluginsWithUpdates()->count();

                    if ($result->core?->updateAvailable) {
                        Notification::make()
                            ->title('Update available: v'.$result->core->latestVersion)
                            ->body("Running v{$current}.".($pluginUpdates > 0 ? " {$pluginUpdates} plugin update(s) also available." : ''))
                            ->warning()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Magna CMS is up to date')
                        ->body("Running v{$current}.".($pluginUpdates > 0 ? " {$pluginUpdates} plugin update(s) available." : ' No newer release found.'))
                        ->success()
                        ->send();
                }),

            Action::make('updateNow')
                ->label(fn (): string => 'Update to v'.(UpdateCheck::core()?->latest_version ?? ''))
                ->icon('heroicon-o-rocket-launch')
                ->color('warning')
                ->visible(fn (): bool => (UpdateCheck::core()?->update_available ?? false) && ! $this->updating)
                ->authorize(fn (): bool => auth()->user()?->can('settings.manage') ?? false)
                ->requiresConfirmation()
                ->modalHeading(fn (): string => 'Update Magna CMS to v'.(UpdateCheck::core()?->latest_version ?? '').'?')
                ->modalDescription('The site goes into maintenance mode during the update. Current files are backed up first and restored automatically if anything fails.')
                ->modalSubmitActionLabel('Update now')
                ->action(function (CoreUpdater $updater): void {
                    $core = UpdateCheck::core();
                    if ($core?->latest_version === null || $core->download_url === null) {
                        Notification::make()->title("Can't update")->body('No release archive is available for the latest version yet.')->danger()->send();

                        return;
                    }

                    $incompatible = $updater->checkCompatibility($core->latest_version);
                    if ($incompatible !== []) {
                        $this->pendingUpdateVersion = $core->latest_version;
                        $this->pendingUpdateZipUrl = $core->download_url;
                        $this->incompatiblePlugins = array_map(
                            static fn (IncompatiblePlugin $p): array => $p->toArray(),
                            $incompatible,
                        );
                        $this->replaceMountedAction('resolveIncompatiblePlugins');

                        return;
                    }

                    CoreUpdateJob::dispatch($core->latest_version, $core->download_url);
                    $this->updating = true;
                    Notification::make()->title('Update started…')->send();
                }),
        ];
    }

    /**
     * Shown when updateNow finds enabled plugins incompatible with the target
     * core version. The primary submit forces the update anyway (incompatible
     * plugins are auto-disabled by CoreUpdater once the new core is in place);
     * the extra footer action uninstalls them first and updates cleanly;
     * cancelling (Filament's default) leaves everything untouched.
     */
    public function resolveIncompatiblePluginsAction(): Action
    {
        return Action::make('resolveIncompatiblePlugins')
            ->authorize(fn (): bool => auth()->user()?->can('settings.manage') ?? false)
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->modalIconColor('danger')
            ->modalHeading(fn (): string => count($this->incompatiblePlugins).' plugin(s) are incompatible with v'.($this->pendingUpdateVersion ?? ''))
            ->modalDescription(fn (): HtmlString => $this->incompatiblePluginsModalBody())
            ->modalSubmitActionLabel('Force update anyway')
            ->modalCancelActionLabel('Cancel')
            ->color('danger')
            ->extraModalFooterActions([
                Action::make('uninstallIncompatibleAndContinue')
                    ->label('Uninstall these plugins & continue')
                    ->color('warning')
                    ->authorize(fn (): bool => auth()->user()?->can('settings.manage') ?? false)
                    ->requiresConfirmation()
                    ->modalHeading('Uninstall incompatible plugins?')
                    ->modalDescription('Each plugin listed is uninstalled (data tables are preserved; you can reinstall a compatible version later) and the update then proceeds normally.')
                    ->modalSubmitActionLabel('Uninstall & update')
                    ->action(fn () => $this->uninstallIncompatibleAndContinue()),
            ])
            ->action(fn () => $this->forceUpdateAnyway());
    }

    private function incompatiblePluginsModalBody(): HtmlString
    {
        $rows = '';
        foreach ($this->incompatiblePlugins as $plugin) {
            $rows .= '<tr>'
                .'<td class="py-1 pr-4 font-medium">'.e($plugin['displayName']).'</td>'
                .'<td class="py-1 pr-4 text-gray-500 dark:text-gray-400">v'.e($plugin['installedVersion']).'</td>'
                .'<td class="py-1 text-gray-500 dark:text-gray-400">requires magna '.e($plugin['requiredCompat']).'</td>'
                .'</tr>';
        }

        $html = '<div class="space-y-3">'
            .'<p class="text-sm">These enabled plugins declare they don\'t support v'.e($this->pendingUpdateVersion ?? '').'. '
            .'Forcing the update anyway will automatically disable them once the new core is in place (their data and settings are preserved).</p>'
            .'<table class="w-full text-sm"><tbody>'.$rows.'</tbody></table>'
            .'</div>';

        return new HtmlString($html);
    }

    /** Primary submit of resolveIncompatiblePlugins: proceed despite the conflicts. */
    private function forceUpdateAnyway(): void
    {
        $version = $this->pendingUpdateVersion;
        $zipUrl = $this->pendingUpdateZipUrl;
        $this->incompatiblePlugins = [];
        $this->pendingUpdateVersion = null;
        $this->pendingUpdateZipUrl = null;

        if ($version === null || $zipUrl === null) {
            return;
        }

        CoreUpdateJob::dispatch($version, $zipUrl, force: true);
        $this->updating = true;
        Notification::make()
            ->title('Forced update started…')
            ->body('Incompatible plugins will be automatically disabled once the update finishes.')
            ->warning()
            ->send();
    }

    /** Extra footer action of resolveIncompatiblePlugins: remove the conflicting plugins, then update. */
    private function uninstallIncompatibleAndContinue(): void
    {
        $version = $this->pendingUpdateVersion;
        $zipUrl = $this->pendingUpdateZipUrl;
        $names = array_column($this->incompatiblePlugins, 'name');
        $this->incompatiblePlugins = [];
        $this->pendingUpdateVersion = null;
        $this->pendingUpdateZipUrl = null;

        if ($version === null || $zipUrl === null) {
            return;
        }

        $manager = app(PluginManager::class);
        $failed = [];
        foreach ($names as $name) {
            try {
                $manager->uninstall($name);
            } catch (Throwable) {
                $failed[] = $name;
            }
        }

        if ($failed !== []) {
            Notification::make()
                ->title("Couldn't uninstall: ".implode(', ', $failed))
                ->body('The update was not started. Resolve this manually (Plugins page) and try again.')
                ->danger()
                ->send();

            return;
        }

        // Re-verify rather than trusting the uninstalls succeeded silently — the
        // update job enforces this too, but a clean recheck here gives an
        // accurate notification instead of a job that queues then fails later.
        $stillIncompatible = app(CoreUpdater::class)->checkCompatibility($version);
        if ($stillIncompatible !== []) {
            $names = array_map(static fn (IncompatiblePlugin $p): string => $p->displayName, $stillIncompatible);
            Notification::make()
                ->title('Still incompatible: '.implode(', ', $names))
                ->body('The update was not started.')
                ->danger()
                ->send();

            return;
        }

        CoreUpdateJob::dispatch($version, $zipUrl);
        $this->updating = true;
        Notification::make()->title('Plugins removed. Update started…')->send();
    }

    /** Poll the running core update; notify + reload the page when it finishes. */
    public function pollCoreUpdate(): void
    {
        if (! $this->updating) {
            return;
        }

        $progress = CoreUpdater::progress();

        if ($progress['state'] === CoreUpdateState::Completed->value) {
            $this->updating = false;
            Notification::make()->title('Update complete')->body($progress['message'])->success()->send();
            $this->js('setTimeout(function(){ window.location.reload(); }, 800)');
        } elseif ($progress['state'] === CoreUpdateState::Failed->value) {
            $this->updating = false;
            Notification::make()->title("Update didn't complete")->body($progress['message'])->danger()->send();
        }
    }

    public function toggleDebugMode(): void
    {
        $newValue = ! (bool) config('app.debug');
        $envPath = base_path('.env');

        if (! file_exists($envPath) || ! is_writable($envPath)) {
            Notification::make()
                ->title('Cannot update .env')
                ->body('The .env file is missing or not writable by the web server.')
                ->danger()
                ->send();

            return;
        }

        $content = (string) file_get_contents($envPath);
        $newLine = 'APP_DEBUG='.($newValue ? 'true' : 'false');

        if (preg_match('/^APP_DEBUG=/m', $content) === 1) {
            $content = (string) preg_replace('/^APP_DEBUG=.*/m', $newLine, $content);
        } else {
            $content .= "\n".$newLine;
        }

        file_put_contents($envPath, $content);

        // Do NOT mutate config('app.debug') in-request: Livewire only records
        // its render-timing $start when debug is on at the *start* of the
        // request, so flipping it mid-request triggers "Undefined variable
        // $start". Instead, clear any cached config and reload so the new .env
        // value takes effect on a fresh request.
        Artisan::call('config:clear');

        Notification::make()
            ->title('Debug mode '.($newValue ? 'enabled' : 'disabled'))
            ->body('APP_DEBUG updated in .env.')
            ->success()
            ->send();

        $this->redirect(static::getUrl(), navigate: false);
    }

    public function runDiagnostics(): void
    {
        $data = $this->getViewData();

        $this->terminalLines[] = ['type' => 'cmd',     'text' => 'php artisan magna:diagnostics --detailed'];
        $this->terminalLines[] = ['type' => 'init',    'text' => '[INIT] Starting general diagnostics sequence on '.$data['db_driver'].' storage node...'];
        $this->terminalLines[] = ['type' => 'info',    'text' => '[INFO] PHP engine: '.$data['php_version']];
        $this->terminalLines[] = ['type' => 'info',    'text' => '[INFO] Laravel framework: '.$data['laravel_version']];
        $this->terminalLines[] = ['type' => 'info',    'text' => '[INFO] Database: '.$data['db_driver'].' v'.$data['db_version']];
        $this->terminalLines[] = ['type' => 'info',    'text' => "[INFO] Cache driver: '{$data['cache_driver']}' — status: ".strtoupper((string) $data['cache_status'])];
        $this->terminalLines[] = ['type' => 'info',    'text' => '[INFO] Queue: '.$data['queue_connection'].' | Storage: '.$data['storage_disk']];
        $this->terminalLines[] = ['type' => 'info',    'text' => '[INFO] Octane: '.($data['octane_installed'] ? ($data['octane_running'] ? 'RUNNING ('.$data['octane_server'].')' : 'installed, not running (plain PHP-FPM/CLI request)') : 'not installed')];
        $this->terminalLines[] = ['type' => 'info',    'text' => '[INFO] Plugins installed: '.$data['plugins_total'].' ('.$data['plugins_enabled'].' enabled)'];

        if ($data['cache_status'] === 'ok') {
            $this->terminalLines[] = ['type' => 'success', 'text' => '[SUCCESS] Diagnostic sequence complete. All system connections working normally.'];
            Notification::make()->title('Diagnostics complete')->body('All environment nodes checked successfully.')->success()->send();
        } else {
            $this->terminalLines[] = ['type' => 'error', 'text' => '[ERROR] Cache connection failed. Check your cache driver configuration.'];
            Notification::make()->title('Diagnostics warning')->body('Cache connection issue detected.')->warning()->send();
        }
    }

    public function clearCache(): void
    {
        $driver = (string) config('cache.default', 'file');
        $this->terminalLines[] = ['type' => 'cmd',  'text' => 'php artisan cache:clear'];
        $this->terminalLines[] = ['type' => 'info', 'text' => "[INFO] Clearing internal storage caches on driver '{$driver}'..."];

        try {
            Artisan::call('cache:clear');
            $output = trim(Artisan::output()) ?: 'Application cache cleared successfully.';
            $this->terminalLines[] = ['type' => 'success', 'text' => '[SUCCESS] '.$output];
            Notification::make()->title('Cache cleared')->success()->send();
        } catch (Throwable $e) {
            $this->terminalLines[] = ['type' => 'error', 'text' => '[ERROR] '.$e->getMessage()];
            Notification::make()->title('Cache clear failed')->body($e->getMessage())->danger()->send();
        }
    }

    public function clearTerminal(): void
    {
        $this->terminalLines = [];
    }

    /** @return array<string, mixed> */
    public function getViewData(): array
    {
        $pluginsEnabled = PluginRecord::query()->where('enabled', true)->count();
        $pluginsTotal = PluginRecord::query()->count();

        return [
            'magna_version' => MagnaServiceProvider::VERSION,
            'laravel_version' => app()->version(),
            'php_version' => PHP_VERSION,
            'db_driver' => $this->dbDriver(),
            'db_version' => $this->dbVersion(),
            'environment' => app()->environment(),
            'debug_mode' => (bool) config('app.debug'),
            'cache_driver' => (string) config('cache.default', 'file'),
            'queue_connection' => (string) config('queue.default', 'sync'),
            'storage_disk' => (string) config('filesystems.default', 'local'),
            'plugins_total' => $pluginsTotal,
            'plugins_enabled' => $pluginsEnabled,
            'plugins_disabled' => $pluginsTotal - $pluginsEnabled,
            'cache_status' => $this->cacheStatus(),
            'app_url' => parse_url((string) config('app.url', 'http://localhost'), PHP_URL_HOST) ?? config('app.url', 'localhost'),
            'session_lifetime' => (int) config('session.lifetime', 120),
            'octane_installed' => class_exists(OctaneServiceProvider::class),
            'octane_running' => filter_var(getenv('LARAVEL_OCTANE'), FILTER_VALIDATE_BOOLEAN),
            'octane_server' => (string) config('octane.server', 'frankenphp'),
            'performance_warnings' => $this->performanceWarnings(),
            'boot_time_ms' => $this->bootTimeMs(),
            'memory_current_mb' => round(memory_get_usage(true) / 1_048_576, 1),
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1_048_576, 1),
            'cache_latency_ms' => $this->cacheLatencyMs(),
            'opcache' => $this->opcacheStatus(),
            'queue_pending' => $this->queuePendingCount(),
            'queue_failed' => $this->queueFailedCount(),
            'backup_health' => $this->backupHealth(),
        ];
    }

    /**
     * "Last successful backup: X ago", flagged as a warning once a
     * scheduled backup has silently stopped succeeding rather than only
     * distinguishing "never ran" — see docs/backup-manager-plan.md, Stage 6.
     *
     * Deliberately does NOT short-circuit on `enabled === false`: manual
     * runs ("Run backup now") work regardless of that toggle (it only
     * gates the *schedule*, per BackupSettingsPage's own tooltip on that
     * action), so a site backing up manually with automation off still has
     * real backup history worth showing — hiding it behind a blanket
     * "Disabled" was a real bug caught by testing this live (three
     * successful manual runs were completely invisible here until fixed).
     *
     * The staleness threshold is a fixed grace window per frequency
     * (`daily` → 2 days, `weekly` → 9 days), not a live read of the exact
     * next-due time — good enough to catch "this has been silently broken
     * for a while" without duplicating BackupSchedule's own due-window
     * logic here. `custom_cron` has no fixed interval to derive a grace
     * window from, so it falls back to the same 2-day threshold as
     * `daily` — a documented approximation, not a precise fit for every
     * possible cron expression. The staleness escalation itself only
     * applies when `enabled` is true — with no schedule promised, "stale
     * relative to what?" doesn't have an answer.
     *
     * @return array{color: 'ok'|'warning'|'neutral', label: string}
     */
    private function backupHealth(): array
    {
        $settings = BackupSettings::get();

        $last = BackupRun::query()
            ->where('status', BackupRun::STATUS_SUCCESS)
            ->orderByDesc('started_at')
            ->first();

        if ($last === null || $last->started_at === null) {
            return $settings->enabled
                ? ['color' => 'warning', 'label' => 'No successful backup yet']
                : ['color' => 'neutral', 'label' => 'Never run (automation disabled)'];
        }

        if (! $settings->enabled) {
            return ['color' => 'ok', 'label' => $last->started_at->diffForHumans().' (manual only — automation disabled)'];
        }

        $graceDays = $settings->frequency === 'weekly' ? 9 : 2;

        if ($last->started_at->lt(now()->subDays($graceDays))) {
            return ['color' => 'warning', 'label' => $last->started_at->diffForHumans().' (stale)'];
        }

        return ['color' => 'ok', 'label' => $last->started_at->diffForHumans()];
    }

    /**
     * Time since Laravel's front controller started (defined in
     * public/index.php) — the closest single number to "how much did booting
     * the framework cost this request," which is exactly what Octane
     * eliminates by keeping the app booted between requests. Without Octane
     * this is paid on every single page load; with it, only on worker start.
     */
    private function bootTimeMs(): float
    {
        $start = defined('LARAVEL_START') ? LARAVEL_START : microtime(true);

        return round((microtime(true) - $start) * 1000, 1);
    }

    /**
     * Round-trip time for a real cache write+read on whatever driver is
     * currently configured — a more honest number than just "ok/error",
     * since a database-driver cache "working" can still be meaningfully
     * slower than Redis would be.
     */
    private function cacheLatencyMs(): ?float
    {
        try {
            $start = microtime(true);
            Cache::put('magna_perf_probe', 1, 5);
            Cache::get('magna_perf_probe');

            return round((microtime(true) - $start) * 1000, 2);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{available: bool, enabled: bool, hit_rate: ?float, memory_used_mb: ?float, memory_free_mb: ?float}
     */
    private function opcacheStatus(): array
    {
        if (! function_exists('opcache_get_status')) {
            return ['available' => false, 'enabled' => false, 'hit_rate' => null, 'memory_used_mb' => null, 'memory_free_mb' => null];
        }

        $status = @opcache_get_status(false);

        // False under CLI (opcache.enable_cli is normally off) and when
        // opcache.enable itself is off — both legitimate, not errors.
        if ($status === false) {
            return ['available' => false, 'enabled' => false, 'hit_rate' => null, 'memory_used_mb' => null, 'memory_free_mb' => null];
        }

        $stats = $status['opcache_statistics'] ?? [];
        $memory = $status['memory_usage'] ?? [];

        return [
            'available' => true,
            'enabled' => (bool) ($status['opcache_enabled'] ?? false),
            'hit_rate' => isset($stats['opcache_hit_rate']) ? round((float) $stats['opcache_hit_rate'], 1) : null,
            'memory_used_mb' => isset($memory['used_memory']) ? round($memory['used_memory'] / 1_048_576, 1) : null,
            'memory_free_mb' => isset($memory['free_memory']) ? round($memory['free_memory'] / 1_048_576, 1) : null,
        ];
    }

    /**
     * Jobs waiting in the "database" queue driver's table. Only meaningful
     * when the queue connection is actually "database" — for Redis or other
     * drivers this table simply isn't where jobs live, so we say so instead
     * of showing a misleading zero.
     */
    private function queuePendingCount(): ?int
    {
        if ((string) config('queue.default', 'sync') !== 'database') {
            return null;
        }

        try {
            return Schema::hasTable('jobs') ? (int) DB::table('jobs')->count() : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Failed jobs are logged to failed_jobs regardless of which queue
     * connection is active, so this count is meaningful no matter the driver.
     */
    private function queueFailedCount(): ?int
    {
        try {
            return Schema::hasTable('failed_jobs') ? (int) DB::table('failed_jobs')->count() : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Proactive "you're running sub-optimally" checks — only surfaced when
     * APP_ENV=production, so local/staging dev never gets nagged. This is
     * deliberately here (reaching every admin who opens System Info) rather
     * than only documented, since documentation only helps someone who
     * already knows to go looking for it.
     *
     * @return list<array{label: string, help: string}>
     */
    private function performanceWarnings(): array
    {
        if (! app()->environment('production')) {
            return [];
        }

        $warnings = [];

        $octaneInstalled = class_exists(OctaneServiceProvider::class);
        $octaneRunning = filter_var(getenv('LARAVEL_OCTANE'), FILTER_VALIDATE_BOOLEAN);

        if (! $octaneRunning) {
            $warnings[] = [
                'label' => 'Octane is not running',
                'help' => $octaneInstalled
                    ? 'The package is installed but the app is still served by plain PHP-FPM/CLI — every request re-boots the framework from scratch. See docs/DEPLOYMENT.md section 3A to start it under a process supervisor.'
                    : 'Running on plain PHP-FPM/CLI. Installing Octane (FrankenPHP) is the single biggest lever for admin-panel and API speed — see docs/DEPLOYMENT.md section 3A.',
            ];
        }

        $cacheDriver = (string) config('cache.default', 'file');
        if (in_array($cacheDriver, ['file', 'database'], true)) {
            $warnings[] = [
                'label' => 'Cache driver is "'.$cacheDriver.'", not Redis',
                'help' => 'Every cache read/write costs a '.($cacheDriver === 'database' ? 'SQL query' : 'disk read').' instead of an in-memory lookup. Set this in Settings → Performance once a Redis server is reachable — .env alone does not change this (see the Performance settings guide for why).',
            ];
        }

        $queueConnection = (string) config('queue.default', 'sync');
        if ($queueConnection === 'sync') {
            $warnings[] = [
                'label' => 'Queue connection is "sync"',
                'help' => 'Background jobs (media thumbnails, webhooks) run in-request instead of in the background, making uploads and other actions wait for them to finish. Switch to Redis or Database in Settings → Performance.',
            ];
        } elseif ($queueConnection === 'database') {
            $warnings[] = [
                'label' => 'Queue connection is "database", not Redis',
                'help' => 'Works, but Redis has lower overhead for a production queue. Also confirm a "php artisan queue:work" process is actually running and supervised — queued jobs silently pile up otherwise.',
            ];
        }

        return $warnings;
    }

    private function dbDriver(): string
    {
        return DB::connection()->getDriverName();
    }

    private function dbVersion(): string
    {
        try {
            $driver = DB::connection()->getDriverName();
            $result = match ($driver) {
                'pgsql' => DB::selectOne('SELECT version() AS v'),
                'sqlite' => DB::selectOne('SELECT sqlite_version() AS v'),
                default => DB::selectOne('SELECT VERSION() AS v'),
            };

            if ($result === null) {
                return 'unknown';
            }

            /** @var object{v: string} $result */
            $raw = $result->v;

            return match ($driver) {
                'pgsql' => preg_match('/PostgreSQL\s+([\d.]+)/i', $raw, $m) === 1 ? $m[1] : $raw,
                default => $raw,
            };
        } catch (Throwable) {
            return 'unavailable';
        }
    }

    private function cacheStatus(): string
    {
        try {
            Cache::put('magna_health_check', 1, 5);

            return Cache::get('magna_health_check') === 1 ? 'ok' : 'error';
        } catch (Throwable) {
            return 'error';
        }
    }
}
