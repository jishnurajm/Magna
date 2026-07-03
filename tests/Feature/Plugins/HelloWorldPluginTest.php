<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Magna\Admin\Nav\NavGroup;
use Magna\Auth\PermissionRegistry;
use Magna\Auth\Role;
use Magna\Contracts\RegistersAdminNavigation;
use Magna\Plugins\PluginManager;
use Magna\Testing\PluginTestCase;
use Magna\Users\User;

uses(PluginTestCase::class);

beforeEach(function (): void {
    Cache::tags(['magna-settings'])->flush();
    $this->enablePlugin('magna/hello-world');
});

it('registers the hello-world.greet permission when the plugin is enabled', function (): void {
    /** @var PermissionRegistry $registry */
    $registry = app(PermissionRegistry::class);

    expect($registry->has('hello-world.greet'))->toBeTrue();
});

it('responds to the greet API endpoint when the user holds the permission', function (): void {
    $user = User::factory()->create();

    $role = Role::create(['name' => 'Greeter', 'handle' => 'greeter', 'is_super_admin' => false]);
    $role->grant('hello-world.greet');
    $user->assignRole('greeter');

    $token = $user->createToken('test', ['management'], now()->addHour());
    $token->accessToken->forceFill(['scope' => 'management'])->save();

    $this->withToken($token->plainTextToken)
        ->getJson('/api/v1/hello-world/greet')
        ->assertOk()
        ->assertJson(['message' => 'Hello from Magna!']);
});

it('returns 403 for a user without the hello-world.greet permission', function (): void {
    $user = User::factory()->create();

    $token = $user->createToken('test', ['management'], now()->addHour());
    $token->accessToken->forceFill(['scope' => 'management'])->save();

    $this->withToken($token->plainTextToken)
        ->getJson('/api/v1/hello-world/greet')
        ->assertForbidden();
});

it('provides admin navigation through the RegistersAdminNavigation contract', function (): void {
    /** @var PluginManager $manager */
    $manager = app(PluginManager::class);

    $plugins = $manager->getEnabled();
    $helloWorld = $plugins['magna/hello-world'] ?? null;

    expect($helloWorld)->not->toBeNull()
        ->and($helloWorld)->toBeInstanceOf(RegistersAdminNavigation::class);

    /** @var RegistersAdminNavigation $helloWorld */
    $nav = $helloWorld->adminNavigation();
    expect($nav)->toBeInstanceOf(NavGroup::class)
        ->and($nav->label)->toBe('Hello World')
        ->and($nav->getItems())->toHaveCount(1);
});
