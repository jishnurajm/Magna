<?php

declare(strict_types=1);

namespace Magna\Admin\Resources\Media;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Magna\Admin\Resources\MediaResource;
use Magna\Media\MediaIngestor;

class CreateMedia extends CreateRecord
{
    protected static string $resource = MediaResource::class;

    /**
     * SECURITY (S1-03): Filament's FileUpload component writes the raw file
     * to the public disk as soon as it's selected, before this hook ever
     * runs — bypassing MediaIngestor's content-sniffing/allowlist/size-guard/
     * re-encode/SVG-sanitize pipeline entirely, the same pipeline the JSON
     * Management API's MediaController::store() already goes through. This
     * override routes the raw upload back through MediaIngestor and deletes
     * the unsanitized original, so the admin panel can no longer be used to
     * land an unsanitized file (e.g. an SVG with a script payload) directly
     * in the public webroot.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $storedPath = is_array($data['file'] ?? null)
            ? ($data['file'][0] ?? null)
            : ($data['file'] ?? null);

        if (! is_string($storedPath) || $storedPath === '') {
            throw new \RuntimeException('No file was uploaded.');
        }

        $disk = 'public';
        $fullPath = Storage::disk($disk)->path($storedPath);

        $media = app(MediaIngestor::class)->ingest(
            sourcePath: $fullPath,
            originalFilename: basename($storedPath),
            disk: $disk,
            folderId: is_string($data['folder_id'] ?? null) ? $data['folder_id'] : null,
            alt: is_string($data['alt'] ?? null) ? $data['alt'] : null,
            title: is_string($data['title'] ?? null) ? $data['title'] : null,
        );

        // The raw, unsanitized upload Filament wrote before this hook ran is
        // no longer needed — MediaIngestor stored its own sanitized copy.
        Storage::disk($disk)->delete($storedPath);

        return $media;
    }
}
