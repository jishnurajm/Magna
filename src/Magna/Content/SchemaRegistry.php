<?php

declare(strict_types=1);

namespace Magna\Content;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;
use Magna\Content\Models\ContentTypeRecord;

class SchemaRegistry
{
    /** @var array<string, ContentType> */
    private array $types = [];

    /** @var list<callable(ContentType): void> */
    private array $onRegisterCallbacks = [];

    public function __construct(private readonly FieldTypeRegistry $fieldTypes) {}

    /**
     * Register a callback that fires every time a content type is registered.
     * Called immediately for types already registered at the time of subscription.
     *
     * @param  callable(ContentType): void  $callback
     */
    public function onTypeRegistered(callable $callback): void
    {
        $this->onRegisterCallbacks[] = $callback;

        foreach ($this->types as $type) {
            $callback($type);
        }
    }

    public function register(ContentType $type): void
    {
        $this->types[$type->handle] = $type;

        foreach ($this->onRegisterCallbacks as $callback) {
            $callback($type);
        }
    }

    /**
     * Load all JSON schema files from a directory.
     */
    public function loadFromDirectory(string $path): void
    {
        foreach (glob($path.'/*.json') ?: [] as $file) {
            $type = ContentType::fromFile($file, $this->fieldTypes);
            $this->register($type);
        }
    }

    /**
     * Load database-defined content types from the content_types table.
     */
    public function loadFromDatabase(): void
    {
        if (! Schema::hasTable('content_types')) {
            return;
        }

        /** @var Collection<int, ContentTypeRecord> $records */
        $records = ContentTypeRecord::query()->get();

        foreach ($records as $record) {
            $type = ContentType::fromArray($record->schema, $this->fieldTypes);
            $this->register($type);
        }
    }

    /**
     * Remove a content type from the in-memory registry. Used when a plugin
     * that owns the type is disabled or uninstalled, so its navigation and
     * resources disappear within the same request.
     */
    public function forget(string $handle): void
    {
        unset($this->types[$handle]);
    }

    public function get(string $handle): ?ContentType
    {
        return $this->types[$handle] ?? null;
    }

    public function has(string $handle): bool
    {
        return isset($this->types[$handle]);
    }

    /** @return array<string, ContentType> */
    public function all(): array
    {
        return $this->types;
    }

    public function fieldTypes(): FieldTypeRegistry
    {
        return $this->fieldTypes;
    }
}
