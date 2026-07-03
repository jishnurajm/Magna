<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Magna\Content\ContentType;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);
use Magna\Content\FieldTypeRegistry;
use Magna\Content\TableGenerator;

// ── Fixed columns ────────────────────────────────────────────────────────────

it('creates the entry table with all fixed columns', function (): void {
    $type = articleType();

    app(TableGenerator::class)->createTable($type);

    $table = $type->tableName();
    expect(Schema::hasTable($table))->toBeTrue()
        ->and(Schema::hasColumn($table, 'id'))->toBeTrue()
        ->and(Schema::hasColumn($table, 'status'))->toBeTrue()
        ->and(Schema::hasColumn($table, 'locale'))->toBeTrue()
        ->and(Schema::hasColumn($table, 'published_at'))->toBeTrue()
        ->and(Schema::hasColumn($table, 'author_id'))->toBeTrue()
        ->and(Schema::hasColumn($table, 'created_at'))->toBeTrue()
        ->and(Schema::hasColumn($table, 'updated_at'))->toBeTrue();
});

// ── Simple field types create real columns ────────────────────────────────────

it('creates a string column for text field', function (): void {
    $type = makeType('col_text', [['handle' => 'title', 'type' => 'text']]);
    app(TableGenerator::class)->createTable($type);
    expect(Schema::hasColumn($type->tableName(), 'title'))->toBeTrue();
});

it('creates a text column for textarea field', function (): void {
    $type = makeType('col_textarea', [['handle' => 'body', 'type' => 'textarea']]);
    app(TableGenerator::class)->createTable($type);
    expect(Schema::hasColumn($type->tableName(), 'body'))->toBeTrue();
});

it('creates a json column for richtext field', function (): void {
    $type = makeType('col_richtext', [['handle' => 'content', 'type' => 'richtext']]);
    app(TableGenerator::class)->createTable($type);
    expect(Schema::hasColumn($type->tableName(), 'content'))->toBeTrue();
});

it('creates a text column for markdown field', function (): void {
    $type = makeType('col_markdown', [['handle' => 'source', 'type' => 'markdown']]);
    app(TableGenerator::class)->createTable($type);
    expect(Schema::hasColumn($type->tableName(), 'source'))->toBeTrue();
});

it('creates an integer column for number field with integer option', function (): void {
    $type = makeType('col_number_int', [['handle' => 'count', 'type' => 'number', 'integer' => true]]);
    app(TableGenerator::class)->createTable($type);
    expect(Schema::hasColumn($type->tableName(), 'count'))->toBeTrue();
});

it('creates a decimal column for number field without integer option', function (): void {
    $type = makeType('col_number_dec', [['handle' => 'price', 'type' => 'number']]);
    app(TableGenerator::class)->createTable($type);
    expect(Schema::hasColumn($type->tableName(), 'price'))->toBeTrue();
});

it('creates a boolean column for boolean field', function (): void {
    $type = makeType('col_boolean', [['handle' => 'active', 'type' => 'boolean']]);
    app(TableGenerator::class)->createTable($type);
    expect(Schema::hasColumn($type->tableName(), 'active'))->toBeTrue();
});

it('creates a date column for date field', function (): void {
    $type = makeType('col_date', [['handle' => 'born_on', 'type' => 'date']]);
    app(TableGenerator::class)->createTable($type);
    expect(Schema::hasColumn($type->tableName(), 'born_on'))->toBeTrue();
});

it('creates a timestamp column for datetime field', function (): void {
    $type = makeType('col_datetime', [['handle' => 'event_at', 'type' => 'datetime']]);
    app(TableGenerator::class)->createTable($type);
    expect(Schema::hasColumn($type->tableName(), 'event_at'))->toBeTrue();
});

it('creates a string column for select field (single)', function (): void {
    $type = makeType('col_select_single', [['handle' => 'category', 'type' => 'select', 'options' => ['a', 'b']]]);
    app(TableGenerator::class)->createTable($type);
    expect(Schema::hasColumn($type->tableName(), 'category'))->toBeTrue();
});

