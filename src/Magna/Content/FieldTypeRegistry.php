<?php

declare(strict_types=1);

namespace Magna\Content;

use Magna\Content\Exceptions\SchemaException;
use Magna\Content\FieldTypes\FieldType;

class FieldTypeRegistry
{
    /** @var array<string, class-string<FieldType>> */
    private array $types = [];

    /** @param class-string<FieldType> $class */
    public function register(string $name, string $class): void
    {
        $this->types[$name] = $class;
    }

    /**
     * @param  array<string, mixed>  $options
     *
     * @throws SchemaException
     */
    public function make(string $name, array $options = []): FieldType
    {
        $class = $this->types[$name] ?? null;
        if ($class === null) {
            throw new SchemaException("Unknown field type: \"{$name}\".");
        }

        return new $class($options);
    }

    public function has(string $name): bool
    {
        return isset($this->types[$name]);
    }

    /** @return array<string, class-string<FieldType>> */
    public function all(): array
    {
        return $this->types;
    }
}
