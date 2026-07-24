<?php

declare(strict_types=1);

namespace Magna\Updater\Console;

use Illuminate\Console\Command;
use Magna\Updater\UpdateCheckClient;

/**
 * Runs the check-in against Update Manager (managemagna.jrstudios.dev) and
 * persists the result to update_checks. Scheduled every 12h by
 * UpdaterServiceProvider::boot(); also invoked directly (synchronously) by
 * System Info's "Check for Updates" action for an on-demand, cache-bypassing
 * check.
 *
 * Usage:
 *   php artisan magna:updater:check
 */
class CheckForUpdatesCommand extends Command
{
    protected $signature = 'magna:updater:check';

    protected $description = 'Check in with Update Manager for core and plugin updates';

    public function handle(UpdateCheckClient $client): int
    {
        $result = $client->checkIn();

        if ($result === null) {
            $this->warn('Could not reach Update Manager — no changes to update state.');

            return self::SUCCESS;
        }

        $coreLine = $result->core?->updateAvailable
            ? "Core update available: {$result->core->latestVersion}"
            : 'Core is up to date.';
        $this->info($coreLine);

        $pluginUpdates = array_filter($result->plugins, fn ($p) => $p->updateAvailable);
        $this->info(count($pluginUpdates).' plugin update(s) available.');

        return self::SUCCESS;
    }
}
