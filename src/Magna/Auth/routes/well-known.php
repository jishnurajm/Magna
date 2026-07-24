<?php

declare(strict_types=1);

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

/**
 * RFC 9116 — security.txt well-known URL.
 * Gives security researchers a canonical place to report vulnerabilities.
 */
Route::get('/.well-known/security.txt', function (): Response {
    $rawUrl = config('app.url', '');
    $appUrl = rtrim(is_string($rawUrl) ? $rawUrl : '', '/');
    $expires = now()->utc()->addYear()->toIso8601ZuluString();

    $content = implode("\n", [
        'Contact: mailto:security@magna.dev',
        "Policy: {$appUrl}/security",
        'Preferred-Languages: en',
        "Expires: {$expires}",
        'Acknowledgments: https://github.com/magna-cms/magna/security/advisories',
        '',
    ]);

    return response($content, 200, [
        'Content-Type' => 'text/plain; charset=utf-8',
        'Cache-Control' => 'public, max-age=86400',
    ]);
})->name('well-known.security-txt');
