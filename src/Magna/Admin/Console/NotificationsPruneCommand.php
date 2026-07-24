<?php

declare(strict_types=1);

namespace Magna\Admin\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Delete read bell notifications older than a configurable retention
 * window — same shape as Magna\Audit\Console\AuditPruneCommand and
 * Magna\Backup\Console\BackupPruneCommand. Unread notifications are never
 * pruned regardless of age: an admin who hasn't seen an alert yet must
 * still see it, however old the underlying event was.
 *
 * Usage:
 *   php artisan magna:notifications:prune
 *   php artisan magna:notifications:prune --days=30
 */
class NotificationsPruneCommand extends Command
{
    protected $signature = 'magna:notifications:prune
        {--days=90 : Delete read notifications older than this many days (default: 90)}';

    protected $description = 'Delete read bell notifications older than the retention window';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $deleted = DB::table('notifications')
            ->whereNotNull('read_at')
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info("Pruned {$deleted} notification".($deleted === 1 ? '' : 's')." older than {$days} day(s).");

        return self::SUCCESS;
    }
}
