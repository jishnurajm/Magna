<?php

declare(strict_types=1);

use Magna\Contracts\RegistersAdminResources;
use Magna\Contracts\RegistersDashboardWidgets;
use Magna\Plugins\PluginManager;
use Magna\Testing\PluginTestCase;
use MagnaMarketplace\Filament\Resources\MarketplacePluginResource;
use MagnaMarketplace\Filament\Widgets\MarketplaceStatsWidget;
use MagnaMarketplace\Models\MagnaAccount;
use MagnaMarketplace\Models\MagnaAccountSite;
use MagnaMarketplace\Models\MarketplacePlugin;
use MagnaMarketplace\Models\MarketplaceReport;

uses(PluginTestCase::class);

beforeEach(function (): void {
    $this->enablePlugin('magna-cms/marketplace');
    // Plugin routes are registered after app boot in tests.
    app('router')->getRoutes()->refreshNameLookups();
});

/** @param  list<string>  $permissions */
function seedMarketplacePlugin(string $package, string $status, string $compat = '^1.0', array $permissions = []): MarketplacePlugin
{
    $plugin = MarketplacePlugin::create([
        'package' => $package,
        'name' => ucfirst(explode('/', $package)[1]),
        'short_description' => 'Desc for '.$package,
        'author' => 'Acme',
        'status' => $status,
        'installs' => 5,
    ]);

    $plugin->versions()->create([
        'version' => '1.2.0',
        'compat' => $compat,
        'permissions' => $permissions,
        'released_at' => now(),
    ]);

    return $plugin;
}

/** Connects a fresh Magna Account + site (unique fingerprint) and returns its bearer token. */
function connectedSiteToken(string $fingerprint): string
{
    $account = MagnaAccount::create([
        'name' => 'Dev', 'email' => $fingerprint.'@example.com', 'provider' => 'google', 'provider_id' => $fingerprint,
    ]);

    $token = MagnaAccountSite::newToken();
    MagnaAccountSite::create([
        'magna_account_id' => $account->id,
        'site_url' => 'https://'.$fingerprint.'.test',
        'fingerprint' => $fingerprint,
        'token_hash' => MagnaAccountSite::hash($token),
        'connected_at' => now(),
    ]);

    return $token;
}

it('lists only approved plugins in the client contract shape', function (): void {
    seedMarketplacePlugin('acme/forum', 'approved', '^1.0', ['forum.thread.manage']);
    seedMarketplacePlugin('acme/pending', 'submitted');

    $data = $this->getJson('/api/v1/plugins')->assertOk()->json();

    expect($data)->toHaveCount(1)
        ->and($data[0]['package'])->toBe('acme/forum')
        ->and($data[0]['version'])->toBe('1.2.0')
        ->and($data[0]['compat'])->toBe('^1.0')
        ->and($data[0]['permissions'])->toBe(['forum.thread.manage']);
});

it('exposes the developer-entered website in the catalog summary', function (): void {
    $plugin = seedMarketplacePlugin('acme/forum', 'approved');
    $plugin->update(['website' => 'https://acmeforum.example.com']);

    $data = $this->getJson('/api/v1/plugins')->assertOk()->json();

    expect($data[0]['website'])->toBe('https://acmeforum.example.com');
});

it('omits the website when a plugin has none', function (): void {
    seedMarketplacePlugin('acme/forum', 'approved');

    $data = $this->getJson('/api/v1/plugins')->assertOk()->json();

    expect($data[0]['website'])->toBeNull();
});

it('filters the catalog by the requesting core version', function (): void {
    seedMarketplacePlugin('acme/ok', 'approved', '^1.0');
    seedMarketplacePlugin('acme/future', 'approved', '^2.0');

    $data = $this->getJson('/api/v1/plugins?magna=1.5.0')->assertOk()->json();

    expect(array_column($data, 'package'))->toContain('acme/ok')->not->toContain('acme/future');
});

it('returns a single plugin detail and 404 for an unknown package', function (): void {
    seedMarketplacePlugin('acme/forum', 'approved');

    $this->getJson('/api/v1/plugins/acme/forum')
        ->assertOk()
        ->assertJsonPath('package', 'acme/forum')
        ->assertJsonPath('shortDescription', 'Desc for acme/forum');

    $this->getJson('/api/v1/plugins/acme/nope')->assertNotFound();
});

