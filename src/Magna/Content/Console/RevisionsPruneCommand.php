<?php

declare(strict_types=1);

namespace Magna\Content\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RevisionsPruneCommand extends Command
{
    protected $signature = 'magna:revisions:prune
                            {--keep=50 : Maximum number of revisions to keep per entry}';

    protected $description = 'Prune old revisions, keeping only the newest N per entry.';

    public function handle(): int
    {
        $keep = max(1, (int) $this->option('keep'));

        $groups = DB::table('magna_revisions')
            ->selectRaw('entry_type, entry_id, COUNT(*) as total')
            ->groupBy('entry_type', 'entry_id')
            ->havingRaw('COUNT(*) > ?', [$keep])
            ->get();

        $pruned = 0;

        foreach ($groups as $group) {
            /** @var object{entry_type: string, entry_id: string} $group */
            $keepIds = DB::table('magna_revisions')
                ->where('entry_type', $group->entry_type)
                ->where('entry_id', $group->entry_id)
                ->orderByDesc('created_at')
                ->limit($keep)
                ->pluck('id');

            $deleted = DB::table('magna_revisions')
                ->where('entry_type', $group->entry_type)
                ->where('entry_id', $group->entry_id)
                ->whereNotIn('id', $keepIds)
                ->delete();

            $pruned += $deleted;
        }

        $this->info("Pruned {$pruned} revision(s).");

        return self::SUCCESS;
    }
}
