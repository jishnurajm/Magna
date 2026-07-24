<?php

declare(strict_types=1);

namespace Magna\Updater;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Magna\Updater\Console\CheckForUpdatesCommand;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The core updater — always registered, never a plugin. Keeps the site's
 * "is an update available" state fresh via a scheduled heartbeat check-in
 * against Update Manager, and exposes UpdateCheckClient for the on-demand
 * check triggered from System Info.
 *
 * Deliberately not a plugin: the thing that keeps a site updatable can't
 * itself be disabled through the plugin UI (see docs/updates-architecture.md).
 */
class UpdaterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(UpdateCheckClient::class);
        $this->app->singleton(Filesystem::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([CheckForUpdatesCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('magna:updater:check')
                ->cron('0 */12 * * *')
                ->withoutOverlapping();
        });
    }
}
