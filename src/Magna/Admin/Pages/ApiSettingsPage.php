<?php

declare(strict_types=1);

namespace Magna\Admin\Pages;

use Filament\Actions\Action;
use Filament\Forms\ComponentContainer;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Magna\Settings\ApiSettings;

/**
 * @property ComponentContainer $form
 */
class ApiSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-code-bracket';

    protected static string|\UnitEnum|null $navigationGroup = 'API';

    protected static ?string $navigationLabel = 'API Settings';

    protected static ?string $title = 'API Settings';

    protected static ?int $navigationSort = 10;

    protected string $view = 'magna::admin.api-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('settings.manage') ?? false;
    }

    public function mount(): void
    {
        $settings = ApiSettings::get();

        $this->form->fill([
            'api_enabled' => $settings->api_enabled,
            'default_per_page' => $settings->default_per_page,
            'max_per_page' => $settings->max_per_page,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Toggle::make('api_enabled')
                    ->label('API enabled')
                    ->helperText('When disabled, all delivery API requests return 503.')
                    ->inline(false),

                TextInput::make('default_per_page')
                    ->label('Default page size')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->maxValue(500)
                    ->helperText('Number of items returned when ?per_page is not specified.'),

                TextInput::make('max_per_page')
                    ->label('Maximum page size')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->maxValue(500)
                    ->helperText('Hard ceiling; requests above this value are clamped silently.'),
            ]);
    }

    public function save(): void
    {
        /** @var array{api_enabled: bool, default_per_page: int|string, max_per_page: int|string} $data */
        $data = $this->form->getState();

        $settings = ApiSettings::get();
        $settings->api_enabled = $data['api_enabled'];
        $settings->default_per_page = (int) $data['default_per_page'];
        $settings->max_per_page = (int) $data['max_per_page'];
        $settings->save();

        Notification::make()
            ->title('API settings saved.')
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
