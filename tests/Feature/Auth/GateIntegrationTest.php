<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Magna\Auth\PermissionRegistry;
use Magna\Auth\Role;
use Magna\Users\User;

function userWithGrants(string ...$grants): User
{
    $role = Role::factory()->create();
    $role->grant(...$grants);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('allows a user whose role holds the exact permission', function (): void {
    app(PermissionRegistry::class)->register('blog.posts.create');

    $user = userWithGrants('blog.posts.create');

    expect($user->can('blog.posts.create'))->toBeTrue();
});

it('allows a user through a wildcard grant', function (): void {
    app(PermissionRegistry::class)->registerMany(['blog.posts.create', 'blog.settings.manage']);

    $user = userWithGrants('blog.*');

    expect($user->can('blog.posts.create'))->toBeTrue()
        ->and($user->can('blog.settings.manage'))->toBeTrue();
});

it('denies a registered permission the user was not granted', function (): void {
    app(PermissionRegistry::class)->register('blog.posts.delete');

    $user = userWithGrants('users.view');

    expect($user->can('blog.posts.delete'))->toBeFalse();
});

it('denies and logs a warning for unregistered permission keys', function (): void {
    Log::spy();

    $user = userWithGrants('*');

    expect($user->can('ghost.feature.use'))->toBeFalse();

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $context['key'] === 'ghost.feature.use');
});

it('lets super admins pass every check, registered or not', function (): void {
    $role = Role::factory()->superAdmin()->create();
    $user = User::factory()->create();
    $user->assignRole($role);

    expect($user->can('users.manage'))->toBeTrue()
        ->and($user->can('ghost.feature.use'))->toBeTrue()
        ->and($user->can('arbitrary-policy-ability'))->toBeTrue();
});

it('lets dot-free abilities fall through to normal gate definitions', function (): void {
    Gate::define('view-dashboard', fn (User $user): bool => true);
    Gate::define('view-secrets', fn (User $user): bool => false);

    $user = User::factory()->create();

    expect($user->can('view-dashboard'))->toBeTrue()
        ->and($user->can('view-secrets'))->toBeFalse();
});

it('denies users with no roles', function (): void {
    app(PermissionRegistry::class)->register('users.view');

    $user = User::factory()->create();

    expect($user->can('users.view'))->toBeFalse();
});
