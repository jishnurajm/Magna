<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Magna\Testing\PluginTestCase;
use MagnaMarketplace\Models\Developer;
use MagnaMarketplace\Models\MarketplacePlugin;
use MagnaMarketplace\Notifications\VerifyDeveloperEmail;

uses(PluginTestCase::class);

beforeEach(function (): void {
    $this->enablePlugin('magna-cms/marketplace');
    app('router')->getRoutes()->refreshNameLookups();
});

/** @param array<string, mixed> $attrs */
function verifiedDeveloper(array $attrs): Developer
{
    $developer = Developer::create($attrs);
    $developer->forceFill(['email_verified_at' => now()])->save();

    return $developer;
}

function devFakePackagist(): void
{
    Http::fake([
        'https://packagist.org/*' => Http::response(['package' => [
            'name' => 'acme/forum', 'description' => 'Forums', 'versions' => ['1.2.0' => [
                'version' => '1.2.0', 'type' => 'magna-plugin',
                'require' => ['php' => '^8.3', 'magna-cms/plugin-sdk' => '^1.0'],
                'source' => ['url' => 'https://github.com/acme/forum.git'],
            ]],
        ]]),
        'https://raw.githubusercontent.com/*' => Http::response([
            'displayName' => 'Acme Forum', 'author' => 'Acme',
            'compat' => ['magna' => '^1.0'], 'permissions' => ['forum.thread.manage'],
        ]),
    ]);
}

