<?php

declare(strict_types=1);

namespace Magna\Media;

use enshrined\svgSanitize\Sanitizer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Magna\Media\Exceptions\MediaIngestException;
use Magna\Media\Exceptions\MimeTypeNotAllowedException;
use Magna\Media\Jobs\ProcessMediaConversionJob;
use Magna\Settings\MediaSettings;

class MediaIngestor
{
    /**
     * Content-sniffed MIME types that are allowed through the upload pipeline.
     * Never trust file extensions — only trust what finfo reports.
     *
     * @var list<string>
     */
    private const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/avif',
        'image/svg+xml',
        'application/pdf',
    ];

    /**
     * Raster image MIME types that must be re-encoded on ingest to strip
     * embedded payloads, EXIF, and malicious metadata.
     *
     * @var list<string>
     */
    private const IMAGE_MIMES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/avif',
    ];

    /** Resolves per-MIME size limits from MediaSettings at ingest time. */
    private function maxSizeFor(string $mime): int
    {
        $s = MediaSettings::get();

        return match ($mime) {
            'image/svg+xml' => $s->max_svg_upload_bytes,
            'application/pdf' => $s->max_document_upload_bytes,
            default => $s->max_image_upload_bytes,
        };
    }

    /** Canonical extensions, keyed by MIME type. */
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
        'image/svg+xml' => 'svg',
        'application/pdf' => 'pdf',
    ];

    public function __construct(
        private readonly ConversionPresetRegistry $presets,
        private readonly string $defaultDisk = 'public',
    ) {}

    /**
     * Ingest a file from $sourcePath into managed media storage.
     *
     * The pipeline is (in order):
     *   1. Content-sniff the MIME type (never trust the extension)
     *   2. Allowlist check — reject unknown types immediately
     *   3. Size guard per MIME type
     *   4. Process: raster images are re-encoded (strips EXIF + embedded payloads);
     *      SVG is sanitized (strips scripts + dangerous attributes); other types pass through
     *   5. Store on disk at a content-addressed path
     *   6. Persist a Media record
     *   7. Dispatch queued conversion jobs for raster images
     *
     * @throws MimeTypeNotAllowedException
     * @throws MediaIngestException
     */
    public function ingest(
        string $sourcePath,
        string $originalFilename,
        ?string $disk = null,
        ?string $folderId = null,
        ?string $alt = null,
        ?string $title = null,
    ): Media {
        // 1. Content-sniff — SECURITY: never trust the file extension.
        $mime = $this->sniffMimeType($sourcePath);

        // 2. Allowlist
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new MimeTypeNotAllowedException($mime, $originalFilename);
        }

        // 3. Size guard
        $fileSize = filesize($sourcePath);
        if ($fileSize === false) {
            throw new MediaIngestException("Cannot read file size for \"{$originalFilename}\".");
        }
        $maxBytes = $this->maxSizeFor($mime);
        if ($fileSize > $maxBytes) {
            throw new MediaIngestException(
                "File \"{$originalFilename}\" exceeds the ".number_format($maxBytes / 1_048_576, 0).' MB limit for '.$mime.'.'
            );
        }

        $disk ??= $this->defaultDisk;

        // 4. Process
        [$content, $width, $height] = match (true) {
            in_array($mime, self::IMAGE_MIMES, true) => $this->processImage($sourcePath, $mime),
            $mime === 'image/svg+xml' => $this->processSvg($sourcePath),
            default => $this->readRaw($sourcePath),
        };

        // 5. Store at a content-addressed path using the pre-allocated ULID
        $mediaId = (string) Str::ulid();
        $ext = self::MIME_EXTENSIONS[$mime];
        $storagePath = 'media/'.now()->format('Y/m').'/'.$mediaId.'.'.$ext;

        Storage::disk($disk)->put($storagePath, $content);

        // 6. Persist
        $media = new Media;
        $media->id = $mediaId;
        $media->folder_id = $folderId;
        $media->uploaded_by = auth()->id() !== null ? (string) auth()->id() : null;
        $media->disk = $disk;
        $media->path = $storagePath;
        $media->filename = basename($storagePath);
        $media->original_filename = $originalFilename;
        $media->mime_type = $mime;
        $media->size = strlen($content);
        $media->width = $width;
        $media->height = $height;
        $media->alt = $alt;
        $media->title = $title;
        $media->save();

        // 7. Dispatch conversions for raster images (SVG/PDF do not get presets)
        if (in_array($mime, self::IMAGE_MIMES, true)) {
            foreach ($this->presets->all() as $preset) {
                ProcessMediaConversionJob::dispatch($media->id, $preset->name);
            }
        }

        return $media;
    }

    // ── Private pipeline steps ────────────────────────────────────────────────

    private function sniffMimeType(string $path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            throw new MediaIngestException('Failed to open finfo for MIME type detection.');
        }
        $mime = finfo_file($finfo, $path);
        finfo_close($finfo);

        return is_string($mime) ? $mime : 'application/octet-stream';
    }

    /**
     * Re-encode a raster image to strip EXIF, embedded scripts, and ICC payloads.
     * Creating a brand-new image from raw pixel data guarantees no metadata survives.
     *
     * @return array{0: string, 1: int, 2: int}
     */
    private function processImage(string $sourcePath, string $mime): array
    {
        $manager = new ImageManager(new Driver);
        $image = $manager->read($sourcePath);

        $width = $image->width();
        $height = $image->height();

        $quality = MediaSettings::get()->default_image_quality;

        $encoded = match ($mime) {
            'image/jpeg' => $image->toJpeg($quality),
            'image/png' => $image->toPng(),
            'image/gif' => $image->toGif(),
            'image/webp' => $image->toWebp($quality),
            'image/avif' => $image->toAvif($quality),
            default => throw new MediaIngestException("Unsupported image MIME: {$mime}"),
        };

        return [(string) $encoded, $width, $height];
    }

    /**
     * Sanitize SVG markup: removes scripts, event handlers, and foreign objects.
     *
     * @return array{0: string, 1: null, 2: null}
     */
    private function processSvg(string $sourcePath): array
    {
        $raw = file_get_contents($sourcePath);
        if ($raw === false) {
            throw new MediaIngestException('Cannot read SVG source file.');
        }

        $sanitizer = new Sanitizer;
        $sanitizer->minify(false);
        $cleaned = $sanitizer->sanitize($raw);

        if ($cleaned === false || trim($cleaned) === '') {
            throw new MediaIngestException('SVG could not be sanitized — file may be malformed or contain only disallowed elements.');
        }

        return [$cleaned, null, null];
    }

    /**
     * @return array{0: string, 1: null, 2: null}
     */
    private function readRaw(string $sourcePath): array
    {
        $content = file_get_contents($sourcePath);
        if ($content === false) {
            throw new MediaIngestException('Cannot read source file.');
        }

        return [$content, null, null];
    }
}
