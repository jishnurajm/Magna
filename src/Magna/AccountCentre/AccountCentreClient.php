<?php

declare(strict_types=1);

namespace Magna\AccountCentre;

use Illuminate\Support\Facades\Http;
use Magna\Marketplace\Marketplace;

/**
 * Talks to Update Manager's Magna Account endpoints on managemagna.jrstudios.dev
 * — the exchange step (server-to-server, right after the browser comes back
 * with a code) and the two authenticated calls (sites, disconnect) made with
 * the bearer token that exchange() returns. Best-effort like
 * MarketplaceClient/UpdateCheckClient: never throws, callers handle null.
 */
class AccountCentreClient
{
    /**
     * @return array{token: string, account: array{name: string, email: string}}|null
     */
    public function exchange(string $code, string $siteUrl, string $fingerprint, ?string $siteLabel): ?array
    {
        $raw = $this->post('/account/exchange', array_filter([
            'code' => $code,
            'site_url' => $siteUrl,
            'fingerprint' => $fingerprint,
            'site_label' => $siteLabel,
        ], fn (mixed $v): bool => $v !== null));

        if (! is_array($raw) || ! is_string($raw['token'] ?? null) || ! is_array($raw['account'] ?? null)) {
            return null;
        }

        $accountName = $raw['account']['name'] ?? null;
        $accountEmail = $raw['account']['email'] ?? null;

        return [
            'token' => $raw['token'],
            'account' => [
                'name' => is_string($accountName) ? $accountName : '',
                'email' => is_string($accountEmail) ? $accountEmail : '',
            ],
        ];
    }

    /**
     * Every Magna CMS install connected to the same account as this one.
     *
     * @return list<array{site_url: string, site_label: ?string, is_this_site: bool, connected_at: string, last_seen_at: ?string}>
     */
    public function sites(string $token): array
    {
        $raw = $this->get('/account/sites', $token);
        if (! is_array($raw) || ! is_array($raw['sites'] ?? null)) {
            return [];
        }

        $sites = [];
        foreach ($raw['sites'] as $site) {
            if (! is_array($site) || ! is_string($site['site_url'] ?? null) || ! is_string($site['connected_at'] ?? null)) {
                continue;
            }

            $sites[] = [
                'site_url' => $site['site_url'],
                'site_label' => is_string($site['site_label'] ?? null) ? $site['site_label'] : null,
                'is_this_site' => (bool) ($site['is_this_site'] ?? false),
                'connected_at' => $site['connected_at'],
                'last_seen_at' => is_string($site['last_seen_at'] ?? null) ? $site['last_seen_at'] : null,
            ];
        }

        return $sites;
    }

    public function disconnect(string $token): void
    {
        // Best-effort: the local disconnect (AccountCentreSettings cleared)
        // must never be blocked by this call failing — the site always gets
        // to forget its own connection even if Update Manager is unreachable.
        $this->post('/account/disconnect', [], $token);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<array-key, mixed>|null
     */
    private function post(string $path, array $payload, ?string $token = null): ?array
    {
        try {
            $request = Http::timeout(Marketplace::REQUEST_TIMEOUT)->acceptJson()->asJson();
            if ($token !== null) {
                $request = $request->withToken($token);
            }

            $response = $request->post(Marketplace::API_BASE.$path, $payload);
            if (! $response->successful()) {
                return null;
            }

            $json = $response->json();

            return is_array($json) ? $json : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<array-key, mixed>|null */
    private function get(string $path, string $token): ?array
    {
        try {
            $response = Http::timeout(Marketplace::REQUEST_TIMEOUT)->acceptJson()->withToken($token)->get(Marketplace::API_BASE.$path);
            if (! $response->successful()) {
                return null;
            }

            $json = $response->json();

            return is_array($json) ? $json : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
