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
use Magna\Settings\SecuritySettings;

/**
 * @property ComponentContainer $form
 */
class SecuritySettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    // Hidden from the sidebar: consolidated into the unified SettingsPage.
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'Security';

    protected static ?string $title = 'Security Settings';

    protected static ?int $navigationSort = 35;

    protected string $view = 'magna::admin.security-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('settings.manage') ?? false;
    }

    public function mount(): void
    {
        $settings = SecuritySettings::get();

        $this->form->fill([
            'force_https' => $settings->force_https,
            'require_email_verification' => $settings->require_email_verification,
            'session_lifetime' => $settings->session_lifetime,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Toggle::make('force_https')
                    ->label('Force HTTPS')
                    ->helperText('Redirect all HTTP requests to HTTPS. Only enable when an SSL certificate is in place.')
                    ->inline(false),

                Toggle::make('require_email_verification')
                    ->label('Require email verification on registration')
                    ->helperText('When enabled, new registrants are sent to the email verification notice instead of being logged in immediately.')
                    ->inline(false),

                TextInput::make('session_lifetime')
                    ->label('Session lifetime (minutes)')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->maxValue(525600)
                    ->helperText('How long an idle web session stays alive. Takes effect on the next request after saving.'),
            ]);
    }

    public function save(): void
    {
        /** @var array{force_https: bool, require_email_verification: bool, session_lifetime: int|string} $data */
        $data = $this->form->getState();

        $settings = SecuritySettings::get();
        $settings->force_https = $data['force_https'];
        $settings->require_email_verification = $data['require_email_verification'];
        $settings->session_lifetime = (int) $data['session_lifetime'];
        $settings->save();

        Notification::make()
            ->title('Security settings saved.')
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
