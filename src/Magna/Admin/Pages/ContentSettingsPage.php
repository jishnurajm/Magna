<?php

declare(strict_types=1);

namespace Magna\Admin\Pages;

use Filament\Actions\Action;
use Filament\Forms\ComponentContainer;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Magna\Settings\ContentSettings;

/**
 * @property ComponentContainer $form
 */
class ContentSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    // Hidden from the sidebar: consolidated into the unified SettingsPage.
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'Content Settings';

    protected static ?string $title = 'Content Settings';

    protected static ?int $navigationSort = 15;

    protected string $view = 'magna::admin.content-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('settings.manage') ?? false;
    }

    public function mount(): void
    {
        $settings = ContentSettings::get();

        $this->form->fill([
            'default_status' => $settings->default_status,
            'revision_limit' => $settings->revision_limit,
            'autosave_interval' => $settings->autosave_interval,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Select::make('default_status')
                    ->label('Default entry status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                    ])
                    ->required()
                    ->helperText('Status applied to newly created entries.'),

                TextInput::make('revision_limit')
                    ->label('Revision limit per entry')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->maxValue(500)
                    ->helperText('Older revisions are pruned when this limit is exceeded.'),

                TextInput::make('autosave_interval')
                    ->label('Autosave interval (seconds)')
                    ->numeric()
                    ->required()
                    ->minValue(15)
                    ->maxValue(600)
                    ->helperText('How often the block editor autosaves a draft.'),
            ]);
    }

    public function save(): void
    {
        /** @var array{default_status: string, revision_limit: int|string, autosave_interval: int|string} $data */
        $data = $this->form->getState();

        $settings = ContentSettings::get();
        $settings->default_status = $data['default_status'];
        $settings->revision_limit = (int) $data['revision_limit'];
        $settings->autosave_interval = (int) $data['autosave_interval'];
        $settings->save();

        Notification::make()
            ->title('Content settings saved.')
            ->success()
            ->send();
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save settings')
                ->action(fn () => $this->save()),
        ];
    }
}
