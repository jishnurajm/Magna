<?php

declare(strict_types=1);

use Magna\Updater\CoreUpdater;
use Tests\TestCase;

uses(TestCase::class);

// zip_url comes straight from Update Manager's /updates response with no
// signature/checksum on the archive itself, and this class overlays it
// directly onto app/, bootstrap/, and src/Magna — the code that runs on
// every request. guardDownloadUrl() is the floor that stops a compromised
// or MITM'd response pointing this at an arbitrary host; exercised via
// reflection since it's a private guard called at the top of download().
function coreUpdaterGuard(string $zipUrl): void
{
    $updater = app(CoreUpdater::class);
    $method = new ReflectionMethod(CoreUpdater::class, 'guardDownloadUrl');
    $method->setAccessible(true);
    $method->invoke($updater, $zipUrl);
}

it('allows an https download url on an allowlisted host', function (): void {
    expect(fn () => coreUpdaterGuard('https://github.com/magna-cms/magna/archive/refs/tags/v1.2.0.zip'))
        ->not->toThrow(Throwable::class);
});

it('rejects a download url on a host not on the allowlist', function (): void {
    expect(fn () => coreUpdaterGuard('https://evil.example.com/payload.zip'))
        ->toThrow(RuntimeException::class);
});

it('rejects a plain http download url even on an allowlisted host', function (): void {
    expect(fn () => coreUpdaterGuard('http://managemagna.jrstudios.dev/releases/v1.2.0.zip'))
        ->toThrow(RuntimeException::class);
});

it('rejects a malformed url with no host', function (): void {
    expect(fn () => coreUpdaterGuard('not-a-url'))
        ->toThrow(RuntimeException::class);
});
