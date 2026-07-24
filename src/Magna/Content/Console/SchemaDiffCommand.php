<?php

declare(strict_types=1);

namespace Magna\Content\Console;

use Illuminate\Console\Command;
use Magna\Content\SchemaDiffer;
use Magna\Content\SchemaRegistry;

class SchemaDiffCommand extends Command
{
    protected $signature = 'magna:schema:diff';

    protected $description = 'Compare registered content type schemas against the live database.';

    public function handle(SchemaRegistry $registry, SchemaDiffer $differ): int
    {
        $diff = $differ->diffAll($registry);

        if ($diff->isEmpty()) {
            $this->info('Schema is in sync — no changes needed.');

            return self::SUCCESS;
        }

        $this->line('');
        $this->line('<fg=cyan>Migration plan:</>');
        $this->line('');

        foreach ($diff->changes as $change) {
            $icon = $change->destructive ? '<fg=red>  DESTRUCTIVE</>' : '<fg=green>  non-destructive</>';
            $this->line("  [{$change->type->value}] {$change->description}");
            $this->line($icon);
            $this->line('');
        }

        if ($diff->hasDestructive()) {
            $this->warn('Destructive changes detected. Run magna:schema:sync --allow-destructive to apply.');
        } else {
            $this->line('Run <fg=yellow>magna:schema:sync</> to apply these changes.');
        }

        return self::SUCCESS;
    }
}
