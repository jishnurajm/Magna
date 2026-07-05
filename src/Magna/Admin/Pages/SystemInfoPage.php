<?php

declare(strict_types=1);

namespace Magna\Admin\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Magna\MagnaServiceProvider;
use Magna\Plugins\PluginRecord;

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

    public static function canAccess(): bool
    {
        return auth()->user()?->can('settings.view') ?? false;
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('checkForUpdates')
                ->label('Check for Updates')
                ->icon('heroicon-o-arrow-down-circle')
                ->color('primary')
                ->action(function (): void {
                    $current = MagnaServiceProvider::VERSION;
                    Notification::make()
                        ->title('Magna CMS is up to date')
                        ->body("Running v{$current} — no newer release found.")
                        ->success()
                        ->send();
                }),
        ];
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
        } catch (\Throwable $e) {
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
        ];
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
        } catch (\Throwable) {
            return 'unavailable';
        }
    }

    private function cacheStatus(): string
    {
        try {
            Cache::put('magna_health_check', 1, 5);

            return Cache::get('magna_health_check') === 1 ? 'ok' : 'error';
        } catch (\Throwable) {
            return 'error';
        }
    }
}
