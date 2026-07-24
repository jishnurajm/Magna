<?php

declare(strict_types=1);

namespace Magna\Content\FieldTypes;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Schema\Blueprint;
use Magna\Content\Field;

class MediaField extends FieldType
{
    public function typeName(): string
    {
        return 'media';
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
            $table->char($column, 26)->nullable();
        }
    }

    /** @return list<string> */
    public function validationRules(): array
    {
        if ($this->boolOption('multiple')) {
            return ['array'];
        }

        return ['string', 'size:26'];
    }

    public function cast(): ?string
    {
        return $this->boolOption('multiple') ? 'array' : null;
    }

    public function toFilamentComponent(Field $field): Component
    {
        $target = 'entry_media_'.$field->handle;

        return TextInput::make($field->handle)
            ->label(ucwords(str_replace('_', ' ', $field->handle)))
            ->placeholder('No media selected (ULID or path)')
            ->hint('Enter a media ULID or use the picker')
            ->hintAction(
                Action::make('browse_media_'.$field->handle)
                    ->label('Browse library')
                    ->icon('heroicon-o-photo')
                    ->alpineClickHandler(
                        "\$dispatch('magna:open-media-picker', { target: '{$target}' })",
                    ),
            )
            ->extraInputAttributes([
                'x-on:magna:media-selected.window' => "
                    if (\$event.detail.target === '{$target}') {
                        \$el.value = \$event.detail.path;
                        \$el.dispatchEvent(new Event('input'));
                    }
                ",
            ]);
    }
}