it('creates a json column for select field with multiple option', function (): void {
    $type = makeType('col_select_multi', [['handle' => 'tags', 'type' => 'select', 'multiple' => true, 'options' => ['a', 'b']]]);
    app(TableGenerator::class)->createTable($type);
    expect(Schema::hasColumn($type->tableName(), 'tags'))->toBeTrue();
});

it('creates a char column for media field (single)', function (): void {
    $type = makeType('col_media_single', [['handle' => 'cover', 'type' => 'media']]);
    app(TableGenerator::class)->createTable($type);
    expect(Schema::hasColumn($type->tableName(), 'cover'))->toBeTrue();
});

it('creates a json column for media field with multiple option', function (): void {
    $type = makeType('col_media_multi', [['handle' => 'gallery', 'type' => 'media', 'multiple' => true]]);
    app(TableGenerator::class)->createTable($type);
    expect(Schema::hasColumn($type->tableName(), 'gallery'))->toBeTrue();
});

it('does NOT create a column for relation field — uses pivot table', function (): void {
    $type = makeType('col_relation', [['handle' => 'related', 'type' => 'relation', 'to' => 'article']]);
    app(TableGenerator::class)->createTable($type);
    expect(Schema::hasColumn($type->tableName(), 'related'))->toBeFalse();
});

it('creates a json column for blocks field', function (): void {
    $type = makeType('col_blocks', [['handle' => 'content_blocks', 'type' => 'blocks']]);
    app(TableGenerator::class)->createTable($type);
    expect(Schema::hasColumn($type->tableName(), 'content_blocks'))->toBeTrue();
});

it('creates a json column for json field', function (): void {
    $type = makeType('col_json', [['handle' => 'metadata', 'type' => 'json']]);
    app(TableGenerator::class)->createTable($type);
    expect(Schema::hasColumn($type->tableName(), 'metadata'))->toBeTrue();
});

it('creates a string column for slug field', function (): void {
    $type = makeType('col_slug', [['handle' => 'slug', 'type' => 'slug']]);
    app(TableGenerator::class)->createTable($type);
    expect(Schema::hasColumn($type->tableName(), 'slug'))->toBeTrue();
});

it('creates a string column for email field', function (): void {
    $type = makeType('col_email', [['handle' => 'email', 'type' => 'email']]);
    app(TableGenerator::class)->createTable($type);
    expect(Schema::hasColumn($type->tableName(), 'email'))->toBeTrue();
});

it('creates a string column for url field', function (): void {
    $type = makeType('col_url', [['handle' => 'website', 'type' => 'url']]);
    app(TableGenerator::class)->createTable($type);
    expect(Schema::hasColumn($type->tableName(), 'website'))->toBeTrue();
});

it('creates a string column for color field', function (): void {
    $type = makeType('col_color', [['handle' => 'brand_color', 'type' => 'color']]);
    app(TableGenerator::class)->createTable($type);
    expect(Schema::hasColumn($type->tableName(), 'brand_color'))->toBeTrue();
});

// ── Relations pivot ───────────────────────────────────────────────────────────

it('creates the magna_relations pivot table', function (): void {
    expect(Schema::hasTable('magna_relations'))->toBeTrue()
        ->and(Schema::hasColumn('magna_relations', 'from_type'))->toBeTrue()
        ->and(Schema::hasColumn('magna_relations', 'from_id'))->toBeTrue()
        ->and(Schema::hasColumn('magna_relations', 'to_type'))->toBeTrue()
        ->and(Schema::hasColumn('magna_relations', 'to_id'))->toBeTrue()
        ->and(Schema::hasColumn('magna_relations', 'field'))->toBeTrue()
        ->and(Schema::hasColumn('magna_relations', 'sort'))->toBeTrue();
});

// ── Helpers ───────────────────────────────────────────────────────────────────

function articleType(): ContentType
{
    return makeType('article', [
        ['handle' => 'title', 'type' => 'text', 'required' => true],
        ['handle' => 'body', 'type' => 'richtext'],
        ['handle' => 'related', 'type' => 'relation', 'to' => 'article'],
    ]);
}

/**
 * @param  list<array<string, mixed>>  $fields
 */
function makeType(string $handle, array $fields = []): ContentType
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
