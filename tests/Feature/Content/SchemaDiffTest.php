<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Magna\Content\ContentType;
use Magna\Content\DiffChangeType;
use Magna\Content\Exceptions\DestructiveChangeException;
use Magna\Content\FieldTypeRegistry;
use Magna\Content\SchemaDiffer;
use Magna\Content\SchemaRegistry;
use Magna\Content\SchemaSyncer;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── Detect new table ─────────────────────────────────────────────────────────

it('flags a missing table as create_table', function (): void {
    $type = diffType('new_type', [['handle' => 'title', 'type' => 'text']]);

    $diff = app(SchemaDiffer::class)->diff($type);

    expect($diff->changes)->toHaveCount(1)
        ->and($diff->changes[0]->type)->toBe(DiffChangeType::CreateTable)
        ->and($diff->changes[0]->destructive)->toBeFalse();
});

// ── Detect added field ────────────────────────────────────────────────────────

it('flags a new field as add_column', function (): void {
    $original = diffType('add_col_type', [['handle' => 'title', 'type' => 'text']]);
    syncType($original);

    $updated = diffType('add_col_type', [
        ['handle' => 'title', 'type' => 'text'],
        ['handle' => 'summary', 'type' => 'textarea'],
    ]);

    $diff = app(SchemaDiffer::class)->diff($updated);

    $addChanges = array_values(array_filter(
        $diff->changes,
        fn ($c) => $c->type === DiffChangeType::AddColumn,
    ));
    expect($addChanges)->toHaveCount(1)
        ->and($addChanges[0]->column)->toBe('summary')
        ->and($addChanges[0]->destructive)->toBeFalse();
});

// ── Detect removed field ─────────────────────────────────────────────────────

it('flags a removed field as remove_column (destructive)', function (): void {
    $original = diffType('remove_col_type', [
        ['handle' => 'title', 'type' => 'text'],
        ['handle' => 'summary', 'type' => 'textarea'],
    ]);
    syncType($original);

    $reduced = diffType('remove_col_type', [['handle' => 'title', 'type' => 'text']]);

    $diff = app(SchemaDiffer::class)->diff($reduced);

    $removeChanges = array_values(array_filter(
        $diff->changes,
        fn ($c) => $c->type === DiffChangeType::RemoveColumn,
    ));
    expect($removeChanges)->toHaveCount(1)
        ->and($removeChanges[0]->column)->toBe('summary')
        ->and($removeChanges[0]->destructive)->toBeTrue();
});

// ── Detect field type change ──────────────────────────────────────────────────

it('flags a changed field type as change_column (destructive)', function (): void {
    $original = diffType('change_col_type', [['handle' => 'score', 'type' => 'text']]);
    syncType($original);

    $changed = diffType('change_col_type', [['handle' => 'score', 'type' => 'number', 'integer' => true]]);

    $diff = app(SchemaDiffer::class)->diff($changed);

    $changeChanges = array_values(array_filter(
        $diff->changes,
        fn ($c) => $c->type === DiffChangeType::ChangeColumn,
    ));
    expect($changeChanges)->toHaveCount(1)
        ->and($changeChanges[0]->column)->toBe('score')
        ->and($changeChanges[0]->destructive)->toBeTrue();
});

// ── Idempotency ───────────────────────────────────────────────────────────────

it('produces no changes on the second sync (idempotent)', function (): void {
    $type = diffType('idem_type', [
        ['handle' => 'title', 'type' => 'text'],
        ['handle' => 'body', 'type' => 'richtext'],
    ]);

    syncType($type);

    $secondDiff = app(SchemaDiffer::class)->diff($type);
    expect($secondDiff->isEmpty())->toBeTrue();
});

// ── Destructive guard ────────────────────────────────────────────────────────

it('throws DestructiveChangeException when destructive changes exist without --allow-destructive', function (): void {
    $original = diffType('guard_type', [
        ['handle' => 'title', 'type' => 'text'],
        ['handle' => 'to_remove', 'type' => 'textarea'],
    ]);
    syncType($original);

    $registry = freshRegistry();
    $reduced = diffType('guard_type', [['handle' => 'title', 'type' => 'text']]);
    $registry->register($reduced);

    expect(fn () => app(SchemaSyncer::class)->syncAll($registry, allowDestructive: false))
        ->toThrow(DestructiveChangeException::class);
});

it('applies destructive changes when --allow-destructive is passed', function (): void {
    $original = diffType('destroy_type', [
        ['handle' => 'title', 'type' => 'text'],
        ['handle' => 'old_col', 'type' => 'textarea'],
    ]);
    syncType($original);

    $registry = freshRegistry();
    $reduced = diffType('destroy_type', [['handle' => 'title', 'type' => 'text']]);
    $registry->register($reduced);

    app(SchemaSyncer::class)->syncAll($registry, allowDestructive: true);

    expect(Schema::hasColumn('magna_entries_destroy_type', 'old_col'))->toBeFalse()
        ->and(Schema::hasColumn('magna_entries_destroy_type', 'title'))->toBeTrue();
});

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * @param  list<array<string, mixed>>  $fields
 */
function diffType(string $handle, array $fields): ContentType
{
    /** @var FieldTypeRegistry $registry */
    $registry = app(FieldTypeRegistry::class);

    return ContentType::fromArray([
        'handle' => $handle,
        'displayName' => ucfirst($handle),
        'localizable' => false,
        'draftable' => true,
        'fields' => $fields,
    ], $registry);
}

function syncType(ContentType $type): void
{
    $registry = freshRegistry();
    $registry->register($type);
    app(SchemaSyncer::class)->syncAll($registry, allowDestructive: true);
}

function freshRegistry(): SchemaRegistry
{
    /** @var FieldTypeRegistry $fieldTypes */
    $fieldTypes = app(FieldTypeRegistry::class);

    return new SchemaRegistry($fieldTypes);
}
