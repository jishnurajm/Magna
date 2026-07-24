<?php

declare(strict_types=1);

namespace Magna\Audit\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Magna\Audit\AuditLog;

class AuditExportCommand extends Command
{
    protected $signature = 'magna:audit:export
        {--from= : Start date inclusive (Y-m-d)}
        {--to=   : End date inclusive (Y-m-d)}';

    protected $description = 'Export audit log entries as JSON lines for SIEM ingestion.';

    public function handle(): int
    {
        $query = AuditLog::query();

        $from = $this->option('from');
        if (is_string($from)) {
            $query->where('created_at', '>=', Carbon::parse($from)->startOfDay());
        }

        $to = $this->option('to');
        if (is_string($to)) {
            $query->where('created_at', '<=', Carbon::parse($to)->endOfDay());
        }

        // Stage 12: chunk() with an explicit orderBy('created_at') falls
        // back to LIMIT/OFFSET paging on a non-unique column — cost grows
        // toward O(n^2) on a large export, and rows sharing an identical
        // created_at value at a chunk-page boundary can be silently
        // skipped or duplicated (a correctness bug for a SIEM-ingestion
        // export, not just a perf one). id (ULID) is unique, indexed as
        // the primary key, and already chronologically sortable (ULIDs
        // are time-prefixed), so chunkById() gets the same ordering
        // guarantee with none of the OFFSET-paging downsides.
        $query->orderBy('id')->chunkById(500, function ($logs): void {
            /** @var Collection<int, AuditLog> $logs */
            foreach ($logs as $log) {
                $this->line(json_encode($log->toArray(), JSON_THROW_ON_ERROR));
            }
        });

        return self::SUCCESS;
    }
}
