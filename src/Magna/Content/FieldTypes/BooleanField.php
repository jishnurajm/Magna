<?php

declare(strict_types=1);

namespace Magna\Content\FieldTypes;

use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Schema\Blueprint;
use Magna\Content\Field;

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

    public function toFilamentComponent(Field $field): Component
    {
        return Toggle::make($field->handle)
            ->label(ucwords(str_replace('_', ' ', $field->handle)))
            ->required($field->required)
            ->onColor('success')
            ->offColor('danger');
    }
}
