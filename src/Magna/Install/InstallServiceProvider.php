<?php

declare(strict_types=1);

namespace Magna\Install;

use Illuminate\Support\ServiceProvider;
use Throwable;

class InstallServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(EnvWriter::class, function (): EnvWriter {
            return new EnvWriter(config()->string('magna.install.env_path', base_path('.env')));
        });

        // Must run in register(), before any other provider's boot(): plugin
        // discovery calls Schema::hasTable() and the exception handler reads the
        // cache during boot. On a fresh unzip (no .env) both would hit the
        // default `database` cache store / missing SQLite file and 500 before
        // the installer can render. Overriding config here makes the whole
        // pre-install runtime self-contained.
        if (! Installer::isInstalled() && ! $this->app->runningInConsole()) {
            config([
                'session.driver' => 'file',
                'cache.default' => 'array',
                'database.default' => 'sqlite',
                'database.connections.sqlite.database' => ':memory:',
            ]);
        }
    }

    /**
     * Note: RedirectIfNotInstalled is appended to the web group in
     * bootstrap/app.php — pushing it here would be wiped when the kernel
     * syncs the bootstrap middleware configuration.
     */
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/resources/views', 'magna-install');
        $this->loadRoutesFrom(__DIR__.'/routes.php');

        if (! Installer::isInstalled() && ! $this->app->runningInConsole()) {
            $this->prepareUninstalledRuntime();
        }
    }

    /**
     * On a fresh unzip there is often no APP_KEY. Self-generate one (and try to
     * persist it to .env) so sessions/CSRF work through the installer. The
     * pre-install cache/database/session overrides are applied earlier, in
     * register(), because they must land before any other provider boots.
     */
    private function prepareUninstalledRuntime(): void
    {
        if (config('app.key') !== null && config('app.key') !== '') {
            return;
        }

        $key = 'base64:'.base64_encode(random_bytes(32));

        try {
            $this->app->make(EnvWriter::class)->set(['APP_KEY' => $key]);
        } catch (Throwable) {
            // .env not writable — the requirements screen will surface this.
        }

        config(['app.key' => $key]);
    }
}
