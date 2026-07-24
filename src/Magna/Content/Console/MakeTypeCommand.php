<?php

declare(strict_types=1);

namespace Magna\Content\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Scaffolds a new content type JSON schema file at schemas/{handle}.json.
 *
 * Usage:
 *   php artisan magna:type:make article
 *   php artisan magna:type:make article --displayName="Blog Article" --localizable --draftable
 */
class MakeTypeCommand extends Command
{
    protected $signature = 'magna:type:make
        {handle : The content type handle (lowercase alphanumeric + underscores, e.g. blog_post)}
        {--displayName= : Human-readable name (defaults to title-cased handle)}
        {--localizable : Mark the type as localizable}
        {--draftable : Enable draft/publish workflow}';

    protected $description = 'Scaffold a new content type schema file at schemas/{handle}.json';

    public function handle(): int
    {
        $handle = (string) $this->argument('handle');

        if (! preg_match('/^[a-z][a-z0-9_]*$/', $handle)) {
            $this->error("Handle must be lowercase alphanumeric with underscores (e.g. 'blog_post').");

            return self::FAILURE;
        }

        $path = base_path("schemas/{$handle}.json");

        if (file_exists($path)) {
            $this->error("Schema file already exists: schemas/{$handle}.json");

            return self::FAILURE;
        }

        $displayName = $this->option('displayName')
            ?? Str::title(str_replace(['-', '_'], ' ', $handle));

        $localizable = (bool) $this->option('localizable');
        $draftable = (bool) $this->option('draftable');

        $schema = [
            'handle' => $handle,
            'displayName' => $displayName,
            'localizable' => $localizable,
            'draftable' => $draftable,
            'fields' => [
                [
                    'handle' => 'title',
                    'type' => 'text',
                    'label' => 'Title',
                    'required' => true,
                ],
            ],
        ];

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        $this->info("Created schemas/{$handle}.json");

        return self::SUCCESS;
    }
}
