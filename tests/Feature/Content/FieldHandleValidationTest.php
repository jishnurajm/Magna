<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Magna\Content\ContentType;
use Magna\Content\Exceptions\SchemaException;
use Magna\Content\Field;
use Magna\Content\FieldTypeRegistry;
use Magna\Content\TableGenerator;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// S1-08 regression: field handles are interpolated into raw SQL identifiers
// in TableGenerator::addGinIndex() (Postgres GIN index creation). Previously
// only ContentType handles were format-validated — field handles had no
// charset/length check anywhere below the Filament admin widget, so the
// Management REST API and artisan commands could register a field with an
// arbitrary handle string. Field::fromArray() now enforces the same
// allowlist ContentType::fromArray() already used.

it('rejects a field handle containing SQL-significant characters', function (): void {
    expect(fn () => Field::fromArray(
        ['handle' => 'title"); DROP TABLE users; --', 'type' => 'text'],
        app(FieldTypeRegistry::class),
    ))->toThrow(SchemaException::class);
});

it('rejects a field handle with spaces or special characters', function (): void {
    foreach (['my field', 'field-name', 'field.name', 'field;name', '1field', ''] as $handle) {
        expect(fn () => Field::fromArray(
            ['handle' => $handle, 'type' => 'text'],
            app(FieldTypeRegistry::class),
        ))->toThrow(SchemaException::class, null, "handle \"{$handle}\" should have been rejected");
    }
});

it('rejects a field handle exceeding the identifier length limit', function (): void {
    $tooLong = 'a'.str_repeat('b', 63);

    expect(fn () => Field::fromArray(
        ['handle' => $tooLong, 'type' => 'text'],
        app(FieldTypeRegistry::class),
    ))->toThrow(SchemaException::class);
});

it('accepts a well-formed field handle', function (): void {
    $field = Field::fromArray(['handle' => 'valid_field_1', 'type' => 'text'], app(FieldTypeRegistry::class));

    expect($field->handle)->toBe('valid_field_1');
});

it('rejects an unsafe identifier defensively at the SQL-building layer even if validation is bypassed', function (): void {
    // Exercises TableGenerator::addGinIndex()'s own defense-in-depth guard
    // directly, independent of Field::fromArray()'s validation above.
    $generator = app(TableGenerator::class);
    $reflection = new ReflectionMethod($generator, 'addGinIndex');
    $reflection->setAccessible(true);

    expect(fn () => $reflection->invoke($generator, 'magna_entries_x', 'x', 'col"); DROP TABLE users; --'))
        ->toThrow(InvalidArgumentException::class);
});
