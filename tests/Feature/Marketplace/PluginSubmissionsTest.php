<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Magna\Audit\AuditLog;
use Magna\Testing\PluginTestCase;
use MagnaMarketplace\Models\Developer;
use MagnaMarketplace\Models\MarketplacePlugin;
use MagnaMarketplace\PluginSubmissions;
use MagnaMarketplace\Support\PackagistInspector;

uses(PluginTestCase::class);

beforeEach(function (): void {
    $this->enablePlugin('magna-cms/marketplace');
});

/**
 * @param  array<string, mixed>  $versionOverrides
 * @param  array<string, mixed>  $manifestOverrides
 */
function fakePackagist(array $versionOverrides = [], array $manifestOverrides = []): void
{
    $version = array_merge([
        'name' => 'acme/forum', 'version' => '1.2.0', 'type' => 'magna-plugin',
        'require' => ['php' => '^8.3', 'magna-cms/plugin-sdk' => '^1.0'],
        'source' => ['url' => 'https://github.com/acme/forum.git', 'type' => 'git'],
    ], $versionOverrides);

    $manifest = array_merge([
        'displayName' => 'Acme Forum', 'author' => 'Acme',
        'compat' => ['magna' => '^1.0'], 'permissions' => ['forum.thread.manage'],
    ], $manifestOverrides);

    Http::fake([
        'https://packagist.org/*' => Http::response(['package' => [
            'name' => 'acme/forum', 'description' => 'Community forums', 'versions' => ['1.2.0' => $version],
        ]]),
        'https://raw.githubusercontent.com/*' => Http::response($manifest),
    ]);
}

it('passes a valid magna-plugin and creates a submitted catalog entry', function (): void {
    fakePackagist();

    $result = app(PluginSubmissions::class)->submit('acme/forum');

    expect($result->ok)->toBeTrue();

    $plugin = MarketplacePlugin::query()->where('package', 'acme/forum')->first();
    expect($plugin)->not->toBeNull()
        ->and($plugin->status)->toBe('submitted')
        ->and($plugin->name)->toBe('Acme Forum')
        ->and($plugin->versions()->first()->compat)->toBe('^1.0')
        ->and($plugin->versions()->first()->permissions)->toBe(['forum.thread.manage']);
});

// Stage 13 (C3-11, full fix): self-service claiming of a pre-existing
// unclaimed listing is refused outright now — the only path to giving such
// a listing an owner is PluginSubmissions::transferOwnership(), an
// admin-mediated action (see MarketplacePluginResource's "Assign developer"
// action, gated by marketplace.plugins.review).
it('refuses to let a developer self-service-claim a pre-existing unclaimed listing', function (): void {
    fakePackagist();
    app(PluginSubmissions::class)->submit('acme/forum'); // admin-pre-seeded, developer_id null
    $plugin = MarketplacePlugin::query()->where('package', 'acme/forum')->firstOrFail();
    app(PluginSubmissions::class)->approve($plugin);
    expect($plugin->fresh()->status)->toBe('approved');

    $developer = Developer::create(['name' => 'Claimer', 'email' => 'claimer@example.com', 'password' => 'x']);

    $result = app(PluginSubmissions::class)->submitForDeveloper($developer, [
        'package' => 'acme/forum',
        'short_description' => 'Claimed listing',
    ]);

    expect($result->ok)->toBeFalse()
        ->and($result->failures)->toHaveKey('owner');

    // Nothing about the pre-existing listing changed.
    $plugin->refresh();
    expect($plugin->developer_id)->toBeNull()
        ->and($plugin->status)->toBe('approved');
});

