<?php

declare(strict_types=1);

namespace Magna\Support;

/**
 * A stable, opaque identifier for this Magna install — derived from app.key
 * (falling back to app.url, then a constant) so every outbound integration
 * that needs to recognize "this install" resolves to the same fingerprint:
 * Marketplace\MarketplaceClient::siteId(), Updater\UpdateCheckClient::siteId(),
 * AccountCentre\AccountCentreController::fingerprint().
 *
 * Previously implemented identically in all three places; extracted here so
 * there's exactly one derivation to reason about (and change, if the seed
 * strategy ever needs to).
 */
class InstallFingerprint
{
    public static function derive(): string
    {
        $key = config('app.key');
        $url = config('app.url');

        $seed = match (true) {
            is_string($key) && $key !== '' => $key,
            is_string($url) && $url !== '' => $url,
            default => 'magna',
        };

        return substr(hash('sha256', $seed), 0, 32);
    }
}
