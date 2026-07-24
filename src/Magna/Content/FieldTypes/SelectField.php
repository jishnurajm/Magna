<?php

declare(strict_types=1);

namespace Magna\Content\FieldTypes;

use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Schema\Blueprint;
use Magna\Content\Field;

class SelectField extends FieldType
{
    public function typeName(): string
    {
        return 'select';
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
            $table->string($column)->nullable();
        }
    }

    /** @return list<string> */
    public function validationRules(): array
    {
        $rawOptions = $this->options['options'] ?? [];
        $inRule = null;

        if (is_array($rawOptions) && $rawOptions !== []) {
            $parts = [];
            foreach ($rawOptions as $v) {
                if (is_scalar($v)) {
                    $parts[] = (string) $v;
                }
            }
            $inRule = 'in:'.implode(',', $parts);
        }

        if ($this->boolOption('multiple')) {
            return $inRule !== null ? ['array', $inRule] : ['array'];
        }

        return $inRule !== null ? ['string', $inRule] : ['string'];
    }

    public function cast(): ?string
    {
        return $this->boolOption('multiple') ? 'array' : null;
    }

    public function toFilamentComponent(Field $field): Component
    {
        $rawOptions = $this->options['options'] ?? [];
        $selectOptions = [];

        if (is_array($rawOptions)) {
            foreach ($rawOptions as $v) {
                if (is_scalar($v)) {
                    $str = (string) $v;
                    $selectOptions[$str] = ucwords(str_replace(['_', '-'], ' ', $str));
                }
            }
        }

        $select = Select::make($field->handle)
            ->label(ucwords(str_replace('_', ' ', $field->handle)))
            ->required($field->required)
            ->options($selectOptions);

        if ($this->boolOption('multiple')) {
            $select->multiple();
        }

        return $select;
    }
}
