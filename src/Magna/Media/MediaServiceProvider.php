<?php

declare(strict_types=1);

namespace Magna\Media;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Magna\Media\Console\MediaReconvertCommand;
use Magna\Media\Livewire\MediaPickerModal;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ConversionPresetRegistry::class, function (): ConversionPresetRegistry {
            $registry = new ConversionPresetRegistry;
            $registry->register(new ConversionPreset('thumb', 150, 150, fit: true));
            $registry->register(new ConversionPreset('card', 600, 400, fit: true));
            $registry->register(new ConversionPreset('hero', 1920, 1080, fit: false));

            return $registry;
        });

        $this->app->singleton(MediaIngestor::class, function (Application $app): MediaIngestor {
            $diskConfig = config('magna.media.disk', 'public');

            return new MediaIngestor(
                $app->make(ConversionPresetRegistry::class),
                is_string($diskConfig) ? $diskConfig : 'public',
            );
        });

        $this->app->singleton(MediaUrlResolver::class);
    }

    public function boot(): void
    {
        // Views for the global media picker (magna::livewire.media-picker-modal).
        $this->loadViewsFrom(__DIR__.'/views', 'magna');

        // Global reusable media picker — available in every Filament page and plugin.
        Livewire::component('magna-media-picker', MediaPickerModal::class);

        // Signed-URL delivery route for non-S3 disks.
        // Stage 8 will add preset resolution and auth middleware.
        Route::get('/_media/{media}', function (Media $media): StreamedResponse {
            return Storage::disk($media->disk)->response($media->path);
        })->middleware('signed')->name('magna.media.serve');

        if ($this->app->runningInConsole()) {
            $this->commands([MediaReconvertCommand::class]);
        }
    }
}
