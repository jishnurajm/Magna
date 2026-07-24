<?php

declare(strict_types=1);

namespace Magna\Content\FieldTypes;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Schema\Blueprint;
use Magna\Content\Field;

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

    public function toFilamentComponent(Field $field): Component
    {
        return TextInput::make($field->handle)
            ->label(ucwords(str_replace('_', ' ', $field->handle)))
            ->required($field->required)
            ->maxLength(255);
    }
}
