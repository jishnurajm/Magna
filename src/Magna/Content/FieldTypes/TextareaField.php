<?php

declare(strict_types=1);

namespace Magna\Content\FieldTypes;

use Illuminate\Database\Schema\Blueprint;

class TextareaField extends FieldType
{
    public function typeName(): string
    {
        return 'textarea';
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
        $table->text($column)->nullable();
    }

    /** @return list<string> */
    public function validationRules(): array
    {
        return ['string'];
    }

    public function cast(): ?string
    {
        return null;
    }
}
