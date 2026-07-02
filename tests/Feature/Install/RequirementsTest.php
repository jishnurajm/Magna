<?php

declare(strict_types=1);

use Magna\Install\Requirement;
use Magna\Install\Requirements;

it('produces a non-empty checklist with unique keys', function (): void {
    $checks = (new Requirements)->check();

    $keys = array_map(fn (Requirement $c): string => $c->key, $checks);

    expect($checks)->not->toBeEmpty()
        ->and($keys)->toBe(array_values(array_unique($keys)));
});

it('passes the PHP version requirement on this machine', function (): void {
    $checks = (new Requirements)->check();

    $php = collect($checks)->firstOrFail(fn (Requirement $c): bool => $c->key === 'php');

    expect($php->passed)->toBeTrue()->and($php->required)->toBeTrue();
});

it('reports overall required status', function (): void {
    $requirements = new Requirements;

    $pass = [new Requirement('a', 'A', true, true, ''), new Requirement('b', 'B', false, false, '')];
    $fail = [new Requirement('a', 'A', false, true, '')];

    expect($requirements->requiredPass($pass))->toBeTrue()
        ->and($requirements->requiredPass($fail))->toBeFalse();
});
