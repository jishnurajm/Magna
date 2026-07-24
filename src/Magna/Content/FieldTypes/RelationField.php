<?php

declare(strict_types=1);

namespace Magna\Content\FieldTypes;

use Filament\Forms\Components\TagsInput;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Schema\Blueprint;
use Magna\Content\Field;

class RelationField extends FieldType
{
    public function typeName(): string
    {
        return 'relation';
    }

    public function isJsonColumn(): bool
    {
        return false;
    }

    public function isRelationOnly(): bool
    {
        return true;
    }

    public function addColumn(Blueprint $table, string $column): void
    {
        // Relation fields use the magna_relations pivot — no column in the entry table.
    }

    /** @return list<string> */
    public function validationRules(): array
    {
        return ['array'];
    }

    public function cast(): ?string
    {
        return null;
    }

    public function toFilamentComponent(Field $field): Component
    {
        // Relation IDs stored as tags (ULIDs); proper Select with options
        // is wired in Stage 11 once the entry query builder is panel-aware.
        return TagsInput::make($field->handle)
            ->label(ucwords(str_replace('_', ' ', $field->handle)))
            ->required($field->required)
            ->placeholder('Enter related entry IDs');
    }
}
