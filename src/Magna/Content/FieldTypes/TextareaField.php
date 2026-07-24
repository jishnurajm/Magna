<?php

declare(strict_types=1);

namespace Magna\Content\FieldTypes;

use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Schema\Blueprint;
use Magna\Content\Field;

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

    public function toFilamentComponent(Field $field): Component
    {
        return Textarea::make($field->handle)
            ->label(ucwords(str_replace('_', ' ', $field->handle)))
            ->required($field->required)
            ->rows(4);
    }
}
