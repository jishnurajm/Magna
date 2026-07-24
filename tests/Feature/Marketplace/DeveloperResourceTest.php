<?php

declare(strict_types=1);

use Magna\Auth\Role;
use Magna\Testing\PluginTestCase;
use Magna\Users\User;
use MagnaMarketplace\Filament\Resources\DeveloperResource;
use MagnaMarketplace\Models\Developer;

uses(PluginTestCase::class);

beforeEach(function (): void {
    $this->enablePlugin('magna-cms/marketplace');
});

it('denies developer management with zero marketplace.developers.manage permission', function (): void {
    $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
    $this->actingAs($user);

    $developer = Developer::create(['name' => 'Dev', 'email' => 'dev@example.com', 'password' => 'secret123']);

    expect(DeveloperResource::canViewAny())->toBeFalse()
        ->and(DeveloperResource::canCreate())->toBeFalse()
        ->and(DeveloperResource::canEdit($developer))->toBeFalse();
});

it('grants developer management to a role holding marketplace.developers.manage', function (): void {
    $role = Role::factory()->create();
    $role->grant('marketplace.developers.manage');

    $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
    $user->assignRole($role);
    $this->actingAs($user);

    $developer = Developer::create(['name' => 'Dev', 'email' => 'dev@example.com', 'password' => 'secret123']);

    expect(DeveloperResource::canViewAny())->toBeTrue()
        ->and(DeveloperResource::canCreate())->toBeTrue()
        ->and(DeveloperResource::canEdit($developer))->toBeTrue();
});

it('super admin has full DeveloperResource access without explicit grants', function (): void {
    $role = Role::factory()->create(['is_super_admin' => true]);
    $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
    $user->assignRole($role);
    $this->actingAs($user);

    $developer = Developer::create(['name' => 'Dev', 'email' => 'dev@example.com', 'password' => 'secret123']);

    expect(DeveloperResource::canViewAny())->toBeTrue()
        ->and(DeveloperResource::canCreate())->toBeTrue()
        ->and(DeveloperResource::canEdit($developer))->toBeTrue();
});

it('tracks badges as a slug list and answers hasBadge() correctly', function (): void {
    $developer = Developer::create([
        'name' => 'Official', 'email' => 'official@example.com', 'badges' => ['official', 'verified'],
    ]);

    expect($developer->hasBadge('official'))->toBeTrue()
        ->and($developer->hasBadge('verified'))->toBeTrue()
        ->and($developer->fresh()->hasBadge('spam'))->toBeFalse();
});

it('has no badges by default', function (): void {
    $developer = Developer::create(['name' => 'Plain', 'email' => 'plain@example.com']);

    expect($developer->hasBadge('verified'))->toBeFalse();
});

it('rotates the remember token, invalidating any remembered login', function (): void {
    $developer = Developer::create([
        'name' => 'Dev', 'email' => 'dev@example.com', 'password' => 'secret123', 'remember_token' => 'old-token',
    ]);

    $developer->rotateRememberToken();

    expect($developer->fresh()->remember_token)->not->toBeNull()
        ->and($developer->fresh()->remember_token)->not->toBe('old-token');
});
