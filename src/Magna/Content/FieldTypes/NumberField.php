<?php

declare(strict_types=1);

namespace Magna\Content\FieldTypes;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Schema\Blueprint;
use Magna\Content\Field;

class NumberField extends FieldType
{
    public function typeName(): string
    {
        return 'number';
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
        if ($this->boolOption('integer')) {
            $table->integer($column)->nullable();
        } else {
            $table->decimal($column, 18, 6)->nullable();
        }
    }

    /** @return list<string> */
    public function validationRules(): array
    {
        $rules = ['numeric'];

        $min = $this->options['min'] ?? null;
        $max = $this->options['max'] ?? null;

        if (is_numeric($min)) {
            $rules[] = 'min:'.$min;
        }
        if (is_numeric($max)) {
            $rules[] = 'max:'.$max;
        }

        return $rules;
    }

    public function cast(): ?string
    {
        return $this->boolOption('integer') ? 'integer' : 'float';
    }

    public function toFilamentComponent(Field $field): Component
    {
        $input = TextInput::make($field->handle)
            ->label(ucwords(str_replace('_', ' ', $field->handle)))
            ->required($field->required)
            ->numeric();

        $min = $this->options['min'] ?? null;
        $max = $this->options['max'] ?? null;

        if (is_numeric($min)) {
            $input->minValue((float) $min);
        }

        if (is_numeric($max)) {
            $input->maxValue((float) $max);
        }

        return $input;
    }
}
