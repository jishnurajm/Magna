<?php

declare(strict_types=1);

namespace Magna\Content\Console;

use Illuminate\Console\Command;
use Magna\Content\Exceptions\DestructiveChangeException;
use Magna\Content\SchemaRegistry;
use Magna\Content\SchemaSyncer;

class SchemaSyncCommand extends Command
{
    protected $signature = 'magna:schema:sync {--allow-destructive : Allow destructive changes (drops, type changes)}';

    protected $description = 'Apply schema changes to the live database.';

    public function handle(SchemaRegistry $registry, SchemaSyncer $syncer): int
    {
        $allowDestructive = (bool) $this->option('allow-destructive');

        try {
            $diff = $syncer->syncAll($registry, $allowDestructive);
        } catch (DestructiveChangeException $e) {
            $this->error($e->getMessage());
            $this->line('');
            $this->warn('Re-run with --allow-destructive to apply destructive changes.');

            return self::FAILURE;
        }

        if ($diff->isEmpty()) {
            $this->info('Schema is already in sync — nothing to do.');

            return self::SUCCESS;
        }

        foreach ($diff->changes as $change) {
            $this->line("  Applied: [{$change->type->value}] {$change->description}");
        }

        $this->info('Schema sync complete.');

        return self::SUCCESS;
    }
}
