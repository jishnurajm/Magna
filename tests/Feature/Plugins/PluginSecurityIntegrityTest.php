<?php

declare(strict_types=1);

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Magna\Audit\AuditLog;
use Magna\Auth\PermissionRegistry;
use Magna\Plugins\PluginManager;
use Magna\Testing\PluginTestCase;
use Magna\Users\User;

uses(PluginTestCase::class);

// Stage 13 (S1-07 + S1-09): a plugin's register()/boot() runs with full
// container, Router, and Event-dispatcher access — nothing stops it
// re-aliasing a security-critical middleware name, rebinding a
// security-critical singleton like PermissionRegistry, or silently
// unregistering the login audit listeners via Event::forget(). PluginManager
// now detects and reverts all of these after every plugin-boot pass.
// Exercised directly against the private capture/verify methods (rather than
// via a fixture plugin that actually tampers, which would require a
// dedicated malicious test plugin) since that's the actual unit of behavior
// being protected.

function pluginManagerReflect(string $method): ReflectionMethod
{
    $reflection = new ReflectionMethod(PluginManager::class, $method);
    $reflection->setAccessible(true);

    return $reflection;
}

it('reverts a tampered protected middleware alias and logs it', function (): void {
    /** @var Router $router */
    $router = app('router');
    $manager = app(PluginManager::class);

    $originalClass = $router->getMiddleware()['magna.api'];

    $snapshot = pluginManagerReflect('captureSecurityIntegritySnapshot')->invoke($manager);

    // Simulate a plugin's boot() re-aliasing a protected middleware name.
    $router->aliasMiddleware('magna.api', ThrottleRequests::class);
    expect($router->getMiddleware()['magna.api'])->not->toBe($originalClass);

    Log::shouldReceive('critical')->once()->with(Mockery::pattern('/magna\.api/'));

    pluginManagerReflect('verifySecurityIntegrity')->invoke($manager, $snapshot);

    expect($router->getMiddleware()['magna.api'])->toBe($originalClass);
});

it('does not log or revert when protected middleware aliases are unchanged', function (): void {
    $manager = app(PluginManager::class);

    $snapshot = pluginManagerReflect('captureSecurityIntegritySnapshot')->invoke($manager);

    Log::shouldReceive('critical')->never();

    pluginManagerReflect('verifySecurityIntegrity')->invoke($manager, $snapshot);
});

it('reverts a rebound PermissionRegistry singleton and logs it', function (): void {
    $manager = app(PluginManager::class);
    $original = app(PermissionRegistry::class);

    $snapshot = pluginManagerReflect('captureSecurityIntegritySnapshot')->invoke($manager);

    // Simulate a plugin's boot() rebinding the security-critical singleton.
    app()->instance(PermissionRegistry::class, new PermissionRegistry);
    expect(app(PermissionRegistry::class))->not->toBe($original);

    Log::shouldReceive('critical')->once()->with(Mockery::pattern('/PermissionRegistry/'));

    pluginManagerReflect('verifySecurityIntegrity')->invoke($manager, $snapshot);

    expect(app(PermissionRegistry::class))->toBe($original);
});

// Stage 13 (S1-09): a plugin's boot() calling Event::forget(Login::class)
// silently disables the login-success audit listener for the rest of the
// process — no error, no trace, just quietly-missing audit entries. The
// integrity check must detect this and re-register the listener.
it('re-registers the Login audit listener after a plugin forgets it, and logs it', function (): void {
    $manager = app(PluginManager::class);

    expect(Event::hasListeners(Login::class))->toBeTrue();

    $snapshot = pluginManagerReflect('captureSecurityIntegritySnapshot')->invoke($manager);

    // Simulate a plugin's boot() suppressing the login audit trail.
    Event::forget(Login::class);
    expect(Event::hasListeners(Login::class))->toBeFalse();

    Log::shouldReceive('critical')->once()->with(Mockery::pattern('/Login/'));

    pluginManagerReflect('verifySecurityIntegrity')->invoke($manager, $snapshot);

    expect(Event::hasListeners(Login::class))->toBeTrue();

    $user = User::factory()->create();
    auth('web')->login($user);

    expect(AuditLog::query()->where('action', 'auth.login.success')->count())->toBe(1);
});

it('re-registers the Failed (login) audit listener after a plugin forgets it, and logs it', function (): void {
    $manager = app(PluginManager::class);

    expect(Event::hasListeners(Failed::class))->toBeTrue();

    $snapshot = pluginManagerReflect('captureSecurityIntegritySnapshot')->invoke($manager);

    Event::forget(Failed::class);
    expect(Event::hasListeners(Failed::class))->toBeFalse();

    Log::shouldReceive('critical')->once()->with(Mockery::pattern('/Failed/'));

    pluginManagerReflect('verifySecurityIntegrity')->invoke($manager, $snapshot);

    expect(Event::hasListeners(Failed::class))->toBeTrue();
});

it('does not log or re-register the login listeners when they were never touched', function (): void {
    $manager = app(PluginManager::class);

    $snapshot = pluginManagerReflect('captureSecurityIntegritySnapshot')->invoke($manager);

    Log::shouldReceive('critical')->never();

    pluginManagerReflect('verifySecurityIntegrity')->invoke($manager, $snapshot);

    expect(Event::hasListeners(Login::class))->toBeTrue()
        ->and(Event::hasListeners(Failed::class))->toBeTrue();
});
