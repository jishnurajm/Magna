<?php

declare(strict_types=1);

namespace Magna\Content\Console;

use Illuminate\Console\Command;
use Magna\Content\SchemaRegistry;
use Magna\Content\TableGenerator;

/**
 * Backfills the status/locale/published_at/unpublish_at/updated_at indexes
 * (added to TableGenerator::createTable()) onto content-type tables that
 * already existed before that change. Safe to run repeatedly.
 *
 * Usage:
 *   php artisan magna:content:add-indexes
 */
class AddPerformanceIndexesCommand extends Command
{
    protected $signature = 'magna:content:add-indexes';

    protected $description = 'Add missing performance indexes to existing content-type tables';

    public function handle(SchemaRegistry $registry, TableGenerator $generator): int
    {
        $types = $registry->all();

        if ($types === []) {
            $this->info('No content types registered — nothing to index.');

            return self::SUCCESS;
        }

        foreach ($types as $type) {
            $generator->addPerformanceIndexes($type);
            $this->info("Indexed {$type->tableName()}");
        }

        return self::SUCCESS;
    }
}
