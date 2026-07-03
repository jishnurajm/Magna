<?php

declare(strict_types=1);

namespace Magna\Contracts;

/**
 * Plugin contract: declares custom webhook event types plugins can emit.
 * Semver-guaranteed from core 1.0. Wired to the webhook system in Stage 9.
 */
interface RegistersWebhookEvents
{
    /**
     * Return the event keys this plugin can fire (e.g. "acme.match.created").
     *
     * @return list<string>
     */
    public function webhookEvents(): array;
}
