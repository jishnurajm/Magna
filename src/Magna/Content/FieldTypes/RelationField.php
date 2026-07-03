<?php

declare(strict_types=1);

namespace Magna\Content\FieldTypes;

use Illuminate\Database\Schema\Blueprint;

class RelationField extends FieldType
{
    public function typeName(): string
    {
        return 'relation';
    }

    public function isJsonColumn(): bool
    {
        return false;
    }

    public function isRelationOnly(): bool
    {
        return true;
    }

    public function addColumn(Blueprint $table, string $column): void
    {
        // Relation fields use the magna_relations pivot — no column in the entry table.
    }

    /** @return list<string> */
    public function validationRules(): array
    {
        return ['array'];
    }

    public function cast(): ?string
    {
        return null;
    }
}