it('lists published versions newest first', function (): void {
    $plugin = seedMarketplacePlugin('acme/forum', 'approved');
    $plugin->versions()->create(['version' => '1.1.0', 'compat' => '^1.0', 'released_at' => now()->subDay()]);

    $data = $this->getJson('/api/v1/plugins/acme/forum/versions')->assertOk()->json();

    expect(array_column($data, 'version'))->toBe(['1.2.0', '1.1.0']);
});

it('exposes screenshot URLs in the plugin detail', function (): void {
    $plugin = seedMarketplacePlugin('acme/forum', 'approved');
    $plugin->update(['screenshots' => ['marketplace/screenshots/a.png', 'marketplace/screenshots/b.png']]);

    $shots = $this->getJson('/api/v1/plugins/acme/forum')->assertOk()->json('screenshots');

    expect($shots)->toHaveCount(2)
        ->and($shots[0])->toContain('marketplace/screenshots/a.png');
});

it('records an install ping and counts it once per caller', function (): void {
    $plugin = seedMarketplacePlugin('acme/forum', 'approved'); // installs starts at 5

    $this->postJson('/api/v1/plugins/acme/forum/installed')->assertOk()->assertJsonPath('installs', 6);
    // A second ping from the same caller is de-duplicated.
    $this->postJson('/api/v1/plugins/acme/forum/installed')->assertOk()->assertJsonPath('installs', 6);

    expect($plugin->fresh()->installs)->toBe(6);
});

it('rejects a rating from a site with no connected Magna Account', function (): void {
    seedMarketplacePlugin('acme/forum', 'approved');

    $this->postJson('/api/v1/plugins/acme/forum/ratings', ['stars' => 4])->assertStatus(401);
});

it('rejects a report from a site with no connected Magna Account', function (): void {
    seedMarketplacePlugin('acme/forum', 'approved');

    $this->postJson('/api/v1/plugins/acme/forum/reports', ['reason' => 'spam'])->assertStatus(401);
});

it('accepts a star rating and reflects the average in the catalog', function (): void {
    seedMarketplacePlugin('acme/forum', 'approved');
    $tokenA = connectedSiteToken('site-a');
    $tokenB = connectedSiteToken('site-b');

    // Whole-number averages serialize as JSON integers (4.0 → 4).
    $this->withToken($tokenA)->postJson('/api/v1/plugins/acme/forum/ratings', ['stars' => 4])
        ->assertOk()->assertJsonPath('ratingsCount', 1)->assertJsonPath('rating', 4);

    $this->withToken($tokenB)->postJson('/api/v1/plugins/acme/forum/ratings', ['stars' => 5])
        ->assertOk()->assertJsonPath('ratingsCount', 2)->assertJsonPath('rating', 4.5);

    // Same site re-rating updates rather than adds.
    $this->withToken($tokenA)->postJson('/api/v1/plugins/acme/forum/ratings', ['stars' => 2])
        ->assertOk()->assertJsonPath('ratingsCount', 2)->assertJsonPath('rating', 3.5);

    $summary = $this->getJson('/api/v1/plugins')->assertOk()->json('0');
    expect($summary['rating'])->toBe(3.5)->and($summary['ratingsCount'])->toBe(2);
});

it('rejects an out-of-range star rating', function (): void {
    seedMarketplacePlugin('acme/forum', 'approved');
    $token = connectedSiteToken('site-a');

    $this->withToken($token)->postJson('/api/v1/plugins/acme/forum/ratings', ['stars' => 9])
        ->assertStatus(422);
});

// S1-14: previously a script could loop POSTs with a different 'site' value
// each time to inflate a plugin's rating without limit — ratings/reports
// had no rate limiting at all. Login is now required too, but the throttle
// still guards against one connected account hammering the endpoint.
it('rate limits repeated rating submissions from the same caller', function (): void {
    seedMarketplacePlugin('acme/forum', 'approved');
    $token = connectedSiteToken('site-a');

    for ($i = 0; $i < 10; $i++) {
        $this->withToken($token)->postJson('/api/v1/plugins/acme/forum/ratings', ['stars' => 5])
            ->assertOk();
    }

    $this->withToken($token)->postJson('/api/v1/plugins/acme/forum/ratings', ['stars' => 5])
        ->assertStatus(429);
});

