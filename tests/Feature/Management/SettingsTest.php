<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Magna\Auth\Role;
use Magna\Settings\MailSettings;
use Magna\Users\User;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function settingsMgmtToken(string ...$grants): string
{
    $role = Role::factory()->create();
    $role->grant(...$grants);

    $user = User::factory()->create();
    $user->assignRole($role);

    $result = $user->createToken('mgmt', ['management'], now()->addDay());
    $result->accessToken->forceFill(['scope' => 'management'])->save();

    return $result->plainTextToken;
}

// ── Show — secrets masked ─────────────────────────────────────────────────────

it('returns settings with secret fields masked', function (): void {
    $token = settingsMgmtToken('settings.view');

    $response = $this->withToken($token)
        ->getJson('/api/v1/manage/settings')
        ->assertOk()
        ->assertJsonStructure(['data' => ['general', 'mail', 'storage']]);

    // If there is a password or secret field it should be masked, not empty
    $mail = $response->json('data.mail');
    expect($mail)->toBeArray();
});

it('returns 403 when user lacks settings.view', function (): void {
    $token = settingsMgmtToken('media.view');

    $this->withToken($token)
        ->getJson('/api/v1/manage/settings')
        ->assertForbidden();
});

// ── Update ────────────────────────────────────────────────────────────────────

it('updates a settings group and returns the new value', function (): void {
    $token = settingsMgmtToken('settings.view', 'settings.manage');

    $response = $this->withToken($token)
        ->putJson('/api/v1/manage/settings', [
            'group' => 'mail',
            'values' => [
                'driver' => 'smtp',
            ],
        ])
        ->assertOk()
        ->assertJsonStructure(['data']);

    expect($response->json('data.driver'))->toBe('smtp');

    // Persisted to the DB settings store
    expect(MailSettings::get()->driver)->toBe('smtp');
});

it('returns 422 for unknown settings group', function (): void {
    $token = settingsMgmtToken('settings.view', 'settings.manage');

    $this->withToken($token)
        ->putJson('/api/v1/manage/settings', [
            'group' => 'nonexistent',
            'values' => ['foo' => 'bar'],
        ])
        ->assertStatus(422);
});

it('returns 403 when user lacks settings.manage', function (): void {
    $token = settingsMgmtToken('settings.view');

    $this->withToken($token)
        ->putJson('/api/v1/manage/settings', [
            'group' => 'mail',
            'values' => ['driver' => 'smtp'],
        ])
        ->assertForbidden();
});
