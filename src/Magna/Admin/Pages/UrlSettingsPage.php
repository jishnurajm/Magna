<?php

declare(strict_types=1);

namespace Magna\Admin\Pages;

use Filament\Actions\Action;
use Filament\Forms\ComponentContainer;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Magna\Plugins\PluginRecord;
use Magna\Settings\UrlSettings;

/**
 * @property ComponentContainer $form
 */
class UrlSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-link';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    // Hidden from the sidebar: consolidated into the unified SettingsPage.
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'URLs';

    protected static ?string $title = 'URLs';

    protected static ?int $navigationSort = 11;

    protected string $view = 'magna::admin.url-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('settings.manage') ?? false;
    }

    /**
     * Frontend URLs are configuration for the Magna Pages plugin (the optional
     * rendered website). Without it, Magna is purely headless and those URLs
     * have nothing to point at, so the fields are gated behind the plugin.
     */
    private function magnaPagesInstalled(): bool
    {
        return PluginRecord::query()
            ->where('name', 'magna/pages')
            ->where('enabled', true)
            ->exists();
    }

    public function mount(): void
    {
        $settings = UrlSettings::get();

        $state = ['cdn_url' => $settings->cdn_url];

        if ($this->magnaPagesInstalled()) {
            $state['frontend_url'] = $settings->frontend_url;
            $state['preview_base_url'] = $settings->preview_base_url;
        }

        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                $this->frontendSection(),
                Section::make('Media delivery')
                    ->description('Serve public media from a CDN instead of application storage.')
                    ->schema([
                        TextInput::make('cdn_url')
                            ->label('CDN URL')
                            ->url()
                            ->maxLength(255)
                            ->placeholder('https://cdn.example.com')
                            ->helperText('When set, public media URLs are served from this origin. Leave blank to serve directly from storage.'),
                    ]),
            ]);
    }

    private function frontendSection(): Section
    {
        if ($this->magnaPagesInstalled()) {
            return Section::make('Frontend website')
                ->description('URLs for the public website that renders your content.')
                ->schema([
                    TextInput::make('frontend_url')
                        ->label('Frontend URL')
                        ->url()
                        ->maxLength(255)
                        ->placeholder('https://example.com')
                        ->helperText('Base URL of your public site. Used for "View site" links and email links.'),

                    TextInput::make('preview_base_url')
                        ->label('Preview base URL')
                        ->url()
                        ->maxLength(255)
                        ->placeholder('https://preview.example.com')
                        ->helperText('Base URL for draft preview links. Falls back to the frontend URL when blank.'),
                ]);
        }

        return Section::make('Frontend website')
            ->description('Configure the public website that renders your content.')
            ->schema([
                Placeholder::make('magna_pages_notice')
                    ->hiddenLabel()
                    ->content(new HtmlString(<<<'HTML'
                        <div class="flex gap-3 rounded-xl border border-violet-500/20 bg-violet-500/5 p-4">
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-violet-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a15 15 0 0 1 0 18a15 15 0 0 1 0-18"/>
                            </svg>
                            <div class="text-sm">
                                <p class="font-medium text-gray-800 dark:text-gray-100">Frontend configuration requires Magna Pages</p>
                                <p class="mt-1 text-gray-500 dark:text-gray-400">Magna is headless by default — it delivers content through APIs. To publish a rendered website and set its URLs, install the <strong>Magna Pages</strong> plugin from the Plugins section, then return here to configure it.</p>
                            </div>
                        </div>
                    HTML)),
            ]);
    }

    public function save(): void
    {
        /** @var array<string, string|null> $data */
        $data = $this->form->getState();

        // Optional URL fields return null when cleared, so coerce before
        // trimming — rtrim() rejects null and the settings props are strings.
        $trim = static fn (mixed $value): string => rtrim(is_string($value) ? $value : '', '/');

        $settings = UrlSettings::get();
        $settings->cdn_url = $trim($data['cdn_url'] ?? null);

        if ($this->magnaPagesInstalled()) {
            $settings->frontend_url = $trim($data['frontend_url'] ?? null);
            $settings->preview_base_url = $trim($data['preview_base_url'] ?? null);
        }

        $settings->save();

        Notification::make()
            ->title('URL settings saved.')
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
