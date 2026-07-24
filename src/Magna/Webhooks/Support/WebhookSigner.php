<?php

declare(strict_types=1);

namespace Magna\Webhooks\Support;

/**
 * Signs webhook payloads so consumers can verify authenticity and enforce a
 * replay window: HMAC-SHA256("{timestamp}.{body}", secret).
 */
class WebhookSigner
{
    public function sign(string $payload, string $secret, int $timestamp): string
    {
        return 'sha256='.hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
    }
}
