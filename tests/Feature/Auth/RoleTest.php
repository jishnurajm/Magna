<?php

declare(strict_types=1);

use Magna\Auth\Role;
use Magna\Users\User;

it('grants and revokes permissions', function (): void {
    $role = Role::factory()->create();

    $role->grant('blog.*', 'users.view');
    expect($role->grants())->toEqualCanonicalizing(['blog.*', 'users.view']);

    $role->revoke('blog.*');
    expect($role->grants())->toBe(['users.view']);
});

it('ignores duplicate grants', function (): void {
    $role = Role::factory()->create();

    $role->grant('users.view');
    $role->grant('users.view');

    expect($role->permissions()->count())->toBe(1);
});

it('assigns and removes roles by model and by handle', function (): void {
    $role = Role::factory()->create(['handle' => 'editor-x']);
    $user = User::factory()->create();

    $user->assignRole('editor-x');
    expect($user->hasRole('editor-x'))->toBeTrue();

    $user->assignRole($role); // idempotent
    expect($user->roles()->count())->toBe(1);

    $user->removeRole('editor-x');
    expect($user->hasRole('editor-x'))->toBeFalse();
});

it('throws when assigning an unknown role handle', function (): void {
    $user = User::factory()->create();

    expect(fn () => $user->assignRole('does-not-exist'))->toThrow(InvalidArgumentException::class);
});

it('reflects new grants immediately after role assignment', function (): void {
    $role = Role::factory()->create();
    $role->grant('blog.*');

    $user = User::factory()->create();
    expect($user->hasPermissionGrant('blog.posts.create'))->toBeFalse();

    $user->assignRole($role);
    expect($user->hasPermissionGrant('blog.posts.create'))->toBeTrue();
});

it('flags super admins through the role flag', function (): void {
    $user = User::factory()->create();
    expect($user->isSuperAdmin())->toBeFalse();

    $user->assignRole(Role::factory()->superAdmin()->create());
    expect($user->isSuperAdmin())->toBeTrue();
});
