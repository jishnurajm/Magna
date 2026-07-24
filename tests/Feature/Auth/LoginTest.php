<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Magna\Auth\LoginThrottle;
use Magna\Auth\Role;
use Magna\Users\User;

it('shows the login form', function (): void {
    $this->get(route('auth.login'))->assertOk();
});

it('logs in with correct credentials', function (): void {
    $user = User::factory()->create(['password' => Hash::make('secret')]);

    $this->post(route('auth.login.attempt'), [
        'email' => $user->email,
        'password' => 'secret',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('rejects wrong password', function (): void {
    $user = User::factory()->create(['password' => Hash::make('secret')]);

    $this->post(route('auth.login.attempt'), [
        'email' => $user->email,
        'password' => 'wrong',
    ])->assertRedirect()->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('rejects suspended accounts', function (): void {
    $user = User::factory()->suspended()->create(['password' => Hash::make('secret')]);

    $this->post(route('auth.login.attempt'), [
        'email' => $user->email,
        'password' => 'secret',
    ])->assertRedirect()->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('logs out and clears session', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('auth.logout'))
        ->assertRedirect(route('auth.login'));

    $this->assertGuest();
});

it('brute-force lockout kicks in after max_attempts consecutive failures', function (): void {
    Cache::flush();
    config(['magna.login.max_attempts' => 3, 'magna.login.base_lockout_seconds' => 30]);

    $user = User::factory()->create();
    $payload = ['email' => $user->email, 'password' => 'wrong'];

    // 3 failures — no lockout yet
    $this->post(route('auth.login.attempt'), $payload);
    $this->post(route('auth.login.attempt'), $payload);
    $this->post(route('auth.login.attempt'), $payload);

    // 4th attempt — now locked
    $this->post(route('auth.login.attempt'), $payload)
        ->assertRedirect()
        ->assertSessionHasErrors('email');

    // Verify throttle considers it locked
    expect(app(LoginThrottle::class)->isLocked(request()))->toBeTrue();
});

it('returns 404 for registration when disabled', function (): void {
    // GeneralSettings::registration_enabled defaults to false — no DB entry needed.
    Cache::tags(['magna-settings'])->flush();

    $this->post(route('auth.register.store'), [
        'name' => 'Test',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();
});

it('redirects to 2FA challenge when role requires it and user has 2FA enrolled', function (): void {
    $role = Role::factory()->create(['requires_two_factor' => true]);
    $user = User::factory()->create([
        'password' => Hash::make('secret'),
        'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
        'two_factor_confirmed_at' => now(),
    ]);
    $user->assignRole($role);

    $this->post(route('auth.login.attempt'), [
        'email' => $user->email,
        'password' => 'secret',
    ])->assertRedirect(route('auth.two-factor.challenge'));

    $this->assertGuest();
    expect(session('auth.two_factor_user_id'))->toBe($user->getKey());
});

it('logs in without 2FA challenge when role does not require it, even if user has 2FA', function (): void {
    $role = Role::factory()->create(['requires_two_factor' => false]);
    $user = User::factory()->create([
        'password' => Hash::make('secret'),
        'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
        'two_factor_confirmed_at' => now(),
    ]);
    $user->assignRole($role);

    $this->post(route('auth.login.attempt'), [
        'email' => $user->email,
        'password' => 'secret',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});
