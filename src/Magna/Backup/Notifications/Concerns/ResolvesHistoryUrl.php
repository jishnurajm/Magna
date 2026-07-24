<?php

declare(strict_types=1);

namespace Magna\Backup\Notifications\Concerns;

use Magna\Admin\Resources\BackupResource;
use Throwable;

/**
 * BackupResource::getUrl() requires an active Filament panel context, which
 * exists on a normal web request but not inside a real queue worker process
 * (`php artisan queue:work`) — which is exactly how these notifications
 * normally get sent, since RunBackupJob is queued. Calling it unguarded
 * crashed every notification sent from a worker (NoDefaultPanelSetException),
 * including the super_admin failure-fallback that's supposed to be the
 * "never fail silently" guarantee — found live, not in the Pest suite,
 * because the suite always runs with QUEUE_CONNECTION=sync (no real worker,
 * no missing panel context) — see docs/backup-manager-plan.md.
 */
trait ResolvesHistoryUrl
{
    private function historyUrl(): ?string
    {
        try {
            return BackupResource::getUrl();
        } catch (Throwable) {
            return null;
        }
    }
}
