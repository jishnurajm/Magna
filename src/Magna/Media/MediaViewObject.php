<?php

declare(strict_types=1);

namespace Magna\Media;

use Illuminate\Support\Carbon;

/**
 * Read-only view of a Media record with lazy URL resolution.
 *
 * Entries reference media by ULID. The API layer resolves those ULIDs to
 * MediaViewObjects so consumers get typed URL accessors rather than raw IDs.
 */
final class MediaViewObject
{
    public function __construct(
        public readonly string $id,
        public readonly string $originalFilename,
        public readonly string $mimeType,
        public readonly ?int $width,
        public readonly ?int $height,
        public readonly int $size,
        public readonly ?string $alt,
        public readonly ?string $title,
        private readonly Media $media,
        private readonly MediaUrlResolver $resolver,
    ) {}

    public static function fromModel(Media $media, MediaUrlResolver $resolver): self
    {
        return new self(
            id: $media->id,
            originalFilename: $media->original_filename,
            mimeType: $media->mime_type,
            width: $media->width,
            height: $media->height,
            size: $media->size,
            alt: $media->alt,
            title: $media->title,
            media: $media,
            resolver: $resolver,
        );
    }

    /** Public URL; serves the preset conversion (WebP) when available. */
    public function url(?string $preset = null): string
    {
        return $this->resolver->publicUrl($this->media, $preset);
    }

    /**
     * Signed, expiring URL for private-disk delivery.
     * Defaults to one-hour expiry.
     */
    public function signedUrl(?string $preset = null, ?Carbon $expiresAt = null): string
    {
        return $this->resolver->signedUrl($this->media, $preset, $expiresAt);
    }

    /** Responsive `srcset` string built from all WebP conversions. */
    public function srcset(): string
    {
        return $this->resolver->srcset($this->media);
    }
}
