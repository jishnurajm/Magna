<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Magna\Auth\Role;
use Magna\Users\User;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function usersAdminToken(): string
{
    $role = Role::factory()->create();
    $role->grant('users.view', 'users.manage', 'roles.manage');

    $user = User::factory()->create();
    $user->assignRole($role);

    $result = $user->createToken('mgmt', ['management'], now()->addDay());
    $result->accessToken->forceFill(['scope' => 'management'])->save();

    return $result->plainTextToken;
}

function usersViewerToken(): string
{
    $role = Role::factory()->create();
    $role->grant('users.view');

    $user = User::factory()->create();
    $user->assignRole($role);

    $result = $user->createToken('mgmt', ['management'], now()->addDay());
    $result->accessToken->forceFill(['scope' => 'management'])->save();

    return $result->plainTextToken;
}

// ── List ──────────────────────────────────────────────────────────────────────

it('lists users with pagination', function (): void {
    User::factory()->count(3)->create();
    $token = usersAdminToken();

    $response = $this->withToken($token)
        ->getJson('/api/v1/manage/users')
        ->assertOk()
        ->assertJsonStructure(['data', 'meta' => ['current_page', 'per_page', 'total', 'last_page']]);

    // At least the 3 created + the admin user itself
    expect($response->json('meta.total'))->toBeGreaterThanOrEqual(3);
});

it('returns 403 when user lacks users.view', function (): void {
    $role = Role::factory()->create();
    $role->grant('media.view');
    $user = User::factory()->create();
    $user->assignRole($role);
    $result = $user->createToken('mgmt', ['management'], now()->addDay());
    $result->accessToken->forceFill(['scope' => 'management'])->save();

    $this->withToken($result->plainTextToken)
        ->getJson('/api/v1/manage/users')
        ->assertForbidden();
});

// ── Show ──────────────────────────────────────────────────────────────────────

it('shows a single user', function (): void {
    $token = usersAdminToken();
    $target = User::factory()->create(['name' => 'Jane Doe']);

    $this->withToken($token)
        ->getJson('/api/v1/manage/users/'.$target->id)
        ->assertOk()
        ->assertJsonPath('data.name', 'Jane Doe')
        ->assertJsonStructure(['data' => ['id', 'name', 'email', 'status', 'roles']]);
});

it('returns 404 for a missing user', function (): void {
    $token = usersAdminToken();

    $this->withToken($token)
        ->getJson('/api/v1/manage/users/'.str_repeat('0', 26))
        ->assertNotFound();
});

// ── Update ────────────────────────────────────────────────────────────────────

it('updates a user name', function (): void {
    $token = usersAdminToken();
    $target = User::factory()->create(['name' => 'Old Name']);

    $this->withToken($token)
        ->putJson('/api/v1/manage/users/'.$target->id, ['name' => 'New Name'])
        ->assertOk()
        ->assertJsonPath('data.name', 'New Name');

    expect($target->fresh()->name)->toBe('New Name');
});

it('returns 403 when viewer tries to update a user', function (): void {
    $token = usersViewerToken();
    $target = User::factory()->create();

    $this->withToken($token)
        ->putJson('/api/v1/manage/users/'.$target->id, ['name' => 'Hacked'])
        ->assertForbidden();
});

// ── Assign role ───────────────────────────────────────────────────────────────

it('assigns a role to a user', function (): void {
    $token = usersAdminToken();
    $target = User::factory()->create();
    $role = Role::factory()->create(['name' => 'editor']);

    $this->withToken($token)
        ->postJson('/api/v1/manage/users/'.$target->id.'/roles', ['role' => 'editor'])
        ->assertOk()
        ->assertJsonPath('message', "Role 'editor' assigned.");

    expect($target->fresh()->roles()->where('name', 'editor')->exists())->toBeTrue();
});

it('returns 404 when assigning a non-existent role', function (): void {
    $token = usersAdminToken();
    $target = User::factory()->create();

    $this->withToken($token)
        ->postJson('/api/v1/manage/users/'.$target->id.'/roles', ['role' => 'ghost-role'])
        ->assertNotFound();
});

it('returns 403 when user lacks roles.manage permission', function (): void {
    $token = usersViewerToken();
    $target = User::factory()->create();

    $this->withToken($token)
        ->postJson('/api/v1/manage/users/'.$target->id.'/roles', ['role' => 'editor'])
        ->assertForbidden();
});
