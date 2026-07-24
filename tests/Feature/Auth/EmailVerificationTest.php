<?php

declare(strict_types=1);

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Magna\Users\User;

it('shows the verification notice to unverified users', function (): void {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('verification.notice'))
        ->assertOk();
});

it('redirects verified users away from the notice', function (): void {
    $user = User::factory()->create(); // email_verified_at is set by factory default

    $this->actingAs($user)
        ->get(route('verification.notice'))
        ->assertRedirect(route('dashboard'));
});

it('verifies email with a valid signed URL', function (): void {
    $user = User::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $this->actingAs($user)
        ->get($verificationUrl)
        ->assertRedirect(route('dashboard'));

    expect($user->fresh()?->hasVerifiedEmail())->toBeTrue();
});

it('resends a verification email', function (): void {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->post(route('verification.send'))
        ->assertRedirect();

    Notification::assertSentTo($user, VerifyEmail::class);
});
