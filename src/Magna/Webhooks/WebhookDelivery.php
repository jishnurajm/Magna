<?php

declare(strict_types=1);

namespace Magna\Webhooks;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $subscription_id
 * @property string $event
 * @property array<string, mixed> $payload
 * @property string $status
 * @property int $attempts
 * @property Carbon|null $last_attempt_at
 * @property int|null $response_code
 * @property string|null $response_body
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class WebhookDelivery extends Model
{
    use HasUlids;

    protected $fillable = [
        'subscription_id',
        'event',
        'payload',
        'status',
        'attempts',
        'last_attempt_at',
        'response_code',
        'response_body',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'last_attempt_at' => 'datetime',
            'response_code' => 'integer',
        ];
    }

    /** @return BelongsTo<WebhookSubscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(WebhookSubscription::class, 'subscription_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isDead(): bool
    {
        return $this->status === 'dead';
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }
}
