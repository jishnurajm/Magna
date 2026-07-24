<?php

declare(strict_types=1);

namespace Magna\Auth;

use Illuminate\Support\Facades\DB;
use Magna\Users\User;

/**
 * Kills a suspended user's active credentials: their Sanctum tokens and
 * (best-effort, database session driver only) their browser session rows.
 *
 * Extracted from User::booted() so the model's event hook only dispatches,
 * not implement, the revocation itself.
 */
class SuspendedAccessRevoker
{
    public function revoke(User $user): void
    {
        $user->tokens()->delete();

        // Best-effort session kill: only takes effect with the database
        // session driver (this project's documented default — see
        // .env.example), which is the only driver that lets a *different*
        // request identify and remove a specific user's active session rows.
        // Other drivers (file/redis/etc.) can't be targeted this way; the
        // suspended account's browser session would then only stop working
        // the next time Laravel needs to re-authorize it against a check
        // that reads isActive() — matching this model's existing "best
        // effort" precedent elsewhere.
        if (config('session.driver') === 'database') {
            $sessionTable = config('session.table');
            DB::table(is_string($sessionTable) ? $sessionTable : 'sessions')
                ->where('user_id', $user->getKey())
                ->delete();
        }
    }
}
