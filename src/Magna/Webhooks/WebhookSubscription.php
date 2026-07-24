<?php

declare(strict_types=1);

namespace Magna\Webhooks;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $url
 * @property string $secret
 * @property list<string> $events
 * @property bool $active
 * @property string|null $description
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class WebhookSubscription extends Model
{
    use HasUlids;

    protected $fillable = [
        'url',
        'secret',
        'events',
        'active',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'active' => 'boolean',
        ];
    }

    /** @return HasMany<WebhookDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class, 'subscription_id');
    }

    public function subscribesTo(string $eventKey): bool
    {
        return in_array($eventKey, $this->events, true);
    }
}