it('lets an admin transfer ownership of an unclaimed listing, resetting it to submitted for re-review', function (): void {
    fakePackagist();
    app(PluginSubmissions::class)->submit('acme/forum');
    $plugin = MarketplacePlugin::query()->where('package', 'acme/forum')->firstOrFail();
    app(PluginSubmissions::class)->approve($plugin);

    $developer = Developer::create(['name' => 'New Owner', 'email' => 'newowner@example.com', 'password' => 'x']);
    $adminId = (string) Str::ulid();

    app(PluginSubmissions::class)->transferOwnership($plugin, $developer, $adminId);

    $plugin->refresh();
    expect($plugin->developer_id)->toBe($developer->id)
        // Transferring an approved listing must drop it out of the public
        // catalog until it's re-reviewed, not silently stay live.
        ->and($plugin->status)->toBe('submitted');

    $log = AuditLog::query()->where('action', 'marketplace.plugin_ownership_assigned')->first();
    expect($log)->not->toBeNull()
        ->and($log->actor_id)->toBe($adminId); // @phpstan-ignore-line
});

it('refuses to transfer ownership of a listing that already has one', function (): void {
    fakePackagist();
    $owner = Developer::create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'x']);
    app(PluginSubmissions::class)->submitForDeveloper($owner, ['package' => 'acme/forum']);
    $plugin = MarketplacePlugin::query()->where('package', 'acme/forum')->firstOrFail();

    $other = Developer::create(['name' => 'Other', 'email' => 'other@example.com', 'password' => 'x']);

    expect(fn () => app(PluginSubmissions::class)->transferOwnership($plugin, $other))
        ->toThrow(RuntimeException::class);

    expect($plugin->fresh()->developer_id)->toBe($owner->id);
});

it('stores and updates the developer-entered website, separate from the Packagist-derived homepage', function (): void {
    fakePackagist();
    $developer = Developer::create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'x']);

    app(PluginSubmissions::class)->submitForDeveloper($developer, [
        'package' => 'acme/forum',
        'website' => 'https://acmeforum.example.com',
    ]);

    $plugin = MarketplacePlugin::query()->where('package', 'acme/forum')->firstOrFail();
    expect($plugin->website)->toBe('https://acmeforum.example.com')
        ->and($plugin->homepage)->toBe('https://github.com/acme/forum.git');

    app(PluginSubmissions::class)->updateDetails($plugin, [
        'short_description' => $plugin->short_description,
        'website' => 'https://new-site.example.com',
    ]);

    expect($plugin->fresh()->website)->toBe('https://new-site.example.com');
});

it('allows an ordinary re-submission by the plugin\'s own developer', function (): void {
    fakePackagist();
    $developer = Developer::create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'x']);

    app(PluginSubmissions::class)->submitForDeveloper($developer, ['package' => 'acme/forum']);
    $result = app(PluginSubmissions::class)->submitForDeveloper($developer, ['package' => 'acme/forum', 'short_description' => 'Updated']);

    expect($result->ok)->toBeTrue();
    expect(MarketplacePlugin::query()->where('package', 'acme/forum')->firstOrFail()->developer_id)->toBe($developer->id);
});

it('rejects a package that is not a magna-plugin and creates nothing', function (): void {
    fakePackagist(['type' => 'library']);

    $result = app(PluginSubmissions::class)->submit('acme/forum');

    expect($result->ok)->toBeFalse()
        ->and($result->failures)->toHaveKey('type')
        ->and(MarketplacePlugin::query()->count())->toBe(0);
});

it('rejects a package that does not require the SDK', function (): void {
    fakePackagist(['require' => ['php' => '^8.3']]);

    expect(app(PackagistInspector::class)->inspect('acme/forum')->failures)->toHaveKey('sdk');
});

it('rejects a package that is not on Packagist', function (): void {
    Http::fake(['https://packagist.org/*' => Http::response('', 404)]);

    $result = app(PackagistInspector::class)->inspect('acme/ghost');

    expect($result->ok)->toBeFalse()->and($result->failures)->toHaveKey('exists');
});

it('moves a submission through approve and reject', function (): void {
    fakePackagist();
    app(PluginSubmissions::class)->submit('acme/forum');
    $plugin = MarketplacePlugin::query()->where('package', 'acme/forum')->firstOrFail();

    app(PluginSubmissions::class)->approve($plugin);
    expect($plugin->fresh()->status)->toBe('approved');

    app(PluginSubmissions::class)->reject($plugin);
    expect($plugin->fresh()->status)->toBe('rejected');
});
