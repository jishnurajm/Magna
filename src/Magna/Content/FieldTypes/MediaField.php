<?php

declare(strict_types=1);

namespace Magna\Content\FieldTypes;

use Illuminate\Database\Schema\Blueprint;

class MediaField extends FieldType
{
    public function typeName(): string
    {
        return 'media';
    }

    public function isJsonColumn(): bool
    {
        return $this->boolOption('multiple');
    }

    public function isRelationOnly(): bool
    {
        return false;
    }

    public function addColumn(Blueprint $table, string $column): void
    {
        if ($this->boolOption('multiple')) {
            $table->json($column)->nullable();
        } else {
            $table->char($column, 26)->nullable();
        }
    }

    /** @return list<string> */
    public function validationRules(): array
    {
        if ($this->boolOption('multiple')) {
            return ['array'];
        }

        return ['string', 'size:26'];
    }

    public function cast(): ?string
    {
        return $this->boolOption('multiple') ? 'array' : null;
    }
}
