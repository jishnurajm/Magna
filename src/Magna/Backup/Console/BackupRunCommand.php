<?php

declare(strict_types=1);

namespace Magna\Backup\Console;

use Illuminate\Console\Command;
use Magna\Backup\BackupRun;
use Magna\Backup\Jobs\RunBackupJob;

/**
 * Scheduled trigger — dispatched every minute the scheduler ticks, but only
 * actually runs when BackupSchedule::isDueNow() says so (registered in
 * BackupServiceProvider::boot()). Manual runs go through
 * BackupSettingsPage's "Run backup now" action instead, not this command.
 *
 * Usage:
 *   php artisan magna:backup:run
 */
class BackupRunCommand extends Command
{
    protected $signature = 'magna:backup:run';

    protected $description = 'Run a backup using the current Backup Manager settings.';

    public function handle(): int
    {
        RunBackupJob::dispatch(BackupRun::TYPE_SCHEDULED, null);

        $this->info('Backup dispatched.');

        return self::SUCCESS;
    }
}
