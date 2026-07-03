<?php

declare(strict_types=1);

namespace Magna\Content\FieldTypes;

use Illuminate\Database\Schema\Blueprint;

abstract class FieldType
{
    /** @param array<string, mixed> $options */
    public function __construct(protected readonly array $options = []) {}

    abstract public function typeName(): string;

    abstract public function isJsonColumn(): bool;

    abstract public function isRelationOnly(): bool;

    abstract public function addColumn(Blueprint $table, string $column): void;

    /** @return list<string> */
    abstract public function validationRules(): array;

    abstract public function cast(): ?string;

    protected function boolOption(string $key): bool
    {
        return (bool) ($this->options[$key] ?? false);
    }
}
