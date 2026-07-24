<?php

declare(strict_types=1);

use Magna\Auth\MagnaToken;
use Magna\Users\User;

function makeToken(User $user, string $scope = 'delivery', int $expiryDays = 30): array
{
    $expiresAt = now()->addDays($expiryDays);
    $token = $user->createToken('test-token', [$scope], $expiresAt);

    $token->accessToken->forceFill(['scope' => $scope])->save();

    return [$token->plainTextToken, $token->accessToken];
}

it('creates a delivery token and shows the plaintext once', function (): void {
    $user = User::factory()->create();
    [$plain] = makeToken($user, 'management');

    $this->withToken($plain)
        ->postJson(route('api.tokens.store'), [
            'name' => 'My delivery token',
            'scope' => 'delivery',
        ])
        ->assertCreated()
        ->assertJsonStructure(['token', 'id', 'scope', 'expires_at']);
});

it('creates a management token', function (): void {
    $user = User::factory()->create();
    [$plain] = makeToken($user, 'management');

    $this->withToken($plain)
        ->postJson(route('api.tokens.store'), [
            'name' => 'Deploy token',
            'scope' => 'management',
        ])
        ->assertCreated()
        ->assertJsonPath('scope', 'management');
});

it('rejects delivery token on management endpoints', function (): void {
    $user = User::factory()->create();
    [$plain] = makeToken($user, 'delivery');

    $this->withToken($plain)
        ->postJson(route('api.tokens.store'), ['name' => 'X', 'scope' => 'management'])
        ->assertForbidden();
});

it('rejects an expired token', function (): void {
    $user = User::factory()->create();
    $expiresAt = now()->subDay();
    $token = $user->createToken('old', ['management'], $expiresAt);
    $token->accessToken->forceFill(['scope' => 'management', 'expires_at' => $expiresAt])->save();

    $this->withToken($token->plainTextToken)
        ->getJson(route('api.tokens.index'))
        ->assertUnauthorized();
});

it('lists tokens without exposing the plaintext hash', function (): void {
    $user = User::factory()->create();
    [$plain] = makeToken($user, 'management');

    $response = $this->withToken($plain)
        ->getJson(route('api.tokens.index'))
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name', 'scope', 'expires_at']]]);

    expect($response->json('data.0'))->not->toHaveKey('token');
});

it('revokes a token by id', function (): void {
    $user = User::factory()->create();
    [$plain, $accessToken] = makeToken($user, 'management');

    $this->withToken($plain)
        ->deleteJson(route('api.tokens.destroy', $accessToken->id))
        ->assertOk();

    expect(MagnaToken::find($accessToken->id))->toBeNull();
});

it('returns 404 when revoking another user token', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    [$plain] = makeToken($user, 'management');
    [, $otherToken] = makeToken($other, 'management');

    $this->withToken($plain)
        ->deleteJson(route('api.tokens.destroy', $otherToken->id))
        ->assertNotFound();
});

it('rejects missing bearer token', function (): void {
    $this->getJson(route('api.tokens.index'))->assertUnauthorized();
});
