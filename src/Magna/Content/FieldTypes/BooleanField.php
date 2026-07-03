<?php

declare(strict_types=1);

namespace Magna\Content\FieldTypes;

use Illuminate\Database\Schema\Blueprint;

class BooleanField extends FieldType
{
    public function typeName(): string
    {
        return 'boolean';
    }

    public function isJsonColumn(): bool
    {
        return false;
    }

    public function isRelationOnly(): bool
    {
        return false;
    }

    public function addColumn(Blueprint $table, string $column): void
    {
        $table->boolean($column)->nullable();
    }

    /** @return list<string> */
    public function validationRules(): array
    {
        return ['boolean'];
    }

    public function cast(): ?string
    {
        return 'boolean';
    }
}
