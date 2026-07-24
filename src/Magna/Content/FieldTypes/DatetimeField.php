<?php

declare(strict_types=1);

namespace Magna\Content\FieldTypes;

use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Schema\Blueprint;
use Magna\Content\Field;

class DatetimeField extends FieldType
{
    public function typeName(): string
    {
        return 'datetime';
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
        $table->timestamp($column)->nullable();
    }

    /** @return list<string> */
    public function validationRules(): array
    {
        return ['date'];
    }

    public function cast(): ?string
    {
        return 'datetime';
    }

    public function toFilamentComponent(Field $field): Component
    {
        return DateTimePicker::make($field->handle)
            ->label(ucwords(str_replace('_', ' ', $field->handle)))
            ->required($field->required)
            ->native(false)
            ->seconds(false);
    }
}
