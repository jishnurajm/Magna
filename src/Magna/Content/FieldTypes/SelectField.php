<?php

declare(strict_types=1);

namespace Magna\Content\FieldTypes;

use Illuminate\Database\Schema\Blueprint;

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
}
