<?php

declare(strict_types=1);

namespace Magna\Webhooks\Support;

use Illuminate\Support\Facades\Http;

/** Sends one signed webhook HTTP POST and reports the outcome. */
class WebhookSender
{
    public function send(string $url, string $payload, string $signature, int $timestamp): WebhookSendResult
    {
        $response = Http::timeout(10)
            ->withOptions(['allow_redirects' => false])
            ->withHeaders([
                'X-Magna-Signature-256' => $signature,
                'X-Magna-Timestamp' => (string) $timestamp,
            ])
            ->withBody($payload, 'application/json')
            ->post($url);

        return new WebhookSendResult(
            responseCode: $response->status(),
            responseBody: substr($response->body(), 0, 2000),
            success: $response->successful(),
        );
    }
}
