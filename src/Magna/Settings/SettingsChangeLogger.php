<?php

declare(strict_types=1);

namespace Magna\Settings;

use Illuminate\Support\Facades\Auth;
use Magna\Audit\AuditLog;

/** Writes an audit trail entry for a settings group change. */
class SettingsChangeLogger
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function log(string $group, array $before, array $after): void
    {
        $actorId = Auth::id();

        AuditLog::record(
            action: 'settings.changed',
            actorId: $actorId !== null ? (string) $actorId : null,
            actorType: $actorId !== null ? 'user' : 'system',
            ip: request()->ip(),
            before: $before,
            after: $after,
        );
    }
}
