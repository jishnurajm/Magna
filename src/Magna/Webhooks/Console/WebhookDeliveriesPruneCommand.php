<?php

declare(strict_types=1);

namespace Magna\Webhooks\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Delete webhook delivery log entries older than a configurable retention
 * window. WebhookEventSubscriber writes one row per active subscription on
 * every content/media mutation with no other pruning mechanism (Stage 13,
 * S5-02) — run this on a schedule (see WebhookServiceProvider::boot()) to
 * keep webhook_deliveries bounded.
 *
 * Usage:
 *   php artisan magna:webhooks:prune-deliveries
 *   php artisan magna:webhooks:prune-deliveries --days=90
 */
class WebhookDeliveriesPruneCommand extends Command
{
    protected $signature = 'magna:webhooks:prune-deliveries
        {--days=90 : Delete delivery log entries older than this many days (default: 90)}';

    protected $description = 'Delete webhook delivery log entries older than the retention window';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $deleted = DB::table('webhook_deliveries')
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info("Pruned {$deleted} webhook delivery entr".($deleted === 1 ? 'y' : 'ies')." older than {$days} day(s).");

        return self::SUCCESS;
    }
}
