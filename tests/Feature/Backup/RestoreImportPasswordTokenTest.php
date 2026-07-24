<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Magna\Backup\Jobs\RestoreBackupJob;
use Tests\TestCase;

uses(TestCase::class);

// Security regression: an uploaded archive's password used to travel as a
// plain constructor property on RestoreBackupJob — with the real
// `database` queue driver, that sits in the `jobs` table's payload column
// in plaintext for however long it takes a worker to pick the job up
// (found live, elsewhere in this session, to sometimes be indefinitely
// with no worker running at all). stashImportPassword() replaces the
// plaintext with an encrypted, short-TTL, single-read cache entry —
// verified directly here, not just by trusting the docblock.

afterEach(function (): void {
    // Best-effort: tokens are random per test, nothing deterministic to
    // clean up beyond what the short TTL already handles.
});

it('never stores the password in cache as plaintext', function (): void {
    $token = RestoreBackupJob::stashImportPassword('super-secret-archive-password');

    $cached = Cache::get("magna.backup.import-password.{$token}");

    expect($cached)->not->toBeNull()
        ->and($cached)->not->toBe('super-secret-archive-password');
});

it('the cached value decrypts back to the original password', function (): void {
    $token = RestoreBackupJob::stashImportPassword('super-secret-archive-password');

    $cached = Cache::get("magna.backup.import-password.{$token}");
    $decrypted = Crypt::decryptString((string) $cached);

    expect($decrypted)->toBe('super-secret-archive-password');
});

it('generates a different token on each call, even for the same password', function (): void {
    $tokenA = RestoreBackupJob::stashImportPassword('same-password');
    $tokenB = RestoreBackupJob::stashImportPassword('same-password');

    expect($tokenA)->not->toBe($tokenB);
});
