<?php

declare(strict_types=1);

namespace Magna\Backup\Concerns;

use Illuminate\Support\Facades\Cache;

/**
 * Cache-backed progress reporting for a long-running backup job, so an admin
 * page can poll for state while it runs. Shared by RunBackupJob and
 * RestoreBackupJob, which previously each implemented this identically.
 */
trait TracksProgress
{
    abstract protected static function progressKey(): string;

    /** @return array{state: string|null, message: string} */
    public static function progress(): array
    {
        $value = Cache::get(static::progressKey());

        if (is_array($value) && isset($value['state'], $value['message']) && is_string($value['message'])) {
            $state = is_string($value['state']) ? $value['state'] : null;

            return ['state' => $state, 'message' => $value['message']];
        }

        return ['state' => null, 'message' => ''];
    }

    private function setProgress(string $state, string $message): void
    {
        Cache::put(static::progressKey(), ['state' => $state, 'message' => $message], 1800);
    }
}
