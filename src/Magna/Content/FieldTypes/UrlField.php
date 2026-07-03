<?php

declare(strict_types=1);

namespace Magna\Content\FieldTypes;

use Illuminate\Database\Schema\Blueprint;

class UrlField extends FieldType
{
    public function typeName(): string
    {
        return 'url';
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
        $table->string($column, 2048)->nullable();
    }

    /** @return list<string> */
    public function validationRules(): array
    {
        return ['string', 'url', 'max:2048'];
    }

    public function cast(): ?string
    {
        return null;
    }
}
