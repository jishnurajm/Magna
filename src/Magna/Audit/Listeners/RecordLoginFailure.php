<?php

declare(strict_types=1);

namespace Magna\Audit\Listeners;

use Illuminate\Auth\Events\Failed;
use Magna\Audit\AuditLog;

class RecordLoginFailure
{
    public function handle(Failed $event): void
    {
        $email = $event->credentials['email'] ?? null;

        AuditLog::record(
            action: 'auth.login.failure',
            actorType: 'user',
            ip: request()->ip(),
            before: ['email' => is_string($email) ? $email : null],
        );
    }
}
