<?php

declare(strict_types=1);

namespace Magna\Content\FieldTypes;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Schema\Blueprint;
use Magna\Content\Field;

abstract class FieldType
{
    /** @param array<string, mixed> $options */
    public function __construct(protected readonly array $options = []) {}

    abstract public function typeName(): string;

    abstract public function isJsonColumn(): bool;

    abstract public function isRelationOnly(): bool;

    abstract public function addColumn(Blueprint $table, string $column): void;

    /** @return list<string> */
    abstract public function validationRules(): array;

    abstract public function cast(): ?string;

    /**
     * Return the Filament form component for this field type.
     * Subclasses override to provide the most appropriate control.
     * The default fallback is a plain TextInput.
     */
    public function toFilamentComponent(Field $field): Component
    {
        return TextInput::make($field->handle)
            ->label(ucwords(str_replace('_', ' ', $field->handle)))
            ->required($field->required);
    }

    protected function boolOption(string $key): bool
    {
        return (bool) ($this->options[$key] ?? false);
    }

    protected function stringOption(string $key, string $default = ''): string
    {
        $val = $this->options[$key] ?? $default;

        return is_string($val) ? $val : $default;
    }
}
