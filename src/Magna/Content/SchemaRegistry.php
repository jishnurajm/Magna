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

    public function __construct(private readonly FieldTypeRegistry $fieldTypes) {}

    public function register(ContentType $type): void
    {
        $this->types[$type->handle] = $type;
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
