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
     * Until installation completes there is no configured database and
     * possibly no APP_KEY (fresh unzip). Force file-based sessions and
     * self-generate a key so the installer can run on a bare server.
     */
    private function prepareUninstalledRuntime(): void
    {
        config(['session.driver' => 'file']);

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
