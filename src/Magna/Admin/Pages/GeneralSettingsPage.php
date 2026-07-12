<?php

declare(strict_types=1);

namespace Magna\Admin\Pages;

use Filament\Actions\Action;
use Filament\Forms\ComponentContainer;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Magna\Settings\GeneralSettings;

/**
 * @property ComponentContainer $form
 */
class GeneralSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    // Hidden from the sidebar: consolidated into the unified SettingsPage.
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'General Settings';

    protected static ?string $title = 'General Settings';

    protected static ?int $navigationSort = 10;

    protected string $view = 'magna::admin.general-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('settings.manage') ?? false;
    }

    public function mount(): void
    {
        $settings = GeneralSettings::get();

        $this->form->fill([
            'timezone' => $settings->timezone,
            'default_locale' => $settings->default_locale,
            'registration_enabled' => $settings->registration_enabled,
            'date_format' => $settings->date_format,
            'time_format' => $settings->time_format,
            'first_day_of_week' => $settings->first_day_of_week,
            'currency' => $settings->currency,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Access')
                    ->schema([
                        Toggle::make('registration_enabled')
                            ->label('Allow public registration')
                            ->helperText('When disabled, only admins can create new user accounts.')
                            ->inline(false),
                    ]),

                Section::make('Locale & regional')
                    ->schema([
                        Select::make('timezone')
                            ->label('Default timezone')
                            ->required()
                            ->searchable()
                            ->options(fn (): array => array_combine(timezone_identifiers_list(), timezone_identifiers_list())),

                        TextInput::make('default_locale')
                            ->label('Default language')
                            ->required()
                            ->maxLength(10)
                            ->placeholder('en')
                            ->helperText('BCP 47 locale code (e.g. en, fr, de).'),

                        Select::make('date_format')
                            ->label('Default date format')
                            ->options([
                                'Y-m-d' => 'ISO 8601 (2025-01-31)',
                                'd/m/Y' => 'DD/MM/YYYY (31/01/2025)',
                                'm/d/Y' => 'MM/DD/YYYY (01/31/2025)',
                                'd.m.Y' => 'DD.MM.YYYY (31.01.2025)',
                                'M j, Y' => 'Jan 31, 2025',
                                'j F Y' => '31 January 2025',
                            ])
                            ->required(),

                        Select::make('time_format')
                            ->label('Default time format')
                            ->options([
                                'H:i' => '24-hour (14:30)',
                                'g:i A' => '12-hour (2:30 PM)',
                            ])
                            ->required(),

                        Select::make('first_day_of_week')
                            ->label('First day of week')
                            ->options([
                                0 => 'Sunday',
                                1 => 'Monday',
                                6 => 'Saturday',
                            ])
                            ->required(),

                        TextInput::make('currency')
                            ->label('Default currency')
                            ->maxLength(10)
                            ->placeholder('USD')
                            ->helperText('ISO 4217 currency code (e.g. USD, EUR, GBP). Optional.')
                            ->nullable(),
                    ]),
            ]);
    }

    public function save(): void
    {
        /** @var array{
         *   timezone: string,
         *   default_locale: string,
         *   registration_enabled: bool,
         *   date_format: string,
         *   time_format: string,
         *   first_day_of_week: int|string,
         *   currency: string,
         * } $data
         */
        $data = $this->form->getState();

        $settings = GeneralSettings::get();
        $settings->timezone = $data['timezone'];
        $settings->default_locale = $data['default_locale'];
        $settings->registration_enabled = $data['registration_enabled'];
        $settings->date_format = $data['date_format'];
        $settings->time_format = $data['time_format'];
        $settings->first_day_of_week = (int) $data['first_day_of_week'];
        $settings->currency = $data['currency'] ?? '';
        $settings->save();

        Notification::make()
            ->title('General settings saved.')
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
