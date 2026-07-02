<?php

declare(strict_types=1);

use Magna\Install\EnvWriter;

beforeEach(function (): void {
    $this->path = sys_get_temp_dir().'/magna-env-'.uniqid().'.env';
});

afterEach(function (): void {
    @unlink($this->path);
});

it('creates the file when missing', function (): void {
    (new EnvWriter($this->path))->set(['APP_NAME' => 'Magna']);

    expect(file_get_contents($this->path))->toContain('APP_NAME=Magna');
});

it('updates existing keys in place and preserves other lines', function (): void {
    file_put_contents($this->path, "# comment stays\nAPP_NAME=Old\nAPP_ENV=local\n");

    (new EnvWriter($this->path))->set(['APP_NAME' => 'New']);

    $contents = (string) file_get_contents($this->path);

    expect($contents)->toContain('# comment stays')
        ->and($contents)->toContain('APP_NAME=New')
        ->and($contents)->toContain('APP_ENV=local')
        ->and($contents)->not->toContain('APP_NAME=Old');
});

it('appends keys that do not exist yet', function (): void {
    file_put_contents($this->path, "APP_ENV=local\n");

    (new EnvWriter($this->path))->set(['DB_CONNECTION' => 'pgsql']);

    expect((string) file_get_contents($this->path))
        ->toContain('APP_ENV=local')
        ->toContain('DB_CONNECTION=pgsql');
});

it('quotes values containing spaces and escapes quotes', function (): void {
    (new EnvWriter($this->path))->set([
        'APP_NAME' => 'My Magna Site',
        'DB_PASSWORD' => 'pa"ss word',
    ]);

    $contents = (string) file_get_contents($this->path);

    expect($contents)->toContain('APP_NAME="My Magna Site"')
        ->and($contents)->toContain('DB_PASSWORD="pa\"ss word"');
});

it('strips newlines so a value cannot inject additional env entries', function (): void {
    (new EnvWriter($this->path))->set(['APP_NAME' => "Legit\nDANGER=1"]);

    $contents = (string) file_get_contents($this->path);

    expect($contents)->toContain('APP_NAME="Legit DANGER=1"')
        ->and($contents)->not->toMatch('/^DANGER=/m');
});

it('does not partially match longer key names', function (): void {
    file_put_contents($this->path, "DB_PASSWORD_FALLBACK=keep\nDB_PASSWORD=old\n");

    (new EnvWriter($this->path))->set(['DB_PASSWORD' => 'new']);

    $contents = (string) file_get_contents($this->path);

    expect($contents)->toContain('DB_PASSWORD_FALLBACK=keep')
        ->and($contents)->toContain('DB_PASSWORD=new');
});
