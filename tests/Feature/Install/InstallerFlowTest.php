<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Magna\Users\User;

beforeEach(function (): void {
    $this->dir = sys_get_temp_dir().'/magna-install-'.uniqid();
    File::makeDirectory($this->dir, 0755, true);

    config([
        'magna.installed_override' => null,
        'magna.install.lock_path' => $this->dir.'/installed.json',
        'magna.install.env_path' => $this->dir.'/.env',
    ]);
});

afterEach(function (): void {
    File::deleteDirectory($this->dir);
});

it('redirects all web traffic to the installer until installed', function (): void {
    $this->get('/')->assertRedirect('/install');
});

it('returns 404 for installer routes once installed', function (): void {
    config(['magna.installed_override' => true]);

    $this->get('/install')->assertNotFound();
    $this->post('/install/site', [])->assertNotFound();
});

it('shows the requirements checklist', function (): void {
    $this->get('/install')
        ->assertOk()
        ->assertSee('Welcome to Magna')
        ->assertSee('PHP 8.3 or newer');
});

it('validates the site step', function (): void {
    $this->from('/install/site')
        ->post('/install/site', ['name' => '', 'url' => 'not-a-url'])
        ->assertRedirect('/install/site')
        ->assertSessionHasErrors(['name', 'url']);
});

it('rejects unreachable database servers with a friendly error', function (): void {
    $this->from('/install/database')
        ->post('/install/database', [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 1,
            'database' => 'nope',
            'username' => 'nope',
            'password' => '',
        ])
        ->assertRedirect('/install/database')
        ->assertSessionHasErrors('connection');
});

it('completes the full installation happy path', function (): void {
    // Step 2 — site details are written straight to .env.
    $this->post('/install/site', [
        'name' => 'My Magna Site',
        'url' => 'https://example.com/',
        'production' => '1',
    ])->assertRedirect('/install/database');

    $env = (string) file_get_contents($this->dir.'/.env');
    expect($env)->toContain('APP_NAME="My Magna Site"')
        ->and($env)->toContain('APP_URL=https://example.com')
        ->and($env)->toContain('APP_ENV=production')
        ->and($env)->toContain('APP_DEBUG=false');

    // Step 3 — SQLite database: connection probed, migrated, seeded.
    $this->post('/install/database', [
        'driver' => 'sqlite',
        'sqlite_path' => $this->dir.'/magna.sqlite',
    ])->assertRedirect('/install/account');

    expect(file_exists($this->dir.'/magna.sqlite'))->toBeTrue()
        ->and((string) file_get_contents($this->dir.'/.env'))->toContain('DB_CONNECTION=sqlite');

    // Step 4 — admin account gets super-admin, install locks.
    $this->post('/install/account', [
        'name' => 'Ada Admin',
        'email' => 'ada@example.com',
        'password' => 'a-very-long-password',
        'password_confirmation' => 'a-very-long-password',
    ])->assertRedirect('/install/complete');

    expect(file_exists($this->dir.'/installed.json'))->toBeTrue();

    $admin = User::query()->where('email', 'ada@example.com')->firstOrFail();
    expect($admin->isSuperAdmin())->toBeTrue();

    // Aftermath: success page renders, site is live, installer is gone.
    $this->get('/install/complete')->assertOk()->assertSee('Magna is installed');
    $this->get('/')->assertOk();
    $this->get('/install')->assertNotFound();
});

it('guards the account step until the database is ready', function (): void {
    // Roles are not seeded in the test database, so the guard must bounce.
    $this->get('/install/account')->assertRedirect('/install/database');
});
