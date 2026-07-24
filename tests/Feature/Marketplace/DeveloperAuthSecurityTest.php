<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Magna\Testing\PluginTestCase;
use MagnaMarketplace\Http\Controllers\Developer\SocialAuthController;
use MagnaMarketplace\Models\Developer;
use MagnaMarketplace\Support\SocialProviders;
use MagnaMarketplace\Support\Turnstile;

uses(PluginTestCase::class);

beforeEach(function (): void {
    $this->enablePlugin('magna-cms/marketplace');
    app('router')->getRoutes()->refreshNameLookups();
});

// ── Cloudflare Turnstile ──────────────────────────────────────────────────────

it('skips the bot check when Turnstile is not configured', function (): void {
    expect(app(Turnstile::class)->verify(null))->toBeTrue();
});

it('passes the bot check when Cloudflare returns success', function (): void {
    config(['marketplace.turnstile.site_key' => 'sk', 'marketplace.turnstile.secret' => 'secret']);
    Http::fake(['https://challenges.cloudflare.com/*' => Http::response(['success' => true])]);

    expect(app(Turnstile::class)->verify('token', '1.2.3.4'))->toBeTrue();
});

it('fails the bot check when Cloudflare rejects, or the token is missing', function (): void {
    config(['marketplace.turnstile.site_key' => 'sk', 'marketplace.turnstile.secret' => 'secret']);
    Http::fake(['https://challenges.cloudflare.com/*' => Http::response(['success' => false])]);

    expect(app(Turnstile::class)->verify('bad-token'))->toBeFalse()
        ->and(app(Turnstile::class)->verify(null))->toBeFalse();
});

// ── Social login ──────────────────────────────────────────────────────────────

it('detects which social providers are configured', function (): void {
    config(['services.marketplace_google' => ['client_id' => 'x', 'client_secret' => 'y']]);

    expect(SocialProviders::enabled('google'))->toBeTrue()
        ->and(SocialProviders::enabled('github'))->toBeFalse()
        ->and(SocialProviders::any())->toBeTrue();
});

it('creates and signs in a developer from a social callback', function (): void {
    config(['services.marketplace_google' => ['client_id' => 'x', 'client_secret' => 'y']]);

    $oauthUser = Mockery::mock(SocialiteUser::class);
    $oauthUser->shouldReceive('getEmail')->andReturn('oauth@example.com');
    $oauthUser->shouldReceive('getName')->andReturn('OAuth Dev');
    $oauthUser->shouldReceive('getNickname')->andReturn('oauthdev');
    $oauthUser->shouldReceive('getId')->andReturn('gh-123');

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($oauthUser);
    Socialite::shouldReceive('buildProvider')->andReturn($provider);

    app(SocialAuthController::class)->callback('google');

    $developer = Developer::query()->firstWhere('email', 'oauth@example.com');
    expect($developer)->not->toBeNull()
        ->and($developer->provider)->toBe('google')
        ->and($developer->email_verified_at)->not->toBeNull();

    $this->assertAuthenticatedAs($developer, 'developer');
});

// ── S1-05: social callback must not silently take over a pre-existing account ─

it('refuses to log in via OAuth when the email already belongs to another account', function (): void {
    config(['services.marketplace_google' => ['client_id' => 'x', 'client_secret' => 'y']]);

    // Victim already has a password-based (non-OAuth) developer account.
    $victim = Developer::create([
        'name' => 'Victim',
        'email' => 'victim@example.com',
        'password' => 'a-real-password-hash-placeholder',
    ]);

    $oauthUser = Mockery::mock(SocialiteUser::class);
    $oauthUser->shouldReceive('getEmail')->andReturn('victim@example.com');
    $oauthUser->shouldReceive('getName')->andReturn('Attacker via OAuth');
    $oauthUser->shouldReceive('getNickname')->andReturn('attacker');
    $oauthUser->shouldReceive('getId')->andReturn('attacker-oauth-id');

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($oauthUser);
    Socialite::shouldReceive('buildProvider')->andReturn($provider);

    app(SocialAuthController::class)->callback('google');

    // Must NOT be logged in as the victim, and the victim's account must be
    // untouched (no provider/provider_id silently attached to it).
    $this->assertGuest('developer');

    $victim->refresh();
    expect($victim->provider)->toBeNull()
        ->and($victim->provider_id)->toBeNull();

    // No second account was created for the same email either.
    expect(Developer::query()->where('email', 'victim@example.com')->count())->toBe(1);
});

it('allows OAuth login when the provider identity was already linked to this account', function (): void {
    config(['services.marketplace_google' => ['client_id' => 'x', 'client_secret' => 'y']]);

    $developer = Developer::create([
        'name' => 'Returning Dev',
        'email' => 'returning@example.com',
        'provider' => 'google',
        'provider_id' => 'returning-oauth-id',
    ]);

    $oauthUser = Mockery::mock(SocialiteUser::class);
    $oauthUser->shouldReceive('getEmail')->andReturn('returning@example.com');
    $oauthUser->shouldReceive('getName')->andReturn('Returning Dev');
    $oauthUser->shouldReceive('getNickname')->andReturn('returningdev');
    $oauthUser->shouldReceive('getId')->andReturn('returning-oauth-id');

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($oauthUser);
    Socialite::shouldReceive('buildProvider')->andReturn($provider);

    app(SocialAuthController::class)->callback('google');

    $this->assertAuthenticatedAs($developer, 'developer');
});

// ── Admin suspension blocks login (both paths) ────────────────────────────────

it('refuses OAuth login for a suspended developer even with an already-linked identity', function (): void {
    config(['services.marketplace_google' => ['client_id' => 'x', 'client_secret' => 'y']]);

    $developer = Developer::create([
        'name' => 'Suspended Dev',
        'email' => 'suspended@example.com',
        'provider' => 'google',
        'provider_id' => 'suspended-oauth-id',
        'suspended_at' => now(),
    ]);

    $oauthUser = Mockery::mock(SocialiteUser::class);
    $oauthUser->shouldReceive('getEmail')->andReturn('suspended@example.com');
    $oauthUser->shouldReceive('getName')->andReturn('Suspended Dev');
    $oauthUser->shouldReceive('getNickname')->andReturn('suspendeddev');
    $oauthUser->shouldReceive('getId')->andReturn('suspended-oauth-id');

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($oauthUser);
    Socialite::shouldReceive('buildProvider')->andReturn($provider);

    app(SocialAuthController::class)->callback('google');

    $this->assertGuest('developer');
});

it('refuses password login for a suspended developer', function (): void {
    Developer::create([
        'name' => 'Suspended Dev',
        'email' => 'suspended@example.com',
        'password' => 'a-real-password',
        'suspended_at' => now(),
    ]);

    $this->post('/developer/login', ['email' => 'suspended@example.com', 'password' => 'a-real-password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest('developer');
});
