<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Magna\Marketplace\Marketplace;
use Magna\Updater\UpdateCheck;
use Magna\Updater\UpdateCheckClient;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('persists a core update as available when the server reports a newer version', function (): void {
    Http::fake([
        Marketplace::API_BASE.'/updates' => Http::response([
            'core' => ['latest_version' => '9.9.9', 'update_available' => true, 'changelog_url' => 'https://example.test/changelog'],
            'plugins' => [],
        ]),
    ]);

    $result = app(UpdateCheckClient::class)->checkIn();

    expect($result?->core?->updateAvailable)->toBeTrue();

    $row = UpdateCheck::core();
    expect($row)->not->toBeNull()
        ->and($row->update_available)->toBeTrue()
        ->and($row->latest_version)->toBe('9.9.9')
        ->and($row->changelog_url)->toBe('https://example.test/changelog');
});

it('records no update when the server reports the current version is latest', function (): void {
    Http::fake([
        Marketplace::API_BASE.'/updates' => Http::response([
            'core' => ['latest_version' => '1.0.0-dev', 'update_available' => false, 'changelog_url' => null],
            'plugins' => [],
        ]),
    ]);

    app(UpdateCheckClient::class)->checkIn();

    expect(UpdateCheck::core()?->update_available)->toBeFalse()
        ->and(UpdateCheck::totalAvailable())->toBe(0);
});

it('sends its opaque site fingerprint and never throws when Update Manager is unreachable', function (): void {
    Http::fake([Marketplace::API_BASE.'/updates' => Http::response('', 503)]);

    $result = app(UpdateCheckClient::class)->checkIn();

    expect($result)->toBeNull();
    Http::assertSent(fn (Request $request): bool => $request['site'] !== null && $request['site'] !== '');
});
