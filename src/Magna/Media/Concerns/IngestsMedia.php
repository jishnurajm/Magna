<?php

declare(strict_types=1);

namespace Magna\Media\Concerns;

use Illuminate\Support\Facades\Storage;
use Magna\Media\MediaIngestor;

/**
 * Registers files uploaded through a Filament form field into the central
 * Magna media library (magna_media table) so they appear in /media and carry
 * uploader attribution.
 *
 * Usage in a Livewire/Filament page:
 *
 *   use Magna\Media\Concerns\IngestsMedia;
 *
 *   $path = $this->ingestToMedia($data['logo_path']);
 *
 * If the path already starts with 'media/' it is returned unchanged (already
 * a managed library item, e.g. picked via the media picker). Returns null when
 * the file is missing or ingestion fails.
 */
trait IngestsMedia
{
    /**
     * Ingest a file stored at $path on $disk into the media library and return
     * the canonical library path.  Returns the original path unchanged if it is
     * already a managed media item, or null when the file is missing / ingest
     * fails.
     */
    protected function ingestToMedia(?string $path, string $disk = 'public'): ?string
    {
        $path = (string) $path;

        if ($path === '') {
            return null;
        }

        // Already a managed media item — leave it as-is.
        if (str_starts_with($path, 'media/')) {
            return $path;
        }

        try {
            $absolute = Storage::disk($disk)->path($path);
            if (! is_file($absolute)) {
                return null;
            }

            return app(MediaIngestor::class)
                ->ingest($absolute, basename($path), 'public')
                ->path;
        } catch (\Throwable) {
            return null;
        }
    }
}
