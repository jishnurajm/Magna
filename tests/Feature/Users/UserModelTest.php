<?php

declare(strict_types=1);

use Magna\Users\User;
use Magna\Users\UserStatus;

it('uses ULID primary keys', function (): void {
    $user = User::factory()->create();

    expect($user->getKey())->toBeString()->toHaveLength(26)
        ->and($user->getIncrementing())->toBeFalse();
});

it('casts status to the enum and defaults to active', function (): void {
    $user = User::factory()->create();

    expect($user->status)->toBe(UserStatus::Active)
        ->and($user->isActive())->toBeTrue();

    $suspended = User::factory()->suspended()->create();

    expect($suspended->status)->toBe(UserStatus::Suspended)
        ->and($suspended->isActive())->toBeFalse();
});

it('hashes passwords with argon2id', function (): void {
    $user = User::factory()->create(['password' => 'super-secret-password']);

    expect($user->password)->toStartWith('$argon2id$');
});

it('hides password and remember token from serialization', function (): void {
    $user = User::factory()->create();

    expect($user->toArray())->not->toHaveKeys(['password', 'remember_token']);
});
