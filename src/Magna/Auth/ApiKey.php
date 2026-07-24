<?php

declare(strict_types=1);

namespace Magna\Auth;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Magna\Users\User;

/**
 * A key+secret credential pair used by external apps (Flutter, web, etc.)
 * to authenticate against the Magna delivery/management API.
 *
 * The plain-text secret is shown exactly once at generation time and is
 * never stored — only its SHA-256 hash is persisted here.
 *
 * @property string $id
 * @property string $name
 * @property string $key public identifier (X-Magna-Key header)
 * @property string $secret_hash sha256(plain_secret)
 * @property string $scope 'delivery' | 'management'
 * @property int|null $rate_limit_per_minute
 * @property list<string>|null $allowed_origins
 * @property Carbon|null $last_used_at
 * @property Carbon|null $expires_at
 * @property bool $is_active
 * @property string|null $created_by_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ApiKey extends Model
{
    use HasUlids;

    protected $table = 'api_keys';

    protected $fillable = [
        'name',
        'key',
        'secret_hash',
        'scope',
        'rate_limit_per_minute',
        'allowed_origins',
        'last_used_at',
        'expires_at',
        'is_active',
        'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'allowed_origins' => 'array',
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

        return $this->isManagement() ? 120 : 1000;
    }

    /**
     * Verify a plain-text secret matches this key's stored hash.
     * Uses hash_equals to prevent timing attacks.
     */
    public function verifySecret(string $plainSecret): bool
    {
        return hash_equals($this->secret_hash, hash('sha256', $plainSecret));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
