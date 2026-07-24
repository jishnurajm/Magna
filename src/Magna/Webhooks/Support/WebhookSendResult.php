<?php

declare(strict_types=1);

namespace Magna\Webhooks\Support;

final class WebhookSendResult
{
    public function __construct(
        public readonly ?int $responseCode,
        public readonly ?string $responseBody,
        public readonly bool $success,
    ) {}
}
