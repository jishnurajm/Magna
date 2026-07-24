<?php

declare(strict_types=1);

namespace Magna\Backup;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Magna\Auth\PermissionRegistry;
use Magna\Backup\Console\BackupPruneCommand;
use Magna\Backup\Console\BackupRunCommand;
use Magna\Backup\Http\Controllers\BackupDownloadController;

/**
 * Backup Manager domain. Build order and rationale: docs/backup-manager-plan.md.
 * Stage 1 registered the domain; Stage 2 added settings + permissions;
 * Stage 3 added the backup engine; Stage 4 added the manual-run job,
 * download route, and history resource; Stage 5 adds scheduling + retention.
 */
class BackupServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerPermissions();
        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->commands([BackupRunCommand::class, BackupPruneCommand::class]);
        }

        // Scheduler ticks every minute regardless — BackupSchedule::isDueNow()
        // is what makes this actually fire once per due window, not once per
        // minute inside it (settings-driven frequency, so a fixed ->daily()
        // like magna:audit:prune's own registration won't do). Retention
        // itself has no such timing subtlety, so it keeps the fixed ->daily().
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('magna:backup:run')->everyMinute()->when(fn (): bool => BackupSchedule::isDueNow());
            $schedule->command('magna:backup:prune')->daily();
        });
    }

    private function registerPermissions(): void
    {
        $registry = $this->app->make(PermissionRegistry::class);
        $registry->registerMany([
            'backup.view' => 'View backup run history',
            'backup.manage' => 'Change backup settings, trigger manual runs, and download backups',
            // Stage 8: restore is gated on this permission AND on
            // User::isSuperAdmin() explicitly in BackupResource — a role
            // could theoretically be granted this permission without being
            // flagged is_super_admin, and "gated hard behind super_admin"
            // means the actual role flag, not just a grantable permission
            // string. Both checks must pass.
            'backup.restore' => 'Restore this Magna instance from a backup archive — overwrites the live database and files',
        ]);
    }

    private function registerRoutes(): void
    {
        // A backup archive can contain a full database dump — unlike
        // MediaServeController's signed-URL-only route, this requires the
        // real backup.manage permission, not just an unguessable link.
        Route::middleware(['web', 'auth', 'can:backup.manage'])
            ->get('/_backups/{backupRun}/download', BackupDownloadController::class)
            ->name('magna.backup.download');
    }
}
