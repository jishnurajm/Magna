<?php

declare(strict_types=1);

namespace Magna\Admin\Pages;

use Filament\Actions\Action;
use Filament\Forms\ComponentContainer;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Magna\Settings\LocalizationSettings;

/**
 * @property ComponentContainer $form
 */
class LocalizationSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-language';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    // Hidden from the sidebar: consolidated into the unified SettingsPage.
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'Localization';

    protected static ?string $title = 'Localization Settings';

    protected static ?int $navigationSort = 12;

    protected string $view = 'magna::admin.localization-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('settings.manage') ?? false;
    }

    public function mount(): void
    {
        $settings = LocalizationSettings::get();

        $this->form->fill([
            'available_locales' => $settings->available_locales,
            'fallback_locale' => $settings->fallback_locale,
            'rtl_locales' => $settings->rtl_locales,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                TagsInput::make('available_locales')
                    ->label('Available locales')
                    ->placeholder('Add locale code (e.g. en, fr, de)')
                    ->helperText('Locale codes the site supports. The delivery API accepts ?locale= values from this list.'),

                TextInput::make('fallback_locale')
                    ->label('Fallback locale')
                    ->required()
                    ->maxLength(10)
                    ->placeholder('en')
                    ->helperText('Used when requested content is missing in the requested locale.'),

                TagsInput::make('rtl_locales')
                    ->label('RTL locales')
                    ->placeholder('Add RTL locale code (e.g. ar, he)')
                    ->helperText('Locale codes that use right-to-left text direction.'),
            ]);
    }

    public function save(): void
    {
        /** @var array{available_locales: list<string>, fallback_locale: string, rtl_locales: list<string>} $data */
        $data = $this->form->getState();

        $settings = LocalizationSettings::get();
        $settings->available_locales = array_values($data['available_locales']);
        $settings->fallback_locale = $data['fallback_locale'];
        $settings->rtl_locales = array_values($data['rtl_locales']);
        $settings->save();

        Notification::make()
            ->title('Localization settings saved.')
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
