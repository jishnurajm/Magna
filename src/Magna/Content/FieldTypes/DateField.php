<?php

declare(strict_types=1);

namespace Magna\Content\FieldTypes;

use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Schema\Blueprint;
use Magna\Content\Field;

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

    public function toFilamentComponent(Field $field): Component
    {
        return DatePicker::make($field->handle)
            ->label(ucwords(str_replace('_', ' ', $field->handle)))
            ->required($field->required)
            ->native(false);
    }
}
