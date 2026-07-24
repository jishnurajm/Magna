<?php

declare(strict_types=1);

namespace Magna\Auth;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Extended Sanctum token model adding scope, per-token rate limit, and
 * a typed expiry check used by MagnaApiMiddleware.
 *
 * @property string $scope
 * @property int|null $rate_limit_per_minute
 * @property Carbon|null $expires_at
 */
class MagnaToken extends PersonalAccessToken
{
    protected $table = 'personal_access_tokens';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'abilities' => 'json',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isDelivery(): bool
    {
        return $this->scope === 'delivery';
    }

    public function isManagement(): bool
    {
        return $this->scope === 'management';
    }

    public function effectiveRateLimit(): int
    {
        if ($this->rate_limit_per_minute !== null) {
            return $this->rate_limit_per_minute;
        }

        return $this->isManagement()
            ? Config::integer('magna.token_rate_limit.management', 120)
            : Config::integer('magna.token_rate_limit.delivery', 1000);
    }
}
