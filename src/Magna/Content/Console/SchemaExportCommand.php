<?php

declare(strict_types=1);

namespace Magna\Content\Console;

use Illuminate\Console\Command;
use Magna\Content\Models\ContentTypeRecord;
use Magna\Content\SchemaRegistry;

/**
 * Export all database-defined content types to JSON schema files in schemas/.
 *
 * This enables a schema-as-code round-trip:
 *   dev DB (admin-created types) → export → commit → prod magna:schema:sync
 */
class SchemaExportCommand extends Command
{
    protected $signature = 'magna:schema:export
                            {--dir= : Directory to write files into (default: schemas/)}
                            {--force : Overwrite existing files without prompting}';

    protected $description = 'Export database-defined content types to JSON schema files.';

    public function handle(SchemaRegistry $registry): int
    {
        $dir = $this->option('dir');
        if (! is_string($dir) || $dir === '') {
            $dir = base_path('schemas');
        }

        if (! is_dir($dir) && ! mkdir($dir, 0755, true)) {
            $this->error("Cannot create directory: {$dir}");

            return self::FAILURE;
        }

        $records = ContentTypeRecord::where('is_database_defined', true)->get();

        if ($records->isEmpty()) {
            $this->info('No database-defined content types found.');

            return self::SUCCESS;
        }

        $exported = 0;
        $skipped = 0;

        foreach ($records as $record) {
            $schema = $record->schema;
            if ($schema === []) {
                $this->warn("Skipping \"{$record->handle}\": schema column is empty.");

                continue;
            }

            // Ensure the handle is in the schema for round-trip fidelity.
            $schema['handle'] = $record->handle;

            $filePath = rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$record->handle.'.json';

            if (file_exists($filePath) && ! $this->option('force')) {
                if (! $this->confirm("File \"{$filePath}\" already exists. Overwrite?")) {
                    $skipped++;

                    continue;
                }
            }

            $json = json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                $this->error("Failed to serialise schema for \"{$record->handle}\".");

                continue;
            }

            file_put_contents($filePath, $json.PHP_EOL);
            $this->line("  <info>✓</info> {$record->handle} → ".basename($filePath));
            $exported++;
        }

        $this->info("Exported {$exported} schema(s)".($skipped > 0 ? ", skipped {$skipped}." : '.'));

        return self::SUCCESS;
    }
}
