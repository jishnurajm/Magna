<?php

declare(strict_types=1);

namespace Magna\Content\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Magna\Content\Entry;
use Magna\Content\EntryManager;
use Magna\Content\EntryStatus;
use Magna\Content\SchemaRegistry;

class PublishScheduledCommand extends Command
{
    protected $signature = 'magna:publish:scheduled';

    protected $description = 'Publish entries that were scheduled for a past or current time.';

    public function handle(SchemaRegistry $registry, EntryManager $manager): int
    {
        $published = 0;
        $unpublished = 0;

        foreach ($registry->all() as $type) {
            if (! Schema::hasTable($type->tableName())) {
                continue;
            }

            // Publish scheduled entries whose publish time has arrived.
            $due = Entry::type($type->handle)
                ->where('status', EntryStatus::Scheduled)
                ->where('published_at', '<=', now())
                ->get();

            foreach ($due as $entry) {
                $manager->publish($entry);
                $published++;
            }

            // Unpublish entries whose unpublish_at time has arrived.
            if (Schema::hasColumn($type->tableName(), 'unpublish_at')) {
                $toUnpublish = Entry::type($type->handle)
                    ->where('status', EntryStatus::Published)
                    ->whereNotNull('unpublish_at')
                    ->where('unpublish_at', '<=', now())
                    ->get();

                foreach ($toUnpublish as $entry) {
                    $manager->unpublish($entry);
                    $unpublished++;
                }
            }
        }

        $this->info("Published {$published} scheduled entries; auto-unpublished {$unpublished} entries.");

        return self::SUCCESS;
    }
}
