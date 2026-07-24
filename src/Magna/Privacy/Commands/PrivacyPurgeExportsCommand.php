<?php

declare(strict_types=1);

namespace Magna\Privacy\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Delete GDPR export archives older than a configurable TTL.
 *
 * Run on a schedule (e.g. weekly) to prevent the privacy/ directory in
 * local storage from accumulating indefinitely.
 *
 * Usage:
 *   php artisan magna:privacy:purge-exports
 *   php artisan magna:privacy:purge-exports --days=14
 */
class PrivacyPurgeExportsCommand extends Command
{
    protected $signature = 'magna:privacy:purge-exports
        {--days=30 : Delete export archives older than this many days (default: 30)}';

    protected $description = 'Delete GDPR export archives older than the TTL';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);
        $disk = Storage::disk('local');

        $files = $disk->files('privacy');
        $deleted = 0;

        foreach ($files as $file) {
            if (! str_starts_with(basename($file), 'export-')) {
                continue;
            }

            if ($disk->lastModified($file) < $cutoff->timestamp) {
                $disk->delete($file);
                $deleted++;
                $this->line("  deleted: {$file}");
            }
        }

        $this->info("Purged {$deleted} export archive(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
