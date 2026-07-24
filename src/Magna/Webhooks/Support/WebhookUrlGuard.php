<?php

declare(strict_types=1);

namespace Magna\Webhooks\Support;

/**
 * SSRF guard for webhook target URLs (S1-04).
 *
 * A webhook URL is fully attacker-controlled (any user with `webhooks.manage`
 * can set it) and the delivery job POSTs to it with the app's own network
 * access. Without this guard, a URL like `http://169.254.169.254/...` (cloud
 * metadata) or `http://127.0.0.1:6379/` (internal service) is delivered to
 * on a schedule, and the response body is stored and readable via the
 * deliveries API — a non-blind SSRF oracle.
 *
 * Resolves the host and rejects any address in a private, loopback,
 * link-local, or otherwise non-publicly-routable range, for both IPv4 and
 * IPv6. Must be called both when a subscription's URL is set (create/update)
 * and again immediately before each dispatch/retry, since DNS can be
 * rebound between the two.
 */
final class WebhookUrlGuard
{
    /**
     * @throws WebhookUrlBlockedException if the URL is missing, uses a
     *                                    disallowed scheme, or resolves to a non-routable address.
     */
    public static function ensureSafe(string $url): void
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new WebhookUrlBlockedException("Webhook URL must use http or https (got \"{$scheme}\").");
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            throw new WebhookUrlBlockedException('Webhook URL has no resolvable host.');
        }

        foreach (self::resolveIps($host) as $ip) {
            if (self::isBlockedIp($ip)) {
                throw new WebhookUrlBlockedException(
                    "Webhook URL host \"{$host}\" resolves to a non-routable address ({$ip}).",
                );
            }
        }
    }

    public static function isSafe(string $url): bool
    {
        try {
            self::ensureSafe($url);

            return true;
        } catch (WebhookUrlBlockedException) {
            return false;
        }
    }

    private static function isBlockedIp(string $ip): bool
    {
        // NO_PRIV_RANGE blocks 10/8, 172.16/12, 192.168/16, fc00::/7.
        // NO_RES_RANGE blocks 0/8, 127/8 (loopback), 169.254/16 (link-local,
        // which includes the 169.254.169.254 cloud metadata endpoint), ::1,
        // and the other IANA-reserved blocks.
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) === false;
    }

    /**
     * @return list<string>
     *
     * @throws WebhookUrlBlockedException if the host cannot be resolved —
     *                                    fail closed rather than let an unresolvable/unstable host
     *                                    through the check.
     */
    private static function resolveIps(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if ($records === false || $records === []) {
            throw new WebhookUrlBlockedException("Webhook URL host \"{$host}\" could not be resolved.");
        }

        $ips = [];
        foreach ($records as $record) {
            if (isset($record['ip']) && is_string($record['ip'])) {
                $ips[] = $record['ip'];
            } elseif (isset($record['ipv6']) && is_string($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        if ($ips === []) {
            throw new WebhookUrlBlockedException("Webhook URL host \"{$host}\" could not be resolved.");
        }

        return $ips;
    }
}
