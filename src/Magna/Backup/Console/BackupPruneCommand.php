<?php

declare(strict_types=1);

namespace Magna\Backup\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Magna\Backup\BackupRun;
use Magna\Settings\BackupSettings;
use Throwable;

/**
 * Enforces BackupSettings' retention policy: a successful backup is kept if
 * it's among the `retention_count` most recent OR newer than
 * `retention_days` — only pruned when it's beyond both. The single most
 * recent successful backup is never deleted regardless of settings, so a
 * misconfiguration (e.g. retention_count somehow reaching 0) can't leave the
 * instance with zero recovery points. Run daily — see
 * BackupServiceProvider::boot(), same registration pattern as
 * magna:audit:prune.
 *
 * Usage:
 *   php artisan magna:backup:prune
 */
class BackupPruneCommand extends Command
{
    protected $signature = 'magna:backup:prune';

    protected $description = 'Delete backups past the configured retention policy.';

    public function handle(): int
    {
        $settings = BackupSettings::get();

        $successfulRuns = BackupRun::query()
            ->where('status', BackupRun::STATUS_SUCCESS)
            ->orderByDesc('started_at')
            ->get();

        if ($successfulRuns->isEmpty()) {
            $this->info('No successful backups to prune.');

            return self::SUCCESS;
        }

        $mostRecent = $successfulRuns->first();
        $keepCount = max(1, $settings->retention_count);
        $cutoff = now()->subDays(max(1, $settings->retention_days));

        $toPrune = $successfulRuns
            ->skip($keepCount)
            ->filter(fn (BackupRun $run): bool => $run->started_at !== null && $run->started_at->lt($cutoff))
            ->reject(fn (BackupRun $run): bool => $run->is($mostRecent));

        $pruned = 0;
        foreach ($toPrune as $run) {
            $this->deleteArchive($run);
            $run->delete();
            $pruned++;
        }

        $this->info("Pruned {$pruned} backup(s).");

        return self::SUCCESS;
    }

    private function deleteArchive(BackupRun $run): void
    {
        if ($run->disk === null || $run->path === null) {
            return;
        }

        try {
            Storage::disk($run->disk)->delete($run->path);
        } catch (Throwable) {
            // Disk unreachable — still prune the DB row; nothing more we can
            // do about the orphaned file from here.
        }
    }
}
