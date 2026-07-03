<?php

declare(strict_types=1);

namespace Magna\Content\FieldTypes;

use Illuminate\Database\Schema\Blueprint;

class TextField extends FieldType
{
    public function typeName(): string
    {
        return 'text';
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
        $table->string($column)->nullable();
    }

    /** @return list<string> */
    public function validationRules(): array
    {
        return ['string', 'max:255'];
    }

    public function cast(): ?string
    {
        return null;
    }
}
