<?php

declare(strict_types=1);

namespace Magna\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

/**
 * Exponential-backoff brute-force protection for the login endpoint.
 *
 * Each failure increases the lockout window: base * 2^(excess failures).
 * The attempt counter lives in cache under a hashed key derived from the
 * lowercased email + IP address, so neither value leaks to cache backends.
 */
class LoginThrottle
{
    private int $maxAttempts;

    private int $baseLockoutSeconds;

    private int $maxLockoutSeconds;

    public function __construct()
    {
        $this->maxAttempts = Config::integer('magna.login.max_attempts', 5);
        $this->baseLockoutSeconds = Config::integer('magna.login.base_lockout_seconds', 30);
        $this->maxLockoutSeconds = Config::integer('magna.login.max_lockout_seconds', 900);
    }

    public function isLocked(Request $request): bool
    {
        return Cache::has($this->lockKey($request));
    }

    /** Seconds until the current lockout expires, or 0 if not locked. */
    public function availableIn(Request $request): int
    {
        $ttl = Cache::getStore()->get($this->lockKey($request));

        if (! is_numeric($ttl)) {
            return 0;
        }

        return max(0, is_int($ttl) ? $ttl : 0);
    }

    public function hit(Request $request): void
    {
        $attemptsKey = $this->attemptsKey($request);
        $cached = Cache::get($attemptsKey, 0);
        $attempts = (is_int($cached) ? $cached : 0) + 1;

        Cache::put($attemptsKey, $attempts, now()->addDay());

        if ($attempts >= $this->maxAttempts) {
            $excess = $attempts - $this->maxAttempts;
            $lockoutSeconds = min(
                $this->baseLockoutSeconds * (2 ** $excess),
                $this->maxLockoutSeconds,
            );

            // Store the expiry timestamp so availableIn() can compute TTL.
            Cache::put($this->lockKey($request), now()->addSeconds($lockoutSeconds)->timestamp, $lockoutSeconds);
        }
    }

    public function clear(Request $request): void
    {
        Cache::forget($this->attemptsKey($request));
        Cache::forget($this->lockKey($request));
    }

    private function attemptsKey(Request $request): string
    {
        return 'login.attempts:'.$this->fingerprint($request);
    }

    private function lockKey(Request $request): string
    {
        return 'login.lock:'.$this->fingerprint($request);
    }

    private function fingerprint(Request $request): string
    {
        $email = mb_strtolower($request->string('email')->toString());

        return hash('sha256', $email.'|'.$request->ip());
    }
}
