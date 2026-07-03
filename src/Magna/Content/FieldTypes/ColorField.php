<?php

declare(strict_types=1);

namespace Magna\Content\FieldTypes;

use Illuminate\Database\Schema\Blueprint;

class ColorField extends FieldType
{
    public function typeName(): string
    {
        return 'color';
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
        $table->string($column, 9)->nullable();
    }

    /** @return list<string> */
    public function validationRules(): array
    {
        return ['string', 'regex:/^#[0-9a-fA-F]{3,8}$/'];
    }

    public function cast(): ?string
    {
        return null;
    }
}
