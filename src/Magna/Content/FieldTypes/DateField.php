<?php

declare(strict_types=1);

namespace Magna\Content\FieldTypes;

use Illuminate\Database\Schema\Blueprint;

class DateField extends FieldType
{
    public function typeName(): string
    {
        return 'date';
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
        $table->date($column)->nullable();
    }

    /** @return list<string> */
    public function validationRules(): array
    {
        return ['date'];
    }

    public function cast(): ?string
    {
        return 'date';
    }
}
