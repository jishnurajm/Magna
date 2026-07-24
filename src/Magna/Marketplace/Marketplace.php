<?php

declare(strict_types=1);

namespace Magna\Marketplace;

/**
 * Compile-time constants for the official Magna plugin marketplace.
 *
 * The API base URL is intentionally a hardcoded constant (not a config value)
 * so every Magna install points at the official registry by default.
 */
final class Marketplace
{
    /** Origin of the marketplace server — the browser-facing legs of Account Centre's connect handshake redirect here directly (not through /api/v1). */
    public const WEB_BASE = 'https://managemagna.jrstudios.dev';

    /** Base URL of the marketplace catalog API (v1). */
    public const API_BASE = self::WEB_BASE.'/api/v1';

    /** How long a catalog response is cached, in seconds. */
    public const CACHE_TTL = 3600;

    /** HTTP request timeout, in seconds. */
    public const REQUEST_TIMEOUT = 8;

    /** Cache key for the full catalog listing. */
    public const CACHE_KEY = 'magna.marketplace.plugins';
}
