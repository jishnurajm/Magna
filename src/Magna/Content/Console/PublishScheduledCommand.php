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

        foreach ($registry->all() as $type) {
            if (! Schema::hasTable($type->tableName())) {
                continue;
            }

            $due = Entry::type($type->handle)
                ->where('status', EntryStatus::Scheduled)
                ->where('published_at', '<=', now())
                ->get();

            foreach ($due as $entry) {
                $manager->publish($entry);
                $published++;
            }
        }

        $this->info("Published {$published} scheduled entry/entries.");

        return self::SUCCESS;
    }
}