it('registers a new developer and signs them in', function (): void {
    $this->post('/developer/register', [
        'name' => 'Dev One',
        'email' => 'dev@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertRedirect(route('developer.dashboard'));

    $this->assertAuthenticatedAs(Developer::query()->firstWhere('email', 'dev@example.com'), 'developer');
});

it('redirects guests from the dashboard to the developer login', function (): void {
    $this->get('/developer')->assertRedirect(route('developer.login'));
});

it('lets a signed-in developer submit a valid plugin', function (): void {
    devFakePackagist();
    $developer = verifiedDeveloper(['name' => 'Dev', 'email' => 'd@e.com', 'password' => 'secret123']);

    $this->actingAs($developer, 'developer')
        ->post('/developer/plugins/submit', [
            'package' => 'acme/forum',
            'short_description' => 'Great forums',
        ])
        ->assertRedirect(route('developer.dashboard'));

    $plugin = MarketplacePlugin::query()->firstWhere('package', 'acme/forum');
    expect($plugin)->not->toBeNull()
        ->and($plugin->developer_id)->toBe($developer->id)
        ->and($plugin->status)->toBe('submitted')
        ->and($plugin->short_description)->toBe('Great forums');
});

it("prevents a developer from claiming another developer's package", function (): void {
    devFakePackagist();
    $owner = Developer::create(['name' => 'Owner', 'email' => 'o@e.com', 'password' => 'secret123']);
    MarketplacePlugin::create(['package' => 'acme/forum', 'name' => 'Acme Forum', 'developer_id' => $owner->id, 'status' => 'approved']);

    $intruder = verifiedDeveloper(['name' => 'Intruder', 'email' => 'i@e.com', 'password' => 'secret123']);

    $this->actingAs($intruder, 'developer')
        ->post('/developer/plugins/submit', ['package' => 'acme/forum', 'short_description' => 'Mine now'])
        ->assertSessionHasErrors('package');

    expect(MarketplacePlugin::query()->firstWhere('package', 'acme/forum')->developer_id)->toBe($owner->id);
});

it('accepts multiple screenshots when submitting a plugin', function (): void {
    devFakePackagist();
    Storage::fake('public');
    $developer = verifiedDeveloper(['name' => 'Dev', 'email' => 'shots@e.com', 'password' => 'secret123']);

    $this->actingAs($developer, 'developer')
        ->post('/developer/plugins/submit', [
            'package' => 'acme/forum',
            'short_description' => 'Great forums',
            'screenshots' => [
                UploadedFile::fake()->image('one.png'),
                UploadedFile::fake()->image('two.png'),
                UploadedFile::fake()->image('three.png'),
            ],
        ])
        ->assertRedirect(route('developer.dashboard'));

    expect(MarketplacePlugin::query()->firstWhere('package', 'acme/forum')->screenshots)->toHaveCount(3);
});

it('lets an owner preview their plugin but blocks others', function (): void {
    $owner = Developer::create(['name' => 'Owner', 'email' => 'own@e.com', 'password' => 'secret123']);
    $plugin = MarketplacePlugin::create(['package' => 'acme/forum', 'name' => 'Acme Forum', 'developer_id' => $owner->id, 'status' => 'approved']);

    $this->actingAs($owner, 'developer')->get("/developer/plugins/{$plugin->id}")->assertOk()->assertSee('Acme Forum');

    $intruder = Developer::create(['name' => 'Intruder', 'email' => 'int@e.com', 'password' => 'secret123']);
    $this->actingAs($intruder, 'developer')->get("/developer/plugins/{$plugin->id}")->assertForbidden();
});

it('lets a developer edit an approved plugin without changing its status', function (): void {
    Storage::fake('public');
    $owner = verifiedDeveloper(['name' => 'Owner', 'email' => 'edit@e.com', 'password' => 'secret123']);
    $plugin = MarketplacePlugin::create([
        'package' => 'acme/forum', 'name' => 'Acme Forum', 'developer_id' => $owner->id,
        'status' => 'approved', 'short_description' => 'Old', 'screenshots' => [],
    ]);

    $this->actingAs($owner, 'developer')
        ->put("/developer/plugins/{$plugin->id}", [
            'name' => 'Acme Forum',
            'short_description' => 'New and improved',
            'screenshots' => [UploadedFile::fake()->image('shot.png')],
        ])
        ->assertRedirect(route('developer.plugins.show', $plugin));

    $plugin->refresh();
    expect($plugin->short_description)->toBe('New and improved')
        ->and($plugin->status)->toBe('approved')
        ->and($plugin->screenshots)->toHaveCount(1);
});

it('leaves a freshly registered developer unverified', function (): void {
    $this->post('/developer/register', [
        'name' => 'Dev', 'email' => 'verify@e.com',
        'password' => 'password123', 'password_confirmation' => 'password123',
    ])->assertRedirect(route('developer.dashboard'));

    expect(Developer::query()->firstWhere('email', 'verify@e.com')->hasVerifiedEmail())->toBeFalse();
});

it('sends the console verification notification', function (): void {
    Notification::fake();

    $developer = Developer::create(['name' => 'Dev', 'email' => 'notify@e.com', 'password' => 'secret123']);
    $developer->sendEmailVerificationNotification();

    Notification::assertSentTo($developer, VerifyDeveloperEmail::class);
});

it('blocks unverified developers from the submit page', function (): void {
    $developer = Developer::create(['name' => 'Dev', 'email' => 'unv@e.com', 'password' => 'secret123']);

    $this->actingAs($developer, 'developer')
        ->get('/developer/plugins/submit')
        ->assertRedirect(route('developer.verification.notice'));
});

it('verifies email via the signed link and then allows submitting', function (): void {
    $developer = Developer::create(['name' => 'Dev', 'email' => 'link@e.com', 'password' => 'secret123']);

    $url = URL::temporarySignedRoute('developer.verification.verify', now()->addHour(), [
        'id' => $developer->id,
        'hash' => sha1($developer->getEmailForVerification()),
    ]);

    $this->actingAs($developer, 'developer')->get($url)->assertRedirect(route('developer.dashboard'));

    expect($developer->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('submits a new version and returns the plugin to review', function (): void {
    devFakePackagist();
    $owner = verifiedDeveloper(['name' => 'Owner', 'email' => 'ver@e.com', 'password' => 'secret123']);
    $plugin = MarketplacePlugin::create([
        'package' => 'acme/forum', 'name' => 'Acme Forum', 'developer_id' => $owner->id, 'status' => 'approved',
    ]);

    $this->actingAs($owner, 'developer')
        ->post("/developer/plugins/{$plugin->id}/version")
        ->assertRedirect(route('developer.plugins.show', $plugin));

    $plugin->refresh();
    expect($plugin->status)->toBe('submitted')
        ->and($plugin->versions()->where('version', '1.2.0')->exists())->toBeTrue();
});
