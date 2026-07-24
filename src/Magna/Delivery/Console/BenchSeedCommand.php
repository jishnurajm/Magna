<?php

declare(strict_types=1);

namespace Magna\Delivery\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Magna\Content\Entry;
use Magna\Content\EntryStatus;
use Magna\Content\SchemaRegistry;
use Magna\Media\Media;

/**
 * Seeds realistic benchmark data for the delivery API performance harness.
 *
 * Seeded counts (per performance-spec §1):
 *   100 000 entries spread across 20 content types
 *     5 000 entries per type
 *   50 000 media rows (fake disk objects, no actual files)
 *   3–5 sections per blocks-enabled entry (realistic JSONB payload)
 */
final class BenchSeedCommand extends Command
{
    protected $signature = 'magna:bench:seed
                            {--types=20  : Number of content types to create}
                            {--entries=5000 : Entries per type}
                            {--media=50000 : Total media rows}
                            {--fresh : Drop existing bench data first}';

    protected $description = 'Seed benchmark data for the delivery API performance harness';

    /** Block types to cycle through for blocks_data payloads. */
    private const BLOCK_TYPES = ['heading', 'rich-text', 'image', 'columns', 'cta'];

    public function handle(SchemaRegistry $schema): int
    {
        if ($this->option('fresh')) {
            $this->freshWipe();
        }

        $types = (int) $this->option('types');
        $entriesPerType = (int) $this->option('entries');
        $mediaCount = (int) $this->option('media');

        $this->info("Seeding {$mediaCount} media rows…");
        $this->seedMedia($mediaCount);

        $this->info("Seeding {$types} content types × {$entriesPerType} entries…");
        $this->seedEntries($types, $entriesPerType);

        $total = $types * $entriesPerType;
        $this->info("Done. {$total} entries, {$mediaCount} media rows seeded.");

        return self::SUCCESS;
    }

    private function seedMedia(int $count): void
    {
        $rows = [];
        $now = now()->toDateTimeString();

        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'id' => (string) Str::ulid(),
                'filename' => 'bench-'.$i.'.jpg',
                'disk' => 'local',
                'path' => 'bench/'.$i.'.jpg',
                'mime_type' => 'image/jpeg',
                'size' => random_int(50_000, 500_000),
                'width' => random_int(800, 3000),
                'height' => random_int(600, 2000),
                'alt' => 'Benchmark image '.$i,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) >= 500) {
                DB::table('media')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('media')->insert($rows);
        }
    }

    private function seedEntries(int $typeCount, int $entriesPerType): void
    {
        $now = now()->toDateTimeString();

        for ($t = 0; $t < $typeCount; $t++) {
            $handle = 'bench_type_'.$t;
            $this->ensureTable($handle);

            $this->output->write("  [{$handle}] ");
            $bar = $this->output->createProgressBar($entriesPerType);
            $bar->start();

            $rows = [];
            for ($e = 0; $e < $entriesPerType; $e++) {
                $rows[] = [
                    'id' => (string) Str::ulid(),
                    'content_type' => $handle,
                    'status' => EntryStatus::Published->value,
                    'locale' => 'en',
                    'title' => "Bench entry {$t}-{$e}",
                    'slug' => "bench-{$t}-{$e}",
                    'blocks_data' => $this->buildBlocksData(random_int(3, 5)),
                    'published_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($rows) >= 200) {
                    DB::table('entries')->insert($rows);
                    $rows = [];
                    $bar->advance(200);
                }
            }

            if ($rows !== []) {
                DB::table('entries')->insert($rows);
                $bar->advance(count($rows));
            }

            $bar->finish();
            $this->line('');
        }
    }

    private function ensureTable(string $handle): void
    {
        // Entries share the `entries` table with a `content_type` discriminator.
        // Nothing to create — the unified table is created by the entries migration.
    }

    /**
     * Build a realistic blocks_data JSON payload with $sectionCount sections,
     * 1–3 columns each, 1–2 blocks per column.
     */
    private function buildBlocksData(int $sectionCount): string
    {
        $sections = [];
        for ($s = 0; $s < $sectionCount; $s++) {
            $colCount = random_int(1, 3);
            $spans = $this->randomSpans($colCount);
            $columns = [];
            foreach ($spans as $span) {
                $blockCount = random_int(1, 2);
                $blocks = [];
                for ($b = 0; $b < $blockCount; $b++) {
                    $blockType = self::BLOCK_TYPES[array_rand(self::BLOCK_TYPES)];
                    $blocks[] = [
                        'id' => (string) Str::ulid(),
                        'block' => $blockType,
                        'settings' => [
                            'spacing' => ['top' => 'md', 'bottom' => 'md'],
                            'visibility' => ['desktop' => true, 'tablet' => true, 'mobile' => true],
                            'cssClass' => '',
                        ],
                        'data' => $this->blockData($blockType),
                    ];
                }
                $columns[] = [
                    'id' => (string) Str::ulid(),
                    'span' => $span,
                    'settings' => ['verticalAlign' => 'top', 'padding' => ['x' => 'none', 'y' => 'none'], 'cssClass' => ''],
                    'blocks' => $blocks,
                ];
            }
            $sections[] = [
                'id' => (string) Str::ulid(),
                'type' => 'section',
                'settings' => [
                    'background' => ['type' => 'none', 'value' => '', 'overlay' => 0],
                    'padding' => ['top' => 'lg', 'bottom' => 'lg'],
                    'maxWidth' => '2xl',
                    'anchor' => '',
                    'visibility' => ['desktop' => true, 'tablet' => true, 'mobile' => true],
                    'cssClass' => '',
                    'tokenOverrides' => (object) [],
                ],
                'columns' => $columns,
            ];
        }

        return (string) json_encode($sections);
    }

    /**
     * @return list<int>
     */
    private function randomSpans(int $count): array
    {
        if ($count === 1) {
            return [12];
        }
        if ($count === 2) {
            return [[6, 6], [4, 8], [8, 4]][array_rand([[6, 6], [4, 8], [8, 4]])];
        }

        return [4, 4, 4];
    }

    /**
     * @return array<string, mixed>
     */
    private function blockData(string $blockType): array
    {
        return match ($blockType) {
            'heading' => ['text' => 'Benchmark heading '.random_int(1, 999), 'level' => 'h2'],
            'rich-text' => ['content' => '<p>Benchmark paragraph '.Str::random(40).'</p>'],
            'image' => ['src' => '', 'alt' => 'bench', 'caption' => ''],
            'cta' => ['text' => 'Learn more', 'url' => 'https://example.com', 'style' => 'primary'],
            default => [],
        };
    }

    private function freshWipe(): void
    {
        $this->warn('Wiping existing bench data…');
        DB::table('entries')->where('content_type', 'like', 'bench_%')->delete();
        DB::table('media')->where('filename', 'like', 'bench-%')->delete();
    }
}
