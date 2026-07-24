<?php

declare(strict_types=1);

namespace Magna\Audit\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Delete audit log entries older than a configurable retention window.
 *
 * audit_logs is append-only and grows on every admin/moderation action with
 * no other pruning mechanism (Stage 13, S5-02) — run this on a schedule
 * (see AuditServiceProvider::boot()) to keep it bounded.
 *
 * Usage:
 *   php artisan magna:audit:prune
 *   php artisan magna:audit:prune --days=180
 */
class AuditPruneCommand extends Command
{
    protected $signature = 'magna:audit:prune
        {--days=365 : Delete audit log entries older than this many days (default: 365)}';

    protected $description = 'Delete audit log entries older than the retention window';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        // chunkById-safe delete: a plain ->delete() on the whole matching
        // set in one statement is fine here (no cross-request chunking
        // needed, unlike AuditExportCommand's read-and-stream case).
        $deleted = DB::table('audit_logs')
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info("Pruned {$deleted} audit log entr".($deleted === 1 ? 'y' : 'ies')." older than {$days} day(s).");

        return self::SUCCESS;
    }
}
