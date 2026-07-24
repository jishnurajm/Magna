<?php

declare(strict_types=1);

namespace Magna\Backup;

use Cron\CronExpression;
use Illuminate\Support\Carbon;
use Magna\Settings\BackupSettings;
use Throwable;

/**
 * Decides whether a scheduled backup is due right now. Read by the
 * `->everyMinute()->when(...)` guard registered in BackupServiceProvider —
 * the scheduler itself ticks every minute regardless; this is what makes it
 * actually fire only once per due window instead of once per minute inside
 * it. See docs/backup-manager-plan.md, Stage 5.
 */
class BackupSchedule
{
    public static function isDueNow(): bool
    {
        $settings = BackupSettings::get();

        if (! $settings->enabled) {
            return false;
        }

        if ($settings->frequency === 'custom_cron') {
            return self::customCronIsDue($settings);
        }

        return self::intervalIsDue($settings);
    }

    private static function customCronIsDue(BackupSettings $settings): bool
    {
        if (blank($settings->cron_expression)) {
            return false;
        }

        try {
            return (new CronExpression($settings->cron_expression))->isDue(now()->toDateTimeString());
        } catch (Throwable) {
            // An invalid expression must never fire and must never crash the
            // scheduler tick for every other registered command.
            return false;
        }
    }

    private static function intervalIsDue(BackupSettings $settings): bool
    {
        $runAt = self::parseRunAt($settings->run_at);
        if ($runAt === null) {
            return false;
        }

        $now = now();
        [$hour, $minute] = $runAt;

        if ($now->hour !== $hour || $now->minute !== $minute) {
            return false;
        }

        $last = self::lastScheduledRunAt();
        if ($last === null) {
            return true;
        }

        return match ($settings->frequency) {
            'weekly' => $last->lt($now->copy()->subDays(7)),
            default => ! $last->isSameDay($now), // 'daily'
        };
    }

    private static function lastScheduledRunAt(): ?Carbon
    {
        return BackupRun::query()
            ->where('type', BackupRun::TYPE_SCHEDULED)
            ->orderByDesc('started_at')
            ->first()
            ?->started_at;
    }

    /** @return array{0: int, 1: int}|null */
    private static function parseRunAt(string $runAt): ?array
    {
        if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $runAt, $matches) !== 1) {
            return null;
        }

        return [(int) $matches[1], (int) $matches[2]];
    }
}
