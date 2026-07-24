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
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Magna\Settings\MailSettings;

/**
 * @property ComponentContainer $form
 */
class MailSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    // Hidden from the sidebar: consolidated into the unified SettingsPage.
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'Mail Settings';

    protected static ?string $title = 'Mail Settings';

    protected static ?int $navigationSort = 20;

    protected string $view = 'magna::admin.mail-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('settings.manage') ?? false;
    }

    public function mount(): void
    {
        $settings = MailSettings::get();

        $this->form->fill([
            'driver' => $settings->driver,
            'host' => $settings->host,
            'port' => $settings->port,
            'username' => $settings->username,
            // Password is secret — never pre-fill; show placeholder instead.
            'password' => null,
            'from_address' => $settings->from_address,
            'from_name' => $settings->from_name,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components(self::fields());
    }

    /**
     * The mail settings field group — shared with SettingsPage's "Email" tab
     * so the two surfaces can't drift out of sync with each other.
     *
     * @return list<Component>
     */
    public static function fields(): array
    {
        return [
            Select::make('driver')
                ->label('Mail driver')
                ->options([
                    'smtp' => 'SMTP',
                    'sendmail' => 'Sendmail',
                    'log' => 'Log (development)',
                    'array' => 'Array (testing)',
                    'ses' => 'Amazon SES',
                    'mailgun' => 'Mailgun',
                ])
                ->required(),

            TextInput::make('host')
                ->label('SMTP host')
                ->maxLength(255),

            TextInput::make('port')
                ->label('SMTP port')
                ->numeric()
                ->minValue(1)
                ->maxValue(65535),

            TextInput::make('username')
                ->label('Username')
                ->maxLength(255)
                ->nullable(),

            TextInput::make('password')
                ->label('Password')
                ->password()
                ->nullable()
                ->placeholder('[secret — leave blank to keep current]')
                ->helperText('Leave blank to keep the existing password unchanged.'),

            TextInput::make('from_address')
                ->label('From address')
                ->email()
                ->maxLength(255),

            TextInput::make('from_name')
                ->label('From name')
                ->maxLength(255),
        ];
    }

    public function save(): void
    {
        /** @var array{driver: string, host: string, port: int|string, username: ?string, password: ?string, from_address: ?string, from_name: string} $data */
        $data = $this->form->getState();

        $settings = MailSettings::get();
        $settings->driver = $data['driver'];
        // host and from_name are optional inputs → null when cleared; coerce to
        // string since the settings properties are non-nullable.
        $settings->host = (string) ($data['host'] ?? '');
        $settings->port = (int) $data['port'];
        $settings->username = $data['username'] ?: null;
        $settings->from_address = $data['from_address'] ?? $settings->from_address;
        $settings->from_name = (string) ($data['from_name'] ?? '');

        // Only overwrite the secret password if the user supplied a new value.
        if (filled($data['password'])) {
            $settings->password = $data['password'];
        }

        $settings->save();

        Notification::make()
            ->title('Mail settings saved.')
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
