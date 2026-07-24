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
use Magna\Settings\MediaSettings;

/**
 * @property ComponentContainer $form
 */
class MediaSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    // Hidden from the sidebar: consolidated into the unified SettingsPage.
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'Media Settings';

    protected static ?string $title = 'Media Settings';

    protected static ?int $navigationSort = 25;

    protected string $view = 'magna::admin.media-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('settings.manage') ?? false;
    }

    public function mount(): void
    {
        $settings = MediaSettings::get();

        $this->form->fill([
            'max_image_upload_bytes' => $settings->max_image_upload_bytes,
            'max_svg_upload_bytes' => $settings->max_svg_upload_bytes,
            'max_document_upload_bytes' => $settings->max_document_upload_bytes,
            'default_image_quality' => $settings->default_image_quality,
            'webp_enabled' => $settings->webp_enabled,
            'avif_enabled' => $settings->avif_enabled,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                TextInput::make('max_image_upload_bytes')
                    ->label('Max image upload (bytes)')
                    ->numeric()
                    ->required()
                    ->minValue(1_048_576)
                    ->helperText('Applies to JPEG, PNG, GIF, WebP, and AVIF uploads. Default: 20971520 (20 MB).'),

                TextInput::make('max_svg_upload_bytes')
                    ->label('Max SVG upload (bytes)')
                    ->numeric()
                    ->required()
                    ->minValue(1_024)
                    ->helperText('Default: 2097152 (2 MB).'),

                TextInput::make('max_document_upload_bytes')
                    ->label('Max document upload (bytes)')
                    ->numeric()
                    ->required()
                    ->minValue(1_048_576)
                    ->helperText('Applies to PDFs and other documents. Default: 52428800 (50 MB).'),

                TextInput::make('default_image_quality')
                    ->label('Image encoding quality (1–100)')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->maxValue(100)
                    ->helperText('Used when re-encoding JPEG, WebP, and AVIF during ingest and conversion.'),

                Toggle::make('webp_enabled')
                    ->label('Generate WebP conversions')
                    ->helperText('Produce a WebP variant for each conversion preset.')
                    ->inline(false),

                Toggle::make('avif_enabled')
                    ->label('Generate AVIF conversions')
                    ->helperText('Produce an AVIF variant for each conversion preset (best-effort; requires GD with libavif).')
                    ->inline(false),
            ]);
    }

    public function save(): void
    {
        /** @var array{max_image_upload_bytes: int|string, max_svg_upload_bytes: int|string, max_document_upload_bytes: int|string, default_image_quality: int|string, webp_enabled: bool, avif_enabled: bool} $data */
        $data = $this->form->getState();

        $settings = MediaSettings::get();
        $settings->max_image_upload_bytes = (int) $data['max_image_upload_bytes'];
        $settings->max_svg_upload_bytes = (int) $data['max_svg_upload_bytes'];
        $settings->max_document_upload_bytes = (int) $data['max_document_upload_bytes'];
        $settings->default_image_quality = (int) $data['default_image_quality'];
        $settings->webp_enabled = $data['webp_enabled'];
        $settings->avif_enabled = $data['avif_enabled'];
        $settings->save();

        Notification::make()
            ->title('Media settings saved.')
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