it('rate limits repeated report submissions from the same caller', function (): void {
    seedMarketplacePlugin('acme/forum', 'approved');
    $token = connectedSiteToken('site-a');

    for ($i = 0; $i < 10; $i++) {
        $this->withToken($token)->postJson('/api/v1/plugins/acme/forum/reports', [
            'reason' => 'spam',
        ])->assertStatus(201);
    }

    $this->withToken($token)->postJson('/api/v1/plugins/acme/forum/reports', ['reason' => 'spam'])
        ->assertStatus(429);
});

it('stores a written review and lists it, newest first', function (): void {
    seedMarketplacePlugin('acme/forum', 'approved');
    $tokenA = connectedSiteToken('site-a');
    $tokenB = connectedSiteToken('site-b');

    $this->withToken($tokenA)->postJson('/api/v1/plugins/acme/forum/ratings', ['stars' => 4, 'author' => 'Ada', 'review' => 'Solid plugin.'])->assertOk();
    $this->withToken($tokenB)->postJson('/api/v1/plugins/acme/forum/ratings', ['stars' => 2])->assertOk(); // no text → not a review

    $reviews = $this->getJson('/api/v1/plugins/acme/forum/reviews')->assertOk()->json();

    expect($reviews)->toHaveCount(1)
        ->and($reviews[0]['author'])->toBe('Ada')
        ->and($reviews[0]['stars'])->toBe(4)
        ->and($reviews[0]['review'])->toBe('Solid plugin.');

    // The catalog detail also carries recent reviews.
    expect($this->getJson('/api/v1/plugins/acme/forum')->json('reviews'))->toHaveCount(1);
});

it('accepts a plugin report and queues it for the operator', function (): void {
    $plugin = seedMarketplacePlugin('acme/forum', 'approved');
    $token = connectedSiteToken('site-a');

    $this->withToken($token)->postJson('/api/v1/plugins/acme/forum/reports', [
        'reason' => 'malicious', 'details' => 'Ships a backdoor.',
    ])->assertCreated();

    $report = MarketplaceReport::query()->firstWhere('marketplace_plugin_id', $plugin->id);
    expect($report)->not->toBeNull()
        ->and($report->reason)->toBe('malicious')
        ->and($report->status)->toBe('open')
        ->and($report->magna_account_id)->not->toBeNull();
});

it('rejects a report with an unknown reason', function (): void {
    seedMarketplacePlugin('acme/forum', 'approved');
    $token = connectedSiteToken('site-a');

    $this->withToken($token)->postJson('/api/v1/plugins/acme/forum/reports', ['reason' => 'because'])
        ->assertStatus(422);
});

it('counts open reports in the dashboard widget', function (): void {
    $plugin = seedMarketplacePlugin('acme/forum', 'approved');
    $plugin->reports()->create(['site' => 's1', 'reason' => 'spam', 'status' => 'open']);
    $plugin->reports()->create(['site' => 's2', 'reason' => 'broken', 'status' => 'resolved']);

    expect(MarketplaceStatsWidget::counts()['reports'])->toBe(1);
});

it('registers the review resource with the admin panel', function (): void {
    $plugin = app(PluginManager::class)->getEnabled()['magna-cms/marketplace'] ?? null;

    expect($plugin)->toBeInstanceOf(RegistersAdminResources::class)
        ->and($plugin->adminResources())->toContain(MarketplacePluginResource::class);
});

it('registers the dashboard overview widget', function (): void {
    $plugin = app(PluginManager::class)->getEnabled()['magna-cms/marketplace'] ?? null;

    expect($plugin)->toBeInstanceOf(RegistersDashboardWidgets::class)
        ->and($plugin->dashboardWidgets())->toContain(MarketplaceStatsWidget::class);
});

it('computes dashboard counts by status and total installs', function (): void {
    seedMarketplacePlugin('acme/a', 'submitted');
    seedMarketplacePlugin('acme/b', 'approved');
    seedMarketplacePlugin('acme/c', 'approved');
    seedMarketplacePlugin('acme/d', 'rejected');
    // seed installs = 5 each → 4 plugins → 20 total.

    expect(MarketplaceStatsWidget::counts())->toBe([
        'pending' => 1,
        'approved' => 2,
        'rejected' => 1,
        'installs' => 20,
        'reports' => 0,
    ]);
});
